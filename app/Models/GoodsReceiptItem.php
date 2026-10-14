<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'item_id',
        'batch_no',
        'expiry_date',
        'qty',
        'unit_cost',
        'line_total',
        'batch_id',
        'created_by',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'qty' => 'integer',
        'unit_cost' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id')->withTrashed();
    }

    public function batch()
    {
        return $this->belongsTo(ItemBatch::class, 'batch_id');
    }
}
