<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class patient extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable=[
        'name',
        'email',
        'phone',
        'address',
        'gender',
        'age',
        'bloodgroup',
        'photo_path',
    ];

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'patient_id');
    }

    public function latestInvoice()
    {
        return $this->hasOne(Invoice::class, 'patient_id')->latestOfMany();
    }

    public function appointments()
    {
        return $this->hasMany(appointment::class, 'patient_id');
    }

    public function latestAppointment()
    {
        return $this->hasOne(appointment::class, 'patient_id')->latestOfMany();
    }

    public function consultationForms()
    {
        return $this->hasMany(ConsultationForm::class, 'patient_id');
    }
}
