<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * goods_receipt_id null = the amount pays off the supplier's opening balance.
 */
class SupplierPaymentAllocation extends Model
{
    protected $fillable = ['supplier_payment_id', 'goods_receipt_id', 'amount', 'created_by'];

    protected $casts = ['amount' => 'decimal:2'];

    public function payment()
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }
}
