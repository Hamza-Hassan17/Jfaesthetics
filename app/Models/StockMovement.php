<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only ledger. Written only by App\Services\Inventory\StockService.
 */
class StockMovement extends Model
{
    public const TYPES = [
        'grn_in',
        'invoice_out',
        'invoice_reversal',
        'supplier_return_out',
        'patient_return_in',
        'adjustment_in',
        'adjustment_out',
        'shortfall_settle',
        'reversal',
    ];

    protected $fillable = [
        'item_id',
        'batch_id',
        'type',
        'qty',
        'unit_cost',
        'reference_type',
        'reference_id',
        'reference_line_id',
        'note',
        'moved_at',
        'created_by',
    ];

    protected $casts = [
        'qty' => 'integer',
        'unit_cost' => 'decimal:2',
        'moved_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function batch()
    {
        return $this->belongsTo(ItemBatch::class, 'batch_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
