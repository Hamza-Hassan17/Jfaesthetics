<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'service_id',
        'medicine_id',
        'service',
        'quantity',
        'session_number',
        'service_charges',
        'discount_type',
        'discount_value',
        'sub_total',
        'discount',
        'after_discount',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function catalogService()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function medicine()
    {
        return $this->belongsTo(medicine::class);
    }
}
