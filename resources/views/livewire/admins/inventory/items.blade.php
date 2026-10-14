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
                    @if ($_page === 'index' && $canManage)
                        <button class="btn btn-jfr-teal" wire:click="show_create" data-toggle="modal" data-target="#inventoryItemModal">Add Item</button>
                    @endif
                </div>
            </div>

            @include('admins.inventory.partials.tabs')

            @if (session()->has('message'))
                <div class="alert alert-success">{{ session('message') }}</div>
            @endif

            <div class="text-info" wire:loading>Loading..</div>

            {{-- ============ INDEX ============ --}}
            @if ($_page === 'index')
                <div class="jfr-filter-card">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4 mb-0">
                            <label>Search</label>
                            <input type="text" wire:model.defer="search" wire:keydown.enter="$refresh" autocomplete="one-time-code" class="form-control" placeholder="Name or code...">
                        </div>
                        <div class="form-group col-md-3 mb-0">
                            <label>Category</label>
                            <select wire:model.defer="filter_category" class="form-control">
                                <option value="">All categories</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2 mb-0">
                            <label>Status</label>
                            <select wire:model.defer="filter_status" class="form-control">
                                <option value="">All</option>
                                @foreach (\App\Http\Livewire\Admins\Inventory\Items::STATUS_FILTERS as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-auto mb-0">
                            <button type="button" wire:click="$refresh" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                            <button type="button" wire:click="resetFilters" class="btn btn-outline-secondary"><i class="fas fa-redo"></i> Reset</button>
                        </div>
                    </div>
                </div>

                <div class="jfr-table-card">
                    <div class="table-responsive">
                        <table class="table table-hover jfr-table mb-0">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>On hand</th>
                                    @if ($canViewCost)<th>Cost</th>@endif
                                    <th>Sale price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $item)
                                    <tr>
                                        <td>{{ $item->code }}</td>
                                        <td>{{ $item->name }}</td>
                                        <td>{{ $item->category->name ?? '-' }}</td>
                                        <td>
                                            <span class="{{ $item->is_negative ? 'jfr-qty-negative' : '' }}">{{ $item->qty_on_hand }}</span>
                                            @if ((int) $item->usable_qty !== $item->qty_on_hand)
                                                <small class="text-muted">({{ (int) $item->usable_qty }} usable)</small>
                                            @endif
                                        </td>
                                        @if ($canViewCost)<td>{{ number_format($item->cost_price, 2) }}</td>@endif
                                        <td>{{ number_format($item->sale_price, 2) }}</td>
                                        <td>
                                            @if (!$item->is_active)
                                                <span class="jfr-badge jfr-badge-muted">Inactive</span>
                                            @endif
                                            @if ($item->is_negative)
                                                <span class="jfr-badge jfr-badge-negative">Negative</span>
                                            @elseif ((int) $item->usable_qty <= $item->low_stock_threshold)
                                                <span class="jfr-badge jfr-badge-low">Low</span>
                                            @endif
                                            @if (isset($expiredIds[$item->id]))
                                                <span class="jfr-badge jfr-badge-negative">Expired</span>
                                            @endif
                                            @if (isset($expiringIds[$item->id]))
                                                <span class="jfr-badge jfr-badge-expiring">Expiring</span>
                                            @endif
                                        </td>
                                        <td>
                                            <button wire:click="view({{ $item->id }})" class="jfr-icon-btn view" title="Batches and ledger"><i class="fas fa-eye"></i></button>
                                            @if ($canManage)
                                                <button wire:click="edit({{ $item->id }})" class="jfr-icon-btn edit" title="Edit"><i class="fas fa-pen"></i></button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-muted">No items found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center flex-wrap px-3">
                        <div class="jfr-entries-info">
                            @if ($items->total() > 0)
                                Showing {{ $items->firstItem() }} to {{ $items->lastItem() }} of {{ $items->total() }} items
                            @endif
                        </div>
                        <div>{{ $items->links() }}</div>
                    </div>
                </div>
            @endif

            {{-- ============ ITEM DETAIL ============ --}}
            @if ($_page === 'view')
                <div class="mb-3">
                    <button class="btn btn-secondary" wire:click="show_index"><i class="fas fa-arrow-left"></i> Back to Items</button>
                    @if ($canManage)
                        <button class="btn btn-outline-primary" wire:click="edit({{ $item->id }})"><i class="fas fa-pen"></i> Edit</button>
                    @endif
                </div>

                <div class="jfr-panel">
                    <h5 class="mb-1">{{ $item->name }} <small class="text-muted">{{ $item->code }}</small></h5>
                    <div class="text-muted mb-2">{{ $item->category->name ?? 'No category' }} · unit: {{ $item->unit }}</div>
                    <div>
                        <strong>On hand:</strong> <span class="{{ $item->is_negative ? 'jfr-qty-negative' : '' }}">{{ $item->qty_on_hand }}</span>
                        &nbsp;·&nbsp; <strong>Usable:</strong> {{ (int) $item->usable_qty }}
                        &nbsp;·&nbsp; <strong>Low-stock threshold:</strong> {{ $item->low_stock_threshold }}
                        &nbsp;·&nbsp; <strong>Sale price:</strong> PKR {{ number_format($item->sale_price, 2) }}
                        @if ($canViewCost)
                            &nbsp;·&nbsp; <strong>Cost:</strong> PKR {{ number_format($item->cost_price, 2) }}
                        @endif
                        &nbsp;·&nbsp; <strong>Billable by default:</strong> {{ $item->default_billable ? 'Yes' : 'No' }}
                    </div>
                </div>

                <h5>Batches</h5>
                <div class="jfr-table-card">
                    <div class="table-responsive">
                        <table class="table jfr-table mb-0">
                            <thead>
                                <tr>
                                    <th>Batch no</th>
                                    <th>Expiry</th>
                                    <th>Received</th>
                                    <th>Remaining</th>
                                    @if ($canViewCost)<th>Unit cost</th>@endif
                                    <th>Source</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($batches as $batch)
                                    <tr>
                                        <td>{{ $batch->batch_no ?: '-' }}</td>
                                        <td>
                                            {{ $batch->expiry_date ? $batch->expiry_date->format('d M Y') : 'No expiry' }}
                                            @if ($batch->is_expired)
                                                <span class="jfr-badge jfr-badge-negative">Expired</span>
                                            @elseif ($batch->is_expiring_soon)
                                                <span class="jfr-badge jfr-badge-expiring">Expiring soon</span>
                                            @endif
                                        </td>
                                        <td>{{ $batch->qty_received }}</td>
                                        <td>{{ $batch->qty_remaining }}</td>
                                        @if ($canViewCost)<td>{{ number_format($batch->unit_cost, 2) }}</td>@endif
                                        <td>{{ ucwords(str_replace('_', ' ', $batch->source)) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-muted">No batches yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <h5>Movement ledger</h5>
                <div class="jfr-table-card">
                    <div class="table-responsive">
                        <table class="table jfr-table mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Document</th>
                                    <th>Batch</th>
                                    <th>Qty</th>
                                    <th>Balance</th>
                                    @if ($canViewCost)<th>Unit cost</th>@endif
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($movements as $movement)
                                    @php $ref = $references[$movement->reference_type . ':' . $movement->reference_id] ?? null; @endphp
                                    <tr>
                                        <td>{{ $movement->moved_at->format('d M Y H:i') }}</td>
                                        <td>{{ ucwords(str_replace('_', ' ', $movement->type)) }}</td>
                                        <td>
                                            @if ($ref && $ref['url'])
                                                <a href="{{ $ref['url'] }}">{{ $ref['label'] }}</a>
                                            @else
                                                {{ $ref['label'] ?? '-' }}
                                            @endif
                                        </td>
                                        <td>{{ $movement->batch ? ($movement->batch->batch_no ?: '#' . $movement->batch->id) : 'none' }}</td>
                                        <td class="{{ $movement->qty < 0 ? 'text-danger' : 'text-success' }}">{{ $movement->qty > 0 ? '+' : '' }}{{ $movement->qty }}</td>
                                        <td class="{{ $movement->running_balance < 0 ? 'jfr-qty-negative' : '' }}">{{ $movement->running_balance }}</td>
                                        @if ($canViewCost)<td>{{ number_format($movement->unit_cost, 2) }}</td>@endif
                                        <td>{{ $movement->creator->name ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-muted">No movements yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="modal fade" id="inventoryItemModal" tabindex="-1" role="dialog" aria-hidden="true" wire:ignore.self>
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <form wire:submit.prevent="save">
                            <div class="modal-header">
                                <h5 class="modal-title">{{ $editing_id ? 'Edit Item' : 'Add Item' }}</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Code *</label>
                                        <input type="text" class="form-control" wire:model.defer="code">
                                        @error('code') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="form-group col-md-8">
                                        <label>Name *</label>
                                        <input type="text" class="form-control" wire:model.defer="name">
                                        @error('name') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Category</label>
                                    <select class="form-control" wire:model.defer="category_id">
                                        <option value="">No category</option>
                                        @foreach (\App\Models\InventoryCategory::where('is_active', true)->orWhere('id', $category_id ?: 0)->orderBy('name')->get() as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('category_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-row">
                                    @if ($canViewCost)
                                        <div class="form-group col-md-6">
                                            <label>Cost price</label>
                                            <input type="number" step="0.01" min="0" class="form-control" wire:model.defer="cost_price">
                                            <small class="text-muted">Updated automatically from received stock.</small>
                                            @error('cost_price') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                        </div>
                                    @endif
                                    <div class="form-group col-md-6">
                                        <label>Sale price *</label>
                                        <input type="number" step="0.01" min="0" class="form-control" wire:model.defer="sale_price">
                                        @error('sale_price') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Low-stock threshold *</label>
                                        <input type="number" min="0" class="form-control" wire:model.defer="low_stock_threshold">
                                        @error('low_stock_threshold') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Unit</label>
                                        <input type="text" class="form-control" value="piece" readonly style="background: #f4f6f6;">
                                    </div>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="itemBillable" wire:model.defer="default_billable">
                                    <label class="form-check-label" for="itemBillable">Billable by default (charged when used in a service)</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="itemActive" wire:model.defer="is_active">
                                    <label class="form-check-label" for="itemActive">Active</label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-jfr-teal">{{ $editing_id ? 'Update Item' : 'Save Item' }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <script>
                if (!window.__jfInventoryItemModalBound) {
                    window.__jfInventoryItemModalBound = true;
                    window.addEventListener('inventory-item-modal-open', function () { $('#inventoryItemModal').modal('show'); });
                    window.addEventListener('inventory-item-modal-close', function () { $('#inventoryItemModal').modal('hide'); });
                }
            </script>
        </div>
    </div>
</div>
