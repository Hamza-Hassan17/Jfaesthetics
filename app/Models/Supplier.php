<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'opening_balance',
        'credit_days',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'credit_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }
}
