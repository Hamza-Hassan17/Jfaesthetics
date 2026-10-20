<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sale of inventory products, separate from patient (service) invoices.
 * Optionally linked to a patient invoice; has its own total and payments.
 */
class ProductInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = ['pi_no', 'invoice_id', 'patient_id', 'walkin_name', 'walkin_phone', 'source', 'notes', 'created_by'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function patient()
    {
        return $this->belongsTo(patient::class);
    }

    public function items()
    {
        return $this->hasMany(ProductInvoiceItem::class)->orderBy('position')->orderBy('id');
    }

    public function payments()
    {
        return $this->hasMany(ProductInvoicePayment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Patient from the linked invoice, else the one picked, else the walk-in name. */
    public function getBuyerNameAttribute(): string
    {
        $patient = $this->invoice->patient ?? $this->patient;

        return $patient->name ?? ($this->walkin_name ?: 'Walk-in customer');
    }

    public function getBuyerPhoneAttribute(): ?string
    {
        $patient = $this->invoice->patient ?? $this->patient;

        return $patient->phone ?? $this->walkin_phone;
    }

    public function getGrandTotalAttribute(): float
    {
        return round((float) $this->items->sum('after_discount'), 2);
    }

    public function getPaidTotalAttribute(): float
    {
        return round((float) $this->payments->sum('amount'), 2);
    }

    public function getUnpaidTotalAttribute(): float
    {
        return max(0, round($this->grand_total - $this->paid_total, 2));
    }
}
