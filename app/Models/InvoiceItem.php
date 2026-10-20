<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'position',
        'stock_qty',
        'qty_per_session',
        'qty_overridden',
        'recipe_applied',
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

    protected $casts = [
        'is_billable' => 'boolean',
        'qty_overridden' => 'boolean',
        'recipe_applied' => 'boolean',
        'quantity' => 'integer',
        'stock_qty' => 'integer',
        'qty_per_session' => 'integer',
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
        return $this->belongsTo(InventoryItem::class)->withTrashed();
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_line_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_line_id')->orderBy('position');
    }

    public function getIsStockLineAttribute(): bool
    {
        return $this->inventory_item_id !== null;
    }
}
