<?php

namespace App\Http\Livewire\Admins\Inventory;

use App\Http\Livewire\Admins\Inventory\Concerns\PurchasingAccess;
use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Inventory\PurchasingService;
use App\Traits\LogsActivity;
use DomainException;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Receiving extends Component
{
    use WithPagination;
    use LogsActivity;
    use PurchasingAccess;

    public const TAB_PERMISSION = 'manage_grn';

    protected $paginationTheme = 'bootstrap';

    public $_page = 'index';
    public $view_id;
    public $search = '';

    // create
    public $po_id;
    public $supplier_id = '';
    public $supplier_bill_no = '';
    public $receive_date;
    public $due_date;
    public $due_date_touched = false;
    public $discount = 0;
    public $other_charges = 0;
    public $notes = '';
    public $lines = [];

    // edit
    public $editing_id;
    public $edit_bill_no = '';
    public $edit_due_date;
    public $edit_notes = '';
    public $edit_lines = [];

    public function mount()
    {
        $this->authorizePurchasingTab(self::TAB_PERMISSION);

        if ($grnId = request()->query('grn')) {
            if (GoodsReceipt::withTrashed()->find($grnId)) {
                $this->view((int) $grnId);
            }
        } elseif ($poId = request()->query('po')) {
            $po = PurchaseOrder::find($poId);
            if ($po && auth()->user()->hasPermission('inventory', 'manage_grn')) {
                $this->receive_against($po->id);
            }
        }
    }

    protected function startForm()
    {
        $this->supplier_bill_no = '';
        $this->receive_date = now()->toDateString();
        $this->due_date_touched = false;
        $this->discount = 0;
        $this->other_charges = 0;
        $this->notes = '';
        $this->resetErrorBag();
        $this->_page = 'create';
    }

    protected function blankLine(): array
    {
        return ['po_item_id' => null, 'item_id' => '', 'ordered' => null, 'received' => null, 'outstanding' => null,
            'qty' => 1, 'unit_cost' => 0, 'batch_no' => '', 'expiry_date' => '', 'no_expiry' => false];
    }

    public function receive_against($poId)
    {
        $this->authorizeAction('manage_grn');

        $po = PurchaseOrder::with('items.item')->findOrFail($poId);
        if (!$po->isReceivable()) {
            session()->flash('error', "{$po->po_no} is " . strtolower(PurchaseOrder::STATUSES[$po->status]) . ' and can\'t be received against.');
            return;
        }

        $this->startForm();
        $this->po_id = $po->id;
        $this->supplier_id = $po->supplier_id;
        $this->lines = $po->items->filter(fn ($line) => $line->qty_outstanding > 0)->map(fn ($line) => [
            'po_item_id' => $line->id,
            'item_id' => $line->item_id,
            'item_name' => $line->item->name . ' (' . $line->item->code . ')',
            'ordered' => $line->qty_ordered,
            'received' => $line->qty_received,
            'outstanding' => $line->qty_outstanding,
            'qty' => $line->qty_outstanding,
            'unit_cost' => $line->unit_cost,
            'batch_no' => '',
            'expiry_date' => '',
            'no_expiry' => false,
        ])->values()->all();
        $this->recalcDueDate();
    }

    public function show_direct_form()
    {
        $this->authorizeAction('manage_grn');

        $this->startForm();
        $this->po_id = null;
        $this->supplier_id = '';
        $this->lines = [$this->blankLine()];
        $this->recalcDueDate();
    }

    public function add_line()
    {
        if (!$this->po_id) {
            $this->lines[] = $this->blankLine();
        }
    }

    public function remove_line($index)
    {
        if (!$this->po_id && count($this->lines) > 1) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
        }
    }

    public function updated($name, $value)
    {
        if (in_array($name, ['supplier_id', 'receive_date'], true)) {
            $this->recalcDueDate();
        }
        if ($name === 'due_date') {
            $this->due_date_touched = true;
        }
        if (preg_match('/^lines\.(\d+)\.item_id$/', $name) && $value !== '') {
            $index = (int) explode('.', $name)[1];
            $item = InventoryItem::find($value);
            if ($item) {
                $this->lines[$index]['unit_cost'] = $item->cost_price;
            }
        }
        if (preg_match('/^(lines|edit_lines)\.(\w+)\.no_expiry$/', $name, $m) && $value) {
            $this->{$m[1]}[$m[2]]['expiry_date'] = '';
        }
    }

    /** Due date = receive date + supplier credit days, until the user edits it. */
    protected function recalcDueDate()
    {
        if ($this->due_date_touched) {
            return;
        }
        $supplier = $this->supplier_id ? Supplier::find($this->supplier_id) : null;
        $this->due_date = Carbon::parse($this->receive_date ?: now())->addDays($supplier->credit_days ?? 0)->toDateString();
    }

    protected function expiryErrors(string $field, array $lines): bool
    {
        $failed = false;
        foreach ($lines as $key => $line) {
            if (empty($line['no_expiry']) && empty($line['expiry_date']) && (int) ($line['qty'] ?? 1) > 0) {
                $this->addError("$field.$key.expiry_date", 'Enter the expiry date or tick "No expiry".');
                $failed = true;
            }
        }
        return $failed;
    }

    public function save()
    {
        $this->authorizeAction('manage_grn');

        $this->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'supplier_bill_no' => 'nullable|string|max:100',
            'receive_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:receive_date',
            'discount' => 'nullable|numeric|min:0',
            'other_charges' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:inventory_items,id',
            'lines.*.qty' => 'required|integer|min:' . ($this->po_id ? 0 : 1),
            'lines.*.unit_cost' => 'required|numeric|min:0',
            'lines.*.batch_no' => 'nullable|string|max:50',
            'lines.*.expiry_date' => 'nullable|date',
        ], [], ['supplier_id' => 'supplier', 'lines.*.item_id' => 'item', 'lines.*.qty' => 'qty', 'lines.*.unit_cost' => 'unit cost', 'lines.*.expiry_date' => 'expiry date']);

        if ($this->expiryErrors('lines', $this->lines)) {
            return;
        }

        try {
            $receipt = app(PurchasingService::class)->receive([
                'supplier_id' => $this->supplier_id,
                'supplier_bill_no' => $this->supplier_bill_no ?: null,
                'receive_date' => $this->receive_date,
                'due_date' => $this->due_date,
                'discount' => $this->discount ?: 0,
                'other_charges' => $this->other_charges ?: 0,
                'notes' => $this->notes ?: null,
            ], array_map(fn ($line) => [
                'purchase_order_item_id' => $line['po_item_id'],
                'item_id' => $line['item_id'],
                'qty' => (int) $line['qty'],
                'unit_cost' => $line['unit_cost'],
                'batch_no' => $line['batch_no'],
                'expiry_date' => $line['no_expiry'] ? null : $line['expiry_date'],
            ], $this->lines), $this->po_id ? PurchaseOrder::find($this->po_id) : null);
        } catch (DomainException $e) {
            $this->addError('lines', $e->getMessage());
            return;
        }

        $this->logActivity('created', 'inventory', $receipt->id, "Received {$receipt->grn_no}" . ($receipt->purchaseOrder ? " against {$receipt->purchaseOrder->po_no}" : ' (direct)') . '.');
        session()->flash('message', "{$receipt->grn_no} saved and stock received.");
        $this->view($receipt->id);
    }

    public function edit($id)
    {
        $this->authorizeAction('manage_grn');

        $receipt = GoodsReceipt::with('items.item', 'items.batch')->findOrFail($id);
        $service = app(PurchasingService::class);

        $this->editing_id = $receipt->id;
        $this->edit_bill_no = $receipt->supplier_bill_no;
        $this->edit_due_date = $receipt->due_date->toDateString();
        $this->edit_notes = $receipt->notes;
        $this->edit_lines = $receipt->items->mapWithKeys(fn ($line) => [$line->id => [
            'item_name' => $line->item->name . ' (' . $line->item->code . ')',
            'batch_no' => $line->batch_no,
            'expiry_date' => optional($line->expiry_date)->toDateString() ?? '',
            'no_expiry' => $line->expiry_date === null,
            'qty' => $line->qty,
            'unit_cost' => $line->unit_cost,
            'used' => $service->isLineUsed($line),
        ]])->all();
        $this->resetErrorBag();
        $this->_page = 'edit';
    }

    public function update()
    {
        $this->authorizeAction('manage_grn');

        $receipt = GoodsReceipt::findOrFail($this->editing_id);

        $this->validate([
            'edit_bill_no' => 'nullable|string|max:100',
            'edit_due_date' => 'required|date',
            'edit_notes' => 'nullable|string',
            'edit_lines.*.batch_no' => 'nullable|string|max:50',
            'edit_lines.*.expiry_date' => 'nullable|date',
            'edit_lines.*.qty' => 'required|integer|min:1',
            'edit_lines.*.unit_cost' => 'required|numeric|min:0',
        ], [], ['edit_due_date' => 'due date', 'edit_lines.*.qty' => 'qty', 'edit_lines.*.unit_cost' => 'unit cost']);

        if ($this->expiryErrors('edit_lines', $this->edit_lines)) {
            return;
        }

        try {
            app(PurchasingService::class)->updateReceipt($receipt, [
                'supplier_bill_no' => $this->edit_bill_no ?: null,
                'due_date' => $this->edit_due_date,
                'notes' => $this->edit_notes ?: null,
            ], collect($this->edit_lines)->map(fn ($line) => [
                'batch_no' => $line['batch_no'],
                'expiry_date' => $line['no_expiry'] ? null : $line['expiry_date'],
                'qty' => (int) $line['qty'],
                'unit_cost' => $line['unit_cost'],
            ])->all());
        } catch (DomainException $e) {
            $this->addError('edit_lines', $e->getMessage());
            return;
        }

        session()->flash('message', "{$receipt->grn_no} updated.");
        $this->view($receipt->id);
    }

    public function delete($id)
    {
        $this->authorizeAction('manage_grn');

        $receipt = GoodsReceipt::findOrFail($id);
        try {
            app(PurchasingService::class)->deleteReceipt($receipt);
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        $this->logActivity('deleted', 'inventory', $receipt->id, "Deleted {$receipt->grn_no} (stock reversed, payments released as advance).");
        session()->flash('message', "{$receipt->grn_no} deleted. Its stock was reversed and any payments against it are now advance.");
        $this->show_index();
    }

    public function view($id)
    {
        $this->view_id = GoodsReceipt::withTrashed()->findOrFail($id)->id;
        $this->_page = 'view';
    }

    public function show_index()
    {
        $this->_page = 'index';
        $this->view_id = null;
        $this->editing_id = null;
    }

    public function render()
    {
        $data = ['canManage' => auth()->user()->hasPermission('inventory', 'manage_grn')];

        if ($this->_page === 'view' && $this->view_id) {
            $receipt = GoodsReceipt::withTrashed()->with(['items.item', 'items.batch', 'supplier', 'purchaseOrder', 'allocations.payment', 'creator'])->findOrFail($this->view_id);
            $service = app(PurchasingService::class);
            $data['receipt'] = $receipt;
            $data['anyUsed'] = $receipt->items->contains(fn ($line) => $service->isLineUsed($line));
        } elseif ($this->_page === 'create') {
            $data['suppliers'] = Supplier::where('is_active', true)->orderBy('name')->get();
            $data['inventoryItems'] = InventoryItem::where('is_active', true)->orderBy('name')->get();
            $data['po'] = $this->po_id ? PurchaseOrder::find($this->po_id) : null;
        } elseif ($this->_page === 'edit') {
            $data['receipt'] = GoodsReceipt::findOrFail($this->editing_id);
        } else {
            $data['receipts'] = GoodsReceipt::with(['supplier', 'purchaseOrder'])
                ->when($this->search, fn ($q) => $q->where(fn ($q2) => $q2->where('grn_no', 'like', "%{$this->search}%")
                    ->orWhere('supplier_bill_no', 'like', "%{$this->search}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$this->search}%"))))
                ->latest('receive_date')
                ->latest('id')
                ->paginate(15);
        }

        return view('livewire.admins.inventory.receiving', $data)->layout('admins.layouts.app');
    }
}
