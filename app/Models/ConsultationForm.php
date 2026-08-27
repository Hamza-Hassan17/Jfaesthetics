<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConsultationForm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'consultant_id',
        'consultation_date',
        'consultation_for',
        'medical_history',
        'medical_history_other',
        'female_status',
        'declaration_confirmed',
        'patient_signature_name',
        'recommended_treatment',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'consultation_for' => 'array',
        'medical_history' => 'array',
        'female_status' => 'array',
        'declaration_confirmed' => 'boolean',
        'consultation_date' => 'date',
    ];

    public function patient()
    {
        return $this->belongsTo(patient::class);
    }

    public function consultant()
    {
        return $this->belongsTo(doctor::class, 'consultant_id');
    }
}
