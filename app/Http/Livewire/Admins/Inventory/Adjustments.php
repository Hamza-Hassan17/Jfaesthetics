<?php

namespace App\Http\Livewire\Admins\Inventory;

use App\Support\RetryingTransaction;
use App\Models\DocumentSequence;
use App\Models\InventoryItem;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Services\Inventory\StockService;
use App\Traits\LogsActivity;
use DomainException;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Phase 1 supports the Opening stock reason only (stock in, with batch
 * details per line). Damaged / expired / count correction / other arrive
 * in Phase 4 (docs/inventory-spec.md).
 */
class Adjustments extends Component
{
    use WithPagination;
    use LogsActivity;

    protected $paginationTheme = 'bootstrap';

    public $_page = 'index';
    public $view_id;

    public $adj_date;
    public $notes = '';
    public $lines = [];

    public function mount()
    {
        $adjustmentId = request()->query('adjustment');
        if ($adjustmentId && StockAdjustment::withTrashed()->find($adjustmentId)) {
            $this->view_id = (int) $adjustmentId;
            $this->_page = 'view';
        }
    }

    protected function blankLine(): array
    {
        return ['item_id' => '', 'qty' => 1, 'batch_no' => '', 'expiry_date' => '', 'unit_cost' => ''];
    }

    public function show_create_form()
    {
        abort_unless(auth()->user()->hasPermission('inventory', 'manage_adjustments'), 403);

        $this->adj_date = now()->format('Y-m-d');
        $this->notes = '';
        $this->lines = [$this->blankLine()];
        $this->resetErrorBag();
        $this->_page = 'create';
    }

    public function show_index()
    {
        $this->_page = 'index';
        $this->view_id = null;
    }

    public function view($id)
    {
        $this->view_id = StockAdjustment::withTrashed()->findOrFail($id)->id;
        $this->_page = 'view';
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

    public function updated($name, $value)
    {
        // Pre-fill unit cost from the item's last cost when an item is picked.
        if (preg_match('/^lines\.(\d+)\.item_id$/', $name) && $value !== '') {
            $index = (int) explode('.', $name)[1];
            $item = InventoryItem::find($value);
            if ($item && $this->lines[$index]['unit_cost'] === '') {
                $this->lines[$index]['unit_cost'] = $item->cost_price;
            }
        }
    }

    public function save()
    {
        abort_unless(auth()->user()->hasPermission('inventory', 'manage_adjustments'), 403);

        $this->validate([
            'adj_date' => 'required|date',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:inventory_items,id',
            'lines.*.qty' => 'required|integer|min:1',
            'lines.*.batch_no' => 'nullable|string|max:50',
            'lines.*.expiry_date' => 'nullable|date',
            'lines.*.unit_cost' => 'nullable|numeric|min:0',
        ], [], [
            'lines.*.item_id' => 'item',
            'lines.*.qty' => 'quantity',
            'lines.*.expiry_date' => 'expiry date',
            'lines.*.unit_cost' => 'unit cost',
        ]);

        $canViewCost = auth()->user()->hasPermission('inventory', 'view_cost');
        $stock = app(StockService::class);

        $adjustment = RetryingTransaction::run(function () use ($canViewCost, $stock) {
            $stock->lockItems(collect($this->lines)->pluck('item_id'));

            $adjustment = StockAdjustment::create([
                'adj_no' => DocumentSequence::next('ADJ'),
                'adj_date' => $this->adj_date,
                'reason' => 'opening_stock',
                'notes' => $this->notes ?: null,
                'created_by' => auth()->id(),
            ]);

            foreach ($this->lines as $line) {
                $item = InventoryItem::findOrFail($line['item_id']);
                $unitCost = $canViewCost && $line['unit_cost'] !== '' && $line['unit_cost'] !== null
                    ? (float) $line['unit_cost']
                    : (float) $item->cost_price;

                $adjustmentLine = StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'item_id' => $item->id,
                    'batch_no' => $line['batch_no'] ?: null,
                    'expiry_date' => $line['expiry_date'] ?: null,
                    'qty' => (int) $line['qty'],
                    'unit_cost' => $unitCost,
                    'created_by' => auth()->id(),
                ]);

                $batch = $stock->stockIn($item, (int) $line['qty'], $line['batch_no'] ?: null, $line['expiry_date'] ?: null, $unitCost, $adjustment, $adjustmentLine->id, 'adjustment_in');
                $adjustmentLine->update(['batch_id' => $batch->id]);

                if ($unitCost > 0) {
                    $item->update(['cost_price' => $unitCost]);
                }
            }

            return $adjustment;
        });

        $this->logActivity('created', 'inventory', $adjustment->id, "Recorded opening stock adjustment {$adjustment->adj_no}.");
        session()->flash('message', "Adjustment {$adjustment->adj_no} saved.");
        $this->view_id = $adjustment->id;
        $this->_page = 'view';
    }

    public function delete($id)
    {
        abort_unless(auth()->user()->hasPermission('inventory', 'manage_adjustments'), 403);

        $adjustment = StockAdjustment::findOrFail($id);

        try {
            RetryingTransaction::run(function () use ($adjustment) {
                app(StockService::class)->reverse($adjustment);
                $adjustment->delete();
            });
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage() . ' Use a stock adjustment to correct it instead.');
            return;
        }

        $this->logActivity('deleted', 'inventory', $adjustment->id, "Deleted stock adjustment {$adjustment->adj_no} (stock reversed).");
        session()->flash('message', "Adjustment {$adjustment->adj_no} deleted and its stock reversed.");
        $this->show_index();
    }

    public function render()
    {
        $data = [
            'canManage' => auth()->user()->hasPermission('inventory', 'manage_adjustments'),
            'canViewCost' => auth()->user()->hasPermission('inventory', 'view_cost'),
        ];

        if ($this->_page === 'view' && $this->view_id) {
            $data['adjustment'] = StockAdjustment::withTrashed()->with(['items.item', 'items.batch', 'creator'])->findOrFail($this->view_id);
        } elseif ($this->_page === 'create') {
            $data['inventoryItems'] = InventoryItem::where('is_active', true)->orderBy('name')->get();
        } else {
            $data['adjustments'] = StockAdjustment::withCount('items')->with('creator')->latest('adj_date')->latest('id')->paginate(15);
        }

        return view('livewire.admins.inventory.adjustments', $data)->layout('admins.layouts.app');
    }
}
