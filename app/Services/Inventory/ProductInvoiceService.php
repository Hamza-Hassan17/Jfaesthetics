<?php

namespace App\Services\Inventory;

use App\Models\DocumentSequence;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductInvoice;
use App\Models\ProductInvoiceItem;
use App\Models\ServiceItem;
use App\Support\RetryingTransaction;
use App\Traits\LogsActivity;
use DomainException;
use Illuminate\Support\Carbon;

/**
 * Product invoices (PI-...): inventory sales kept separate from patient
 * invoices, with their own total and payments. A product invoice can be
 * linked to a patient invoice; recipe consumables of that invoice's
 * services live on an automatically maintained "recipe" product invoice.
 *
 * Lock order: patient invoice → product invoice → items (ascending id).
 */
class ProductInvoiceService
{
    use LogsActivity;

    private const MONEY_EPSILON = 0.004;

    public function __construct(private StockService $stock)
    {
    }

    /**
     * @param array $header  invoice_id, patient_id, walkin_name, walkin_phone, notes
     * @param array $lines   id, item_id, quantity, is_billable, unit_price, discount_type, discount_value
     * @param array $payments id, paid_on, amount, payment_mode
     */
    public function save(array $header, array $lines, array $payments, ?ProductInvoice $pi, bool $canOverridePrice): ProductInvoice
    {
        if (empty($lines)) {
            throw new DomainException('Add at least one product.');
        }

        return RetryingTransaction::run(function () use ($header, $lines, $payments, $pi, $canOverridePrice) {
            if (!empty($header['invoice_id'])) {
                Invoice::lockForUpdate()->findOrFail($header['invoice_id']);
            }
            $pi = $pi ? ProductInvoice::lockForUpdate()->findOrFail($pi->id) : null;
            $this->stock->lockItems(array_merge(collect($lines)->pluck('item_id')->all(), $pi ? $this->stock->itemsMovedBy($pi) : []));

            $items = InventoryItem::withTrashed()->whereIn('id', collect($lines)->pluck('item_id')->filter())->get()->keyBy('id');
            $lines = array_map(fn ($line) => $this->priced($line, $items, $canOverridePrice), array_values($lines));
            $this->guardPaid($pi, $lines, $payments);

            $isNew = !$pi;
            $attributes = [
                'invoice_id' => $header['invoice_id'] ?: null,
                'patient_id' => !empty($header['invoice_id']) ? null : ($header['patient_id'] ?: null),
                'walkin_name' => !empty($header['invoice_id']) || !empty($header['patient_id']) ? null : ($header['walkin_name'] ?: null),
                'walkin_phone' => !empty($header['invoice_id']) || !empty($header['patient_id']) ? null : ($header['walkin_phone'] ?: null),
                'notes' => $header['notes'] ?? null,
            ];
            if ($pi) {
                $pi->update($attributes);
            } else {
                $pi = ProductInvoice::create($attributes + ['pi_no' => DocumentSequence::next('PI'), 'source' => 'manual', 'created_by' => auth()->id()]);
            }

            $audit = $this->applyLines($pi, $lines, $items);
            $audit = array_merge($audit, $this->applyPayments($pi, $payments));

            if ($isNew) {
                $this->logActivity('created', 'inventory', $pi->id, "Created product invoice {$pi->pi_no}" . ($pi->invoice ? " linked to invoice #{$pi->invoice->invoice_number}" : '') . '.');
            } else {
                foreach ($audit as $change) {
                    $this->logActivity('updated', 'inventory', $pi->id, "Product invoice {$pi->pi_no}: {$change}");
                }
            }

            return $pi->fresh(['items', 'payments']);
        });
    }

