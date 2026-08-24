<?php

namespace App\Http\Livewire\Admins;

use App\Models\appointment;
use App\Models\doctor;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\medicine;
use App\Models\patient;
use App\Models\requestedAppointment;
use Carbon\Carbon;
use Livewire\Component;

class Dashboard extends Component
{
    public $period = 'month';

    public function setPeriod($period)
    {
        $this->period = $period;
    }

    protected function periodRange()
    {
        return match ($this->period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    public function render()
    {
        [$from, $to] = $this->periodRange();

        $revenue = InvoicePayment::whereBetween('paid_on', [$from, $to])->sum('amount');
        $invoicedInPeriod = Invoice::whereBetween('created_at', [$from, $to])->with('items')->get();
        $outstanding = $invoicedInPeriod->sum(fn ($invoice) => $invoice->unpaid_total);
        $newPatients = patient::whereBetween('created_at', [$from, $to])->count();
        $appointments = requestedAppointment::whereBetween('created_at', [$from, $to])->count();

        // Cash Flow: last 7 months, invoiced vs collected
        $months = collect(range(6, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());
        $cashFlowLabels = $months->map(fn ($m) => $m->format('F'))->all();
        $cashFlowInvoiced = $months->map(function ($m) {
            return (float) Invoice::whereBetween('created_at', [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()])
                ->with('items')->get()->sum(fn ($invoice) => $invoice->grand_total);
        })->all();
        $cashFlowCollected = $months->map(function ($m) {
            return (float) InvoicePayment::whereBetween('paid_on', [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()])->sum('amount');
        })->all();

        // Current month donut: collected vs outstanding
        $monthInvoices = Invoice::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->with('items', 'payments')->get();
        $monthCollected = (float) $monthInvoices->sum(fn ($invoice) => $invoice->paid_total);
        $monthOutstanding = max((float) $monthInvoices->sum(fn ($invoice) => $invoice->unpaid_total), 0);

        // Yearly report: invoiced vs collected per month, current year
        $yearMonths = collect(range(1, 12));
        $yearlyInvoiced = $yearMonths->map(function ($m) {
            $start = Carbon::create(now()->year, $m, 1)->startOfMonth();
            return (float) Invoice::whereBetween('created_at', [$start, $start->copy()->endOfMonth()])
                ->with('items')->get()->sum(fn ($invoice) => $invoice->grand_total);
        })->all();
        $yearlyCollected = $yearMonths->map(function ($m) {
            $start = Carbon::create(now()->year, $m, 1)->startOfMonth();
            return (float) InvoicePayment::whereBetween('paid_on', [$start, $start->copy()->endOfMonth()])->sum('amount');
        })->all();

        $recentInvoices = Invoice::with('patient')->latest()->take(5)->get();
        $recentAppointments = requestedAppointment::with('doctor.employ')->latest()->take(5)->get();
        $recentPayments = InvoicePayment::with('invoice.patient')->latest()->take(5)->get();

        $topServicesThisMonth = InvoiceItem::whereHas('invoice', function ($q) {
            $q->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        })->selectRaw('service, SUM(quantity) as qty')->groupBy('service')->orderByDesc('qty')->take(5)->get();

        $topServicesByQtyThisYear = InvoiceItem::whereHas('invoice', function ($q) {
            $q->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]);
        })->selectRaw('service, SUM(quantity) as qty')->groupBy('service')->orderByDesc('qty')->take(5)->get();

        $topServicesByRevenueThisYear = InvoiceItem::whereHas('invoice', function ($q) {
            $q->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]);
        })->selectRaw('service, SUM(after_discount) as revenue')->groupBy('service')->orderByDesc('revenue')->take(5)->get();

        $lowStockMedicines = medicine::whereColumn('quantity', '<=', 'low_stock_threshold')->orderBy('quantity')->take(5)->get();

        $topDoctors = doctor::with('employ')->get()->map(function ($doc) use ($from, $to) {
            $appointmentsCount = appointment::where('doctor_id', $doc->id)->whereBetween('intime', [$from, $to])->count();
            $servicesCount = InvoiceItem::whereHas('invoice', function ($q) use ($doc, $from, $to) {
                $q->where('doctor_id', $doc->id)->whereBetween('created_at', [$from, $to]);
            })->sum('quantity');
            return [
                'name' => $doc->employ->name ?? 'Unknown',
                'appointments' => $appointmentsCount,
                'services' => $servicesCount,
            ];
        })->sortByDesc('services')->take(5)->values();

        return view('livewire.admins.dashboard', [
            'revenue' => $revenue,
            'outstanding' => $outstanding,
            'newPatients' => $newPatients,
            'appointments' => $appointments,
            'cashFlowLabels' => $cashFlowLabels,
            'cashFlowInvoiced' => $cashFlowInvoiced,
            'cashFlowCollected' => $cashFlowCollected,
            'monthCollected' => $monthCollected,
            'monthOutstanding' => $monthOutstanding,
            'yearlyInvoiced' => $yearlyInvoiced,
            'yearlyCollected' => $yearlyCollected,
            'recentInvoices' => $recentInvoices,
            'recentAppointments' => $recentAppointments,
            'recentPayments' => $recentPayments,
            'topServicesThisMonth' => $topServicesThisMonth,
            'topServicesByQtyThisYear' => $topServicesByQtyThisYear,
            'topServicesByRevenueThisYear' => $topServicesByRevenueThisYear,
            'lowStockMedicines' => $lowStockMedicines,
            'topDoctors' => $topDoctors,
        ])->layout('admins.layouts.app');
    }
}
