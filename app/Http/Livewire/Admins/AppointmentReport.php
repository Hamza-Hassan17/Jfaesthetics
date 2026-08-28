<?php

namespace App\Http\Livewire\Admins;

use App\Models\appointment;
use App\Models\doctor;
use App\Models\patient;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class AppointmentReport extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $filter_from = '';
    public $filter_to = '';
    public $filter_status = '';
    public $filter_patient_id = '';

    public const STATUS_LABELS = [
        'booked' => 'Booked',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No Show',
    ];

    public function resetFilters()
    {
        $this->filter_from = '';
        $this->filter_to = '';
        $this->filter_status = '';
        $this->filter_patient_id = '';
        $this->resetPage();
    }

    protected function currentFilters()
    {
        return [
            'from' => $this->filter_from,
            'to' => $this->filter_to,
            'status' => $this->filter_status,
            'patient_id' => $this->filter_patient_id,
        ];
    }

    /**
     * Locked to Dr. Fabreen Naz — MediLife's Appointments/Doctor Visit tab
     * has exactly one consultant in scope, so there's no doctor dropdown
     * here (unlike the Consultancy Report). Same lookup pattern as
     * Appiontment::medilifeDoctor().
     */
    public static function medilifeDoctorId()
    {
        $doctor = doctor::whereHas('employ', function ($q) {
            $q->where('email', Appiontment::MEDILIFE_DOCTOR_EMAIL);
        })->first();

        return $doctor?->id;
    }

    public static function queryFilteredAppointments(array $filters)
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $status = $filters['status'] ?? null;
        $patientId = $filters['patient_id'] ?? null;

        return appointment::with('patient')
            ->where('doctor_id', self::medilifeDoctorId())
            ->when($from, fn ($q) => $q->whereDate('intime', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('intime', '<=', $to))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($patientId, fn ($q) => $q->where('patient_id', $patientId))
            ->latest('intime');
    }

    public function exportCsv()
    {
        $filtered = self::queryFilteredAppointments($this->currentFilters())->get();

        return response()->streamDownload(function () use ($filtered) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Case No.', 'Patient', 'Appointment Date', 'Status', 'Purpose of Visit', 'Consultation Fee']);
            foreach ($filtered as $appointment) {
                fputcsv($handle, [
                    $appointment->case_no,
                    $appointment->patient->name ?? 'N/A',
                    optional($appointment->intime)->format('d M Y h:i A'),
                    self::STATUS_LABELS[$appointment->status] ?? ucfirst($appointment->status),
                    $appointment->description,
                    number_format($appointment->consultation_fee, 2, '.', ''),
                ]);
            }
            fclose($handle);
        }, 'appointment-report-' . now()->format('Ymd-His') . '.csv');
    }

    public function render()
    {
        $query = self::queryFilteredAppointments($this->currentFilters());

        $metrics = [
            'total' => (clone $query)->count(),
            'unique_patients' => (clone $query)->distinct('patient_id')->count('patient_id'),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'cancelled_no_show' => (clone $query)->whereIn('status', ['cancelled', 'no_show'])->count(),
        ];

        return view('livewire.admins.appointment-report', [
            'appointments' => $query->paginate(10),
            'metrics' => $metrics,
            'patients' => patient::all(),
        ])->layout('admins.layouts.app');
    }
}
