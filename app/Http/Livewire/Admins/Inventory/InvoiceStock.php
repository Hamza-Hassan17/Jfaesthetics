<?php

namespace App\Http\Livewire\Admins\Inventory;

use App\Models\Invoice;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Inventory → Invoices: invoices that sold or used stock (product lines
 * and consumables under services).
 */
class InvoiceStock extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $filter = '';
    public $search = '';

    public const FILTERS = [
        'product_only' => 'Product-only',
        'with_services' => 'With services',
    ];

    public function render()
    {
        $invoices = Invoice::with(['patient', 'items'])
            ->whereHas('items', fn ($q) => $q->whereNotNull('inventory_item_id'))
            ->when($this->filter === 'product_only', fn ($q) => $q->whereDoesntHave('items', fn ($i) => $i->where('line_type', 'service')))
            ->when($this->filter === 'with_services', fn ($q) => $q->whereHas('items', fn ($i) => $i->where('line_type', 'service')))
            ->when($this->search, fn ($q) => $q->where(fn ($q2) => $q2->where('invoice_number', 'like', "%{$this->search}%")
                ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))))
            ->latest()
            ->paginate(15);

        return view('livewire.admins.inventory.invoice-stock', [
            'invoices' => $invoices,
            'canViewCost' => auth()->user()->hasPermission('inventory', 'view_cost'),
            'canCreate' => auth()->user()->hasPermission('invoices', 'create'),
        ])->layout('admins.layouts.app');
    }
}
