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
    public $revenueChartRange = 'year';
    public $donutRange = 'month';

    public function setPeriod($period)
    {
        $this->period = $period;
    }

    public function setRevenueChartRange($range)
    {
        $this->revenueChartRange = $range;
    }

    public function setDonutRange($range)
    {
        $this->donutRange = $range;
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

    protected function pctChange($old, $new)
    {
        $old = (float) $old;
        $new = (float) $new;

        if ($old <= 0) {
            return $new > 0 ? 100.0 : 0.0;
        }

        return round((($new - $old) / $old) * 100, 1);
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

        $recentInvoices = Invoice::with('patient', 'doctor.employ')->latest()->take(5)->get();
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

        $topDoctors = doctor::with('employ')->whereHas('employ')->get()->map(function ($doc) use ($from, $to) {
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

        // ---------- New summary header: month-over-month stat cards ----------
        $thisMonthStart = now()->startOfMonth();
        $thisMonthEnd = now()->endOfMonth();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth();

        $revenueThisMonth = (float) InvoicePayment::whereBetween('paid_on', [$thisMonthStart, $thisMonthEnd])->sum('amount');
        $revenueLastMonth = (float) InvoicePayment::whereBetween('paid_on', [$lastMonthStart, $lastMonthEnd])->sum('amount');
        $revenueTrend = $this->pctChange($revenueLastMonth, $revenueThisMonth);

        $allInvoicesForOutstanding = Invoice::with('items', 'payments')->get();
        $totalOutstanding = (float) $allInvoicesForOutstanding->sum(fn ($invoice) => $invoice->unpaid_total);
        $outstandingThisMonthCohort = (float) $allInvoicesForOutstanding
            ->whereBetween('created_at', [$thisMonthStart, $thisMonthEnd])
            ->sum(fn ($invoice) => $invoice->unpaid_total);
        $outstandingLastMonthCohort = (float) $allInvoicesForOutstanding
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum(fn ($invoice) => $invoice->unpaid_total);
        $outstandingTrend = $this->pctChange($outstandingLastMonthCohort, $outstandingThisMonthCohort);

        $totalPatients = patient::count();
        $totalPatientsAsOfLastMonthEnd = patient::where('created_at', '<=', $lastMonthEnd)->count();
        $totalPatientsTrend = $this->pctChange($totalPatientsAsOfLastMonthEnd, $totalPatients);

        $newPatientsThisMonth = patient::whereBetween('created_at', [$thisMonthStart, $thisMonthEnd])->count();
        $newPatientsLastMonth = patient::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        $newPatientsTrend = $this->pctChange($newPatientsLastMonth, $newPatientsThisMonth);

        $todayAppointmentsList = appointment::whereDate('intime', today())->get();
        $todayAppointmentsCount = $todayAppointmentsList->count();
        $todayCompletedCount = $todayAppointmentsList->where('status', 'completed')->count();
        $todayPendingCount = $todayAppointmentsCount - $todayCompletedCount;

        // ---------- Revenue & Collections chart (toggle: This Year / Last 7 Months) ----------
        if ($this->revenueChartRange === '6months') {
            $revenueChartLabels = $cashFlowLabels;
            $revenueChartInvoiced = $cashFlowInvoiced;
            $revenueChartCollected = $cashFlowCollected;
        } else {
            $revenueChartLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $revenueChartInvoiced = $yearlyInvoiced;
            $revenueChartCollected = $yearlyCollected;
        }

        // ---------- Payment Status donut: Collected / Outstanding / Overdue ----------
        $overdueCutoff = now()->subDays(30);
        $unpaidInvoices = $allInvoicesForOutstanding->filter(fn ($invoice) => $invoice->unpaid_total > 0);
        $donutOverdue = (float) $unpaidInvoices->where('created_at', '<', $overdueCutoff)->sum(fn ($invoice) => $invoice->unpaid_total);
        $donutOutstanding = (float) $unpaidInvoices->where('created_at', '>=', $overdueCutoff)->sum(fn ($invoice) => $invoice->unpaid_total);
        $donutCollected = $this->donutRange === 'year'
            ? (float) InvoicePayment::whereBetween('paid_on', [now()->startOfYear(), now()->endOfYear()])->sum('amount')
            : $monthCollected;

        // ---------- Top Performing Doctors (this month, with revenue trend) ----------
        $topPerformingDoctors = doctor::with('employ')->whereHas('employ')->get()->map(function ($doc) use ($thisMonthStart, $thisMonthEnd, $lastMonthStart, $lastMonthEnd) {
            $revenueThisMonthForDoc = (float) Invoice::where('doctor_id', $doc->id)
                ->whereBetween('created_at', [$thisMonthStart, $thisMonthEnd])
                ->with('items')->get()->sum(fn ($invoice) => $invoice->grand_total);
            $revenueLastMonthForDoc = (float) Invoice::where('doctor_id', $doc->id)
                ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
                ->with('items')->get()->sum(fn ($invoice) => $invoice->grand_total);
            $appointmentsCountForDoc = appointment::where('doctor_id', $doc->id)
                ->whereBetween('intime', [$thisMonthStart, $thisMonthEnd])->count();

            return [
                'name' => $doc->employ->name ?? 'Unknown',
                'image' => $doc->employ->image ?? null,
                'revenue' => $revenueThisMonthForDoc,
                'trend' => $this->pctChange($revenueLastMonthForDoc, $revenueThisMonthForDoc),
                'appointments' => $appointmentsCountForDoc,
            ];
        })->filter(fn ($d) => $d['revenue'] > 0 || $d['appointments'] > 0)->sortByDesc('revenue')->take(4)->values();

        // ---------- Upcoming Appointments ----------
        $upcomingAppointments = appointment::with('patient', 'doctor.employ')
            ->where('intime', '>=', now())
            ->orderBy('intime')
            ->take(5)
            ->get();

        $lowStockCount = medicine::whereColumn('quantity', '<=', 'low_stock_threshold')->count();

        $this->dispatchBrowserEvent('revenue-chart-updated', [
            'labels' => $revenueChartLabels,
            'invoiced' => $revenueChartInvoiced,
            'collected' => $revenueChartCollected,
        ]);
        $this->dispatchBrowserEvent('donut-chart-updated', [
            'collected' => $donutCollected,
            'outstanding' => $donutOutstanding,
            'overdue' => $donutOverdue,
        ]);

        return view('livewire.admins.dashboard', [
            'revenueThisMonth' => $revenueThisMonth,
            'revenueTrend' => $revenueTrend,
            'totalOutstanding' => $totalOutstanding,
            'outstandingTrend' => $outstandingTrend,
            'totalPatients' => $totalPatients,
            'totalPatientsTrend' => $totalPatientsTrend,
            'newPatientsThisMonth' => $newPatientsThisMonth,
            'newPatientsTrend' => $newPatientsTrend,
            'todayAppointmentsCount' => $todayAppointmentsCount,
            'todayCompletedCount' => $todayCompletedCount,
            'todayPendingCount' => $todayPendingCount,
            'revenueChartLabels' => $revenueChartLabels,
            'revenueChartInvoiced' => $revenueChartInvoiced,
            'revenueChartCollected' => $revenueChartCollected,
            'donutCollected' => $donutCollected,
            'donutOutstanding' => $donutOutstanding,
            'donutOverdue' => $donutOverdue,
            'topPerformingDoctors' => $topPerformingDoctors,
            'upcomingAppointments' => $upcomingAppointments,
            'lowStockCount' => $lowStockCount,
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
