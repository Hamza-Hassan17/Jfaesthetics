<?php

namespace App\Http\Livewire\Admins;

use App\Support\RetryingTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\InventoryItem;
use App\Models\doctor;
use App\Models\patient;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Services\Inventory\InvoiceService;
use App\Services\Inventory\StockService;
use App\Traits\LogsActivity;
use DomainException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Invoices extends Component
{
    use WithPagination;
    use LogsActivity;

    protected $paginationTheme = 'bootstrap';

    public $_page = 'index';

    public $patient_id;
    public $doctor_id;
    public $notes;
    public $items = [];
    public $payments = [];

    public $view_invoice_id;
    public $editing_invoice_id;

    // Product lines whose qty exceeds sellable stock: [['name' => ..., 'needed' => ..., 'available' => ...]]
    public $shortfalls = [];

    public $search = '';
    public $filter_from = '';
    public $filter_to = '';
    public $filter_status = '';

    public $quick_patient_name;
    public $quick_patient_phone;
    public $quick_patient_address;
    public $quick_patient_gender;
    public $quick_patient_age;
    public $quick_patient_bloodgroup;

    public function resetFilters()
    {
        $this->search = '';
        $this->filter_from = '';
        $this->filter_to = '';
        $this->filter_status = '';
        $this->resetPage();
    }

    public function mount()
    {
        $this->resetForm();

        /**
         * Deep-link support for the navbar search dropdown: /admin/invoices?invoice=5
         * opens straight into that invoice's view page.
         */
        $invoiceId = request()->query('invoice');
        if ($invoiceId && Invoice::find($invoiceId)) {
            $this->view($invoiceId);
        } elseif (request()->query('new') && auth()->user()->hasPermission('invoices', 'create')) {
            $this->show_create_form();
        }
    }

    /**
     * Super Admin (rank 0) and Admin (rank 1) see/manage every invoice by
     * rank. Receptionist is also allowed to see every invoice by name (not
     * rank, since Doctor/Nurse/Accountant share rank 2 with it but should
     * stay scoped to their own) - front-desk staff routinely need to look
     * up any patient's invoice, not just ones they personally created.
     * Every other role stays scoped to invoices they created themselves.
     */
    protected function canViewAllInvoices(): bool
    {
        $role = auth()->user()->role;
        return $role && ($role->rank <= 1 || $role->name === 'Receptionist');
    }

    /**
     * Form lines carry a uid so a child line can point at its service line
     * (parent_uid) before either is saved; `id` is the saved line id, kept
     * stable across edits.
     */
    protected function blankItem(array $overrides = [])
    {
        return array_merge([
            'uid' => 'n' . Str::random(10),
            'parent_uid' => null,
            'id' => null,
            'type' => config('app.custom_invoice_lines') ? 'custom' : 'service',
            'catalog_id' => '',
            'category' => '', // form-only: narrows the service list
            'service' => '',
            'quantity' => 1,
            'session' => '',
            'service_charges' => 0,
            'discount_type' => 'flat',
            'discount_value' => 0,
            'is_billable' => true,
            'qty_per_session' => null,
            'qty_overridden' => false,
        ], $overrides);
    }

    protected function blankPayment()
    {
        return ['id' => null, 'paid_on' => now()->format('Y-m-d'), 'amount' => 0, 'payment_mode' => 'Cash'];
    }

    public function resetForm()
    {
        $this->editing_invoice_id = null;
        $this->patient_id = '';
        $this->doctor_id = '';
        $this->notes = '';
        $this->items = [$this->blankItem()];
        $this->payments = [$this->blankPayment()];
    }

    public function show_create_form()
    {
        $this->resetForm();
        $this->_page = 'create';
    }

    public function show_index()
    {
        $this->_page = 'index';
    }

    public function view($id)
    {
        $invoice = Invoice::findOrFail($id);
        abort_unless($this->canViewAllInvoices() || $invoice->created_by === auth()->id(), 403, 'You do not have access to this invoice.');

        $this->view_invoice_id = $id;
        $this->_page = 'view';
    }

    public function edit_invoice($id)
    {
        abort_unless(auth()->user()->hasPermission('invoices', 'update'), 403);

        $invoice = Invoice::with(['items', 'payments'])->findOrFail($id);
        abort_unless($this->canViewAllInvoices() || $invoice->created_by === auth()->id(), 403, 'You do not have access to this invoice.');

        $this->editing_invoice_id = $invoice->id;
        $this->patient_id = $invoice->patient_id;
        $this->doctor_id = $invoice->doctor_id;
        $this->notes = $invoice->notes;

        $this->items = $invoice->items->sortBy(fn ($item) => [$item->position, $item->id])->values()->map(fn ($item) => [
            'uid' => 'l' . $item->id,
            'parent_uid' => $item->parent_line_id ? 'l' . $item->parent_line_id : null,
            'id' => $item->id,
            'type' => $item->line_type,
            'catalog_id' => $item->service_id ?: ($item->inventory_item_id ?: ''),
            'category' => $item->service_id ? (string) Service::withTrashed()->whereKey($item->service_id)->value('category') : '',
            'service' => $item->service,
            'quantity' => $item->quantity,
            'session' => $item->session_number,
            'service_charges' => $item->service_charges,
            'discount_type' => $item->discount_type,
            'discount_value' => $item->discount_value,
            'is_billable' => (bool) $item->is_billable,
            'qty_per_session' => $item->qty_per_session,
            'qty_overridden' => (bool) $item->qty_overridden,
        ])->all();

        if (empty($this->items)) {
            $this->items = [$this->blankItem()];
        }

        $this->payments = $invoice->payments->map(fn ($payment) => [
            'id' => $payment->id,
            'paid_on' => \Carbon\Carbon::parse($payment->paid_on)->format('Y-m-d'),
            'amount' => $payment->amount,
            'payment_mode' => $payment->payment_mode,
        ])->all();

        if (empty($this->payments)) {
            $this->payments = [$this->blankPayment()];
        }

        $this->_page = 'create';
    }

    public function add_item()
    {
        $this->items[] = $this->blankItem();
    }

    /** "Add item used" under a service line. */
    public function add_child($index)
    {
        $parent = $this->items[$index] ?? null;
        if (!$parent || $parent['type'] !== 'service') {
            return;
        }

        $this->insertAfterFamily($parent['uid'], [$this->blankItem(['type' => 'product', 'parent_uid' => $parent['uid'], 'qty_overridden' => true])]);
    }

    /** Removing a service line removes its child lines too. */
    public function remove_item($index)
    {
        $line = $this->items[$index] ?? null;
        if (!$line) {
            return;
        }

        $remaining = collect($this->items)->reject(fn ($item, $i) => $i === (int) $index || ($line['type'] === 'service' && $item['parent_uid'] === $line['uid']));
        if ($remaining->isEmpty()) {
            return;
        }
        $this->items = $remaining->values()->all();
    }

    /** Inserts lines right after a service line and its existing children. */
    protected function insertAfterFamily(string $parentUid, array $newLines)
    {
        $at = 0;
        foreach ($this->items as $i => $item) {
            if ($item['uid'] === $parentUid || $item['parent_uid'] === $parentUid) {
                $at = $i + 1;
            }
        }
        array_splice($this->items, $at, 0, $newLines);
        $this->items = array_values($this->items);
    }

    /** Replaces a service line's children with its recipe (qty × sessions). */
    protected function fillRecipe(int $index)
    {
        $service = $this->items[$index];
        $this->items = array_values(array_filter($this->items, fn ($item) => $item['parent_uid'] !== $service['uid']));
        $sessions = max(1, (int) $service['session']);

        $children = ServiceItem::with('item')->where('service_id', $service['catalog_id'])->get()
            ->filter(fn ($recipeLine) => $recipeLine->item && $recipeLine->item->is_active)
            ->map(fn ($recipeLine) => $this->blankItem([
                'type' => 'product',
                'parent_uid' => $service['uid'],
                'catalog_id' => $recipeLine->item_id,
                'service' => $recipeLine->item->name,
                'qty_per_session' => $recipeLine->qty_per_session,
                'quantity' => $recipeLine->qty_per_session * $sessions,
                'is_billable' => $recipeLine->is_billable,
                'service_charges' => $recipeLine->is_billable ? $recipeLine->item->sale_price : 0,
            ]))
            ->values()
            ->all();

        if ($children) {
            $this->insertAfterFamily($service['uid'], $children);
        }
    }

    /** Sessions changed: recipe children follow, unless typed by hand. */
    protected function recalcChildren(string $parentUid, $sessions)
    {
        $sessions = max(1, (int) $sessions);
        foreach ($this->items as $i => $item) {
            if ($item['parent_uid'] === $parentUid && !$item['qty_overridden'] && $item['qty_per_session']) {
                $this->items[$i]['quantity'] = $item['qty_per_session'] * $sessions;
            }
        }
    }

    public function updated($name, $value)
    {
        if (preg_match('/^items\.(\d+)\.session$/', $name)) {
            $index = (int) explode('.', $name)[1];
            if ($this->items[$index]['type'] === 'service') {
                $this->recalcChildren($this->items[$index]['uid'], $value);
            }
        }

        if (preg_match('/^items\.(\d+)\.quantity$/', $name)) {
            $index = (int) explode('.', $name)[1];
            if ($this->items[$index]['parent_uid']) {
                $this->items[$index]['qty_overridden'] = true;
            }
        }

        if (preg_match('/^items\.(\d+)\.is_billable$/', $name)) {
            $index = (int) explode('.', $name)[1];
            $item = InventoryItem::find($this->items[$index]['catalog_id']);
            $this->items[$index]['service_charges'] = $value && $item ? $item->sale_price : 0;
            if (!$value) {
                $this->items[$index]['discount_value'] = 0;
            }
        }

        if (preg_match('/^items\.(\d+)\.category$/', $name)) {
            $index = (int) explode('.', $name)[1];
            $uid = $this->items[$index]['uid'];
            $this->items = array_values(array_filter($this->items, fn ($item) => $item['parent_uid'] !== $uid));
            $this->items[$index]['catalog_id'] = '';
            $this->items[$index]['service'] = '';
            $this->items[$index]['service_charges'] = 0;
        }

        if (preg_match('/^items\.(\d+)\.type$/', $name)) {
            $index = (int) explode('.', $name)[1];
            $uid = $this->items[$index]['uid'];
            $this->items = array_values(array_filter($this->items, fn ($item) => $item['parent_uid'] !== $uid));
            $this->items[$index]['catalog_id'] = '';
            $this->items[$index]['service'] = '';
            if ($this->items[$index]['type'] === 'custom') {
                $this->items[$index]['service_charges'] = 0;
            }
            // Products are sold by quantity and have no sessions; a service
            // is one package (quantity fixed at 1, hidden) whose session
            // count is informational and never multiplies the price.
            if ($this->items[$index]['type'] === 'product') {
                $this->items[$index]['session'] = '';
            } elseif ($this->items[$index]['type'] === 'service') {
                $this->items[$index]['quantity'] = 1;
            }
        }

        if (preg_match('/^items\.(\d+)\.catalog_id$/', $name) && $value !== '') {
            $index = (int) explode('.', $name)[1];
            $type = $this->items[$index]['type'];

            if ($type === 'service') {
                $catalogItem = Service::find($value);
                if ($catalogItem) {
                    $this->items[$index]['service'] = $catalogItem->name;
                    $this->items[$index]['service_charges'] = $catalogItem->price;
                }
            } elseif ($type === 'product') {
                $catalogItem = InventoryItem::find($value);
                if ($catalogItem) {
                    $isChild = (bool) $this->items[$index]['parent_uid'];
                    // A hand-added child takes the item's billable default.
                    if ($isChild && !$this->items[$index]['qty_per_session']) {
                        $this->items[$index]['is_billable'] = (bool) $catalogItem->default_billable;
                    }
                    $this->items[$index]['service'] = $catalogItem->name;
                    $this->items[$index]['service_charges'] = $this->items[$index]['is_billable'] ? $catalogItem->sale_price : 0;
                }
            }
        }
    }

    public function add_quick_patient()
    {
        $this->validate([
            'quick_patient_name' => 'required|string|min:2|max:50',
            'quick_patient_phone' => 'required|numeric',
            'quick_patient_address' => 'nullable|string',
            'quick_patient_gender' => 'nullable|string',
            'quick_patient_age' => 'nullable|numeric',
            'quick_patient_bloodgroup' => 'nullable|string',
        ]);

        $newPatient = patient::create([
            'name' => $this->quick_patient_name,
            'phone' => $this->quick_patient_phone,
            'address' => $this->quick_patient_address,
            'gender' => $this->quick_patient_gender,
            'age' => $this->quick_patient_age,
            'bloodgroup' => $this->quick_patient_bloodgroup,
        ]);

        $this->patient_id = $newPatient->id;

        $this->quick_patient_name = '';
        $this->quick_patient_phone = '';
        $this->quick_patient_address = '';
        $this->quick_patient_gender = '';
        $this->quick_patient_age = '';
        $this->quick_patient_bloodgroup = '';

        $this->dispatchBrowserEvent('patient-quick-added');
        session()->flash('message', 'Patient added and selected for this invoice.');
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

    public function getItemTotalsProperty()
    {
        return collect($this->items)->map(function ($item) {
            $subTotal = (float) $item['quantity'] * (float) $item['service_charges'];
            $discountValue = (float) ($item['discount_value'] ?? 0);
            $discountAmount = ($item['discount_type'] ?? 'flat') === 'percent'
                ? $subTotal * $discountValue / 100
                : $discountValue;
            $discountAmount = min($discountAmount, $subTotal);
            return [
                'sub_total' => $subTotal,
                'discount_amount' => $discountAmount,
                'after_discount' => $subTotal - $discountAmount,
            ];
        });
    }

    public function getGrandTotalProperty()
    {
        return $this->item_totals->sum('after_discount');
    }

    public function getPaidTotalProperty()
    {
        return collect($this->payments)->sum(fn ($p) => (float) $p['amount']);
    }

    /**
     * @param bool $confirmShortfall true when the user chose "Save anyway"
     *        in the shortfall modal (stock may go negative, spec decision 11)
     */
    public function generate_invoice($confirmShortfall = false)
    {
        abort_unless(auth()->user()->hasPermission('invoices', $this->editing_invoice_id ? 'update' : 'create'), 403);

        $this->validate([
            'patient_id' => 'required',
            'doctor_id' => 'nullable',
            'items' => 'required|array|min:1',
            'items.*.type' => ['required', 'in:service,product,custom', function ($attribute, $value, $fail) {
                // Lines saved before custom lines were switched off may stay.
                $index = (int) explode('.', $attribute)[1];
                if ($value === 'custom' && !config('app.custom_invoice_lines') && empty($this->items[$index]['id'])) {
                    $fail('Choose a service from the list.');
                }
            }],
            'items.*.service' => 'required_unless:items.*.type,product|nullable|string',
            'items.*.catalog_id' => 'required_if:items.*.type,product',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.session' => 'nullable|integer|between:1,10',
            'items.*.service_charges' => 'required|numeric|min:0',
            'items.*.discount_type' => 'required|in:flat,percent',
            'items.*.discount_value' => 'required|numeric|min:0',
            'payments.*.paid_on' => 'required|date',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.payment_mode' => 'required|string',
        ], [
            'items.*.catalog_id.required_if' => 'Choose a product from inventory.',
            'items.*.service.required_unless' => 'Enter or choose what this line is for.',
        ]);

        $stock = app(StockService::class);

        if (!$confirmShortfall) {
            $this->shortfalls = $this->findShortfalls($stock);
            if ($this->shortfalls) {
                $this->dispatchBrowserEvent('invoice-shortfall-open');
                return;
            }
        }
        $this->shortfalls = [];

        $editing = $this->editing_invoice_id ? Invoice::findOrFail($this->editing_invoice_id) : null;

        try {
            $invoice = app(InvoiceService::class)->save(
                ['patient_id' => $this->patient_id, 'doctor_id' => $this->doctor_id, 'notes' => $this->notes],
                $this->items,
                $this->payments,
                $editing,
                auth()->user()->hasPermission('inventory', 'price_override')
            );
        } catch (DomainException $e) {
            $this->addError('items', $e->getMessage());
            return;
        }

        if (!$editing) {
            $this->logActivity('created', 'invoices', $invoice->id, "Created invoice #{$invoice->invoice_number}.");
        }

        session()->flash('message', $editing ? 'Invoice updated successfully.' : 'Invoice generated successfully.');
        $this->view_invoice_id = $invoice->id;
        $this->_page = 'view';
    }

    /**
     * Stock lines (summed per item) needing more than is sellable. A saved
     * line whose item and billed qty are unchanged keeps what it holds
     * (it may have had a stock-only reduction); stock the invoice already
     * holds counts as available.
     */
    protected function findShortfalls(StockService $stock): array
    {
        $editing = $this->editing_invoice_id ? Invoice::find($this->editing_invoice_id) : null;
        $saved = $editing ? $editing->items()->get()->keyBy('id') : collect();

        $needed = [];
        foreach ($this->items as $line) {
            if ($line['type'] !== 'product' || !$line['catalog_id']) {
                continue;
            }
            $old = $line['id'] ? $saved->get((int) $line['id']) : null;
            $unchanged = $old && (int) $old->inventory_item_id === (int) $line['catalog_id'] && (int) $old->quantity === (int) $line['quantity'];
            $needed[(int) $line['catalog_id']] = ($needed[(int) $line['catalog_id']] ?? 0) + ($unchanged ? (int) $old->stock_qty : (int) $line['quantity']);
        }

        $shortfalls = [];
        foreach ($needed as $itemId => $qty) {
            $product = InventoryItem::find($itemId);
            if (!$product) {
                continue;
            }
            $available = $stock->sellableQty($product) + ($editing ? $stock->heldBy($editing, $itemId) : 0);
            if ($qty > $available) {
                $shortfalls[] = ['name' => $product->name, 'needed' => $qty, 'available' => $available, 'short' => $qty - $available];
            }
        }

        return $shortfalls;
    }

    public function cancel_shortfall()
    {
        $this->shortfalls = [];
    }

    /**
     * Live "available" figure shown beside each product and child line.
     *
     * @return array<int, int> inventory item id => sellable qty
     */
    protected function availableForSelectedProducts(): array
    {
        $stock = app(StockService::class);
        $editing = $this->editing_invoice_id ? Invoice::find($this->editing_invoice_id) : null;

        return collect($this->items)
            ->where('type', 'product')
            ->pluck('catalog_id')
            ->filter()
            ->unique()
            ->mapWithKeys(function ($id) use ($stock, $editing) {
                $product = InventoryItem::find($id);
                if (!$product) {
                    return [];
                }
                return [$id => $stock->sellableQty($product) + ($editing ? $stock->heldBy($editing, (int) $id) : 0)];
            })
            ->all();
    }

    // ---- Stock-only reduction (prepaid package, patient stopped early) ----

    public $reduce_line_id;
    public $reduce_qty = 1;
    public $reduce_reason = '';

    public function prompt_reduce($lineId)
    {
        abort_unless(auth()->user()->hasPermission('invoices', 'update'), 403);

        $line = InvoiceItem::findOrFail($lineId);
        abort_unless($this->canViewAllInvoices() || $line->invoice->created_by === auth()->id(), 403, 'You do not have access to this invoice.');

        $this->reduce_line_id = $line->id;
        $this->reduce_qty = 1;
        $this->reduce_reason = '';
        $this->resetErrorBag(['reduce_qty', 'reduce_reason']);
    }

    public function reduce_stock()
    {
        abort_unless(auth()->user()->hasPermission('invoices', 'update'), 403);

        $this->validate([
            'reduce_qty' => 'required|integer|min:1',
            'reduce_reason' => 'required|string|max:500',
        ], [], ['reduce_qty' => 'qty', 'reduce_reason' => 'reason']);

        $line = InvoiceItem::findOrFail($this->reduce_line_id);
        abort_unless($this->canViewAllInvoices() || $line->invoice->created_by === auth()->id(), 403, 'You do not have access to this invoice.');

        try {
            app(InvoiceService::class)->reduceStockUsed($line, (int) $this->reduce_qty, $this->reduce_reason);
        } catch (DomainException $e) {
            $this->addError('reduce_qty', $e->getMessage());
            return;
        }

        $this->reduce_line_id = null;
        $this->dispatchBrowserEvent('invoice-reduce-closed');
        session()->flash('message', "Unused {$line->service} returned to stock. The billed amount is unchanged.");
    }

    public function add_payment_to_invoice()
    {
        $this->validate([
            'new_paid_on' => 'required|date',
            'new_amount' => 'required|numeric|min:0.01',
            'new_payment_mode' => 'required|string',
        ]);

        InvoicePayment::create([
            'invoice_id' => $this->view_invoice_id,
            'paid_on' => $this->new_paid_on,
            'amount' => $this->new_amount,
            'payment_mode' => $this->new_payment_mode,
        ]);

        $this->new_amount = '';
        session()->flash('message', 'Payment recorded successfully.');
    }

    public $new_paid_on;
    public $new_amount;
    public $new_payment_mode = 'Cash';

    public $confirm_delete_id;
    public $confirm_delete_password;

    public function prompt_delete($id)
    {
        abort_unless(auth()->user()->hasPermission('invoices', 'delete'), 403);
        $invoice = Invoice::findOrFail($id);
        abort_unless($this->canViewAllInvoices() || $invoice->created_by === auth()->id(), 403, 'You do not have access to this invoice.');
        $this->confirm_delete_id = $id;
        $this->confirm_delete_password = '';
        $this->resetErrorBag('confirm_delete_password');
    }

    /**
     * Deleting an invoice is a high-risk financial action (RBAC spec 8.6,
     * closest analog to "issuing a refund") — require the acting user to
     * re-enter their own password immediately before it happens.
     */
    public function delete()
    {
        abort_unless(auth()->user()->hasPermission('invoices', 'delete'), 403);

        if (!\Illuminate\Support\Facades\Hash::check($this->confirm_delete_password ?? '', auth()->user()->password)) {
            $this->addError('confirm_delete_password', 'Incorrect password.');
            return;
        }

        $invoice = Invoice::find($this->confirm_delete_id);
        if (!$invoice) {
            $this->confirm_delete_id = null;
            return;
        }

        abort_unless($this->canViewAllInvoices() || $invoice->created_by === auth()->id(), 403, 'You do not have access to this invoice.');

        $invoiceNumber = $invoice->invoice_number;
        $id = $invoice->id;

        // Delete is the invoice "cancel" (spec decision K): stock goes back,
        // the record stays soft-deleted.
        RetryingTransaction::run(function () use ($invoice) {
            $stock = app(StockService::class);
            // Lock order: invoice → its product invoices → items.
            Invoice::lockForUpdate()->findOrFail($invoice->id);
            $productInvoices = \App\Models\ProductInvoice::where('invoice_id', $invoice->id)->orderBy('id')->lockForUpdate()->get();
            $stock->lockItems(array_merge($stock->itemsMovedBy($invoice), ...$productInvoices->map(fn ($pi) => $stock->itemsMovedBy($pi))->all()));

            foreach ($productInvoices as $pi) {
                $stock->reverse($pi);
                $pi->delete();
            }
            $stock->reverse($invoice);
            $invoice->delete();
        });
        $this->logActivity('deleted', 'invoices', $id, "Deleted invoice #{$invoiceNumber}.");

        $this->confirm_delete_id = null;
        $this->confirm_delete_password = '';
        $this->dispatchBrowserEvent('invoice-delete-confirmed');
        session()->flash('message', 'Invoice deleted successfully.');
    }

    public function render()
    {
        if ($this->_page === 'view' && $this->view_invoice_id) {
            $this->new_paid_on = $this->new_paid_on ?: now()->format('Y-m-d');
            $invoice = Invoice::with(['items', 'payments', 'patient', 'doctor.employ'])->findOrFail($this->view_invoice_id);
            $lines = $invoice->items->sortBy(fn ($item) => [$item->position, $item->id])->values();

            return view('livewire.admins.invoices', [
                'invoice' => $invoice,
                // Billable lines in order, each child right under its service.
                'billedLines' => $lines->filter(fn ($line) => $line->is_billable)->values(),
                // D3: shown to staff here, never on the patient PDF.
                'consumables' => $lines->reject(fn ($line) => $line->is_billable)->values(),
                'reduceLine' => $this->reduce_line_id ? $invoice->items->firstWhere('id', $this->reduce_line_id) : null,
                'canEdit' => auth()->user()->hasPermission('invoices', 'update'),
                'productInvoices' => \App\Models\ProductInvoice::with(['items', 'payments'])->where('invoice_id', $invoice->id)->orderBy('id')->get(),
            ])->layout('admins.layouts.app');
        }

        if ($this->_page === 'create') {
            return view('livewire.admins.invoices', [
                'patients' => patient::all(),
                'doctors' => doctor::with('employ')->whereHas('employ')->get(),
                'services' => Service::orderByRaw('category IS NULL')->orderBy('category')->orderBy('id')->get(),
                'serviceCategories' => Service::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
                'products' => InventoryItem::where('is_active', true)->orderBy('name')->get(),
                'available' => $this->availableForSelectedProducts(),
                'canOverridePrice' => auth()->user()->hasPermission('inventory', 'price_override'),
            ])->layout('admins.layouts.app');
        }

        $from = $this->filter_from ?: null;
        $to = $this->filter_to ?: null;

        $invoices = Invoice::with(['patient', 'doctor.employ'])
            ->when(!$this->canViewAllInvoices(), fn ($q) => $q->where('created_by', auth()->id()))
            ->when($this->search, fn ($q) => $q->where('invoice_number', 'like', "%{$this->search}%"))
            // Same "activity within the range" semantics as the Reports
            // page: an invoice whose payment was recorded today should
            // surface when filtering to today, even if it was created
            // earlier.
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
            ->latest()
            ->get();

        if ($this->filter_status) {
            $status = $this->filter_status;
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

        $perPage = 10;
        $page = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $pagedItems = $invoices->slice(($page - 1) * $perPage, $perPage)->values();
        $invoicesPaginated = new \Illuminate\Pagination\LengthAwarePaginator($pagedItems, $invoices->count(), $perPage, $page, [
            'path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath(),
        ]);

        return view('livewire.admins.invoices', [
            'invoices' => $invoicesPaginated,
        ])->layout('admins.layouts.app');
    }
}
