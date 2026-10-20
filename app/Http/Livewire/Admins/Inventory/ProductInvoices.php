<?php

namespace App\Http\Livewire\Admins\Inventory;

use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\ProductInvoice;
use App\Models\ProductInvoiceItem;
use App\Models\patient;
use App\Services\Inventory\ProductInvoiceService;
use App\Services\Inventory\StockService;
use DomainException;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Inventory → Product Invoices (PI-...). Selling actions use the invoices
 * permissions (create / update / delete); the page needs inventory.view.
 */
class ProductInvoices extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $_page = 'index';
    public $view_id;
    public $editing_id;

    public $search = '';
    public $filter_link = '';

    // form
    public $link_mode = 'linked';
    public $invoice_id = '';
    public $patient_id = '';
    public $walkin_name = '';
    public $walkin_phone = '';
    public $notes = '';
    public $lines = [];
    public $payments = [];
    public $shortfalls = [];

    // view actions
    public $new_paid_on;
    public $new_amount;
    public $new_payment_mode = 'Cash';
    public $reduce_line_id;
    public $reduce_qty = 1;
    public $reduce_reason = '';
    public $cancel_password = '';

    public const PAYMENT_MODES = ['Cash', 'Card', 'Bank Transfer', 'Other'];

    public function mount()
    {
        if ($id = request()->query('pi')) {
            if (ProductInvoice::withTrashed()->find($id)) {
                $this->view((int) $id);
            }
        } elseif (request()->query('new')) {
            $this->show_create_form();
            if ($invoiceId = request()->query('invoice')) {
                $this->invoice_id = (int) $invoiceId;
            }
        }
    }

    protected function blankLine(): array
    {
        return ['id' => null, 'item_id' => '', 'quantity' => 1, 'is_billable' => true, 'unit_price' => 0, 'discount_type' => 'flat', 'discount_value' => 0,
            'service_line_id' => null, 'qty_per_session' => null, 'qty_overridden' => false];
    }

    protected function blankPayment(): array
    {
        return ['id' => null, 'paid_on' => now()->toDateString(), 'amount' => 0, 'payment_mode' => 'Cash'];
    }

    public function show_create_form()
    {
        abort_unless(auth()->user()->hasPermission('invoices', 'create'), 403);

        $this->editing_id = null;
        $this->link_mode = 'linked';
        $this->invoice_id = '';
        $this->patient_id = '';
        $this->walkin_name = '';
        $this->walkin_phone = '';
        $this->notes = '';
        $this->lines = [$this->blankLine()];
        $this->payments = [$this->blankPayment()];
        $this->shortfalls = [];
        $this->resetErrorBag();
        $this->_page = 'form';
    }

    public function edit($id)
    {
        abort_unless(auth()->user()->hasPermission('invoices', 'update'), 403);

        $pi = ProductInvoice::with(['items', 'payments'])->findOrFail($id);
        $this->editing_id = $pi->id;
        $this->link_mode = $pi->invoice_id ? 'linked' : 'standalone';
        $this->invoice_id = $pi->invoice_id ?: '';
        $this->patient_id = $pi->patient_id ?: '';
        $this->walkin_name = $pi->walkin_name;
        $this->walkin_phone = $pi->walkin_phone;
        $this->notes = $pi->notes;
        $this->lines = $pi->items->map(fn ($l) => [
            'id' => $l->id, 'item_id' => $l->item_id, 'quantity' => $l->quantity, 'is_billable' => $l->is_billable,
            'unit_price' => $l->unit_price, 'discount_type' => $l->discount_type, 'discount_value' => $l->discount_value,
            'service_line_id' => $l->service_line_id, 'qty_per_session' => $l->qty_per_session, 'qty_overridden' => $l->qty_overridden,
        ])->all() ?: [$this->blankLine()];
        $this->payments = $pi->payments->map(fn ($p) => ['id' => $p->id, 'paid_on' => $p->paid_on->toDateString(), 'amount' => $p->amount, 'payment_mode' => $p->payment_mode])->all() ?: [$this->blankPayment()];
        $this->shortfalls = [];
        $this->resetErrorBag();
        $this->_page = 'form';
    }

    public function add_line()
    {
        $this->lines[] = $this->blankLine();
    }

    public function remove_line($index)
    {
        if (count($this->lines) > 1) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
        }
    }

    public function add_payment()
    {
        $this->payments[] = $this->blankPayment();
    }

    public function remove_payment($index)
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
    }

    public function updated($name, $value)
    {
        if (preg_match('/^lines\.(\d+)\.item_id$/', $name) && $value !== '') {
            $index = (int) explode('.', $name)[1];
            $item = InventoryItem::find($value);
            if ($item) {
                $this->lines[$index]['unit_price'] = $this->lines[$index]['is_billable'] ? $item->sale_price : 0;
            }
        }
        if (preg_match('/^lines\.(\d+)\.is_billable$/', $name)) {
            $index = (int) explode('.', $name)[1];
            $item = InventoryItem::find($this->lines[$index]['item_id']);
            $this->lines[$index]['unit_price'] = $value && $item ? $item->sale_price : 0;
            if (!$value) {
                $this->lines[$index]['discount_value'] = 0;
            }
        }
        if (preg_match('/^lines\.(\d+)\.quantity$/', $name)) {
            $this->lines[(int) explode('.', $name)[1]]['qty_overridden'] = true;
        }
    }

    public function getTotalsProperty(): array
    {
        $rows = collect($this->lines)->map(function ($line) {
            $sub = (float) $line['quantity'] * (float) $line['unit_price'];
            $disc = ($line['discount_type'] ?? 'flat') === 'percent' ? $sub * (float) $line['discount_value'] / 100 : (float) $line['discount_value'];
            return $sub - min($sub, $disc);
        });

        return ['lines' => $rows->all(), 'total' => $rows->sum(), 'paid' => collect($this->payments)->sum(fn ($p) => (float) $p['amount'])];
    }

    public function save($confirmShortfall = false)
    {
        abort_unless(auth()->user()->hasPermission('invoices', $this->editing_id ? 'update' : 'create'), 403);

        $this->validate([
            'link_mode' => 'required|in:linked,standalone',
            'invoice_id' => 'required_if:link_mode,linked|nullable|exists:invoices,id',
            'patient_id' => 'nullable|exists:patients,id',
            'walkin_name' => 'nullable|string|max:150',
            'walkin_phone' => 'nullable|string|max:50',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:inventory_items,id',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_type' => 'required|in:flat,percent',
            'lines.*.discount_value' => 'required|numeric|min:0',
            'payments.*.paid_on' => 'required|date',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.payment_mode' => 'required|string',
        ], ['invoice_id.required_if' => 'Choose the patient invoice, or switch to "Without patient invoice".'], [
            'lines.*.item_id' => 'product', 'lines.*.quantity' => 'qty', 'lines.*.unit_price' => 'price',
        ]);

        $pi = $this->editing_id ? ProductInvoice::findOrFail($this->editing_id) : null;

        if (!$confirmShortfall) {
            $this->shortfalls = $this->findShortfalls($pi);
            if ($this->shortfalls) {
                $this->dispatchBrowserEvent('pi-shortfall-open');
                return;
            }
        }
        $this->shortfalls = [];

        $linked = $this->link_mode === 'linked';

        try {
            $pi = app(ProductInvoiceService::class)->save([
                'invoice_id' => $linked ? $this->invoice_id : null,
                'patient_id' => $linked ? null : $this->patient_id,
                'walkin_name' => $linked ? null : $this->walkin_name,
                'walkin_phone' => $linked ? null : $this->walkin_phone,
                'notes' => $this->notes,
            ], $this->lines, $this->payments, $pi, auth()->user()->hasPermission('inventory', 'price_override'));
        } catch (DomainException $e) {
            $this->addError('lines', $e->getMessage());
            return;
        }

        session()->flash('message', "{$pi->pi_no} saved.");
        $this->view($pi->id);
    }

    /** Products (summed per item) needing more than is sellable; stock this invoice holds counts as available. */
    protected function findShortfalls(?ProductInvoice $pi): array
    {
        $stock = app(StockService::class);
        $saved = $pi ? $pi->items()->get()->keyBy('id') : collect();
        $needed = [];

        foreach ($this->lines as $line) {
            if (!$line['item_id']) {
                continue;
            }
            $old = $line['id'] ? $saved->get((int) $line['id']) : null;
            $unchanged = $old && (int) $old->item_id === (int) $line['item_id'] && (int) $old->quantity === (int) $line['quantity'];
            $needed[(int) $line['item_id']] = ($needed[(int) $line['item_id']] ?? 0) + ($unchanged ? (int) $old->stock_qty : (int) $line['quantity']);
        }

        $shortfalls = [];
        foreach ($needed as $itemId => $qty) {
            $item = InventoryItem::find($itemId);
            $available = $stock->sellableQty($item) + ($pi ? $stock->heldBy($pi, $itemId) : 0);
            if ($qty > $available) {
                $shortfalls[] = ['name' => $item->name, 'needed' => $qty, 'available' => $available, 'short' => $qty - $available];
            }
        }

        return $shortfalls;
    }

    public function cancel_shortfall()
    {
        $this->shortfalls = [];
    }

    public function view($id)
    {
        $this->view_id = ProductInvoice::withTrashed()->findOrFail($id)->id;
        $this->new_paid_on = now()->toDateString();
        $this->_page = 'view';
    }

    public function show_index()
    {
        $this->_page = 'index';
        $this->view_id = null;
    }

    public function add_payment_to_pi()
    {
        abort_unless(auth()->user()->hasPermission('invoices', 'update'), 403);
        $this->validate(['new_paid_on' => 'required|date', 'new_amount' => 'required|numeric|min:0.01', 'new_payment_mode' => 'required|string']);

        app(ProductInvoiceService::class)->addPayment(ProductInvoice::findOrFail($this->view_id), $this->new_paid_on, (float) $this->new_amount, $this->new_payment_mode);
        $this->new_amount = '';
        session()->flash('message', 'Payment recorded.');
    }

    public function prompt_reduce($lineId)
    {
        abort_unless(auth()->user()->hasPermission('invoices', 'update'), 403);
        $this->reduce_line_id = ProductInvoiceItem::findOrFail($lineId)->id;
        $this->reduce_qty = 1;
        $this->reduce_reason = '';
        $this->resetErrorBag(['reduce_qty', 'reduce_reason']);
    }

    public function reduce_stock()
    {
        abort_unless(auth()->user()->hasPermission('invoices', 'update'), 403);
        $this->validate(['reduce_qty' => 'required|integer|min:1', 'reduce_reason' => 'required|string|max:500'], [], ['reduce_qty' => 'qty', 'reduce_reason' => 'reason']);

        $line = ProductInvoiceItem::findOrFail($this->reduce_line_id);
        try {
            app(ProductInvoiceService::class)->reduceStockUsed($line, (int) $this->reduce_qty, $this->reduce_reason);
        } catch (DomainException $e) {
            $this->addError('reduce_qty', $e->getMessage());
            return;
        }

        $this->reduce_line_id = null;
        $this->dispatchBrowserEvent('pi-reduce-closed');
        session()->flash('message', "Unused {$line->name} returned to stock. The billed amount is unchanged.");
    }

    public function cancel_pi()
    {
        abort_unless(auth()->user()->hasPermission('invoices', 'delete'), 403);

        if (!Hash::check($this->cancel_password ?? '', auth()->user()->password)) {
            $this->addError('cancel_password', 'Incorrect password.');
            return;
        }

        $pi = ProductInvoice::findOrFail($this->view_id);
        app(ProductInvoiceService::class)->cancel($pi);
        $this->cancel_password = '';
        $this->dispatchBrowserEvent('pi-cancel-closed');
        session()->flash('message', "{$pi->pi_no} cancelled and its stock returned.");
        $this->show_index();
    }

    public function render()
    {
        $user = auth()->user();
        $data = [
            'canCreate' => $user->hasPermission('invoices', 'create'),
            'canEdit' => $user->hasPermission('invoices', 'update'),
            'canDelete' => $user->hasPermission('invoices', 'delete'),
            'canViewCost' => $user->hasPermission('inventory', 'view_cost'),
            'canOverridePrice' => $user->hasPermission('inventory', 'price_override'),
        ];

        if ($this->_page === 'view' && $this->view_id) {
            $data['pi'] = ProductInvoice::withTrashed()->with(['items', 'payments', 'invoice.patient', 'patient', 'creator'])->findOrFail($this->view_id);
            $data['reduceLine'] = $this->reduce_line_id ? $data['pi']->items->firstWhere('id', $this->reduce_line_id) : null;
        } elseif ($this->_page === 'form') {
            $data['invoices'] = Invoice::with('patient')->latest()->take(300)->get();
            $data['patients'] = patient::latest()->get();
            $data['products'] = InventoryItem::where('is_active', true)->orderBy('name')->get();
            $stock = app(StockService::class);
            $editing = $this->editing_id ? ProductInvoice::find($this->editing_id) : null;
            $data['available'] = collect($this->lines)->pluck('item_id')->filter()->unique()->mapWithKeys(function ($id) use ($stock, $editing) {
                $item = InventoryItem::find($id);
                return $item ? [$id => $stock->sellableQty($item) + ($editing ? $stock->heldBy($editing, (int) $id) : 0)] : [];
            })->all();
        } else {
            $data['productInvoices'] = ProductInvoice::with(['items', 'payments', 'invoice.patient', 'patient'])
                ->when($this->filter_link === 'linked', fn ($q) => $q->whereNotNull('invoice_id'))
                ->when($this->filter_link === 'standalone', fn ($q) => $q->whereNull('invoice_id'))
                ->when($this->filter_link === 'recipe', fn ($q) => $q->where('source', 'recipe'))
                ->when($this->search, fn ($q) => $q->where(fn ($q2) => $q2->where('pi_no', 'like', "%{$this->search}%")
                    ->orWhere('walkin_name', 'like', "%{$this->search}%")
                    ->orWhereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', "%{$this->search}%"))
                    ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))
                    ->orWhereHas('invoice.patient', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))))
                ->latest()
                ->latest('id')
                ->paginate(15);
        }

        return view('livewire.admins.inventory.product-invoices', $data)->layout('admins.layouts.app');
    }
}
