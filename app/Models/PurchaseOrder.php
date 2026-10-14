<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'draft' => 'Draft',
        'ordered' => 'Ordered',
        'partially_received' => 'Partially received',
        'received' => 'Received',
        'closed' => 'Closed short',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'po_no',
        'supplier_id',
        'order_date',
        'expected_date',
        'status',
        'subtotal',
        'discount',
        'other_charges',
        'total',
        'notes',
        'closed_reason',
        'closed_at',
        'closed_by',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'closed_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, ['ordered', 'partially_received'], true);
    }

    /** Draft: fully editable. Ordered / partially received: add lines or increase qty only. */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'ordered', 'partially_received'], true);
    }
}
