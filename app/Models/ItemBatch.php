<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Expiry rules (relative to today):
 *   expired  - expiry date before today
 *   expiring - expiry date from today up to today + 30 days, inclusive
 * A batch expiring today is still sellable, so it counts as expiring.
 */
class ItemBatch extends Model
{
    protected $fillable = [
        'item_id',
        'batch_no',
        'expiry_date',
        'unit_cost',
        'qty_received',
        'qty_remaining',
        'source',
        'source_line_id',
        'created_by',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'unit_cost' => 'decimal:2',
        'qty_received' => 'integer',
        'qty_remaining' => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function scopeExpiredWithStock($query)
    {
        return $query->where('qty_remaining', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->toDateString());
    }

    public function scopeExpiringWithStock($query)
    {
        return $query->where('qty_remaining', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->whereDate('expiry_date', '<=', now()->addDays(InventoryItem::EXPIRY_WARNING_DAYS)->toDateString());
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->lt(now()->startOfDay());
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->expiry_date
            && !$this->is_expired
            && $this->expiry_date->lte(now()->startOfDay()->addDays(InventoryItem::EXPIRY_WARNING_DAYS));
    }
}
