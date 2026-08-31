<?php

namespace App\Http\Livewire\Admins;

use App\Models\Invoice;
use App\Models\doctor;
use App\Models\patient;
use App\Models\Service;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class Reports extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $search = '';
    public $filter_from = '';
    public $filter_to = '';
    public $filter_doctor_id = '';
    public $filter_patient_id = '';
    public $filter_status = '';
    public $filter_service = '';

    public function resetFilters()
    {
        $this->search = '';
        $this->filter_from = '';
        $this->filter_to = '';
        $this->filter_doctor_id = '';
        $this->filter_patient_id = '';
        $this->filter_status = '';
        $this->filter_service = '';
        $this->resetPage();
    }

    protected function currentFilters()
    {
        return [
            'search' => $this->search,
            'from' => $this->filter_from,
            'to' => $this->filter_to,
            'doctor_id' => $this->filter_doctor_id,
            'patient_id' => $this->filter_patient_id,
            'status' => $this->filter_status,
            'service' => $this->filter_service,
        ];
    }

    public static function queryFilteredInvoices(array $filters)
    {
        $search = $filters['search'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $doctorId = $filters['doctor_id'] ?? null;
        $patientId = $filters['patient_id'] ?? null;
        $service = $filters['service'] ?? null;
        $status = $filters['status'] ?? null;

        $invoices = Invoice::with(['patient', 'doctor.employ', 'items', 'payments'])
            ->when($search, fn ($q) => $q->where('invoice_number', 'like', "%{$search}%"))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
            ->when($patientId, fn ($q) => $q->where('patient_id', $patientId))
            ->when($service, fn ($q) => $q->whereHas('items', fn ($q2) => $q2->where('service', $service)))
            ->latest()
            ->get();

        if ($status) {
            $invoices = $invoices->filter(function ($invoice) use ($status) {
                $unpaid = $invoice->unpaid_total;
                $paid = $invoice->paid_total;
                return match ($status) {
                    'paid' => $unpaid <= 0,
                    'unpaid' => $paid <= 0,
                    'partial' => $paid > 0 && $unpaid > 0,
                    default => true,
                };
            })->values();
        }

        return $invoices;
    }

    public function render()
    {
        $filtered = self::queryFilteredInvoices($this->currentFilters());

        $summary = [
            'total_invoices' => $filtered->count(),
            'total_revenue' => $filtered->sum('grand_total'),
            'total_paid' => $filtered->sum('paid_total'),
            'outstanding' => $filtered->sum('unpaid_total'),
        ];

        $perPage = 10;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $pagedItems = $filtered->slice(($page - 1) * $perPage, $perPage)->values();
        $invoicesPaginated = new LengthAwarePaginator($pagedItems, $filtered->count(), $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);

        return view('livewire.admins.reports', [
            'invoices' => $invoicesPaginated,
            'summary' => $summary,
            'doctors' => doctor::with('employ')->whereHas('employ')->get(),
            'patients' => patient::all(),
            'services' => Service::all(),
        ])->layout('admins.layouts.app');
    }
}
