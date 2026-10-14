<?php

namespace App\Http\Livewire\Admins\Inventory;

use App\Http\Livewire\Admins\Inventory\Concerns\PurchasingAccess;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Inventory\PurchasingService;
use App\Traits\LogsActivity;
use DomainException;
use Livewire\Component;
use Livewire\WithPagination;

class PurchaseOrders extends Component
{
    use WithPagination;
    use LogsActivity;
    use PurchasingAccess;

    public const TAB_PERMISSION = 'manage_po';

    protected $paginationTheme = 'bootstrap';

    public $_page = 'index';
    public $view_id;

    public $search = '';
    public $filter_status = '';

    public $editing_id;
    public $editing_status = 'draft';
    public $supplier_id = '';
    public $order_date;
    public $expected_date = '';
    public $discount = 0;
    public $other_charges = 0;
    public $notes = '';
    public $lines = [];

    public $close_reason = '';

    public function mount()
    {
        $this->authorizePurchasingTab(self::TAB_PERMISSION);

        $poId = request()->query('po');
        if ($poId && PurchaseOrder::find($poId)) {
            $this->view((int) $poId);
        }
    }

    protected function blankLine(): array
    {
        return ['id' => null, 'item_id' => '', 'qty_ordered' => 1, 'unit_cost' => 0, 'min_qty' => 1];
    }

    public function show_create_form()
    {
        $this->authorizeAction('manage_po');

        $this->editing_id = null;
        $this->editing_status = 'draft';
        $this->supplier_id = '';
        $this->order_date = now()->toDateString();
        $this->expected_date = '';
        $this->discount = 0;
        $this->other_charges = 0;
        $this->notes = '';
        $this->lines = [$this->blankLine()];
        $this->resetErrorBag();
        $this->_page = 'form';
    }

    public function edit($id)
    {
        $this->authorizeAction('manage_po');

        $po = PurchaseOrder::with('items')->findOrFail($id);
        if (!$po->isEditable()) {
            session()->flash('error', "{$po->po_no} can no longer be edited.");
            return;
        }

        $this->editing_id = $po->id;
        $this->editing_status = $po->status;
        $this->supplier_id = $po->supplier_id;
        $this->order_date = $po->order_date->toDateString();
        $this->expected_date = optional($po->expected_date)->toDateString() ?? '';
        $this->discount = $po->discount;
        $this->other_charges = $po->other_charges;
        $this->notes = $po->notes;
        $this->lines = $po->items->map(fn ($line) => [
            'id' => $line->id,
            'item_id' => $line->item_id,
            'qty_ordered' => $line->qty_ordered,
            'unit_cost' => $line->unit_cost,
            'min_qty' => $po->status === 'draft' ? 1 : $line->qty_ordered,
        ])->all();
        $this->resetErrorBag();
        $this->_page = 'form';
    }

    public function add_line()
    {
        $this->lines[] = $this->blankLine();
    }

