<?php

namespace App\Services\Inventory;

use App\Support\RetryingTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ShortfallSettlement;
use App\Models\StockMovement;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only writer of stock_movements, item_batches and
 * inventory_items.qty_on_hand (docs/inventory-spec.md, "Stock engine rules").
 *
 * Invariants kept by every method:
 *   qty_on_hand = Σ movements.qty
 *   qty_on_hand = Σ batches.qty_remaining + Σ movements.qty where batch_id is null
 * The second term is ≤ 0 while a shortfall (negative stock) is unsettled.
 */
class StockService
{
    private const SOURCE_BY_TYPE = [
        'grn_in' => 'grn',
        'adjustment_in' => 'adjustment',
        'patient_return_in' => 'patient_return',
    ];

    /**
     * Receives stock into a batch, then settles any outstanding shortfall
     * for the item out of that batch. With $mergeBatch the stock tops up an
     * existing batch with the same batch no + expiry; without it (GRNs) the
     * line always gets its own batch, so the batch can later be edited or
     * re-stated without touching stock from other documents.
     */
    public function stockIn(
        InventoryItem $item,
        int $qty,
        ?string $batchNo,
        $expiry,
        float $unitCost,
        Model $reference,
        ?int $lineId = null,
        string $type = 'grn_in',
        bool $mergeBatch = true
    ): ItemBatch {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Stock-in quantity must be positive.');
        }

