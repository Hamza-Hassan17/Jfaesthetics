<div>
    @include('admins.inventory.partials.styles')
    @php $payBadge = ['unpaid' => 'jfr-badge-negative', 'partial' => 'jfr-badge-low', 'paid' => 'jfr-badge-ok']; @endphp

    <div class="content">
        <div class="container">
            <div class="row page-title">
                <div class="col"><h3 class="jfr-title">Inventory</h3></div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                    @if ($_page === 'index' && $canManage)
                        <button class="btn btn-jfr-teal" wire:click="show_direct_form">Direct Receive</button>
                    @endif
                </div>
            </div>

            @include('admins.inventory.partials.tabs')

            @if (session()->has('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
            @if (session()->has('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
            <div class="text-info" wire:loading>Loading..</div>

            {{-- ============ INDEX ============ --}}
            @if ($_page === 'index')
                <div class="jfr-filter-card">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-5 mb-0">
                            <label>Search</label>
                            <input type="text" wire:model.defer="search" wire:keydown.enter="$refresh" autocomplete="one-time-code" class="form-control" placeholder="GRN no, bill no or supplier...">
                        </div>
                        <div class="form-group col-md-auto mb-0">
                            <button type="button" wire:click="$refresh" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">To receive against a purchase order, open it under Purchase Orders and click Receive.</small>
                </div>

                <div class="jfr-table-card">
                    <div class="table-responsive">
                        <table class="table table-hover jfr-table mb-0">
                            <thead>
                                <tr><th>GRN no</th><th>P.O no</th><th>Supplier</th><th>Bill no</th><th>Date</th><th>Due</th><th class="text-right">Total</th><th>Payment</th><th></th></tr>
                            </thead>
                            <tbody>
                                @forelse ($receipts as $receipt)
                                    <tr>
                                        <td>{{ $receipt->grn_no }}</td>
                                        <td>{{ $receipt->purchaseOrder->po_no ?? 'Direct' }}</td>
                                        <td>{{ $receipt->supplier->name }}</td>
                                        <td>{{ $receipt->supplier_bill_no ?: '-' }}</td>
                                        <td>{{ $receipt->receive_date->format('d M Y') }}</td>
                                        <td>{{ $receipt->due_date->format('d M Y') }}</td>
                                        <td class="text-right">{{ number_format($receipt->total, 2) }}</td>
                                        <td><span class="jfr-badge {{ $payBadge[$receipt->payment_status] ?? '' }}">{{ ucfirst($receipt->payment_status) }}</span></td>
                                        <td><button wire:click="view({{ $receipt->id }})" class="jfr-icon-btn view" title="View"><i class="fas fa-eye"></i></button></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="text-muted">Nothing received yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end px-3">{{ $receipts->links() }}</div>
                </div>
            @endif

            {{-- ============ CREATE ============ --}}
            @if ($_page === 'create')
                <form wire:submit.prevent="save">
                    <div class="jfr-panel">
                        <h5>{{ $po ? 'Receive against ' . $po->po_no : 'Direct receive' }}</h5>
                        @error('lines') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Supplier *</label>
                                @if ($po)
                                    <input type="text" class="form-control" value="{{ $po->supplier->name }}" readonly style="background:#f4f6f6">
                                @else
                                    <select class="form-control" wire:model="supplier_id">
                                        <option value="">Choose supplier</option>
                                        @foreach ($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                @error('supplier_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-2">
                                <label>Supplier bill no</label>
                                <input type="text" class="form-control" wire:model.defer="supplier_bill_no">
                            </div>
                            <div class="form-group col-md-3">
                                <label>Receive date *</label>
                                <input type="date" class="form-control" wire:model="receive_date">
                            </div>
                            <div class="form-group col-md-3">
                                <label>Due date *</label>
                                <input type="date" class="form-control" wire:model="due_date">
                                <small class="text-muted">Receive date + supplier credit days.</small>
                                @error('due_date') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th style="min-width:220px">Item *</th>
                                        @if ($po)<th>Ordered</th><th>Already received</th><th>Remaining</th>@endif
                                        <th style="min-width:90px">Qty now *</th>
                                        <th style="min-width:120px">Unit cost *</th>
                                        <th style="min-width:130px">Batch no</th>
                                        <th style="min-width:170px">Expiry *</th>
                                        @unless ($po)<th></th>@endunless
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lines as $index => $line)
                                        <tr>
                                            <td>
                                                @if ($po)
                                                    {{ $line['item_name'] }}
                                                @else
                                                    <select class="form-control" wire:model="lines.{{ $index }}.item_id">
                                                        <option value="">Choose item</option>
                                                        @foreach ($inventoryItems as $inventoryItem)
                                                            <option value="{{ $inventoryItem->id }}">{{ $inventoryItem->name }} ({{ $inventoryItem->code }})</option>
                                                        @endforeach
                                                    </select>
                                                    @error("lines.$index.item_id") <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                                @endif
                                            </td>
                                            @if ($po)
                                                <td>{{ $line['ordered'] }}</td>
                                                <td>{{ $line['received'] }}</td>
                                                <td>{{ $line['outstanding'] }}</td>
                                            @endif
                                            <td>
                                                <input type="number" min="0" @if ($po) max="{{ $line['outstanding'] }}" @endif class="form-control" wire:model.defer="lines.{{ $index }}.qty">
                                                @error("lines.$index.qty") <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                            </td>
                                            <td><input type="number" step="0.01" min="0" class="form-control" wire:model.defer="lines.{{ $index }}.unit_cost"></td>
                                            <td><input type="text" class="form-control" wire:model.defer="lines.{{ $index }}.batch_no" placeholder="Auto if blank"></td>
                                            <td>
                                                <input type="date" class="form-control" wire:model.defer="lines.{{ $index }}.expiry_date" @if ($line['no_expiry']) disabled @endif>
                                                <label class="small mb-0"><input type="checkbox" wire:model="lines.{{ $index }}.no_expiry"> No expiry</label>
                                                @error("lines.$index.expiry_date") <span class="text-danger text-xs d-block">{{ $message }}</span> @enderror
                                            </td>
                                            @unless ($po)
                                                <td class="align-middle"><button type="button" class="btn btn-outline-danger btn-sm" wire:click="remove_line({{ $index }})"><i class="fas fa-trash"></i></button></td>
                                            @endunless
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @unless ($po)
                            <button type="button" class="btn btn-outline-primary btn-sm mb-3" wire:click="add_line">+ Add line</button>
                        @endunless
                        @if ($po)<small class="text-muted d-block mb-3">Enter 0 for lines that didn't arrive in this delivery.</small>@endif

                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Discount</label>
                                <input type="number" step="0.01" min="0" class="form-control" wire:model.defer="discount">
                            </div>
                            <div class="form-group col-md-3">
                                <label>Other charges</label>
                                <input type="number" step="0.01" min="0" class="form-control" wire:model.defer="other_charges">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Notes</label>
                                <input type="text" class="form-control" wire:model.defer="notes">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-jfr-teal">Save GRN &amp; Receive Stock</button>
                    <button type="button" class="btn btn-secondary" wire:click="show_index">Cancel</button>
                </form>
            @endif

            {{-- ============ EDIT ============ --}}
            @if ($_page === 'edit')
                <form wire:submit.prevent="update">
                    <div class="jfr-panel">
                        <h5>Edit {{ $receipt->grn_no }}</h5>
                        <p class="text-muted small mb-2">Bill no, due date, notes, batch no and expiry can always be changed. Qty and unit cost are locked on lines whose stock has already been used.</p>
                        @error('edit_lines') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Supplier bill no</label>
                                <input type="text" class="form-control" wire:model.defer="edit_bill_no">
                            </div>
                            <div class="form-group col-md-3">
                                <label>Due date *</label>
                                <input type="date" class="form-control" wire:model.defer="edit_due_date">
                                @error('edit_due_date') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Notes</label>
                                <input type="text" class="form-control" wire:model.defer="edit_notes">
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead><tr><th>Item</th><th style="min-width:90px">Qty</th><th style="min-width:120px">Unit cost</th><th style="min-width:130px">Batch no</th><th style="min-width:170px">Expiry</th></tr></thead>
                                <tbody>
                                    @foreach ($edit_lines as $lineId => $line)
                                        <tr>
                                            <td>
                                                {{ $line['item_name'] }}
                                                @if ($line['used'])<span class="jfr-badge jfr-badge-muted" title="Stock from this batch has been used">Stock used</span>@endif
                                            </td>
                                            <td>
                                                <input type="number" min="1" class="form-control" wire:model.defer="edit_lines.{{ $lineId }}.qty" @if ($line['used']) readonly style="background:#f4f6f6" @endif>
                                                @error("edit_lines.$lineId.qty") <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                            </td>
                                            <td><input type="number" step="0.01" min="0" class="form-control" wire:model.defer="edit_lines.{{ $lineId }}.unit_cost" @if ($line['used']) readonly style="background:#f4f6f6" @endif></td>
                                            <td><input type="text" class="form-control" wire:model.defer="edit_lines.{{ $lineId }}.batch_no" placeholder="Auto if blank"></td>
                                            <td>
                                                <input type="date" class="form-control" wire:model.defer="edit_lines.{{ $lineId }}.expiry_date" @if ($line['no_expiry']) disabled @endif>
                                                <label class="small mb-0"><input type="checkbox" wire:model="edit_lines.{{ $lineId }}.no_expiry"> No expiry</label>
                                                @error("edit_lines.$lineId.expiry_date") <span class="text-danger text-xs d-block">{{ $message }}</span> @enderror
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-jfr-teal">Save Changes</button>
                    <button type="button" class="btn btn-secondary" wire:click="view({{ $receipt->id }})">Cancel</button>
                </form>
            @endif

            {{-- ============ VIEW ============ --}}
            @if ($_page === 'view')
                <div class="mb-3">
                    <button class="btn btn-secondary" wire:click="show_index"><i class="fas fa-arrow-left"></i> Back</button>
                    <a href="{{ route('admin_inventory_grn_print', $receipt->id) }}" target="_blank" class="btn btn-outline-dark"><i class="fas fa-print"></i> Print GRN</a>
                    @if ($canManage && !$receipt->trashed())
                        <button class="btn btn-outline-primary" wire:click="edit({{ $receipt->id }})"><i class="fas fa-pen"></i> Edit</button>
                        @unless ($anyUsed)
                            <button class="btn btn-outline-danger" wire:click="delete({{ $receipt->id }})" onclick="return confirm('Delete {{ $receipt->grn_no }}? Its stock is reversed and any payments against it become advance.')"><i class="fas fa-trash"></i> Delete</button>
                        @endunless
                    @endif
                </div>

                <div class="jfr-panel">
                    <h5 class="mb-1">{{ $receipt->grn_no }}
                        <span class="jfr-badge {{ $payBadge[$receipt->payment_status] ?? '' }}">{{ ucfirst($receipt->payment_status) }}</span>
                        @if ($receipt->trashed())<span class="jfr-badge jfr-badge-negative">Deleted</span>@endif
                    </h5>
                    <div class="text-muted">
                        {{ $receipt->supplier->name }} · received {{ $receipt->receive_date->format('d M Y') }} · due {{ $receipt->due_date->format('d M Y') }}
                        · bill {{ $receipt->supplier_bill_no ?: '-' }}
                        · {{ $receipt->purchaseOrder ? 'against ' . $receipt->purchaseOrder->po_no : 'direct receive' }}
                        · by {{ $receipt->creator->name ?? '-' }}
                    </div>
                    @if ($receipt->notes)<div class="mt-2">{{ $receipt->notes }}</div>@endif
                    @if ($anyUsed && !$receipt->trashed())<small class="text-muted d-block mt-2">Some of this stock has been used, so the GRN can't be deleted. Use a supplier return or an adjustment.</small>@endif
                </div>

                <div class="jfr-table-card">
                    <div class="table-responsive">
                        <table class="table jfr-table mb-0">
                            <thead><tr><th>Item</th><th>Batch no</th><th>Expiry</th><th>Qty</th><th class="text-right">Unit cost</th><th class="text-right">Line total</th></tr></thead>
                            <tbody>
                                @foreach ($receipt->items as $line)
                                    <tr>
                                        <td>{{ $line->item->name }} <small class="text-muted">{{ $line->item->code }}</small></td>
                                        <td>{{ $line->batch_no }}</td>
                                        <td>{{ $line->expiry_date ? $line->expiry_date->format('d M Y') : 'No expiry' }}</td>
                                        <td>{{ $line->qty }}</td>
                                        <td class="text-right">{{ number_format($line->unit_cost, 2) }}</td>
                                        <td class="text-right">{{ number_format($line->line_total, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr><td colspan="5" class="text-right">Subtotal</td><td class="text-right">{{ number_format($receipt->subtotal, 2) }}</td></tr>
                                <tr><td colspan="5" class="text-right">Discount</td><td class="text-right">- {{ number_format($receipt->discount, 2) }}</td></tr>
                                <tr><td colspan="5" class="text-right">Other charges</td><td class="text-right">{{ number_format($receipt->other_charges, 2) }}</td></tr>
                                <tr><td colspan="5" class="text-right font-weight-bold">Total</td><td class="text-right font-weight-bold">{{ number_format($receipt->total, 2) }}</td></tr>
                                <tr><td colspan="5" class="text-right">Paid</td><td class="text-right">{{ number_format($receipt->amount_paid, 2) }}</td></tr>
                                <tr><td colspan="5" class="text-right">Outstanding</td><td class="text-right">{{ number_format($receipt->outstanding, 2) }}</td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                @if ($receipt->allocations->isNotEmpty())
                    <h5>Payments applied</h5>
                    <div class="jfr-table-card">
                        <table class="table jfr-table mb-0">
                            <thead><tr><th>Payment</th><th>Date</th><th class="text-right">Applied</th></tr></thead>
                            <tbody>
                                @foreach ($receipt->allocations as $allocation)
                                    <tr>
                                        <td><a href="{{ route('admin_inventory_supplier_payments', ['payment' => $allocation->supplier_payment_id]) }}">{{ $allocation->payment->payment_no ?? '-' }}</a></td>
                                        <td>{{ optional(optional($allocation->payment)->payment_date)->format('d M Y') }}</td>
                                        <td class="text-right">{{ number_format($allocation->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