    /**
     * Keeps the patient invoice's recipe product invoice in step with its
     * service lines. Called inside the patient-invoice save transaction,
     * after the invoice row and its lines are written.
     *
     * - a new service line gets its recipe (qty per session × sessions)
     * - a sessions change recalculates consumables not edited by hand
     * - a removed service line takes its consumables (and their stock) with it
     * Recipes are read once per service line: later recipe edits don't
     * change invoices already billed.
     */
    public function syncRecipes(Invoice $invoice): void
    {
        $serviceLines = $invoice->items()->where('line_type', 'service')->where('recipe_applied', true)->get()->keyBy('id');
        $pi = ProductInvoice::where('invoice_id', $invoice->id)->where('source', 'recipe')->lockForUpdate()->first();

        if (!$pi && $serviceLines->isEmpty()) {
            return;
        }

        $existing = $pi ? $pi->items()->get() : collect();
        $lines = [];

        // Hand-added items on the recipe invoice (no service line) stay as they are.
        foreach ($existing->whereNull('service_line_id') as $line) {
            $lines[] = $this->lineFromModel($line);
        }

        foreach ($serviceLines as $serviceLine) {
            $sessions = max(1, (int) $serviceLine->session_number);
            $own = $existing->where('service_line_id', $serviceLine->id);

            if ($own->isEmpty()) {
                // First time this service line is seen: copy its recipe.
                foreach (ServiceItem::with('item')->where('service_id', $serviceLine->service_id)->get() as $recipeLine) {
                    if (!$recipeLine->item || !$recipeLine->item->is_active) {
                        continue;
                    }
                    $lines[] = [
                        'id' => null,
                        'item_id' => $recipeLine->item_id,
                        'service_line_id' => $serviceLine->id,
                        'quantity' => $recipeLine->qty_per_session * $sessions,
                        'qty_per_session' => $recipeLine->qty_per_session,
                        'qty_overridden' => false,
                        'is_billable' => $recipeLine->is_billable,
                        'unit_price' => null,
                        'discount_type' => 'flat',
                        'discount_value' => 0,
                    ];
                }
                continue;
            }

            foreach ($own as $line) {
                $row = $this->lineFromModel($line);
                if (!$line->qty_overridden && $line->qty_per_session) {
                    $row['quantity'] = $line->qty_per_session * $sessions;
                }
                $lines[] = $row;
            }
        }

        if (!$pi) {
            if (empty($lines)) {
                return;
            }
            $pi = ProductInvoice::create([
                'pi_no' => DocumentSequence::next('PI'),
                'invoice_id' => $invoice->id,
                'source' => 'recipe',
                'notes' => 'Consumables used for the services on invoice #' . $invoice->invoice_number,
                'created_by' => auth()->id(),
            ]);
        }

        $items = InventoryItem::withTrashed()->whereIn('id', collect($lines)->pluck('item_id'))->get()->keyBy('id');
        $lines = array_map(fn ($line) => $this->priced($line, $items, true), $lines);
        $audit = $this->applyLines($pi, $lines, $items);

        foreach ($audit as $change) {
            $this->logActivity('updated', 'inventory', $pi->id, "Product invoice {$pi->pi_no} (from invoice #{$invoice->invoice_number}): {$change}");
        }

        if ($pi->items()->count() === 0 && $pi->payments()->count() === 0) {
            $pi->delete();
        }
    }

    /** Item ids a patient-invoice save may touch through its recipe product invoice. */
    public function recipeItemIdsFor(?Invoice $invoice, array $serviceIds): array
    {
        $ids = ServiceItem::whereIn('service_id', array_filter($serviceIds))->pluck('item_id')->all();
        if ($invoice) {
            foreach (ProductInvoice::where('invoice_id', $invoice->id)->get() as $pi) {
                $ids = array_merge($ids, $this->stock->itemsMovedBy($pi));
            }
        }

        return $ids;
    }

    /** Cancel = return all stock and soft-delete. */
    public function cancel(ProductInvoice $pi): void
    {
        RetryingTransaction::run(function () use ($pi) {
            $pi = ProductInvoice::lockForUpdate()->findOrFail($pi->id);
            $this->stock->lockItems($this->stock->itemsMovedBy($pi));
            $this->stock->reverse($pi);
            $pi->delete();
            $this->logActivity('deleted', 'inventory', $pi->id, "Cancelled product invoice {$pi->pi_no} (stock returned).");
        });
    }