        return RetryingTransaction::run(function () use ($item, $qty, $batchNo, $expiry, $unitCost, $reference, $lineId, $type, $mergeBatch) {
            $item = $this->lockItem($item->id);
            $batchNo = $batchNo !== null && trim($batchNo) !== '' ? trim($batchNo) : null;
            $expiry = $expiry ? Carbon::parse($expiry)->toDateString() : null;

            $batch = $mergeBatch
                ? ItemBatch::where('item_id', $item->id)
                    ->where('batch_no', $batchNo)
                    ->when($expiry, fn ($q) => $q->whereDate('expiry_date', $expiry), fn ($q) => $q->whereNull('expiry_date'))
                    ->lockForUpdate()
                    ->first()
                : null;

            $batch = $batch ?? ItemBatch::create([
                'item_id' => $item->id,
                'batch_no' => $batchNo,
                'expiry_date' => $expiry,
                'unit_cost' => $unitCost,
                'qty_received' => 0,
                'qty_remaining' => 0,
                'source' => self::SOURCE_BY_TYPE[$type] ?? 'adjustment',
                'source_line_id' => $lineId,
                'created_by' => auth()->id(),
            ]);

            return $this->receiveInto($item, $batch, $qty, $unitCost, $reference, $lineId, $type);
        });
    }

    /**
     * Changes a received line's qty and/or unit cost in place: the line's
     * movements are reversed and re-applied into the same batch. Refused
     * when any other document has used stock from that batch.
     */
    public function restateStockIn(Model $reference, int $lineId, ItemBatch $batch, int $qty, float $unitCost, string $type = 'grn_in'): ItemBatch
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Stock-in quantity must be positive.');
        }

        return RetryingTransaction::run(function () use ($reference, $lineId, $batch, $qty, $unitCost, $type) {
            $item = $this->lockItem($batch->item_id);
            $batch = ItemBatch::lockForUpdate()->findOrFail($batch->id);

            if ($this->isBatchUsedOutside($batch, $reference)) {
                throw new DomainException('Qty and unit cost can\'t be changed: stock from batch ' . ($batch->batch_no ?: '#' . $batch->id) . ' has already been used.');
            }

            $this->reverse($reference, $lineId);

            $batch->refresh();
            $batch->qty_received = 0;
            $batch->unit_cost = $unitCost;
            $batch->save();

            return $this->receiveInto($item->fresh(), $batch, $qty, $unitCost, $reference, $lineId, $type);
        });
    }

    /**
     * Batch no and expiry are labels: they can be corrected at any time,
     * even after the stock has been used.
     */
    public function updateBatchDetails(ItemBatch $batch, ?string $batchNo, $expiry): ItemBatch
    {
        $batch->update([
            'batch_no' => $batchNo !== null && trim($batchNo) !== '' ? trim($batchNo) : null,
            'expiry_date' => $expiry ? Carbon::parse($expiry)->toDateString() : null,
        ]);

        return $batch;
    }

    /**
     * True when stock in the batch has moved because of any document other
     * than $reference (i.e. its qty_remaining no longer equals what
     * $reference itself put there).
     */
    public function isBatchUsedOutside(ItemBatch $batch, Model $reference): bool
    {
        $own = (int) StockMovement::where('batch_id', $batch->id)
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->sum('qty');

        return (int) $batch->fresh()->qty_remaining !== $own;
    }

    private function receiveInto(InventoryItem $item, ItemBatch $batch, int $qty, float $unitCost, Model $reference, ?int $lineId, string $type): ItemBatch
    {
        $batch->qty_received += $qty;
        $batch->qty_remaining += $qty;
        $batch->save();

        $this->writeMovement($item, $batch->id, $type, $qty, $unitCost, $reference, $lineId);
        $this->settleShortfall($item, $batch, $reference, $lineId);

        return $batch->fresh();
    }

    /**
     * Takes stock out, earliest expiry first (or from one given batch).
     * Whatever the batches can't cover is written against no batch, so
     * qty_on_hand goes negative (warn-and-allow, spec decision 11).
     *
     * @return array<int, array{batch_id: int|null, qty: int}>
     */
    public function stockOut(
        InventoryItem $item,
        int $qty,
        Model $reference,
        ?int $batchId = null,
        ?int $lineId = null,
        string $type = 'invoice_out'
    ): array {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Stock-out quantity must be positive.');
        }

        return RetryingTransaction::run(function () use ($item, $qty, $reference, $batchId, $lineId, $type) {
            $item = $this->lockItem($item->id);
            $allocations = [];

            if ($batchId) {
                $batch = ItemBatch::where('item_id', $item->id)->lockForUpdate()->findOrFail($batchId);
                if ($batch->qty_remaining < $qty) {
                    throw new DomainException("Batch {$batch->batch_no} of {$item->name} has only {$batch->qty_remaining} left.");
                }
                $batch->decrement('qty_remaining', $qty);
                $this->writeMovement($item, $batch->id, $type, -$qty, (float) $batch->unit_cost, $reference, $lineId);

                return [['batch_id' => $batch->id, 'qty' => $qty]];
            }

            $remaining = $qty;
            foreach ($this->fefoBatches($item->id, true) as $batch) {
                if ($remaining === 0) {
                    break;
                }
                $take = min($remaining, $batch->qty_remaining);
                $batch->decrement('qty_remaining', $take);
                $this->writeMovement($item, $batch->id, $type, -$take, (float) $batch->unit_cost, $reference, $lineId);
                $allocations[] = ['batch_id' => $batch->id, 'qty' => $take];
                $remaining -= $take;
            }

            if ($remaining > 0) {
                $this->writeMovement($item, null, $type, -$remaining, (float) $item->cost_price, $reference, $lineId);
                $allocations[] = ['batch_id' => null, 'qty' => $remaining];
            }

            $this->recostLine($reference, $lineId);

            return $allocations;
        });
    }

    /**
     * Gives back part of what one document line took, to the batches it
     * came from, newest allocation first (any unbatched/negative part
     * first, then batches by most recent take). Never re-picks FEFO, so a
     * reduced line never silently moves to a different batch.
     */
    public function returnFromLine(Model $reference, int $lineId, int $itemId, int $qty, string $type = 'invoice_reversal'): void
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Return quantity must be positive.');
        }

        RetryingTransaction::run(function () use ($reference, $lineId, $itemId, $qty, $type) {
            $item = $this->lockItem($itemId);
            $held = $this->lineHoldings($reference, $lineId, $itemId);

            if ($qty > $held['total']) {
                throw new DomainException("Can't return {$qty} of {$item->name}: this line only holds {$held['total']}.");
            }

            $left = $qty;
            if ($held['unbatched'] > 0) {
                $part = min($left, $held['unbatched']);
                $this->returnUnbatched($item, $part, $type, $reference, $lineId);
                $left -= $part;
            }

            foreach ($held['batches'] as $batchId => $batchQty) {
                if ($left === 0) {
                    break;
                }
                $put = min($left, $batchQty);
                $batch = ItemBatch::lockForUpdate()->findOrFail($batchId);
                $batch->increment('qty_remaining', $put);
                $this->writeMovement($item, $batch->id, $type, $put, (float) $batch->unit_cost, $reference, $lineId);
                $left -= $put;
            }

            $this->recostLine($reference, $lineId);
        });
    }

    /**
     * What one line currently holds out of an item: its unbatched
     * (negative-stock) qty and, per batch, newest take first.
     *
     * @return array{total: int, unbatched: int, batches: array<int, int>}
     */
    public function lineHoldings(Model $reference, int $lineId, int $itemId): array
    {
        $rows = StockMovement::where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->where('reference_line_id', $lineId)
            ->where('item_id', $itemId)
            ->selectRaw('batch_id, -SUM(qty) as held, MAX(id) as last_id')
            ->groupBy('batch_id')
            ->get();

        $unbatched = (int) optional($rows->firstWhere('batch_id', null))->held;
        $batches = $rows->whereNotNull('batch_id')->filter(fn ($row) => (int) $row->held > 0)
            ->sortByDesc('last_id')
            ->mapWithKeys(fn ($row) => [(int) $row->batch_id => (int) $row->held])
            ->all();

        return ['total' => max(0, $unbatched) + array_sum($batches), 'unbatched' => max(0, $unbatched), 'batches' => $batches];
    }

    /**
     * Weighted cost of what a line holds: each batch at its own unit cost,
     * the negative-stock part at the batch that later settled it, and any
     * still-unsettled part at the item's cost price on the day of sale.
     */
    public function lineUnitCost(Model $reference, int $lineId): ?float
    {
        $movements = StockMovement::where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->where('reference_line_id', $lineId)
            ->get();

        $batchQty = -(int) $movements->whereNotNull('batch_id')->sum('qty');
        $batchCost = -$movements->whereNotNull('batch_id')->sum(fn ($m) => $m->qty * (float) $m->unit_cost);

        $unbatched = max(0, -(int) $movements->whereNull('batch_id')->sum('qty'));
        $settlements = ShortfallSettlement::where('owner_type', $reference->getMorphClass())
            ->where('owner_id', $reference->getKey())
            ->where('owner_line_id', $lineId)
            ->get();
        $settledQty = (int) $settlements->sum('qty');
        $settledCost = $settlements->sum(fn ($s) => $s->qty * (float) $s->unit_cost);
        $saleCost = (float) optional($movements->whereNull('batch_id')->where('qty', '<', 0)->sortByDesc('id')->first())->unit_cost;
        $unsettledCost = max(0, $unbatched - $settledQty) * $saleCost;

        $qty = $batchQty + $unbatched;

        return $qty > 0 ? round(($batchCost + $settledCost + $unsettledCost) / $qty, 2) : null;
    }

    /** Keeps an invoice line's unit_cost equal to its actual allocations. */
    private function recostLine(Model $reference, ?int $lineId): void
    {
        if ($lineId === null || !$reference instanceof Invoice) {
            return;
        }

        $cost = $this->lineUnitCost($reference, $lineId);
        if ($cost !== null) {
            InvoiceItem::whereKey($lineId)->update(['unit_cost' => $cost]);
        }
    }

    /**
     * Undoes everything a document moved, back to the same batches, by
     * writing the opposite of the document's net movement per
     * (item, batch). Calling it again is a no-op. Refuses when undoing a
     * stock-in would push a batch below zero (its stock was already used).
     */
    public function reverse(Model $reference, ?int $lineId = null): void
    {
        RetryingTransaction::run(function () use ($reference, $lineId) {
            $nets = StockMovement::where('reference_type', $reference->getMorphClass())
                ->where('reference_id', $reference->getKey())
                ->when($lineId !== null, fn ($q) => $q->where('reference_line_id', $lineId))
                ->selectRaw('item_id, batch_id, reference_line_id, SUM(qty) as net')
                ->groupBy('item_id', 'batch_id', 'reference_line_id')
                ->get()
                ->filter(fn ($row) => (int) $row->net !== 0);

            $type = $reference instanceof Invoice ? 'invoice_reversal' : 'reversal';

            foreach ($nets->groupBy('item_id')->sortKeys() as $itemId => $rows) {
                $item = $this->lockItem($itemId);

                // Batch rows first: a blocked stock-in reversal must fail
                // before anything else for the item is written.
                foreach ($rows->sortBy(fn ($row) => $row->batch_id === null ? 1 : 0) as $row) {
                    $undo = -(int) $row->net;

                    if ($row->batch_id !== null) {
                        $batch = ItemBatch::lockForUpdate()->findOrFail($row->batch_id);
                        if ($batch->qty_remaining + $undo < 0) {
                            throw new DomainException("Can't undo: stock from batch " . ($batch->batch_no ?: '#' . $batch->id) . " of {$item->name} has already been used.");
                        }
                        $batch->increment('qty_remaining', $undo);
                        $this->writeMovement($item, $batch->id, $type, $undo, (float) $batch->unit_cost, $reference, $row->reference_line_id);
                        continue;
                    }

                    if ($undo > 0) {
                        // This document sold into negative: give that back.
                        $this->returnUnbatched($item, $undo, $type, $reference, $row->reference_line_id);
                    } else {
                        // This document's stock-in settled other documents'
                        // shortfalls: those shortfalls are open again.
                        $this->unsettleBy($item, -$undo, $type, $reference, $row->reference_line_id);
                    }
                }

                foreach ($rows->pluck('reference_line_id')->unique() as $line) {
                    $this->recostLine($reference, $line);
                }
            }
        });
    }

    /**
     * Stock that can be sold right now: unexpired batches minus any
     * unsettled shortfall. Expired batches still count in qty_on_hand
     * but are never picked automatically, so they're excluded here.
     */
    public function sellableQty(InventoryItem $item): int
    {
        $inBatches = (int) $this->fefoQuery($item->id)->sum('qty_remaining');

        return $inBatches + $this->unbatchedNet($item->id);
    }

    /**
     * Net quantity a document currently holds out of an item (positive =
     * the document took stock). Used to treat an invoice's own stock as
     * available while it is being edited.
     */
    public function heldBy(Model $reference, int $itemId): int
    {
        return -(int) StockMovement::where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->where('item_id', $itemId)
            ->sum('qty');
    }

    /**
     * Checks both integrity rules for every item.
     *
     * @return array<int, string> one message per mismatch; empty when clean
     */
    public function verify(): array
    {
        $problems = [];

        foreach (InventoryItem::withTrashed()->get() as $item) {
            $ledger = (int) StockMovement::where('item_id', $item->id)->sum('qty');
            $batches = (int) ItemBatch::where('item_id', $item->id)->sum('qty_remaining');
            $unbatched = $this->unbatchedNet($item->id);

            if ($ledger !== $item->qty_on_hand) {
                $problems[] = "{$item->code} {$item->name}: qty_on_hand {$item->qty_on_hand} ≠ ledger total {$ledger}";
            }
            if ($batches + $unbatched !== $item->qty_on_hand) {
                $problems[] = "{$item->code} {$item->name}: batches {$batches} + unbatched {$unbatched} ≠ qty_on_hand {$item->qty_on_hand}";
            }

            // Each stock-in's net "settled" qty must match its settlement records.
            $settledInLedger = (int) StockMovement::where('item_id', $item->id)
                ->whereNull('batch_id')
                ->selectRaw('SUM(qty) as net')
                ->groupBy('reference_type', 'reference_id', 'reference_line_id')
                ->havingRaw('SUM(qty) > 0')
                ->get()
                ->sum('net');
            $settledRecorded = (int) ShortfallSettlement::where('item_id', $item->id)->sum('qty');
            if ($settledInLedger !== $settledRecorded) {
                $problems[] = "{$item->code} {$item->name}: settled shortfall {$settledInLedger} in ledger ≠ {$settledRecorded} in settlement records";
            }

            foreach (ItemBatch::where('item_id', $item->id)->get() as $batch) {
                $batchLedger = (int) StockMovement::where('batch_id', $batch->id)->sum('qty');
                if ($batchLedger !== $batch->qty_remaining) {
                    $problems[] = "{$item->code} batch #{$batch->id}: qty_remaining {$batch->qty_remaining} ≠ ledger {$batchLedger}";
                }
            }
        }

        return $problems;
    }

    /**
     * Settles open shortfalls of the item out of a batch just received,
     * oldest shortfall first, recording which batch covered each one so
     * the selling line can be re-costed at that batch's cost.
     */
    private function settleShortfall(InventoryItem $item, ItemBatch $batch, Model $reference, ?int $lineId): void
    {
        // The ledger's own figure is the ceiling: shortfalls settled before
        // settlement records existed must not be settled a second time.
        $outstanding = max(0, -$this->unbatchedNet($item->id));

        foreach ($this->openShortfalls($item->id) as $owner) {
            $batch->refresh();
            if ($batch->qty_remaining <= 0 || $outstanding <= 0) {
                break;
            }
            $settle = min($owner['unsettled'], $batch->qty_remaining, $outstanding);
            $outstanding -= $settle;

            $batch->decrement('qty_remaining', $settle);
            $this->writeMovement($item, null, 'shortfall_settle', $settle, (float) $batch->unit_cost, $reference, $lineId, false);
            $this->writeMovement($item, $batch->id, 'shortfall_settle', -$settle, (float) $batch->unit_cost, $reference, $lineId, false);

            ShortfallSettlement::create([
                'item_id' => $item->id,
                'owner_type' => $owner['type'],
                'owner_id' => $owner['id'],
                'owner_line_id' => $owner['line'],
                'batch_id' => $batch->id,
                'settled_by_type' => $reference->getMorphClass(),
                'settled_by_id' => $reference->getKey(),
                'settled_by_line_id' => $lineId,
                'qty' => $settle,
                'unit_cost' => $batch->unit_cost,
                'created_by' => auth()->id(),
            ]);

            $this->recostOwner($owner);
        }
    }

    /**
     * Documents/lines holding stock they sold into negative and that no
     * stock-in has covered yet, oldest first.
     *
     * @return array<int, array{type: string, id: int, line: ?int, unsettled: int}>
     */
    private function openShortfalls(int $itemId): array
    {
        $owners = StockMovement::where('item_id', $itemId)
            ->whereNull('batch_id')
            ->selectRaw('reference_type, reference_id, reference_line_id, -SUM(qty) as held, MIN(id) as first_id')
            ->groupBy('reference_type', 'reference_id', 'reference_line_id')
            ->havingRaw('SUM(qty) < 0')
            ->orderBy('first_id')
            ->get();

        $open = [];
        foreach ($owners as $owner) {
            $settled = (int) ShortfallSettlement::where('item_id', $itemId)
                ->where('owner_type', $owner->reference_type)
                ->where('owner_id', $owner->reference_id)
                ->where('owner_line_id', $owner->reference_line_id)
                ->sum('qty');
            $unsettled = (int) $owner->held - $settled;
            if ($unsettled > 0) {
                $open[] = ['type' => $owner->reference_type, 'id' => (int) $owner->reference_id, 'line' => $owner->reference_line_id !== null ? (int) $owner->reference_line_id : null, 'unsettled' => $unsettled];
            }
        }

        return $open;
    }

    /**
     * A line gives back stock it sold into negative. The still-unsettled
     * part just cancels the shortfall; any part a later stock-in already
     * covered goes back into the batch that covered it (newest settlement
     * first) and that settlement shrinks.
     */
    private function returnUnbatched(InventoryItem $item, int $qty, string $type, Model $reference, ?int $lineId): void
    {
        $settlements = ShortfallSettlement::where('item_id', $item->id)
            ->where('owner_type', $reference->getMorphClass())
            ->where('owner_id', $reference->getKey())
            ->where('owner_line_id', $lineId)
            ->orderByDesc('id')
            ->get();

        $held = max(0, -(int) StockMovement::where('item_id', $item->id)
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->where('reference_line_id', $lineId)
            ->whereNull('batch_id')
            ->sum('qty'));
        $unsettled = max(0, $held - (int) $settlements->sum('qty'));

        $cancel = min($qty, $unsettled);
        if ($cancel > 0) {
            $this->writeMovement($item, null, $type, $cancel, (float) $item->cost_price, $reference, $lineId);
        }

        $left = $qty - $cancel;
        foreach ($settlements as $settlement) {
            if ($left === 0) {
                break;
            }
            $put = min($left, $settlement->qty);
            $batch = ItemBatch::lockForUpdate()->findOrFail($settlement->batch_id);
            $settledBy = $this->settledByReference($settlement);

            // The line's negative stock is given back...
            $this->writeMovement($item, null, $type, $put, (float) $batch->unit_cost, $reference, $lineId);
            // ...and the stock-in's settlement is undone, back into its batch.
            $batch->increment('qty_remaining', $put);
            $this->writeMovement($item, null, 'shortfall_settle', -$put, (float) $batch->unit_cost, $settledBy, $settlement->settled_by_line_id, false);
            $this->writeMovement($item, $batch->id, 'shortfall_settle', $put, (float) $batch->unit_cost, $settledBy, $settlement->settled_by_line_id, false);

            $settlement->qty - $put > 0 ? $settlement->update(['qty' => $settlement->qty - $put]) : $settlement->delete();
            $left -= $put;
        }

        if ($left > 0) {
            throw new DomainException("Can't return {$qty} of {$item->name}: more than this line took.");
        }
    }

    /**
     * Reversing a stock-in that settled other lines' shortfalls: those
     * shortfalls are open again (their lines go back to sale-day cost).
     */
    private function unsettleBy(InventoryItem $item, int $qty, string $type, Model $reference, ?int $lineId): void
    {
        $settlements = ShortfallSettlement::where('item_id', $item->id)
            ->where('settled_by_type', $reference->getMorphClass())
            ->where('settled_by_id', $reference->getKey())
            ->where('settled_by_line_id', $lineId)
            ->orderByDesc('id')
            ->get();

        if ((int) $settlements->sum('qty') !== $qty) {
            throw new DomainException("Settlement records for {$item->name} don't match the ledger; run inventory:verify.");
        }

        $this->writeMovement($item, null, $type, -$qty, (float) $item->cost_price, $reference, $lineId);

        foreach ($settlements as $settlement) {
            $owner = ['type' => $settlement->owner_type, 'id' => $settlement->owner_id, 'line' => $settlement->owner_line_id];
            $settlement->delete();
            $this->recostOwner($owner);
        }
    }

    private function settledByReference(ShortfallSettlement $settlement): Model
    {
        $class = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($settlement->settled_by_type) ?? $settlement->settled_by_type;

        return $class::withoutGlobalScopes()->findOrFail($settlement->settled_by_id);
    }

    private function recostOwner(array $owner): void
    {
        if ($owner['line'] === null || $owner['type'] !== (new Invoice)->getMorphClass()) {
            return;
        }
        $invoice = Invoice::withTrashed()->find($owner['id']);
        if ($invoice) {
            $this->recostLine($invoice, $owner['line']);
        }
    }

    private function fefoQuery(int $itemId)
    {
        return ItemBatch::where('item_id', $itemId)
            ->where('qty_remaining', '>', 0)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', now()->toDateString()));
    }

    private function fefoBatches(int $itemId, bool $lock)
    {
        return $this->fefoQuery($itemId)
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->get();
    }

    private function unbatchedNet(int $itemId): int
    {
        return (int) StockMovement::where('item_id', $itemId)->whereNull('batch_id')->sum('qty');
    }

    /**
     * Locks every item a document will touch, in ascending id order, so two
     * documents sharing items always queue on the lowest id first instead
     * of each holding one and waiting for the other. Call inside a
     * transaction before any stock work (see App\Support\RetryingTransaction).
     */
    public function lockItems(iterable $itemIds): void
    {
        $ids = collect($itemIds)->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values();

        if ($ids->isNotEmpty()) {
            InventoryItem::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        }
    }

    /** Item ids a document currently has stock movements for. */
    public function itemsMovedBy(Model $reference): array
    {
        return StockMovement::where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->distinct()
            ->pluck('item_id')
            ->all();
    }

    private function lockItem(int $itemId): InventoryItem
    {
        return InventoryItem::withTrashed()->lockForUpdate()->findOrFail($itemId);
    }

    private function writeMovement(
        InventoryItem $item,
        ?int $batchId,
        string $type,
        int $qty,
        float $unitCost,
        Model $reference,
        ?int $lineId,
        bool $changesOnHand = true
    ): void {
        StockMovement::create([
            'item_id' => $item->id,
            'batch_id' => $batchId,
            'type' => $type,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
            'reference_line_id' => $lineId,
            'moved_at' => now(),
            'created_by' => auth()->id(),
        ]);

        if ($changesOnHand) {
            $item->qty_on_hand += $qty;
            $item->save();
        }
    }
}
