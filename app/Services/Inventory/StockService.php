<?php

namespace App\Services\Inventory;

use App\Models\Invoice;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
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
     * Receives stock into a batch (created, or topped up when the same
     * batch no + expiry already exists), then settles any outstanding
     * shortfall for the item out of that batch.
     */
    public function stockIn(
        InventoryItem $item,
        int $qty,
        ?string $batchNo,
        $expiry,
        float $unitCost,
        Model $reference,
        ?int $lineId = null,
        string $type = 'grn_in'
    ): ItemBatch {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Stock-in quantity must be positive.');
        }

        return DB::transaction(function () use ($item, $qty, $batchNo, $expiry, $unitCost, $reference, $lineId, $type) {
            $item = $this->lockItem($item->id);
            $batchNo = $batchNo !== null && trim($batchNo) !== '' ? trim($batchNo) : null;
            $expiry = $expiry ? Carbon::parse($expiry)->toDateString() : null;

            $batch = ItemBatch::where('item_id', $item->id)
                ->where('batch_no', $batchNo)
                ->when($expiry, fn ($q) => $q->whereDate('expiry_date', $expiry), fn ($q) => $q->whereNull('expiry_date'))
                ->lockForUpdate()
                ->first();

            if ($batch) {
                $batch->qty_received += $qty;
                $batch->qty_remaining += $qty;
                $batch->save();
            } else {
                $batch = ItemBatch::create([
                    'item_id' => $item->id,
                    'batch_no' => $batchNo,
                    'expiry_date' => $expiry,
                    'unit_cost' => $unitCost,
                    'qty_received' => $qty,
                    'qty_remaining' => $qty,
                    'source' => self::SOURCE_BY_TYPE[$type] ?? 'adjustment',
                    'source_line_id' => $lineId,
                    'created_by' => auth()->id(),
                ]);
            }

            $this->writeMovement($item, $batch->id, $type, $qty, $unitCost, $reference, $lineId);
            $this->settleShortfall($item, $batch, $reference, $lineId);

            return $batch->fresh();
        });
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

        return DB::transaction(function () use ($item, $qty, $reference, $batchId, $lineId, $type) {
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

            return $allocations;
        });
    }

    /**
     * Undoes everything a document moved, back to the same batches, by
     * writing the opposite of the document's net movement per
     * (item, batch). Calling it again is a no-op. Refuses when undoing a
     * stock-in would push a batch below zero (its stock was already used).
     */
    public function reverse(Model $reference): void
    {
        DB::transaction(function () use ($reference) {
            $nets = StockMovement::where('reference_type', $reference->getMorphClass())
                ->where('reference_id', $reference->getKey())
                ->selectRaw('item_id, batch_id, SUM(qty) as net')
                ->groupBy('item_id', 'batch_id')
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
                        $this->writeMovement($item, $batch->id, $type, $undo, (float) $batch->unit_cost, $reference, null);
                        continue;
                    }

                    $this->reverseUnbatched($item, $undo, $type, $reference);
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

            foreach (ItemBatch::where('item_id', $item->id)->get() as $batch) {
                $batchLedger = (int) StockMovement::where('batch_id', $batch->id)->sum('qty');
                if ($batchLedger !== $batch->qty_remaining) {
                    $problems[] = "{$item->code} batch #{$batch->id}: qty_remaining {$batch->qty_remaining} ≠ ledger {$batchLedger}";
                }
            }
        }

        return $problems;
    }

    private function settleShortfall(InventoryItem $item, ItemBatch $batch, Model $reference, ?int $lineId): void
    {
        $shortfall = -$this->unbatchedNet($item->id);
        $settle = min($shortfall, $batch->qty_remaining);

        if ($settle <= 0) {
            return;
        }

        $batch->decrement('qty_remaining', $settle);
        $this->writeMovement($item, null, 'shortfall_settle', $settle, (float) $batch->unit_cost, $reference, $lineId, false);
        $this->writeMovement($item, $batch->id, 'shortfall_settle', -$settle, (float) $batch->unit_cost, $reference, $lineId, false);
    }

    /**
     * Undoing a sale that went negative. While the shortfall is still
     * unsettled the qty simply cancels it; any part a later stock-in has
     * already settled goes back to the batch(es) that absorbed it,
     * newest settlement first.
     */
    private function reverseUnbatched(InventoryItem $item, int $undo, string $type, Model $reference): void
    {
        if ($undo < 0) {
            $this->writeMovement($item, null, $type, $undo, (float) $item->cost_price, $reference, null);
            return;
        }

        $toShortfall = min($undo, max(0, -$this->unbatchedNet($item->id)));
        if ($toShortfall > 0) {
            $this->writeMovement($item, null, $type, $toShortfall, (float) $item->cost_price, $reference, null);
        }

        $left = $undo - $toShortfall;
        $settlements = StockMovement::where('item_id', $item->id)
            ->where('type', 'shortfall_settle')
            ->whereNotNull('batch_id')
            ->where('qty', '<', 0)
            ->orderByDesc('id')
            ->get();

        foreach ($settlements as $settlement) {
            if ($left === 0) {
                break;
            }
            $put = min($left, -$settlement->qty);
            $batch = ItemBatch::lockForUpdate()->find($settlement->batch_id);
            $batch->increment('qty_remaining', $put);
            $this->writeMovement($item, $batch->id, $type, $put, (float) $batch->unit_cost, $reference, null);
            $left -= $put;
        }

        if ($left > 0) {
            $this->writeMovement($item, null, $type, $left, (float) $item->cost_price, $reference, null);
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