    /** Stock-only reduction: return unused stock without changing what was billed. */
    public function reduceStockUsed(ProductInvoiceItem $line, int $qty, string $reason): void
    {
        if (trim($reason) === '') {
            throw new DomainException('Give a reason for the reduction.');
        }

        RetryingTransaction::run(function () use ($line, $qty, $reason) {
            $pi = ProductInvoice::lockForUpdate()->findOrFail($line->product_invoice_id);
            $this->stock->lockItems([$line->item_id]);
            $line = ProductInvoiceItem::findOrFail($line->id);
            $used = (int) $line->stock_qty;

            if ($qty <= 0 || $qty > $used) {
                throw new DomainException("You can reduce {$line->name} by 1 to {$used}.");
            }

            $this->stock->returnFromLine($pi, $line->id, $line->item_id, $qty);
            $line->update(['stock_qty' => $used - $qty]);

            $this->logActivity('updated', 'inventory', $pi->id, "Product invoice {$pi->pi_no}: stock-only reduction on '{$line->name}': used {$used} → " . ($used - $qty)
                . " (billed {$line->quantity}, unchanged). Reason: " . trim($reason));
        });
    }

    public function addPayment(ProductInvoice $pi, string $paidOn, float $amount, string $mode): void
    {
        RetryingTransaction::run(function () use ($pi, $paidOn, $amount, $mode) {
            $pi = ProductInvoice::lockForUpdate()->findOrFail($pi->id);
            $pi->payments()->create(['paid_on' => $paidOn, 'amount' => $amount, 'payment_mode' => $mode, 'created_by' => auth()->id()]);
            $this->logActivity('updated', 'inventory', $pi->id, "Product invoice {$pi->pi_no}: payment added " . number_format($amount, 2) . " ({$mode}).");
        });
    }

    // ------------------------------------------------------------------

    /** Writes lines in place, moving only the stock each change needs. */
    private function applyLines(ProductInvoice $pi, array $lines, $items): array
    {
        $audit = [];
        $existing = $pi->items()->get()->keyBy('id');
        $keptIds = collect($lines)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        foreach ($existing->reject(fn ($line) => in_array($line->id, $keptIds, true)) as $removed) {
            $this->stock->reverse($pi, $removed->id);
            $audit[] = "removed '{$removed->name}' (qty {$removed->quantity})";
            $removed->delete();
        }

        foreach (array_values($lines) as $position => $line) {
            $item = $items->get((int) $line['item_id']);
            if (!$item) {
                throw new DomainException('Choose a product for every line.');
            }

            $quantity = (int) $line['quantity'];
            $subTotal = round($quantity * (float) $line['unit_price'], 2);
            $discountValue = (float) $line['discount_value'];
            $discount = min($subTotal, $line['discount_type'] === 'percent' ? round($subTotal * $discountValue / 100, 2) : $discountValue);

            $attributes = [
                'product_invoice_id' => $pi->id,
                'item_id' => $item->id,
                'service_line_id' => $line['service_line_id'] ?? null,
                'name' => $item->name,
                'position' => $position + 1,
                'quantity' => $quantity,
                'qty_per_session' => $line['qty_per_session'] ?? null,
                'qty_overridden' => (bool) ($line['qty_overridden'] ?? false),
                'is_billable' => (bool) $line['is_billable'],
                'unit_price' => $line['unit_price'],
                'discount_type' => $line['discount_type'],
                'discount_value' => $discountValue,
                'sub_total' => $subTotal,
                'discount' => $discount,
                'after_discount' => $subTotal - $discount,
            ];

            $old = !empty($line['id']) ? $existing->get((int) $line['id']) : null;

            if (!$old) {
                $saved = ProductInvoiceItem::create($attributes + ['stock_qty' => $quantity, 'created_by' => auth()->id()]);
                $this->stock->stockOut($item, $quantity, $pi, null, $saved->id);
                if (!$pi->wasRecentlyCreated) {
                    $audit[] = "added '{$saved->name}' (qty {$quantity})";
                }
                continue;
            }

            $oldItemId = (int) $old->item_id;
            $oldQuantity = (int) $old->quantity;
            $holding = (int) $old->stock_qty;
            $changes = $this->diff([
                'item' => [$old->name, $item->name],
                'qty' => [$oldQuantity, $quantity],
                'unit price' => [number_format((float) $old->unit_price, 2, '.', ''), number_format((float) $attributes['unit_price'], 2, '.', '')],
                'discount' => [$old->discount_type . ' ' . number_format((float) $old->discount_value, 2, '.', ''), $attributes['discount_type'] . ' ' . number_format($discountValue, 2, '.', '')],
                'billable' => [$old->is_billable ? 'yes' : 'no', $attributes['is_billable'] ? 'yes' : 'no'],
            ]);
            $old->update($attributes);

            if ($oldItemId !== $item->id) {
                $this->stock->reverse($pi, $old->id);
                $this->stock->stockOut($item, $quantity, $pi, null, $old->id);
                $old->update(['stock_qty' => $quantity]);
            } elseif ($quantity !== $oldQuantity) {
                if ($quantity > $holding) {
                    $this->stock->stockOut($item, $quantity - $holding, $pi, null, $old->id);
                } elseif ($quantity < $holding) {
                    $this->stock->returnFromLine($pi, $old->id, $item->id, $holding - $quantity);
                }
                $old->update(['stock_qty' => $quantity]);
            }

            if ($changes) {
                $audit[] = "'{$item->name}': " . implode('; ', $changes);
            }
        }

        return $audit;
    }

