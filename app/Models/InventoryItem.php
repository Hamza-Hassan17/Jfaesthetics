<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * qty_on_hand, item_batches and stock_movements are written only by
 * App\Services\Inventory\StockService - never assign them directly.
 */
class InventoryItem extends Model
{
    use SoftDeletes;

    public const EXPIRY_WARNING_DAYS = 30;

    protected $fillable = [
        'code',
        'name',
        'category_id',
        'unit',
        'cost_price',
        'sale_price',
        'low_stock_threshold',
        'default_billable',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'default_billable' => 'boolean',
        'is_active' => 'boolean',
        'qty_on_hand' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function batches()
    {
        return $this->hasMany(ItemBatch::class, 'item_id');
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class, 'item_id');
    }

    /**
     * Usable qty = stock in unexpired batches + the unbatched balance
     * (≤ 0 while a shortfall is unsettled). Same figure as
     * StockService::sellableQty(), expressed in SQL so lists and counts
     * can filter and sort on it.
     *
     * @return array{0: string, 1: array}
     */
    public static function usableQtySql(): array
    {
        $sql = '(COALESCE((SELECT SUM(b.qty_remaining) FROM item_batches b'
            . ' WHERE b.item_id = inventory_items.id AND b.qty_remaining > 0'
            . ' AND (b.expiry_date IS NULL OR DATE(b.expiry_date) >= ?)), 0)'
            . ' + COALESCE((SELECT SUM(m.qty) FROM stock_movements m'
            . ' WHERE m.item_id = inventory_items.id AND m.batch_id IS NULL), 0))';

        return [$sql, [now()->toDateString()]];
    }

    public function scopeWithUsableQty($query)
    {
        [$sql, $bindings] = self::usableQtySql();

        if (is_null($query->getQuery()->columns)) {
            $query->select('inventory_items.*');
        }

        return $query->selectRaw("$sql AS usable_qty", $bindings);
    }

    /**
     * Active items whose usable qty is at or below their threshold.
     * Expired stock doesn't count: it can't be sold.
     */
    public function scopeLowStock($query)
    {
        [$sql, $bindings] = self::usableQtySql();

        return $query->where('is_active', true)->whereRaw("$sql <= low_stock_threshold", $bindings);
    }

    public function getIsNegativeAttribute(): bool
    {
        return $this->qty_on_hand < 0;
    }
}
