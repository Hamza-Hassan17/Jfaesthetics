<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class appointment extends Model
{
    use HasFactory;
    protected $fillable=[
        'patient_id',
        'doctor_id',
        'nurse_id',
        'intime',
        'outtime',
        'description',
    ];

    public function patient(){
        return $this->belongsTo(patient::class);
    }

    public function doctor(){
        return $this->belongsTo(doctor::class);
    }

    public function nurse(){
        return $this->belongsTo(nurse::class);
    }

    public function checkups(){
        return $this->hasMany(patientCheckup::class);
    }


}