    private function applyPayments(ProductInvoice $pi, array $payments): array
    {
        $audit = [];
        $existing = $pi->payments()->get()->keyBy('id');
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
                    'date' => [$old->paid_on->toDateString(), Carbon::parse($values['paid_on'])->toDateString()],
                    'amount' => [number_format((float) $old->amount, 2, '.', ''), number_format((float) $values['amount'], 2, '.', '')],
                    'mode' => [$old->payment_mode, $values['payment_mode']],
                ]);
                if ($changes) {
                    $old->update($values);
                    $audit[] = "payment #{$old->id}: " . implode('; ', $changes);
                }
            } else {
                $kept[] = $pi->payments()->create($values + ['created_by' => auth()->id()])->id;
                if (!$pi->wasRecentlyCreated) {
                    $audit[] = 'payment added: ' . number_format((float) $values['amount'], 2) . " ({$values['payment_mode']})";
                }
            }
        }

        foreach ($existing->reject(fn ($p) => in_array($p->id, $kept, true)) as $removed) {
            $audit[] = 'payment removed: ' . number_format((float) $removed->amount, 2);
            $removed->delete();
        }

        return $audit;
    }

    /** Non-billable lines are free; billable ones use the sale price unless overridden. */
    private function priced(array $line, $items, bool $canOverridePrice): array
    {
        $item = $items->get((int) $line['item_id']);
        $line['is_billable'] = (bool) ($line['is_billable'] ?? true);

        if (!$line['is_billable']) {
            $line['unit_price'] = 0;
            $line['discount_value'] = 0;
        } elseif ($item && (!$canOverridePrice || $line['unit_price'] === null || $line['unit_price'] === '')) {
            $line['unit_price'] = $item->sale_price;
        }

        return $line;
    }

    private function guardPaid(?ProductInvoice $pi, array $lines, array $payments): void
    {
        $total = collect($lines)->sum(function ($line) {
            $sub = (int) $line['quantity'] * (float) $line['unit_price'];
            $disc = $line['discount_type'] === 'percent' ? $sub * (float) $line['discount_value'] / 100 : (float) $line['discount_value'];
            return $sub - min($sub, $disc);
        });
        $paid = collect($payments)->sum(fn ($p) => (float) $p['amount']);

        if ($pi && $pi->payments()->exists() && $paid - $total > self::MONEY_EPSILON) {
            throw new DomainException('This change would bring the total (' . number_format($total, 2) . ') below what has been paid (' . number_format($paid, 2) . '). Use "Reduce stock used" for unused items, or a return/refund.');
        }
    }

    private function lineFromModel(ProductInvoiceItem $line): array
    {
        return [
            'id' => $line->id,
            'item_id' => $line->item_id,
            'service_line_id' => $line->service_line_id,
            'quantity' => $line->quantity,
            'qty_per_session' => $line->qty_per_session,
            'qty_overridden' => $line->qty_overridden,
            'is_billable' => $line->is_billable,
            'unit_price' => $line->unit_price,
            'discount_type' => $line->discount_type,
            'discount_value' => $line->discount_value,
        ];
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
