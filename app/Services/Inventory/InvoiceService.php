<?php

namespace App\Services\Inventory;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\InventoryItem;
use App\Support\RetryingTransaction;
use App\Traits\LogsActivity;
use DomainException;

/**
 * Saving an invoice with its lines and payments, in place (stable line and
 * payment ids), moving only the stock each change needs
 * (docs/inventory-spec.md, Phase 3).
 *
 * Lock order: the invoice row first (only sales lock invoices), then every
 * item the save touches by ascending id, as everywhere else.
 */
class InvoiceService
{
    use LogsActivity;

    private const MONEY_EPSILON = 0.004;

    public function __construct(private StockService $stock, private ProductInvoiceService $productInvoices)
    {
    }

    /**
     * @param array $header patient_id, doctor_id, notes
     * @param array $lines  ordered form lines; a child line comes after its parent:
     *   uid, parent_uid, id, type (service|product|custom), catalog_id, service (name),
     *   quantity, session, service_charges, discount_type, discount_value,
     *   is_billable, qty_per_session, qty_overridden
     * @param array $payments id, paid_on, amount, payment_mode
     */
    public function save(array $header, array $lines, array $payments, ?Invoice $invoice, bool $canOverridePrice): Invoice
    {
        $lines = array_values($lines);

        return RetryingTransaction::run(function () use ($header, $lines, $payments, $invoice, $canOverridePrice) {
            $invoice = $invoice ? Invoice::lockForUpdate()->findOrFail($invoice->id) : null;
            $existing = $invoice ? $invoice->items()->get()->keyBy('id') : collect();
            $items = InventoryItem::withTrashed()->whereIn('id', collect($lines)->pluck('catalog_id')->filter())->get()->keyBy('id');

            // Lock order: invoice → its product invoices → items.
            if ($invoice) {
                \App\Models\ProductInvoice::where('invoice_id', $invoice->id)->orderBy('id')->lockForUpdate()->get();
            }
            $serviceIds = collect($lines)->where('type', 'service')->pluck('catalog_id')->filter()->all();
            $this->stock->lockItems(array_merge(
                collect($lines)->filter(fn ($l) => $this->isStockLine($l))->pluck('catalog_id')->all(),
                $invoice ? $this->stock->itemsMovedBy($invoice) : [],
                $this->productInvoices->recipeItemIdsFor($invoice, $serviceIds)
            ));

            $lines = array_map(fn ($line) => $this->priced($line, $items, $canOverridePrice), $lines);
            $this->guardPaidTotal($invoice, $lines, $payments);

            $audit = [];
            if ($invoice) {
                $audit = array_merge($audit, $this->diffHeader($invoice, $header));
                $invoice->update([
                    'patient_id' => $header['patient_id'],
                    'doctor_id' => $header['doctor_id'] ?: null,
                    'notes' => $header['notes'] ?? null,
                ]);
            } else {
                $invoice = Invoice::create([
                    'invoice_number' => date('y') . str_pad(Invoice::withTrashed()->max('id') + 1, 5, '0', STR_PAD_LEFT),
                    'patient_id' => $header['patient_id'],
                    'doctor_id' => $header['doctor_id'] ?: null,
                    'created_by' => auth()->id(),
                    'printed_by' => auth()->user()->name ?? null,
                    'notes' => $header['notes'] ?? null,
                ]);
            }

            // Lines that disappeared (children of a removed service included).
            // Note: Eloquent's except() re-indexes, so use the models' ids.
            $keptIds = collect($lines)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
            $removedLines = $existing->reject(fn ($line) => in_array($line->id, $keptIds, true));
            foreach ($removedLines as $removed) {
                if ($removed->inventory_item_id) {
                    $this->stock->reverse($invoice, $removed->id);
                }
                $audit[] = "removed line '{$removed->service}' (qty {$removed->quantity})";
            }
            InvoiceItem::whereIn('id', $removedLines->pluck('id'))->delete();

            $idsByUid = [];
            foreach ($lines as $position => $line) {
                $parentId = $line['parent_uid'] ? ($idsByUid[$line['parent_uid']] ?? null) : null;
                if ($line['parent_uid'] && !$parentId) {
                    throw new DomainException("'{$line['service']}' has no service line to belong to.");
                }

                $old = !empty($line['id']) ? $existing->get((int) $line['id']) : null;
                $saved = $this->saveLine($invoice, $line, $old, $parentId, $position + 1, $items, $audit);
                $idsByUid[$line['uid']] = $saved->id;
            }

            $audit = array_merge($audit, $this->savePayments($invoice, $payments));

            // Recipe consumables live on the linked product invoice.
            $this->productInvoices->syncRecipes($invoice);

            foreach ($audit as $change) {
                $this->logActivity('updated', 'invoices', $invoice->id, "Invoice #{$invoice->invoice_number}: {$change}");
            }

            return $invoice->fresh(['items', 'payments']);
        });
    }

