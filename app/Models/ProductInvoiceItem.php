<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductInvoiceItem extends Model
{
    protected $fillable = [
        'product_invoice_id', 'item_id', 'service_line_id', 'name', 'position', 'quantity', 'stock_qty',
        'qty_per_session', 'qty_overridden', 'is_billable', 'unit_price', 'discount_type', 'discount_value',
        'sub_total', 'discount', 'after_discount', 'unit_cost', 'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'stock_qty' => 'integer',
        'qty_per_session' => 'integer',
        'qty_overridden' => 'boolean',
        'is_billable' => 'boolean',
        'unit_price' => 'decimal:2',
        'after_discount' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    public function productInvoice()
    {
        return $this->belongsTo(ProductInvoice::class)->withTrashed();
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id')->withTrashed();
    }

    public function serviceLine()
    {
        return $this->belongsTo(InvoiceItem::class, 'service_line_id');
    }
}
