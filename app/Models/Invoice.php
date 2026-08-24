<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'patient_id',
        'doctor_id',
        'printed_by',
        'notes',
    ];

    public function patient()
    {
        return $this->belongsTo(patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(doctor::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function getSubTotalAttribute()
    {
        return $this->items->sum('sub_total');
    }

    public function getDiscountTotalAttribute()
    {
        return $this->items->sum('discount');
    }

    public function getGrandTotalAttribute()
    {
        return $this->items->sum('after_discount');
    }

    public function getPaidTotalAttribute()
    {
        return $this->payments->sum('amount');
    }

    public function getUnpaidTotalAttribute()
    {
        return max($this->grand_total - $this->paid_total, 0);
    }

    public function getOverpaidTotalAttribute()
    {
        return max($this->paid_total - $this->grand_total, 0);
    }
}