    /**
     * Stock-only reduction (D1): give back unused stock on a child line of a
     * prepaid package without changing what was billed.
     */
    public function reduceStockUsed(InvoiceItem $line, int $qty, string $reason): void
    {
        if (!$line->parent_line_id || !$line->inventory_item_id) {
            throw new DomainException('Only consumable lines under a service can be reduced this way.');
        }
        if (trim($reason) === '') {
            throw new DomainException('Give a reason for the reduction.');
        }

        RetryingTransaction::run(function () use ($line, $qty, $reason) {
            $invoice = Invoice::lockForUpdate()->findOrFail($line->invoice_id);
            $this->stock->lockItems([$line->inventory_item_id]);
            $line = InvoiceItem::findOrFail($line->id);
            $used = (int) $line->stock_qty;

            if ($qty <= 0 || $qty > $used) {
                throw new DomainException("You can reduce {$line->service} by 1 to {$used}.");
            }

            $this->stock->returnFromLine($invoice, $line->id, $line->inventory_item_id, $qty);
            $line->update(['stock_qty' => $used - $qty]);

            $this->logActivity('updated', 'invoices', $invoice->id,
                "Invoice #{$invoice->invoice_number}: stock-only reduction on '{$line->service}': used {$used} → " . ($used - $qty)
                . " (billed {$line->quantity}, unchanged). Reason: " . trim($reason));
        });
    }

    private function saveLine(Invoice $invoice, array $line, ?InvoiceItem $old, ?int $parentId, int $position, $items, array &$audit): InvoiceItem
    {
        $quantity = (int) $line['quantity'];
        $subTotal = round($quantity * (float) $line['service_charges'], 2);
        $discountValue = (float) $line['discount_value'];
        $discount = min($subTotal, $line['discount_type'] === 'percent' ? round($subTotal * $discountValue / 100, 2) : $discountValue);
        $itemId = $this->isStockLine($line) ? (int) $line['catalog_id'] : null;

        $attributes = [
            'invoice_id' => $invoice->id,
            'position' => $position,
            'line_type' => $line['type'],
            'parent_line_id' => $parentId,
            'service_id' => $line['type'] === 'service' ? ($line['catalog_id'] ?: null) : null,
            'inventory_item_id' => $itemId,
            'is_billable' => (bool) $line['is_billable'],
            'service' => $line['service'],
            'quantity' => $quantity,
            'session_number' => $line['type'] === 'product' ? null : ($line['session'] ?: null),
            'qty_per_session' => $line['qty_per_session'] ?: null,
            'qty_overridden' => (bool) ($line['qty_overridden'] ?? false),
            'service_charges' => $line['service_charges'],
            'discount_type' => $line['discount_type'],
            'discount_value' => $discountValue,
            'sub_total' => $subTotal,
            'discount' => $discount,
            'after_discount' => $subTotal - $discount,
        ];

        if (!$old) {
            $saved = InvoiceItem::create($attributes + [
                'stock_qty' => $itemId ? $quantity : null,
                'recipe_applied' => $line['type'] === 'service' && !empty($line['catalog_id']),
            ]);
            if ($itemId) {
                $this->stock->stockOut($items->get($itemId), $quantity, $invoice, null, $saved->id);
            }
            if ($old === null && $invoice->wasRecentlyCreated === false) {
                $audit[] = "added line '{$saved->service}' (qty {$quantity})";
            }
            return $saved;
        }

        $changes = $this->diffLine($old, $attributes);
        $oldItemId = $old->inventory_item_id !== null ? (int) $old->inventory_item_id : null;
        $oldQuantity = (int) $old->quantity;
        $oldStockQty = (int) $old->stock_qty;
        $old->update($attributes);

        if ($oldItemId && $oldItemId !== $itemId) {
            // Different item (or no longer a stock line): put the old one back.
            $this->stock->reverse($invoice, $old->id);
            $old->update(['stock_qty' => null]);
        }

        if ($itemId) {
            // Same item and billed qty unchanged: keep what the line holds
            // (it may have had a stock-only reduction). Otherwise the line
            // should hold exactly its billed qty.
            $holding = $oldItemId === $itemId ? $oldStockQty : 0;
            $target = ($oldItemId === $itemId && $quantity === $oldQuantity) ? $holding : $quantity;

            if ($target > $holding) {
                $this->stock->stockOut($items->get($itemId), $target - $holding, $invoice, null, $old->id);
            } elseif ($target < $holding) {
                $this->stock->returnFromLine($invoice, $old->id, $itemId, $holding - $target);
            }
            $old->update(['stock_qty' => $target]);
        }

        if ($changes) {
            $audit[] = "line '{$old->service}': " . implode('; ', $changes);
        }

        return $old;
    }

    /**
     * Non-billable lines are free; stock lines are charged at the item's
     * sale price unless the user may override it.
     */
    private function priced(array $line, $items, bool $canOverridePrice): array
    {
        $line['is_billable'] = $line['type'] === 'product' ? (bool) ($line['is_billable'] ?? true) : true;

        if ($this->isStockLine($line)) {
            $item = $items->get((int) $line['catalog_id']);
            if (!$item) {
                throw new DomainException("Choose a product for '{$line['service']}'.");
            }
            $line['service'] = $line['service'] ?: $item->name;
            if (!$line['is_billable']) {
                $line['service_charges'] = 0;
                $line['discount_value'] = 0;
            } elseif (!$canOverridePrice) {
                $line['service_charges'] = $item->sale_price;
            }
        }

        return $line;
    }

