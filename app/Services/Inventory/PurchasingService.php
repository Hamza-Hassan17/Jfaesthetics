<?php

namespace App\Services\Inventory;

use App\Support\RetryingTransaction;
use App\Models\DocumentSequence;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Traits\LogsActivity;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Purchase orders and goods receipts (docs/inventory-spec.md, Phase 2).
 * A P.O moves no stock and creates no debt; the GRN does both.
 */
class PurchasingService
{
    use LogsActivity;

    public function __construct(private StockService $stock, private PayablesService $payables)
    {
    }

    // ------------------------------------------------------------------
    // Purchase orders
    // ------------------------------------------------------------------

    /**
     * @param array $data supplier_id, order_date, expected_date, discount, other_charges, notes
     * @param array $lines [['id' => ?int, 'item_id' => int, 'qty_ordered' => int, 'unit_cost' => float], ...]
     */
    public function savePurchaseOrder(array $data, array $lines, ?PurchaseOrder $po = null): PurchaseOrder
    {
        if (empty($lines)) {
            throw new DomainException('A purchase order needs at least one line.');
        }

        return RetryingTransaction::run(function () use ($data, $lines, $po) {
            if ($po) {
                $po = PurchaseOrder::lockForUpdate()->findOrFail($po->id);
                if (!$po->isEditable()) {
                    throw new DomainException('This purchase order can no longer be edited.');
                }
            }

            $isDraft = !$po || $po->status === 'draft';
            $header = [
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?: null,
                'notes' => $data['notes'] ?? null,
            ];

            if ($isDraft) {
                $header += [
                    'supplier_id' => $data['supplier_id'],
                    'discount' => (float) ($data['discount'] ?: 0),
                    'other_charges' => (float) ($data['other_charges'] ?: 0),
                ];
            }

            if ($po) {
                $po->update($header);
            } else {
                $po = PurchaseOrder::create($header + [
                    'po_no' => DocumentSequence::next('PO'),
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                ]);
            }

            if ($isDraft) {
                $po->items()->delete();
                foreach ($lines as $line) {
                    $this->createPoLine($po, $line);
                }
            } else {
                // Ordered / partially received: add lines or increase qty only.
                $existing = $po->items()->get()->keyBy('id');
                $keptIds = collect($lines)->pluck('id')->filter()->map(fn ($id) => (int) $id);

                if ($existing->keys()->diff($keptIds)->isNotEmpty()) {
                    throw new DomainException('Lines can\'t be removed from an ordered purchase order.');
                }

                foreach ($lines as $line) {
                    if (empty($line['id'])) {
                        $this->createPoLine($po, $line);
                        continue;
                    }
                    $poLine = $existing->get((int) $line['id']);
                    if ((int) $line['qty_ordered'] < $poLine->qty_ordered) {
                        throw new DomainException('Qty on an ordered purchase order can only be increased.');
                    }
                    $poLine->update([
                        'qty_ordered' => (int) $line['qty_ordered'],
                        'line_total' => round((int) $line['qty_ordered'] * (float) $poLine->unit_cost, 2),
                    ]);
                }
            }

            $this->recalcPoTotals($po);
            $this->recalcPoStatus($po);

            return $po->fresh('items');
        });
    }

    public function markOrdered(PurchaseOrder $po): void
    {
        if ($po->status !== 'draft') {
            throw new DomainException('Only a draft purchase order can be marked as ordered.');
        }
        $po->update(['status' => 'ordered']);
    }

    public function cancel(PurchaseOrder $po): void
    {
        if (!in_array($po->status, ['draft', 'ordered'], true) || $po->items()->sum('qty_received') > 0) {
            throw new DomainException('Only a draft or ordered purchase order with nothing received can be cancelled.');
        }
        $po->update(['status' => 'cancelled']);
    }

    /**
     * Stops receiving the rest of a partially received P.O. qty_ordered is
     * kept; the outstanding qty is reported as closed short.
     */
    public function closeShort(PurchaseOrder $po, string $reason): void
    {
        if ($po->status !== 'partially_received') {
            throw new DomainException('Only a partially received purchase order can be closed short.');
        }
        if (trim($reason) === '') {
            throw new DomainException('Give a reason for closing short.');
        }
        $po->update([
            'status' => 'closed',
            'closed_reason' => trim($reason),
            'closed_at' => now(),
            'closed_by' => auth()->id(),
        ]);
    }

