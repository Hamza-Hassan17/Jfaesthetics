<?php

namespace App\Http\Livewire\Admins;

use App\Models\Expense;
use Livewire\Component;
use Livewire\WithPagination;

class ExpenseReport extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $filter_from = '';
    public $filter_to = '';

    public function resetFilters()
    {
        $this->filter_from = '';
        $this->filter_to = '';
        $this->resetPage();
    }

    protected function currentFilters()
    {
        return [
            'from' => $this->filter_from,
            'to' => $this->filter_to,
        ];
    }

    public static function queryFilteredExpenses(array $filters)
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        return Expense::query()
            ->when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to))
            ->latest('expense_date')
            ->latest('id');
    }

    public function exportCsv()
    {
        $filtered = self::queryFilteredExpenses($this->currentFilters())->get();

        return response()->streamDownload(function () use ($filtered) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Category', 'Description', 'Payment Mode', 'Amount', 'Recorded By']);
            foreach ($filtered as $expense) {
                fputcsv($out, [
                    $expense->expense_date->format('Y-m-d'),
                    $expense->category,
                    $expense->description,
                    $expense->payment_mode,
                    number_format($expense->amount, 2, '.', ''),
                    $expense->created_by,
                ]);
            }
            fclose($out);
        }, 'expense-report-' . now()->format('Ymd-His') . '.csv');
    }

    public function render()
    {
        return view('livewire.admins.expense-report', [
            'expenses' => self::queryFilteredExpenses($this->currentFilters())->paginate(10),
            'totalAmount' => self::queryFilteredExpenses($this->currentFilters())->sum('amount'),
        ])->layout('admins.layouts.app');
    }
}