    /** D1: an edit may not bring the total below what has been paid. */
    private function guardPaidTotal(?Invoice $invoice, array $lines, array $payments): void
    {
        if (!$invoice) {
            return;
        }

        $total = collect($lines)->sum(function ($line) {
            $sub = (int) $line['quantity'] * (float) $line['service_charges'];
            $disc = $line['discount_type'] === 'percent' ? $sub * (float) $line['discount_value'] / 100 : (float) $line['discount_value'];
            return $sub - min($sub, $disc);
        });
        $paid = collect($payments)->sum(fn ($p) => (float) $p['amount']);
        $alreadyPaid = (float) $invoice->payments()->sum('amount');

        if ($alreadyPaid > self::MONEY_EPSILON && $paid - $total > self::MONEY_EPSILON) {
            throw new DomainException('This change would bring the invoice total (' . number_format($total, 2) . ') below what has been paid (' . number_format($paid, 2) . '). Use "Reduce stock used" for unused consumables, or a patient return/refund.');
        }
    }

    /** D2: payments are updated in place by id so their rows survive edits. */
    private function savePayments(Invoice $invoice, array $payments): array
    {
        $audit = [];
        $existing = $invoice->payments()->get()->keyBy('id');
        $kept = [];

        foreach ($payments as $payment) {
            if ((float) $payment['amount'] <= 0 && empty($payment['id'])) {
                continue;
            }
            $values = ['paid_on' => $payment['paid_on'], 'amount' => $payment['amount'], 'payment_mode' => $payment['payment_mode']];
            $old = !empty($payment['id']) ? $existing->get((int) $payment['id']) : null;

            if ($old) {
                $kept[] = $old->id;
                $changes = $this->diff([
                    'date' => [optional($old->paid_on ? \Illuminate\Support\Carbon::parse($old->paid_on) : null)->toDateString(), \Illuminate\Support\Carbon::parse($values['paid_on'])->toDateString()],
                    'amount' => [number_format((float) $old->amount, 2, '.', ''), number_format((float) $values['amount'], 2, '.', '')],
                    'mode' => [$old->payment_mode, $values['payment_mode']],
                ]);
                if ($changes) {
                    $old->update($values);
                    $audit[] = "payment #{$old->id}: " . implode('; ', $changes);
                }
            } else {
                $created = InvoicePayment::create($values + ['invoice_id' => $invoice->id]);
                $kept[] = $created->id;
                if (!$invoice->wasRecentlyCreated) {
                    $audit[] = 'payment added: ' . number_format((float) $created->amount, 2) . " ({$created->payment_mode})";
                }
            }
        }

        foreach ($existing->except($kept) as $removed) {
            $audit[] = 'payment removed: ' . number_format((float) $removed->amount, 2) . " ({$removed->payment_mode})";
            $removed->delete();
        }

        return $audit;
    }

    private function isStockLine(array $line): bool
    {
        return $line['type'] === 'product' && !empty($line['catalog_id']);
    }

    private function diffHeader(Invoice $invoice, array $header): array
    {
        $changes = $this->diff([
            'patient' => [$invoice->patient_id, $header['patient_id']],
            'doctor' => [$invoice->doctor_id, $header['doctor_id'] ?: null],
            'notes' => [$invoice->notes, $header['notes'] ?? null],
        ]);

        return $changes ? ['header: ' . implode('; ', $changes)] : [];
    }

    private function diffLine(InvoiceItem $old, array $new): array
    {
        return $this->diff([
            'item' => [$old->inventory_item_id ?? $old->service_id, $new['inventory_item_id'] ?? $new['service_id']],
            'name' => [$old->service, $new['service']],
            'qty' => [$old->quantity, $new['quantity']],
            'sessions' => [$old->session_number, $new['session_number']],
            'unit price' => [number_format((float) $old->service_charges, 2, '.', ''), number_format((float) $new['service_charges'], 2, '.', '')],
            'discount' => [$old->discount_type . ' ' . number_format((float) $old->discount_value, 2, '.', ''), $new['discount_type'] . ' ' . number_format((float) $new['discount_value'], 2, '.', '')],
            'billable' => [$old->is_billable ? 'yes' : 'no', $new['is_billable'] ? 'yes' : 'no'],
        ]);
    }

    private function diff(array $fields): array
    {
        $changes = [];
        foreach ($fields as $label => [$old, $new]) {
            $old = $old === null || $old === '' ? null : (string) $old;
            $new = $new === null || $new === '' ? null : (string) $new;
            if ($old !== $new) {
                $changes[] = "$label '" . ($old ?? 'none') . "' → '" . ($new ?? 'none') . "'";
            }
        }

        return $changes;
    }
}
