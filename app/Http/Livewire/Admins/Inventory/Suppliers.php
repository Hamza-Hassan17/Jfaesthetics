<?php

namespace App\Http\Livewire\Admins\Inventory;

use App\Http\Livewire\Admins\Inventory\Concerns\PurchasingAccess;
use App\Models\Supplier;
use App\Services\Inventory\PayablesService;
use App\Traits\LogsActivity;
use Livewire\Component;
use Livewire\WithPagination;

class Suppliers extends Component
{
    use WithPagination;
    use LogsActivity;
    use PurchasingAccess;

    public const TAB_PERMISSION = 'manage_suppliers';

    protected $paginationTheme = 'bootstrap';

    public $_page = 'index';
    public $search = '';

    public $ledger_id;
    public $ledger_from = '';
    public $ledger_to = '';

    public $editing_id;
    public $name = '';
    public $contact_person = '';
    public $phone = '';
    public $email = '';
    public $address = '';
    public $opening_balance = 0;
    public $credit_days = 0;
    public $is_active = true;

    public function mount()
    {
        $this->authorizePurchasingTab(self::TAB_PERMISSION);

        $supplierId = request()->query('ledger');
        if ($supplierId && Supplier::find($supplierId)) {
            $this->ledger((int) $supplierId);
        }
    }

    protected function resetForm()
    {
        $this->editing_id = null;
        $this->name = '';
        $this->contact_person = '';
        $this->phone = '';
        $this->email = '';
        $this->address = '';
        $this->opening_balance = 0;
        $this->credit_days = 0;
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function show_create()
    {
        $this->authorizeAction('manage_suppliers');
        $this->resetForm();
        $this->dispatchBrowserEvent('supplier-modal-open');
    }

    public function edit($id)
    {
        $this->authorizeAction('manage_suppliers');

        $supplier = Supplier::findOrFail($id);
        $this->editing_id = $supplier->id;
        $this->name = $supplier->name;
        $this->contact_person = $supplier->contact_person;
        $this->phone = $supplier->phone;
        $this->email = $supplier->email;
        $this->address = $supplier->address;
        $this->opening_balance = $supplier->opening_balance;
        $this->credit_days = $supplier->credit_days;
        $this->is_active = $supplier->is_active;
        $this->resetErrorBag();
        $this->dispatchBrowserEvent('supplier-modal-open');
    }

    public function save()
    {
        $this->authorizeAction('manage_suppliers');

        $this->validate([
            'name' => 'required|string|max:150',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string',
            'opening_balance' => 'nullable|numeric',
            'credit_days' => 'required|integer|min:0|max:365',
            'is_active' => 'boolean',
        ]);

        $data = [
            'name' => trim($this->name),
            'contact_person' => $this->contact_person ?: null,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'address' => $this->address ?: null,
            'opening_balance' => $this->opening_balance ?: 0,
            'credit_days' => (int) $this->credit_days,
            'is_active' => (bool) $this->is_active,
        ];

        if ($this->editing_id) {
            $supplier = Supplier::findOrFail($this->editing_id);
            $supplier->update($data);
            $this->logActivity('updated', 'inventory', $supplier->id, "Updated supplier '{$supplier->name}'.");
            session()->flash('message', 'Supplier updated.');
        } else {
            $supplier = Supplier::create($data + ['created_by' => auth()->id()]);
            $this->logActivity('created', 'inventory', $supplier->id, "Created supplier '{$supplier->name}'.");
            session()->flash('message', 'Supplier added.');
        }

        $this->resetForm();
        $this->dispatchBrowserEvent('supplier-modal-close');
    }

    public function ledger($id)
    {
        $this->authorizeAction('view_cost');
        $this->ledger_id = Supplier::findOrFail($id)->id;
        $this->_page = 'ledger';
    }

    public function show_index()
    {
        $this->_page = 'index';
        $this->ledger_id = null;
    }

    public function render()
    {
        $payables = app(PayablesService::class);
        $canViewCost = auth()->user()->hasPermission('inventory', 'view_cost');

        if ($this->_page === 'ledger' && $this->ledger_id) {
            $supplier = Supplier::findOrFail($this->ledger_id);
            $filters = array_filter(['from' => $this->ledger_from, 'to' => $this->ledger_to]);

            return view('livewire.admins.inventory.suppliers', [
                'supplier' => $supplier,
                'rows' => $payables->ledger($supplier, $this->ledger_from ?: null, $this->ledger_to ?: null),
                'filters' => $filters,
                'canViewCost' => $canViewCost,
                'canManage' => auth()->user()->hasPermission('inventory', 'manage_suppliers'),
                'canPay' => auth()->user()->hasPermission('inventory', 'supplier_payments'),
            ])->layout('admins.layouts.app');
        }

        $suppliers = Supplier::when($this->search, fn ($q) => $q->where(fn ($q2) => $q2->where('name', 'like', "%{$this->search}%")->orWhere('phone', 'like', "%{$this->search}%")))
            ->orderBy('name')
            ->paginate(15);

        $suppliers->getCollection()->each(fn ($supplier) => $supplier->balance_owed = $payables->balance($supplier));

        return view('livewire.admins.inventory.suppliers', [
            'suppliers' => $suppliers,
            'canViewCost' => $canViewCost,
            'canManage' => auth()->user()->hasPermission('inventory', 'manage_suppliers'),
            'canPay' => auth()->user()->hasPermission('inventory', 'supplier_payments'),
        ])->layout('admins.layouts.app');
    }
}
