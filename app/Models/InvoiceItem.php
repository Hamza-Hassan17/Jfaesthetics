<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'line_type',
        'service_id',
        'inventory_item_id',
        'parent_line_id',
        'is_billable',
        'unit_cost',
        'service',
        'quantity',
        'session_number',
        'service_charges',
        'discount_type',
        'discount_value',
        'sub_total',
        'discount',
        'after_discount',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function catalogService()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
