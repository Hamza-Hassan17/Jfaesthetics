<?php

namespace App\Http\Livewire\Admins\Inventory;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\ItemBatch;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Traits\LogsActivity;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Items extends Component
{
    use WithPagination;
    use LogsActivity;

    protected $paginationTheme = 'bootstrap';

    public $_page = 'index';
    public $view_id;

    public $search = '';
    public $filter_category = '';
    public $filter_status = '';

    public $editing_id;
    public $code;
    public $name;
    public $category_id = '';
    public $cost_price = 0;
    public $sale_price = 0;
    public $low_stock_threshold = 10;
    public $default_billable = true;
    public $is_active = true;

    public const STATUS_FILTERS = [
        'low' => 'Low stock',
        'negative' => 'Negative',
        'expiring' => 'Expiring (30 days)',
        'expired' => 'Expired stock',
        'inactive' => 'Inactive',
    ];

    public function resetFilters()
    {
        $this->search = '';
        $this->filter_category = '';
        $this->filter_status = '';
        $this->resetPage();
    }

    protected function resetForm()
    {
        $this->editing_id = null;
        $this->code = '';
        $this->name = '';
        $this->category_id = '';
        $this->cost_price = 0;
        $this->sale_price = 0;
        $this->low_stock_threshold = 10;
        $this->default_billable = true;
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function show_create()
    {
        abort_unless(auth()->user()->hasPermission('inventory', 'manage_items'), 403);

        $this->resetForm();
        $this->dispatchBrowserEvent('inventory-item-modal-open');
    }

    public function edit($id)
    {
        abort_unless(auth()->user()->hasPermission('inventory', 'manage_items'), 403);

        $item = InventoryItem::findOrFail($id);
        $this->editing_id = $item->id;
        $this->code = $item->code;
        $this->name = $item->name;
        $this->category_id = $item->category_id ?: '';
        $this->cost_price = $item->cost_price;
        $this->sale_price = $item->sale_price;
        $this->low_stock_threshold = $item->low_stock_threshold;
        $this->default_billable = $item->default_billable;
        $this->is_active = $item->is_active;
        $this->resetErrorBag();
        $this->dispatchBrowserEvent('inventory-item-modal-open');
    }

    public function save()
    {
        abort_unless(auth()->user()->hasPermission('inventory', 'manage_items'), 403);

        $this->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('inventory_items', 'code')->ignore($this->editing_id)],
            'name' => 'required|string|max:150',
            'category_id' => 'nullable|exists:inventory_categories,id',
            'cost_price' => 'nullable|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'low_stock_threshold' => 'required|integer|min:0',
            'default_billable' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $data = [
            'code' => trim($this->code),
            'name' => trim($this->name),
            'category_id' => $this->category_id ?: null,
            'sale_price' => $this->sale_price,
            'low_stock_threshold' => $this->low_stock_threshold,
            'default_billable' => (bool) $this->default_billable,
            'is_active' => (bool) $this->is_active,
        ];

        // Without view_cost the cost field isn't shown, so it must not be overwritten.
        if (auth()->user()->hasPermission('inventory', 'view_cost')) {
            $data['cost_price'] = $this->cost_price ?: 0;
        }

        if ($this->editing_id) {
            $item = InventoryItem::findOrFail($this->editing_id);
            $item->update($data);
            $this->logActivity('updated', 'inventory', $item->id, "Updated inventory item {$item->code} ({$item->name}).");
            session()->flash('message', 'Item updated successfully.');
        } else {
            $item = InventoryItem::create($data + ['created_by' => auth()->id()]);
            $this->logActivity('created', 'inventory', $item->id, "Created inventory item {$item->code} ({$item->name}).");
            session()->flash('message', 'Item created. Load its current stock with an Opening stock adjustment.');
        }

        $this->resetForm();
        $this->dispatchBrowserEvent('inventory-item-modal-close');
    }

    public function view($id)
    {
        $this->view_id = InventoryItem::findOrFail($id)->id;
        $this->_page = 'view';
    }

