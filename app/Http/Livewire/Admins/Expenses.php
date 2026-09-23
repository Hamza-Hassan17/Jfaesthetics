<?php

namespace App\Http\Livewire\Admins;

use App\Models\Expense;
use App\Traits\LogsActivity;
use Livewire\Component;
use Livewire\WithPagination;

class Expenses extends Component
{
    use WithPagination;
    use LogsActivity;

    protected $paginationTheme = 'bootstrap';

    public const PAYMENT_MODE_OPTIONS = ['Cash', 'Card'];

    public $editing_id;

    public $expense_date;
    public $category;
    public $description;
    public $payment_mode = 'Cash';
    public $amount;

    public $filter_from = '';
    public $filter_to = '';

    public function mount()
    {
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->editing_id = null;
        $this->expense_date = now()->format('Y-m-d');
        $this->category = '';
        $this->description = '';
        $this->payment_mode = 'Cash';
        $this->amount = '';
        $this->resetErrorBag();
    }

    public function resetFilters()
    {
        $this->filter_from = '';
        $this->filter_to = '';
        $this->resetPage();
    }

    public function show_create_modal()
    {
        abort_unless(auth()->user()->hasPermission('expenses', 'create'), 403);

        $this->resetForm();
        $this->dispatchBrowserEvent('expense-modal-open');
    }

    public function edit($id)
    {
        abort_unless(auth()->user()->hasPermission('expenses', 'update'), 403);

        $expense = Expense::findOrFail($id);

        $this->editing_id = $expense->id;
        $this->expense_date = optional($expense->expense_date)->format('Y-m-d') ?? now()->format('Y-m-d');
        $this->category = $expense->category;
        $this->description = $expense->description;
        $this->payment_mode = $expense->payment_mode;
        $this->amount = $expense->amount;

        $this->dispatchBrowserEvent('expense-modal-open');
    }

    public function save()
    {
        abort_unless(auth()->user()->hasPermission('expenses', $this->editing_id ? 'update' : 'create'), 403);

        $this->validate([
            'expense_date' => 'required|date',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'payment_mode' => 'required|in:' . implode(',', self::PAYMENT_MODE_OPTIONS),
            'amount' => 'required|numeric|min:0.01',
        ]);

        $data = [
            'expense_date' => $this->expense_date,
            'category' => $this->category,
            'description' => $this->description ?: null,
            'payment_mode' => $this->payment_mode,
            'amount' => $this->amount,
        ];

        if ($this->editing_id) {
            $expense = Expense::findOrFail($this->editing_id);
            $expense->update($data);
            $this->logActivity('updated', 'expenses', $expense->id, "Updated expense #{$expense->id} ({$expense->category}).");
            session()->flash('message', 'Expense updated successfully.');
        } else {
            $data['created_by'] = auth()->user()->name ?? null;
            $expense = Expense::create($data);
            $this->logActivity('created', 'expenses', $expense->id, "Recorded expense #{$expense->id} ({$expense->category}).");
            session()->flash('message', 'Expense recorded successfully.');
        }

        $this->resetForm();
        $this->dispatchBrowserEvent('expense-modal-close');
    }

    public function delete($id)
    {
        abort_unless(auth()->user()->hasPermission('expenses', 'delete'), 403);

        $expense = Expense::findOrFail($id);
        $expense->delete();
        $this->logActivity('deleted', 'expenses', $id, "Deleted expense #{$id} ({$expense->category}).");
        session()->flash('message', 'Expense deleted successfully.');
    }

    protected function filteredExpenses()
    {
        $from = $this->filter_from ?: null;
        $to = $this->filter_to ?: null;

        return Expense::query()
            ->when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to));
    }

    public function render()
    {
        return view('livewire.admins.expenses', [
            'expenses' => $this->filteredExpenses()->latest('expense_date')->latest('id')->paginate(10),
            'totalAmount' => $this->filteredExpenses()->sum('amount'),
        ])->layout('admins.layouts.app');
    }
}
