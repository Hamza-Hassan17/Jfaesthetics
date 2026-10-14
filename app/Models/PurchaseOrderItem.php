<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'item_id',
        'qty_ordered',
        'qty_received',
        'unit_cost',
        'line_total',
        'created_by',
    ];

    protected $casts = [
        'qty_ordered' => 'integer',
        'qty_received' => 'integer',
        'unit_cost' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id')->withTrashed();
    }

    public function getQtyOutstandingAttribute(): int
    {
        return max(0, $this->qty_ordered - $this->qty_received);
    }
}
