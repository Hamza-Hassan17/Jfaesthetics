<?php

namespace App\Http\Livewire\Admins;

use App\Models\ProductInvoice;
use Livewire\Component;
use Livewire\WithPagination;

/** Reports → Product Invoices: product sales, separate from patient revenue. */
class ProductInvoiceReport extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $filter_from = '';
    public $filter_to = '';
    public $filter_status = '';

    public function resetFilters()
    {
        $this->filter_from = '';
        $this->filter_to = '';
        $this->filter_status = '';
        $this->resetPage();
    }

    public static function filtered(array $filters)
    {
        $rows = ProductInvoice::with(['items', 'payments', 'invoice.patient', 'patient'])
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->latest('id')
            ->get();

        return match ($filters['status'] ?? '') {
            'paid' => $rows->filter(fn ($pi) => $pi->unpaid_total <= 0)->values(),
            'unpaid' => $rows->filter(fn ($pi) => $pi->unpaid_total > 0)->values(),
            default => $rows,
        };
    }

    public function exportCsv()
    {
        $rows = self::filtered($this->filters());

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['PI no', 'Date', 'Patient / buyer', 'Patient invoice', 'Type', 'Total', 'Paid', 'Unpaid']);
            foreach ($rows as $pi) {
                fputcsv($out, [
                    $pi->pi_no, $pi->created_at->toDateString(), $pi->buyer_name, $pi->invoice->invoice_number ?? '',
                    $pi->source === 'recipe' ? 'Consumables for services' : 'Products',
                    number_format($pi->grand_total, 2, '.', ''), number_format($pi->paid_total, 2, '.', ''), number_format($pi->unpaid_total, 2, '.', ''),
                ]);
            }
            fclose($out);
        }, 'product-invoices-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    protected function filters(): array
    {
        return ['from' => $this->filter_from ?: null, 'to' => $this->filter_to ?: null, 'status' => $this->filter_status];
    }

    public function render()
    {
        $rows = self::filtered($this->filters());
        $perPage = 15;
        $page = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();

        return view('livewire.admins.product-invoice-report', [
            'productInvoices' => new \Illuminate\Pagination\LengthAwarePaginator($rows->slice(($page - 1) * $perPage, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => request()->url()]),
            'summary' => [
                'count' => $rows->count(),
                'total' => $rows->sum(fn ($pi) => $pi->grand_total),
                'paid' => $rows->sum(fn ($pi) => $pi->paid_total),
                'unpaid' => $rows->sum(fn ($pi) => $pi->unpaid_total),
            ],
            'filters' => array_filter($this->filters()),
        ])->layout('admins.layouts.app');
    }
}
