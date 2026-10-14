<?php

namespace App\Http\Livewire\Admins\Inventory;

use App\Http\Livewire\Admins\Inventory\Concerns\PurchasingAccess;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\Inventory\PayablesService;
use App\Traits\LogsActivity;
use DomainException;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierPayments extends Component
{
    use WithPagination;
    use LogsActivity;
    use PurchasingAccess;

    public const TAB_PERMISSION = 'supplier_payments';

    protected $paginationTheme = 'bootstrap';

    public $_page = 'index';
    public $view_id;
    public $filter_supplier = '';

    public $editing_id;
    public $supplier_id = '';
    public $payment_date;
    public $amount = '';
    public $mode = 'cash';
    public $reference_no = '';
    public $notes = '';
    public $allocations = [];

    public function mount()
    {
        $this->authorizePurchasingTab(self::TAB_PERMISSION);

        if ($paymentId = request()->query('payment')) {
            if (SupplierPayment::withTrashed()->find($paymentId)) {
                $this->view((int) $paymentId);
            }
        } elseif ($supplierId = request()->query('supplier')) {
            if (Supplier::find($supplierId) && auth()->user()->hasPermission('inventory', 'supplier_payments')) {
                $this->show_create_form();
                $this->supplier_id = (int) $supplierId;
            }
        }
    }

    public function show_create_form()
    {
        $this->authorizeAction('supplier_payments');

        $this->editing_id = null;
        $this->supplier_id = '';
        $this->payment_date = now()->toDateString();
        $this->amount = '';
        $this->mode = 'cash';
        $this->reference_no = '';
        $this->notes = '';
        $this->allocations = [];
        $this->resetErrorBag();
        $this->_page = 'form';
    }

    public function edit($id)
    {
        $this->authorizeAction('supplier_payments');

        $payment = SupplierPayment::with('allocations')->findOrFail($id);
        $this->editing_id = $payment->id;
        $this->supplier_id = $payment->supplier_id;
        $this->payment_date = $payment->payment_date->toDateString();
        $this->amount = $payment->amount;
        $this->mode = $payment->mode;
        $this->reference_no = $payment->reference_no;
        $this->notes = $payment->notes;
        $this->allocations = $payment->allocations
            ->mapWithKeys(fn ($a) => [$a->goods_receipt_id ? 'g' . $a->goods_receipt_id : PayablesService::OPENING => (float) $a->amount])
            ->all();
        $this->resetErrorBag();
        $this->_page = 'form';
    }

    public function updated($name)
    {
        if (in_array($name, ['supplier_id', 'amount'], true)) {
            $this->autoAllocate();
        }
    }

    /** Oldest bill first (opening balance, then GRNs). */
    public function autoAllocate()
    {
        $supplier = $this->supplier_id ? Supplier::find($this->supplier_id) : null;
        if (!$supplier || (float) $this->amount <= 0) {
            $this->allocations = [];
            return;
        }

        $payments = app(PayablesService::class);
        $excluding = $this->editing_id ? SupplierPayment::find($this->editing_id) : null;

        $this->allocations = collect($payments->suggestAllocations($supplier, (float) $this->amount, $excluding))
            ->mapWithKeys(fn ($value, $key) => [$this->uiKey($key) => $value])
            ->all();
    }

    // Livewire keeps array keys stable only if they aren't plain integers.
    protected function uiKey($key): string
    {
        return $key === PayablesService::OPENING ? $key : 'g' . $key;
    }

    protected function serviceKey(string $uiKey): string
    {
        return $uiKey === PayablesService::OPENING ? $uiKey : substr($uiKey, 1);
    }

    public function save()
    {
        $this->authorizeAction('supplier_payments');

        $this->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'mode' => 'required|in:' . implode(',', array_keys(SupplierPayment::MODES)),
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'allocations.*' => 'nullable|numeric|min:0',
        ], [], ['supplier_id' => 'supplier', 'allocations.*' => 'allocation']);

        $allocations = collect($this->allocations)
            ->mapWithKeys(fn ($value, $key) => [$this->serviceKey($key) => $value ?: 0])
            ->all();

        try {
            $payment = app(PayablesService::class)->savePayment([
                'supplier_id' => $this->supplier_id,
                'payment_date' => $this->payment_date,
                'amount' => $this->amount,
                'mode' => $this->mode,
                'reference_no' => $this->reference_no ?: null,
                'notes' => $this->notes ?: null,
            ], $allocations, $this->editing_id ? SupplierPayment::find($this->editing_id) : null);
        } catch (DomainException $e) {
            $this->addError('allocations', $e->getMessage());
            return;
        }

        $this->logActivity($this->editing_id ? 'updated' : 'created', 'inventory', $payment->id, ($this->editing_id ? 'Updated' : 'Recorded') . " supplier payment {$payment->payment_no}.");
        session()->flash('message', "Payment {$payment->payment_no} saved.");
        $this->view($payment->id);
    }

    public function delete($id)
    {
        $this->authorizeAction('supplier_payments');

        $payment = SupplierPayment::findOrFail($id);
        app(PayablesService::class)->deletePayment($payment);

        $this->logActivity('deleted', 'inventory', $payment->id, "Deleted supplier payment {$payment->payment_no}.");
        session()->flash('message', "Payment {$payment->payment_no} deleted.");
        $this->show_index();
    }

    public function view($id)
    {
        $this->view_id = SupplierPayment::withTrashed()->findOrFail($id)->id;
        $this->_page = 'view';
    }

    public function show_index()
    {
        $this->_page = 'index';
        $this->view_id = null;
    }

    public function render()
    {
        $data = ['canManage' => auth()->user()->hasPermission('inventory', 'supplier_payments')];

        if ($this->_page === 'view' && $this->view_id) {
            $data['payment'] = SupplierPayment::withTrashed()->with(['allocations.goodsReceipt', 'supplier', 'creator'])->findOrFail($this->view_id);
        } elseif ($this->_page === 'form') {
            $data['suppliers'] = Supplier::where('is_active', true)->orWhere('id', $this->supplier_id ?: 0)->orderBy('name')->get();
            $supplier = $this->supplier_id ? Supplier::find($this->supplier_id) : null;
            $excluding = $this->editing_id ? SupplierPayment::find($this->editing_id) : null;
            $data['bills'] = $supplier
                ? collect(app(PayablesService::class)->openBills($supplier, $excluding))->map(fn ($bill) => $bill + ['ui_key' => $this->uiKey($bill['key'])])->all()
                : [];
            $data['allocatedTotal'] = collect($this->allocations)->sum(fn ($v) => (float) $v);
        } else {
            $data['payments'] = SupplierPayment::with(['supplier', 'allocations.goodsReceipt'])
                ->when($this->filter_supplier, fn ($q) => $q->where('supplier_id', $this->filter_supplier))
                ->latest('payment_date')
                ->latest('id')
                ->paginate(15);
            $data['suppliers'] = Supplier::orderBy('name')->get();
        }

        return view('livewire.admins.inventory.supplier-payments', $data)->layout('admins.layouts.app');
    }
}
