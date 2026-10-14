<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustmentItem extends Model
{
    protected $fillable = [
        'stock_adjustment_id',
        'item_id',
        'batch_id',
        'batch_no',
        'expiry_date',
        'qty',
        'unit_cost',
        'created_by',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'qty' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function adjustment()
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function batch()
    {
        return $this->belongsTo(ItemBatch::class, 'batch_id');
    }
}
