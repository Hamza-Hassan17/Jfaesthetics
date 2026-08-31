<?php

namespace App\Http\Livewire\Admins;

use App\Models\appointment;
use App\Models\doctor;
use App\Models\InvoiceItem;
use Livewire\Component;

class DoctorPerformanceReport extends Component
{
    public $from;
    public $to;
    public $doctor_id = '';
    public $period = 'month';

    public function mount()
    {
        $this->setPeriod('month');
    }

    public function setPeriod($period)
    {
        $this->period = $period;

        [$from, $to] = match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };

        $this->from = $from->format('Y-m-d');
        $this->to = $to->format('Y-m-d');
    }

    public function updatedFrom()
    {
        $this->period = 'custom';
    }

    public function updatedTo()
    {
        $this->period = 'custom';
    }

    public static function buildReport($from, $to, $doctorId = null)
    {
        $doctors = doctor::with('employ')
            ->whereHas('employ')
            ->when($doctorId, fn ($q) => $q->where('id', $doctorId))
            ->get();

        return $doctors->map(function ($doc) use ($from, $to) {
            $appointments = appointment::with('patient')
                ->where('doctor_id', $doc->id)
                ->whereBetween('intime', [$from, $to])
                ->get();

            $invoiceItems = InvoiceItem::with(['invoice.patient'])
                ->whereHas('invoice', function ($q) use ($doc, $from, $to) {
                    $q->where('doctor_id', $doc->id)->whereBetween('created_at', [$from, $to]);
                })
                ->get();

            return [
                'doctor_id' => $doc->id,
                'doctor_name' => $doc->employ->name ?? 'Unknown',
                'appointments_count' => $appointments->count(),
                'appointments' => $appointments->map(fn ($a) => [
                    'patient_name' => $a->patient->name ?? 'N/A',
                    'date' => $a->intime,
                ]),
                'services_count' => $invoiceItems->sum('quantity'),
                'service_lines' => $invoiceItems->map(fn ($item) => [
                    'service' => $item->service,
                    'patient_name' => $item->invoice->patient->name ?? 'N/A',
                    'quantity' => $item->quantity,
                ]),
            ];
        });
    }

    public function render()
    {
        $from = $this->from . ' 00:00:00';
        $to = $this->to . ' 23:59:59';

        $report = self::buildReport($from, $to, $this->doctor_id);

        return view('livewire.admins.doctor-performance-report', [
            'doctors' => doctor::with('employ')->whereHas('employ')->get(),
            'report' => $report,
        ])->layout('admins.layouts.app');
    }
}
