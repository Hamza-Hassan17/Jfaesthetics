<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'grn_no',
        'purchase_order_id',
        'supplier_id',
        'supplier_bill_no',
        'receive_date',
        'due_date',
        'subtotal',
        'discount',
        'other_charges',
        'total',
        'amount_paid',
        'payment_status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'receive_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class)->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function allocations()
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getOutstandingAttribute(): float
    {
        return max(0, round((float) $this->total - (float) $this->amount_paid, 2));
    }
}
