<?php

namespace App\Http\Livewire\Admins\Inventory;

use App\Models\InventoryCategory;
use App\Traits\LogsActivity;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Categories extends Component
{
    use LogsActivity;

    public $editing_id;
    public $name = '';

    public function edit($id)
    {
        abort_unless(auth()->user()->hasPermission('inventory', 'manage_items'), 403);

        $category = InventoryCategory::findOrFail($id);
        $this->editing_id = $category->id;
        $this->name = $category->name;
        $this->resetErrorBag();
    }

    public function cancel_edit()
    {
        $this->editing_id = null;
        $this->name = '';
        $this->resetErrorBag();
    }

    public function save()
    {
        abort_unless(auth()->user()->hasPermission('inventory', 'manage_items'), 403);

        $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('inventory_categories', 'name')->ignore($this->editing_id)->whereNull('deleted_at')],
        ]);

        if ($this->editing_id) {
            $category = InventoryCategory::findOrFail($this->editing_id);
            $category->update(['name' => trim($this->name)]);
            $this->logActivity('updated', 'inventory', $category->id, "Renamed inventory category to '{$category->name}'.");
            session()->flash('message', 'Category updated.');
        } else {
            $category = InventoryCategory::create(['name' => trim($this->name), 'is_active' => true, 'created_by' => auth()->id()]);
            $this->logActivity('created', 'inventory', $category->id, "Created inventory category '{$category->name}'.");
            session()->flash('message', 'Category added.');
        }

        $this->cancel_edit();
    }

    public function toggle_active($id)
    {
        abort_unless(auth()->user()->hasPermission('inventory', 'manage_items'), 403);

        $category = InventoryCategory::findOrFail($id);
        $category->update(['is_active' => !$category->is_active]);
        $this->logActivity($category->is_active ? 'activated' : 'deactivated', 'inventory', $category->id, ($category->is_active ? 'Activated' : 'Deactivated') . " inventory category '{$category->name}'.");
    }

    public function render()
    {
        return view('livewire.admins.inventory.categories', [
            'categories' => InventoryCategory::withCount('items')->orderBy('name')->get(),
            'canManage' => auth()->user()->hasPermission('inventory', 'manage_items'),
        ])->layout('admins.layouts.app');
    }
}