    public function show_index()
    {
        $this->_page = 'index';
        $this->view_id = null;
    }

    public function render()
    {
        $canViewCost = auth()->user()->hasPermission('inventory', 'view_cost');

        if ($this->_page === 'view' && $this->view_id) {
            return $this->renderItem($canViewCost);
        }

        $expiredIds = ItemBatch::expiredWithStock()->distinct()->pluck('item_id')->all();
        $expiringIds = ItemBatch::expiringWithStock()->distinct()->pluck('item_id')->all();

        $items = InventoryItem::with('category')
            ->withUsableQty()
            ->when($this->search, fn ($q) => $q->where(fn ($q2) => $q2->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%")))
            ->when($this->filter_category, fn ($q) => $q->where('category_id', $this->filter_category))
            ->when($this->filter_status === 'low', fn ($q) => $q->lowStock())
            ->when($this->filter_status === 'negative', fn ($q) => $q->where('qty_on_hand', '<', 0))
            ->when($this->filter_status === 'expiring', fn ($q) => $q->whereIn('id', $expiringIds))
            ->when($this->filter_status === 'expired', fn ($q) => $q->whereIn('id', $expiredIds))
            ->when($this->filter_status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admins.inventory.items', [
            'items' => $items,
            'expiredIds' => array_flip($expiredIds),
            'expiringIds' => array_flip($expiringIds),
            'categories' => InventoryCategory::orderBy('name')->get(),
            'canViewCost' => $canViewCost,
            'canManage' => auth()->user()->hasPermission('inventory', 'manage_items'),
        ])->layout('admins.layouts.app');
    }

    protected function renderItem(bool $canViewCost)
    {
        $item = InventoryItem::with('category')->withUsableQty()->findOrFail($this->view_id);

        $movements = StockMovement::with('batch', 'creator')
            ->where('item_id', $item->id)
            ->orderBy('moved_at')
            ->orderBy('id')
            ->get();

        $balance = 0;
        foreach ($movements as $movement) {
            $balance += $movement->qty;
            $movement->running_balance = $balance;
        }

        return view('livewire.admins.inventory.items', [
            'item' => $item,
            'batches' => ItemBatch::where('item_id', $item->id)->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')->orderBy('id')->get(),
            'movements' => $movements->reverse()->values(),
            'references' => $this->loadReferences($movements),
            'canViewCost' => $canViewCost,
            'canManage' => auth()->user()->hasPermission('inventory', 'manage_items'),
        ])->layout('admins.layouts.app');
    }

    /**
     * Document label + link for each ledger row, loaded in one query per
     * document type (soft-deleted documents included, marked as such).
     *
     * @return array<string, array{label: string, url: string|null}> keyed "type:id"
     */
    protected function loadReferences($movements): array
    {
        $refs = [];

        foreach ($movements->groupBy('reference_type') as $type => $rows) {
            $ids = $rows->pluck('reference_id')->unique()->all();

            if ($type === (new Invoice)->getMorphClass()) {
                foreach (Invoice::withTrashed()->whereIn('id', $ids)->get() as $invoice) {
                    $refs["$type:{$invoice->id}"] = [
                        'label' => "Invoice #{$invoice->invoice_number}" . ($invoice->trashed() ? ' (cancelled)' : ''),
                        'url' => $invoice->trashed() ? null : route('admin_invoices', ['invoice' => $invoice->id]),
                    ];
                }
            } elseif ($type === (new StockAdjustment)->getMorphClass()) {
                foreach (StockAdjustment::withTrashed()->whereIn('id', $ids)->get() as $adjustment) {
                    $refs["$type:{$adjustment->id}"] = [
                        'label' => $adjustment->adj_no . ($adjustment->trashed() ? ' (deleted)' : ''),
                        'url' => route('admin_inventory_adjustments', ['adjustment' => $adjustment->id]),
                    ];
                }
            }
        }

        return $refs;
    }
}
