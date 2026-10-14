<div>
    @include('admins.inventory.partials.styles')

    <div class="content">
        <div class="container">
            <div class="row page-title">
                <div class="col">
                    <h3 class="jfr-title">Inventory</h3>
                </div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                </div>
            </div>

            @include('admins.inventory.partials.tabs')

            @if (session()->has('message'))
                <div class="alert alert-success">{{ session('message') }}</div>
            @endif

            @if ($canManage)
                <div class="jfr-filter-card">
                    <form wire:submit.prevent="save" class="form-row align-items-end">
                        <div class="form-group col-md-6 mb-0">
                            <label>{{ $editing_id ? 'Rename category' : 'New category' }}</label>
                            <input type="text" class="form-control" wire:model.defer="name" placeholder="e.g. Injections, Consumables, Retail">
                            @error('name') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group col-md-auto mb-0">
                            <button type="submit" class="btn btn-jfr-teal">{{ $editing_id ? 'Update' : 'Add Category' }}</button>
                            @if ($editing_id)
                                <button type="button" class="btn btn-outline-secondary" wire:click="cancel_edit">Cancel</button>
                            @endif
                        </div>
                    </form>
                </div>
            @endif

            <div class="jfr-table-card">
                <div class="table-responsive">
                    <table class="table table-hover jfr-table mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Items</th>
                                <th>Status</th>
                                @if ($canManage)<th>Actions</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($categories as $category)
                                <tr>
                                    <td>{{ $category->name }}</td>
                                    <td>{{ $category->items_count }}</td>
                                    <td>
                                        <span class="jfr-badge {{ $category->is_active ? 'jfr-badge-ok' : 'jfr-badge-muted' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span>
                                    </td>
                                    @if ($canManage)
                                        <td>
                                            <button wire:click="edit({{ $category->id }})" class="jfr-icon-btn edit" title="Rename"><i class="fas fa-pen"></i></button>
                                            <button wire:click="toggle_active({{ $category->id }})" class="btn btn-sm btn-outline-secondary">{{ $category->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">No categories yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
