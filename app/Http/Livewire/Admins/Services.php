<?php

namespace App\Http\Livewire\Admins;

use App\Models\InventoryItem;
use App\Models\Service;
use App\Traits\LogsActivity;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Services extends Component
{
    use WithPagination;
    use LogsActivity;

    protected $paginationTheme = 'bootstrap';

    public $name;
    public $category;
    public $price;
    public $filter_category = '';

    protected $queryString = ['filter_category' => ['except' => '']];

    public function updatingFilterCategory()
    {
        $this->resetPage();
    }

    public $edit_service_id;
    public $button_text = "Add New Service";

    public function add_service()
    {
        if ($this->edit_service_id) {
            $this->update($this->edit_service_id);
        } else {
            abort_unless(auth()->user()->hasPermission('services', 'create'), 403);

            $this->validate([
                'name' => 'required',
                'category' => 'nullable|string|max:100',
                'price' => 'required|numeric|min:0',
            ]);

            Service::create([
                'name' => $this->name,
                'category' => trim((string) $this->category) ?: null,
                'price' => $this->price,
            ]);

            $this->name = "";
            $this->price = "";

            session()->flash('message', 'Service Created successfully.');
        }
    }

    public function edit($id)
    {
        abort_unless(auth()->user()->hasPermission('services', 'update'), 403);

        $service = Service::findOrFail($id);
        $this->edit_service_id = $id;
        $this->name = $service->name;
        $this->category = $service->category;
        $this->price = $service->price;
        $this->button_text = "Update Service";
    }

    public function update($id)
    {
        abort_unless(auth()->user()->hasPermission('services', 'update'), 403);

        $this->validate([
            'name' => 'required',
            'category' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
        ]);

        $service = Service::findOrFail($id);
        $service->name = $this->name;
        $service->category = trim((string) $this->category) ?: null;
        $service->price = $this->price;
        $service->save();

        $this->name = "";
        $this->category = "";
        $this->price = "";
        $this->edit_service_id = "";

        session()->flash('message', 'Service Updated Successfully.');
        $this->button_text = "Add New Service";
    }

    public function delete($id)
    {
        abort_unless(auth()->user()->hasPermission('services', 'delete'), 403);

        Service::findOrFail($id)->delete();
        session()->flash('message', 'Service Deleted Successfully.');
    }

    // ---- Recipe (consumables per session) ----

    public $recipe_service_id;
    public $recipe_lines = [];

    public function show_recipe($id)
    {
        abort_unless(auth()->user()->hasPermission('inventory', 'view'), 403);

        $service = Service::with('recipe')->findOrFail($id);
        $this->recipe_service_id = $service->id;
        $this->recipe_lines = $service->recipe->map(fn ($line) => [
            'item_id' => $line->item_id,
            'qty_per_session' => $line->qty_per_session,
            'is_billable' => $line->is_billable,
        ])->all();
        $this->resetErrorBag();
    }

    public function close_recipe()
    {
        $this->recipe_service_id = null;
        $this->recipe_lines = [];
    }

    public function add_recipe_line()
    {
        $this->recipe_lines[] = ['item_id' => '', 'qty_per_session' => 1, 'is_billable' => true];
    }

    public function remove_recipe_line($index)
    {
        unset($this->recipe_lines[$index]);
        $this->recipe_lines = array_values($this->recipe_lines);
    }

    public function updated($name, $value)
    {
        // Billable defaults from the item's own setting when it is picked.
        if (preg_match('/^recipe_lines\.(\d+)\.item_id$/', $name) && $value !== '') {
            $item = InventoryItem::find($value);
            if ($item) {
                $this->recipe_lines[(int) explode('.', $name)[1]]['is_billable'] = (bool) $item->default_billable;
            }
        }
    }

    /** Changing a recipe affects new invoices only: saved invoices keep their own lines. */
    public function save_recipe()
    {
        abort_unless(auth()->user()->hasPermission('inventory', 'manage_recipes'), 403);

        $this->validate([
            'recipe_lines.*.item_id' => 'required|distinct|exists:inventory_items,id',
            'recipe_lines.*.qty_per_session' => 'required|integer|min:1',
            'recipe_lines.*.is_billable' => 'boolean',
        ], ['recipe_lines.*.item_id.distinct' => 'Each item can appear once in a recipe.'], [
            'recipe_lines.*.item_id' => 'item',
            'recipe_lines.*.qty_per_session' => 'qty per session',
        ]);

        $service = Service::findOrFail($this->recipe_service_id);
        $before = $service->recipe()->with('item')->get()->map(fn ($l) => "{$l->item->name} ×{$l->qty_per_session}" . ($l->is_billable ? '' : ' (not billed)'))->implode(', ');

        DB::transaction(function () use ($service) {
            $service->recipe()->delete();
            foreach ($this->recipe_lines as $line) {
                $service->recipe()->create([
                    'item_id' => $line['item_id'],
                    'qty_per_session' => (int) $line['qty_per_session'],
                    'is_billable' => (bool) $line['is_billable'],
                    'created_by' => auth()->id(),
                ]);
            }
        });

        $after = $service->recipe()->with('item')->get()->map(fn ($l) => "{$l->item->name} ×{$l->qty_per_session}" . ($l->is_billable ? '' : ' (not billed)'))->implode(', ');
        $this->logActivity('updated', 'services', $service->id, "Recipe for '{$service->name}': " . ($before ?: 'none') . ' → ' . ($after ?: 'none'));

        session()->flash('message', "Recipe for {$service->name} saved. It applies to new invoices only.");
        $this->close_recipe();
    }

    public function render()
    {
        return view('livewire.admins.services', [
            'services' => Service::withCount('recipe')
                ->when($this->filter_category !== '', fn ($q) => $q->where('category', $this->filter_category))
                ->orderByRaw('category IS NULL')->orderBy('category')->orderBy('id')
                ->paginate(15),
            'categories' => Service::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'recipeService' => $this->recipe_service_id ? Service::find($this->recipe_service_id) : null,
            'inventoryItems' => $this->recipe_service_id ? InventoryItem::where('is_active', true)->orderBy('name')->get() : collect(),
            'canEditRecipe' => auth()->user()->hasPermission('inventory', 'manage_recipes'),
            'canSeeRecipe' => auth()->user()->hasPermission('inventory', 'view'),
        ])->layout('admins.layouts.app');
    }
}
