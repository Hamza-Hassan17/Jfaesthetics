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
        'phone',
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
        'female_status' => 'array',
        'declaration_confirmed' => 'boolean',
        'consultation_date' => 'date',
    ];

    // Single-select dropdowns now, but stored under _single-suffixed
    // columns (the migration avoided renameColumn(), which needs
    // doctrine/dbal — not installed here) — these accessors/mutators keep
    // the clean "consultation_for" / "medical_history" name everywhere else.
    public function getConsultationForAttribute()
    {
        return $this->attributes['consultation_for_single'] ?? null;
    }

    public function setConsultationForAttribute($value)
    {
        $this->attributes['consultation_for_single'] = $value;
    }

    public function getMedicalHistoryAttribute()
    {
        return $this->attributes['medical_history_single'] ?? null;
    }

    public function setMedicalHistoryAttribute($value)
    {
        $this->attributes['medical_history_single'] = $value;
    }

    public function patient()
    {
        return $this->belongsTo(patient::class);
    }

    public function consultant()
    {
        return $this->belongsTo(doctor::class, 'consultant_id');
    }
}
