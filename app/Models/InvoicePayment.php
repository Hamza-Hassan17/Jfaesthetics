<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoicePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'paid_on',
        'amount',
        'payment_mode',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
