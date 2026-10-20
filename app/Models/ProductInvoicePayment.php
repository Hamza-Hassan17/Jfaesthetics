<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductInvoicePayment extends Model
{
    protected $fillable = ['product_invoice_id', 'paid_on', 'amount', 'payment_mode', 'created_by'];

    protected $casts = ['paid_on' => 'date', 'amount' => 'decimal:2'];

    public function productInvoice()
    {
        return $this->belongsTo(ProductInvoice::class)->withTrashed();
    }
}
