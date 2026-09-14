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
            // "Activity within the range" rather than just "created within
            // the range" - an invoice whose payments changed today (even
            // though the invoice row itself was created earlier) should
            // still surface when the report is filtered to include today.
            // Payments match on paid_on (the actual payment date the user
            // picks when recording it), not created_at (when the row was
            // typed into the system) - staff often enter several payments
            // in one sitting with different real payment dates.
            ->when($from || $to, function ($q) use ($from, $to) {
                $dateWindow = function ($q2, $column) use ($from, $to) {
                    $q2->when($from, fn ($q3) => $q3->whereDate($column, '>=', $from))
                        ->when($to, fn ($q3) => $q3->whereDate($column, '<=', $to));
                };

                $q->where(function ($q2) use ($dateWindow) {
                    $dateWindow($q2, 'created_at');
                })->orWhere(function ($q2) use ($dateWindow) {
                    $dateWindow($q2, 'updated_at');
                })->orWhereHas('payments', function ($q2) use ($dateWindow) {
                    $dateWindow($q2, 'paid_on');
                });
            })
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

    /**
     * With no date filter, an invoice's "paid" figure is simply its full
     * lifetime paid_total. But once a date range is applied, an invoice can
     * appear in the list purely because ONE payment was recorded in that
     * window (see queryFilteredInvoices) even though it has other, older
     * payments too - so the amount attributable to this period is just the
     * payments actually dated (paid_on) within the range, not everything
     * ever paid on the invoice.
     */
    public static function paidAmountInRange($invoice, $from, $to)
    {
        if (!$from && !$to) {
            return $invoice->paid_total;
        }

        return $invoice->payments->filter(function ($payment) use ($from, $to) {
            $paidOn = $payment->paid_on;
            if (!$paidOn) {
                return false;
            }
            $paidOn = \Illuminate\Support\Carbon::parse($paidOn)->startOfDay();
            if ($from && $paidOn->lt(\Illuminate\Support\Carbon::parse($from)->startOfDay())) {
                return false;
            }
            if ($to && $paidOn->gt(\Illuminate\Support\Carbon::parse($to)->startOfDay())) {
                return false;
            }
            return true;
        })->sum('amount');
    }

    /**
     * With no date filter, "revenue" is simply the value of the matching
     * invoices. With one applied, it's the sum of each invoice's
     * paidAmountInRange() - the money actually recorded in that window.
     */
    public static function sumRevenueInRange($invoices, $from, $to)
    {
        if (!$from && !$to) {
            return $invoices->sum('grand_total');
        }

        return $invoices->sum(fn ($invoice) => self::paidAmountInRange($invoice, $from, $to));
    }

    public function render()
    {
        $filters = $this->currentFilters();
        $filtered = self::queryFilteredInvoices($filters);

        $summary = [
            'total_invoices' => $filtered->count(),
            'total_revenue' => self::sumRevenueInRange($filtered, $filters['from'], $filters['to']),
            'total_paid' => $filtered->sum('paid_total'),
            'outstanding' => $filtered->sum('unpaid_total'),
        ];

        $perPage = 10;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $pagedItems = $filtered->slice(($page - 1) * $perPage, $perPage)->values();
        $pagedItems->each(function ($invoice) use ($filters) {
            $invoice->paid_in_range = self::paidAmountInRange($invoice, $filters['from'], $filters['to']);
        });
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
