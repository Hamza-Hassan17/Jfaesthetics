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

    protected static function booted()
    {
        static::saved(fn (self $payment) => $payment->touchInvoiceIfPaidNow());
        static::deleted(fn (self $payment) => $payment->touchInvoiceIfPaidNow());
    }

    /**
     * Bumps the parent invoice's updated_at when a payment dated today (or
     * later) is recorded, so "Updated On" and date-range filters reflect
     * real payment activity. Deliberately keyed on paid_on rather than an
     * unconditional touch()/$touches: a payment entered today but dated a
     * week ago must NOT make the invoice look "updated today" - that's the
     * exact backdating bug already fixed for revenue filtering, and this
     * keeps Updated On consistent with it.
     */
    protected function touchInvoiceIfPaidNow(): void
    {
        if ($this->paid_on && \Illuminate\Support\Carbon::parse($this->paid_on)->startOfDay()->gte(now()->startOfDay())) {
            $this->invoice?->touch();
        }
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
