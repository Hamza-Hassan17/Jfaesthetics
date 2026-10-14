<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierPayment extends Model
{
    use SoftDeletes;

    public const MODES = [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank transfer',
        'cheque' => 'Cheque',
    ];

    protected $fillable = [
        'payment_no',
        'supplier_id',
        'payment_date',
        'amount',
        'mode',
        'reference_no',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function allocations()
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getUnallocatedAttribute(): float
    {
        return round((float) $this->amount - (float) $this->allocations->sum('amount'), 2);
    }
}