    public function remove_line($index)
    {
        if (!empty($this->lines[$index]['id']) && $this->editing_status !== 'draft') {
            return;
        }
        if (count($this->lines) > 1) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
        }
    }

    public function updated($name, $value)
    {
        // Unit cost pre-fills from the item's last cost.
        if (preg_match('/^lines\.(\d+)\.item_id$/', $name) && $value !== '') {
            $index = (int) explode('.', $name)[1];
            $item = InventoryItem::find($value);
            if ($item) {
                $this->lines[$index]['unit_cost'] = $item->cost_price;
            }
        }
    }

    public function getSubtotalProperty(): float
    {
        return collect($this->lines)->sum(fn ($line) => (float) $line['qty_ordered'] * (float) $line['unit_cost']);
    }

    public function save()
    {
        $this->authorizeAction('manage_po');

        $this->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date',
            'discount' => 'nullable|numeric|min:0',
            'other_charges' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:inventory_items,id',
            'lines.*.qty_ordered' => 'required|integer|min:1',
            'lines.*.unit_cost' => 'required|numeric|min:0',
        ], [], ['lines.*.item_id' => 'item', 'lines.*.qty_ordered' => 'qty', 'lines.*.unit_cost' => 'unit cost', 'supplier_id' => 'supplier']);

        foreach ($this->lines as $index => $line) {
            if ((int) $line['qty_ordered'] < (int) $line['min_qty']) {
                $this->addError("lines.$index.qty_ordered", "Qty can only be increased (at least {$line['min_qty']}).");
                return;
            }
        }

        try {
            $po = app(PurchasingService::class)->savePurchaseOrder([
                'supplier_id' => $this->supplier_id,
                'order_date' => $this->order_date,
                'expected_date' => $this->expected_date,
                'discount' => $this->discount,
                'other_charges' => $this->other_charges,
                'notes' => $this->notes,
            ], $this->lines, $this->editing_id ? PurchaseOrder::find($this->editing_id) : null);
        } catch (DomainException $e) {
            $this->addError('lines', $e->getMessage());
            return;
        }

        $this->logActivity($this->editing_id ? 'updated' : 'created', 'inventory', $po->id, ($this->editing_id ? 'Updated' : 'Created') . " purchase order {$po->po_no}.");
        session()->flash('message', "Purchase order {$po->po_no} saved.");
        $this->view($po->id);
    }

    public function mark_ordered($id)
    {
        $this->authorizeAction('manage_po');
        $this->runAction($id, fn ($po, $service) => $service->markOrdered($po), 'marked as ordered');
    }

    public function cancel_po($id)
    {
        $this->authorizeAction('manage_po');
        $this->runAction($id, fn ($po, $service) => $service->cancel($po), 'cancelled');
    }

    public function close_short($id)
    {
        $this->authorizeAction('manage_po');
        $this->validate(['close_reason' => 'required|string|max:500'], [], ['close_reason' => 'reason']);
        $this->runAction($id, fn ($po, $service) => $service->closeShort($po, $this->close_reason), 'closed short');
        $this->close_reason = '';
        $this->dispatchBrowserEvent('po-close-modal-hide');
    }

    protected function runAction($id, callable $action, string $done)
    {
        $po = PurchaseOrder::findOrFail($id);
        try {
            $action($po, app(PurchasingService::class));
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
            return;
        }
        $this->logActivity('updated', 'inventory', $po->id, "Purchase order {$po->po_no} {$done}.");
        session()->flash('message', "{$po->po_no} {$done}.");
    }

    public function view($id)
    {
        $this->view_id = PurchaseOrder::findOrFail($id)->id;
        $this->_page = 'view';
    }

    public function show_index()
    {
        $this->_page = 'index';
        $this->view_id = null;
    }

    public function render()
    {
        $data = [
            'canManage' => auth()->user()->hasPermission('inventory', 'manage_po'),
            'canReceive' => auth()->user()->hasPermission('inventory', 'manage_grn'),
        ];

        if ($this->_page === 'view' && $this->view_id) {
            $data['po'] = PurchaseOrder::with(['items.item', 'supplier', 'goodsReceipts', 'creator', 'closer'])->findOrFail($this->view_id);
        } elseif ($this->_page === 'form') {
            $data['suppliers'] = Supplier::where('is_active', true)->orWhere('id', $this->supplier_id ?: 0)->orderBy('name')->get();
            $data['inventoryItems'] = InventoryItem::where('is_active', true)->orderBy('name')->get();
        } else {
            $data['orders'] = PurchaseOrder::with('supplier')->withSum('items as qty_ordered_sum', 'qty_ordered')->withSum('items as qty_received_sum', 'qty_received')
                ->when($this->search, fn ($q) => $q->where(fn ($q2) => $q2->where('po_no', 'like', "%{$this->search}%")->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$this->search}%"))))
                ->when($this->filter_status, fn ($q) => $q->where('status', $this->filter_status))
                ->latest('order_date')
                ->latest('id')
                ->paginate(15);
        }

        return view('livewire.admins.inventory.purchase-orders', $data)->layout('admins.layouts.app');
    }
}
