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
                    @if ($_page !== 'create' && $canManage)
                        <button class="btn btn-jfr-teal" wire:click="show_create_form">Opening Stock</button>
                    @endif
                </div>
            </div>

            @include('admins.inventory.partials.tabs')

            @if (session()->has('message'))
                <div class="alert alert-success">{{ session('message') }}</div>
            @endif
            @if (session()->has('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="text-info" wire:loading>Loading..</div>

            {{-- ============ INDEX ============ --}}
            @if ($_page === 'index')
                <div class="jfr-table-card">
                    <div class="table-responsive">
                        <table class="table table-hover jfr-table mb-0">
                            <thead>
                                <tr><th>Adj no</th><th>Date</th><th>Reason</th><th>Lines</th><th>By</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($adjustments as $adjustment)
                                    <tr>
                                        <td>{{ $adjustment->adj_no }}</td>
                                        <td>{{ $adjustment->adj_date->format('d M Y') }}</td>
                                        <td>{{ \App\Models\StockAdjustment::REASONS[$adjustment->reason] ?? $adjustment->reason }}</td>
                                        <td>{{ $adjustment->items_count }}</td>
                                        <td>{{ $adjustment->creator->name ?? '-' }}</td>
                                        <td>
                                            <button wire:click="view({{ $adjustment->id }})" class="jfr-icon-btn view" title="View"><i class="fas fa-eye"></i></button>
                                            @if ($canManage)
                                                <button wire:click="delete({{ $adjustment->id }})" onclick="return confirm('Delete {{ $adjustment->adj_no }} and reverse its stock?')" class="jfr-icon-btn delete" title="Delete"><i class="fas fa-trash"></i></button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-muted">No adjustments yet. Use <strong>Opening Stock</strong> to load current shelf stock.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end px-3">{{ $adjustments->links() }}</div>
                </div>
            @endif

            {{-- ============ CREATE (opening stock) ============ --}}
            @if ($_page === 'create')
                <form wire:submit.prevent="save">
                    <div class="jfr-panel">
                        <h5>Opening stock</h5>
                        <p class="text-muted mb-3">Load the stock currently on the shelf. Each line becomes a batch with its own expiry date.</p>
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Date *</label>
                                <input type="date" class="form-control" wire:model.defer="adj_date">
                                @error('adj_date') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-9">
                                <label>Notes</label>
                                <input type="text" class="form-control" wire:model.defer="notes">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th style="min-width: 240px;">Item *</th>
                                        <th style="min-width: 90px;">Qty *</th>
                                        <th style="min-width: 130px;">Batch no</th>
                                        <th style="min-width: 150px;">Expiry date</th>
                                        @if ($canViewCost)<th style="min-width: 120px;">Unit cost</th>@endif
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lines as $index => $line)
                                        <tr>
                                            <td>
                                                <select class="form-control" wire:model="lines.{{ $index }}.item_id">
                                                    <option value="">Choose item</option>
                                                    @foreach ($inventoryItems as $inventoryItem)
                                                        <option value="{{ $inventoryItem->id }}">{{ $inventoryItem->name }} ({{ $inventoryItem->code }})</option>
                                                    @endforeach
                                                </select>
                                                @error("lines.$index.item_id") <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                            </td>
                                            <td>
                                                <input type="number" min="1" class="form-control" wire:model.defer="lines.{{ $index }}.qty">
                                                @error("lines.$index.qty") <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                            </td>
                                            <td><input type="text" class="form-control" wire:model.defer="lines.{{ $index }}.batch_no"></td>
                                            <td>
                                                <input type="date" class="form-control" wire:model.defer="lines.{{ $index }}.expiry_date">
                                                @error("lines.$index.expiry_date") <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                            </td>
                                            @if ($canViewCost)
                                                <td>
                                                    <input type="number" step="0.01" min="0" class="form-control" wire:model.defer="lines.{{ $index }}.unit_cost">
                                                    @error("lines.$index.unit_cost") <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                                </td>
                                            @endif
                                            <td class="align-middle">
                                                <button type="button" class="btn btn-outline-danger btn-sm" wire:click="remove_line({{ $index }})"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="add_line">+ Add line</button>
                    </div>

                    <button type="submit" class="btn btn-jfr-teal">Save Opening Stock</button>
                    <button type="button" class="btn btn-secondary" wire:click="show_index">Cancel</button>
                </form>
            @endif

            {{-- ============ VIEW ============ --}}
            @if ($_page === 'view')
                <div class="mb-3">
                    <button class="btn btn-secondary" wire:click="show_index"><i class="fas fa-arrow-left"></i> Back to Adjustments</button>
                </div>
                <div class="jfr-panel">
                    <h5 class="mb-1">{{ $adjustment->adj_no }}
                        @if ($adjustment->trashed())<span class="jfr-badge jfr-badge-negative">Deleted</span>@endif
                    </h5>
                    <div class="text-muted">
                        {{ \App\Models\StockAdjustment::REASONS[$adjustment->reason] ?? $adjustment->reason }}
                        · {{ $adjustment->adj_date->format('d M Y') }}
                        · by {{ $adjustment->creator->name ?? '-' }}
                    </div>
                    @if ($adjustment->notes)<div class="mt-2">{{ $adjustment->notes }}</div>@endif
                </div>
                <div class="jfr-table-card">
                    <div class="table-responsive">
                        <table class="table jfr-table mb-0">
                            <thead>
                                <tr><th>Item</th><th>Qty</th><th>Batch no</th><th>Expiry</th>@if ($canViewCost)<th>Unit cost</th>@endif</tr>
                            </thead>
                            <tbody>
                                @foreach ($adjustment->items as $line)
                                    <tr>
                                        <td>{{ $line->item->name ?? '-' }} <small class="text-muted">{{ $line->item->code ?? '' }}</small></td>
                                        <td>{{ $line->qty }}</td>
                                        <td>{{ $line->batch_no ?: '-' }}</td>
                                        <td>{{ $line->expiry_date ? $line->expiry_date->format('d M Y') : 'No expiry' }}</td>
                                        @if ($canViewCost)<td>{{ number_format($line->unit_cost, 2) }}</td>@endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