    public function recalcPoStatus(PurchaseOrder $po): void
    {
        if (in_array($po->status, ['draft', 'cancelled', 'closed'], true)) {
            return;
        }

        $items = $po->items()->get();
        $received = $items->sum('qty_received');
        $allIn = $items->every(fn ($line) => $line->qty_received >= $line->qty_ordered);

        $po->update(['status' => $received === 0 ? 'ordered' : ($allIn ? 'received' : 'partially_received')]);
    }

    // ------------------------------------------------------------------
    // Goods receipts
    // ------------------------------------------------------------------

    /**
     * @param array $data supplier_id, supplier_bill_no, receive_date, due_date, discount, other_charges, notes
     * @param array $lines [['purchase_order_item_id' => ?int, 'item_id' => int, 'qty' => int,
     *                       'unit_cost' => float, 'batch_no' => ?string, 'expiry_date' => ?string], ...]
     *                      Lines with qty 0 are skipped.
     */
    public function receive(array $data, array $lines, ?PurchaseOrder $po = null): GoodsReceipt
    {
        $lines = array_values(array_filter($lines, fn ($line) => (int) ($line['qty'] ?? 0) > 0));
        if (empty($lines)) {
            throw new DomainException('Enter a quantity for at least one line.');
        }

        $supplierId = $po ? PurchaseOrder::findOrFail($po->id)->supplier_id : $data['supplier_id'];

        return RetryingTransaction::run(function () use ($data, $lines, $po, $supplierId) {
            // Lock order: supplier → P.O → P.O lines → items (see RetryingTransaction).
            $supplier = Supplier::lockForUpdate()->findOrFail($supplierId);

            $poLines = collect();
            if ($po) {
                $po = PurchaseOrder::lockForUpdate()->findOrFail($po->id);
                if (!$po->isReceivable()) {
                    throw new DomainException("{$po->po_no} is " . strtolower(PurchaseOrder::STATUSES[$po->status]) . ' and can\'t be received against.');
                }
                $poLines = PurchaseOrderItem::where('purchase_order_id', $po->id)
                    ->whereIn('id', collect($lines)->pluck('purchase_order_item_id'))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
            }

            $this->stock->lockItems($po ? $poLines->pluck('item_id') : collect($lines)->pluck('item_id'));
            $receiveDate = Carbon::parse($data['receive_date'])->toDateString();

            $receipt = GoodsReceipt::create([
                'grn_no' => DocumentSequence::next('GRN'),
                'purchase_order_id' => $po ? $po->id : null,
                'supplier_id' => $supplier->id,
                'supplier_bill_no' => $data['supplier_bill_no'] ?? null,
                'receive_date' => $receiveDate,
                'due_date' => !empty($data['due_date']) ? $data['due_date'] : Carbon::parse($receiveDate)->addDays($supplier->credit_days)->toDateString(),
                'discount' => (float) ($data['discount'] ?? 0),
                'other_charges' => (float) ($data['other_charges'] ?? 0),
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $index => $line) {
                $qty = (int) $line['qty'];
                $poLine = null;

                if ($po) {
                    $poLine = $poLines->get((int) $line['purchase_order_item_id']);
                    if (!$poLine) {
                        throw new DomainException('A line doesn\'t belong to this purchase order.');
                    }
                    if ($qty > $poLine->qty_outstanding) {
                        throw new DomainException("Can't receive {$qty} of {$poLine->item->name}: only {$poLine->qty_outstanding} still on order. Edit the P.O first.");
                    }
                }

                $item = InventoryItem::findOrFail($poLine ? $poLine->item_id : $line['item_id']);
                $unitCost = round((float) $line['unit_cost'], 2);

                $receiptLine = GoodsReceiptItem::create([
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $poLine ? $poLine->id : null,
                    'item_id' => $item->id,
                    'batch_no' => $this->batchNo($line['batch_no'] ?? null, $receipt, $index + 1),
                    'expiry_date' => $line['expiry_date'] ?: null,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'line_total' => round($qty * $unitCost, 2),
                    'created_by' => auth()->id(),
                ]);

                $batch = $this->stock->stockIn($item, $qty, $receiptLine->batch_no, $receiptLine->expiry_date, $unitCost, $receipt, $receiptLine->id, 'grn_in', false);
                $receiptLine->update(['batch_id' => $batch->id]);

                if ($poLine) {
                    $poLine->increment('qty_received', $qty);
                }

                $item->update(['cost_price' => $unitCost]);
            }

            $this->recalcReceiptTotals($receipt);
            if ($po) {
                $this->recalcPoStatus($po);
            }
            $this->payables->applyAdvances($receipt);

            return $receipt->fresh(['items', 'supplier']);
        });
    }

    /**
     * GRN edit rules:
     *   - bill no, due date, notes: always editable
     *   - batch no, expiry: always editable (labels only)
     *   - qty, unit cost: only while nobody else has used that line's batch
     *
     * @param array $header supplier_bill_no, due_date, notes
     * @param array<int, array{batch_no: ?string, expiry_date: ?string, qty: int, unit_cost: float}> $lines keyed by GRN line id
     */
    public function updateReceipt(GoodsReceipt $receipt, array $header, array $lines): GoodsReceipt
    {
        return RetryingTransaction::run(function () use ($receipt, $header, $lines) {
            $receipt = $this->lockReceipt($receipt);
            $audit = [];

            $headerChanges = $this->diff([
                'bill no' => [$receipt->supplier_bill_no, $header['supplier_bill_no'] ?? null],
                'due date' => [$receipt->due_date->toDateString(), Carbon::parse($header['due_date'])->toDateString()],
                'notes' => [$receipt->notes, $header['notes'] ?? null],
            ]);
            if ($headerChanges) {
                $audit[] = "{$receipt->grn_no}: " . implode('; ', $headerChanges);
            }

            $receipt->update([
                'supplier_bill_no' => $header['supplier_bill_no'] ?? null,
                'due_date' => $header['due_date'],
                'notes' => $header['notes'] ?? null,
            ]);

            foreach ($receipt->items()->with('item')->orderBy('id')->get()->values() as $index => $receiptLine) {
                $change = $lines[$receiptLine->id] ?? null;
                if (!$change) {
                    continue;
                }

                $used = $this->isLineUsed($receiptLine);
                $batchNo = $this->batchNo($change['batch_no'] ?? null, $receipt, $index + 1);
                $expiry = $change['expiry_date'] ? Carbon::parse($change['expiry_date'])->toDateString() : null;
                $qty = (int) $change['qty'];
                $unitCost = round((float) $change['unit_cost'], 2);

                $lineChanges = $this->diff([
                    'batch no' => [$receiptLine->batch_no, $batchNo],
                    'expiry' => [optional($receiptLine->expiry_date)->toDateString(), $expiry],
                    'qty' => [$receiptLine->qty, $qty],
                    'unit cost' => [number_format((float) $receiptLine->unit_cost, 2, '.', ''), number_format($unitCost, 2, '.', '')],
                ]);
                if ($lineChanges) {
                    $audit[] = "{$receipt->grn_no} line " . ($index + 1) . " ({$receiptLine->item->name}, batch #{$receiptLine->batch_id}): "
                        . implode('; ', $lineChanges) . ($used ? ' [stock from this batch already used]' : '');
                }

                $this->stock->updateBatchDetails($receiptLine->batch, $batchNo, $expiry);
                $receiptLine->update(['batch_no' => $batchNo, 'expiry_date' => $expiry]);

                if ($qty === $receiptLine->qty && abs($unitCost - (float) $receiptLine->unit_cost) < 0.005) {
                    continue;
                }

                if ($receiptLine->purchase_order_item_id) {
                    $poLine = PurchaseOrderItem::findOrFail($receiptLine->purchase_order_item_id);
                    $allowed = $poLine->qty_outstanding + $receiptLine->qty;
                    if ($qty > $allowed) {
                        throw new DomainException("Can't receive {$qty} of {$receiptLine->item->name}: the P.O allows at most {$allowed} on this line.");
                    }
                    $poLine->increment('qty_received', $qty - $receiptLine->qty);
                }

                $this->stock->restateStockIn($receipt, $receiptLine->id, $receiptLine->batch, $qty, $unitCost);
                $receiptLine->update(['qty' => $qty, 'unit_cost' => $unitCost, 'line_total' => round($qty * $unitCost, 2)]);

                if ($this->isLatestReceiptOf($receiptLine)) {
                    $receiptLine->item->update(['cost_price' => $unitCost]);
                }
            }

            $this->recalcReceiptTotals($receipt);
            if ($receipt->purchaseOrder) {
                $this->recalcPoStatus($receipt->purchaseOrder);
            }
            $this->payables->trimOverAllocation($receipt->fresh());

            // Written inside the transaction: a refused edit leaves no audit row.
            foreach ($audit as $description) {
                $this->logActivity('updated', 'inventory', $receipt->id, $description);
            }

            return $receipt->fresh(['items', 'supplier']);
        });
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $fields label => [old, new]
     * @return string[] "label 'old' → 'new'" for fields that changed
     */
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

    /** Refused once any of the GRN's stock has been used. */
    public function deleteReceipt(GoodsReceipt $receipt): void
    {
        RetryingTransaction::run(function () use ($receipt) {
            $receipt = $this->lockReceipt($receipt);

            foreach ($receipt->items as $receiptLine) {
                if ($receiptLine->batch && $this->stock->isBatchUsedOutside($receiptLine->batch, $receipt)) {
                    throw new DomainException("{$receipt->grn_no} can't be deleted: stock from batch {$receiptLine->batch_no} has already been used. Use a supplier return or an adjustment instead.");
                }
            }

            $this->stock->reverse($receipt);

            foreach ($receipt->items as $receiptLine) {
                if ($receiptLine->purchase_order_item_id) {
                    PurchaseOrderItem::whereKey($receiptLine->purchase_order_item_id)->decrement('qty_received', $receiptLine->qty);
                }
            }

            $this->payables->releaseReceipt($receipt);
            $receipt->delete();

            if ($receipt->purchaseOrder) {
                $this->recalcPoStatus($receipt->purchaseOrder);
            }
        });
    }

    /**
     * Locks everything a GRN edit/delete touches, in the global order:
     * supplier → P.O → GRN → P.O lines → items. Call inside a transaction.
     */
    private function lockReceipt(GoodsReceipt $receipt): GoodsReceipt
    {
        $snapshot = GoodsReceipt::with('items')->findOrFail($receipt->id);

        Supplier::withTrashed()->lockForUpdate()->findOrFail($snapshot->supplier_id);
        if ($snapshot->purchase_order_id) {
            PurchaseOrder::withTrashed()->lockForUpdate()->findOrFail($snapshot->purchase_order_id);
        }
        $receipt = GoodsReceipt::lockForUpdate()->findOrFail($receipt->id);

        $poLineIds = $snapshot->items->pluck('purchase_order_item_id')->filter()->unique()->sort()->values();
        if ($poLineIds->isNotEmpty()) {
            PurchaseOrderItem::whereIn('id', $poLineIds)->orderBy('id')->lockForUpdate()->get();
        }
        $this->stock->lockItems($snapshot->items->pluck('item_id'));

        return $receipt;
    }

    public function isLineUsed(GoodsReceiptItem $receiptLine): bool
    {
        return $receiptLine->batch && $this->stock->isBatchUsedOutside($receiptLine->batch, $receiptLine->goodsReceipt);
    }

    private function createPoLine(PurchaseOrder $po, array $line): void
    {
        $qty = (int) $line['qty_ordered'];
        if ($qty <= 0) {
            throw new DomainException('Order qty must be at least 1.');
        }

        $po->items()->create([
            'item_id' => $line['item_id'],
            'qty_ordered' => $qty,
            'unit_cost' => round((float) $line['unit_cost'], 2),
            'line_total' => round($qty * (float) $line['unit_cost'], 2),
            'created_by' => auth()->id(),
        ]);
    }

    private function recalcPoTotals(PurchaseOrder $po): void
    {
        $subtotal = (float) $po->items()->sum('line_total');
        $po->update([
            'subtotal' => $subtotal,
            'total' => round($subtotal - (float) $po->discount + (float) $po->other_charges, 2),
        ]);
    }

    private function recalcReceiptTotals(GoodsReceipt $receipt): void
    {
        $subtotal = (float) $receipt->items()->sum('line_total');
        $receipt->update([
            'subtotal' => $subtotal,
            'total' => round($subtotal - (float) $receipt->discount + (float) $receipt->other_charges, 2),
        ]);
    }

    /** Blank batch no becomes "<GRN no>-<line no>". */
    private function batchNo(?string $batchNo, GoodsReceipt $receipt, int $lineNumber): string
    {
        $batchNo = $batchNo !== null ? trim($batchNo) : '';

        return $batchNo !== '' ? $batchNo : "{$receipt->grn_no}-{$lineNumber}";
    }

    private function isLatestReceiptOf(GoodsReceiptItem $receiptLine): bool
    {
        $latest = GoodsReceiptItem::where('item_id', $receiptLine->item_id)
            ->whereHas('goodsReceipt')
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->orderByDesc('goods_receipts.receive_date')
            ->orderByDesc('goods_receipt_items.id')
            ->value('goods_receipt_items.id');

        return (int) $latest === (int) $receiptLine->id;
    }
}
