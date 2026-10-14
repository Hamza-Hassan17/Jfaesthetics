<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A later stock-in covering stock that was sold into negative: the owner
 * (e.g. invoice line) consumed `qty` from `batch_id` at `unit_cost`.
 * Written only by App\Services\Inventory\StockService.
 */
class ShortfallSettlement extends Model
{
    protected $fillable = [
        'item_id',
        'owner_type',
        'owner_id',
        'owner_line_id',
        'batch_id',
        'settled_by_type',
        'settled_by_id',
        'settled_by_line_id',
        'qty',
        'unit_cost',
        'created_by',
    ];

    protected $casts = ['qty' => 'integer', 'unit_cost' => 'decimal:2'];

    public function batch()
    {
        return $this->belongsTo(ItemBatch::class, 'batch_id');
    }
}
