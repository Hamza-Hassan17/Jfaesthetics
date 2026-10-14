<div>
    @include('admins.inventory.partials.styles')
    @php
        $statusBadge = [
            'draft' => 'jfr-badge-muted', 'ordered' => 'jfr-badge-expiring', 'partially_received' => 'jfr-badge-low',
            'received' => 'jfr-badge-ok', 'closed' => 'jfr-badge-muted', 'cancelled' => 'jfr-badge-negative',
        ];
    @endphp

    <div class="content">
        <div class="container">
            <div class="row page-title">
                <div class="col"><h3 class="jfr-title">Inventory</h3></div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                    @if ($_page !== 'form' && $canManage)
                        <button class="btn btn-jfr-teal" wire:click="show_create_form">New Purchase Order</button>
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
                        <div class="form-group col-md-4 mb-0">
                            <label>Search</label>
                            <input type="text" wire:model.defer="search" wire:keydown.enter="$refresh" autocomplete="one-time-code" class="form-control" placeholder="P.O no or supplier...">
                        </div>
                        <div class="form-group col-md-3 mb-0">
                            <label>Status</label>
                            <select wire:model.defer="filter_status" class="form-control">
                                <option value="">All</option>
                                @foreach (\App\Models\PurchaseOrder::STATUSES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-auto mb-0">
                            <button type="button" wire:click="$refresh" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        </div>
                    </div>
                </div>

                <div class="jfr-table-card">
                    <div class="table-responsive">
                        <table class="table table-hover jfr-table mb-0">
                            <thead>
                                <tr><th>P.O no</th><th>Supplier</th><th>Date</th><th>Ordered / Received</th><th class="text-right">Total</th><th>Status</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($orders as $order)
                                    <tr>
                                        <td>{{ $order->po_no }}</td>
                                        <td>{{ $order->supplier->name }}</td>
                                        <td>{{ $order->order_date->format('d M Y') }}</td>
                                        <td>{{ (int) $order->qty_ordered_sum }} / {{ (int) $order->qty_received_sum }}
                                            @if ($order->status === 'closed')<small class="text-muted">({{ (int) $order->qty_ordered_sum - (int) $order->qty_received_sum }} closed short)</small>@endif
                                        </td>
                                        <td class="text-right">{{ number_format($order->total, 2) }}</td>
                                        <td><span class="jfr-badge {{ $statusBadge[$order->status] ?? '' }}">{{ \App\Models\PurchaseOrder::STATUSES[$order->status] ?? $order->status }}</span></td>
                                        <td>
                                            <button wire:click="view({{ $order->id }})" class="jfr-icon-btn view" title="View"><i class="fas fa-eye"></i></button>
                                            @if ($canManage && $order->isEditable())
                                                <button wire:click="edit({{ $order->id }})" class="jfr-icon-btn edit" title="Edit"><i class="fas fa-pen"></i></button>
                                            @endif
                                            @if ($canReceive && $order->isReceivable())
                                                <a href="{{ route('admin_inventory_receiving', ['po' => $order->id]) }}" class="btn btn-sm btn-outline-success">Receive</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-muted">No purchase orders yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end px-3">{{ $orders->links() }}</div>
                </div>
            @endif

            {{-- ============ FORM ============ --}}
            @if ($_page === 'form')
                <form wire:submit.prevent="save">
                    <div class="jfr-panel">
                        <h5>{{ $editing_id ? 'Edit purchase order' : 'New purchase order' }}</h5>
                        @if ($editing_status !== 'draft')
                            <div class="alert alert-info py-2">This P.O has been ordered: you can add lines or increase quantities only.</div>
                        @endif
                        @error('lines') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Supplier *</label>
                                <select class="form-control" wire:model.defer="supplier_id" @if ($editing_status !== 'draft') disabled @endif>
                                    <option value="">Choose supplier</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                                @error('supplier_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-3">
                                <label>Order date *</label>
                                <input type="date" class="form-control" wire:model.defer="order_date">
                            </div>
                            <div class="form-group col-md-3">
                                <label>Expected date</label>
                                <input type="date" class="form-control" wire:model.defer="expected_date">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead><tr><th style="min-width:240px">Item *</th><th style="min-width:100px">Qty *</th><th style="min-width:130px">Unit cost *</th><th class="text-right">Line total</th><th></th></tr></thead>
                                <tbody>
                                    @foreach ($lines as $index => $line)
                                        @php $lockedLine = $line['id'] && $editing_status !== 'draft'; @endphp
                                        <tr>
                                            <td>
                                                <select class="form-control" wire:model="lines.{{ $index }}.item_id" @if ($lockedLine) disabled @endif>
                                                    <option value="">Choose item</option>
                                                    @foreach ($inventoryItems as $inventoryItem)
                                                        <option value="{{ $inventoryItem->id }}">{{ $inventoryItem->name }} ({{ $inventoryItem->code }})</option>
                                                    @endforeach
                                                </select>
                                                @error("lines.$index.item_id") <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                            </td>
                                            <td>
                                                <input type="number" min="{{ $line['min_qty'] }}" class="form-control" wire:model.lazy="lines.{{ $index }}.qty_ordered">
                                                @error("lines.$index.qty_ordered") <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0" class="form-control" wire:model.lazy="lines.{{ $index }}.unit_cost" @if ($lockedLine) readonly style="background:#f4f6f6" @endif>
                                                @error("lines.$index.unit_cost") <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                            </td>
                                            <td class="text-right align-middle">{{ number_format((float) $line['qty_ordered'] * (float) $line['unit_cost'], 2) }}</td>
                                            <td class="align-middle">
                                                @unless ($lockedLine)
                                                    <button type="button" class="btn btn-outline-danger btn-sm" wire:click="remove_line({{ $index }})"><i class="fas fa-trash"></i></button>
                                                @endunless
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mb-3" wire:click="add_line">+ Add line</button>

                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Discount</label>
                                <input type="number" step="0.01" min="0" class="form-control" wire:model.lazy="discount" @if ($editing_status !== 'draft') readonly style="background:#f4f6f6" @endif>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Other charges</label>
                                <input type="number" step="0.01" min="0" class="form-control" wire:model.lazy="other_charges" @if ($editing_status !== 'draft') readonly style="background:#f4f6f6" @endif>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Notes</label>
                                <input type="text" class="form-control" wire:model.defer="notes">
                            </div>
                        </div>
                        <div class="text-right">
                            <div>Subtotal: <strong>{{ number_format($this->subtotal, 2) }}</strong></div>
                            <h5>Total: PKR {{ number_format($this->subtotal - (float) $discount + (float) $other_charges, 2) }}</h5>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-jfr-teal">Save Purchase Order</button>
                    <button type="button" class="btn btn-secondary" wire:click="show_index">Cancel</button>
                </form>
            @endif

            {{-- ============ VIEW ============ --}}
            @if ($_page === 'view')
                <div class="mb-3">
                    <button class="btn btn-secondary" wire:click="show_index"><i class="fas fa-arrow-left"></i> Back</button>
                    <a href="{{ route('admin_inventory_po_print', $po->id) }}" target="_blank" class="btn btn-outline-dark"><i class="fas fa-print"></i> Print P.O</a>
                    @if ($canManage)
                        @if ($po->isEditable())
                            <button class="btn btn-outline-primary" wire:click="edit({{ $po->id }})"><i class="fas fa-pen"></i> Edit</button>
                        @endif
                        @if ($po->status === 'draft')
                            <button class="btn btn-jfr-teal" wire:click="mark_ordered({{ $po->id }})">Mark as Ordered</button>
                        @endif
                        @if (in_array($po->status, ['draft', 'ordered']) && $po->items->sum('qty_received') === 0)
                            <button class="btn btn-outline-danger" wire:click="cancel_po({{ $po->id }})" onclick="return confirm('Cancel {{ $po->po_no }}?')">Cancel P.O</button>
                        @endif
                        @if ($po->status === 'partially_received')
                            <button class="btn btn-outline-warning" data-toggle="modal" data-target="#poCloseShortModal">Close short</button>
                        @endif
                    @endif
                    @if ($canReceive && $po->isReceivable())
                        <a href="{{ route('admin_inventory_receiving', ['po' => $po->id]) }}" class="btn btn-success">Receive</a>
                    @endif
                </div>

                <div class="jfr-panel">
                    <h5 class="mb-1">{{ $po->po_no }} <span class="jfr-badge {{ $statusBadge[$po->status] ?? '' }}">{{ \App\Models\PurchaseOrder::STATUSES[$po->status] }}</span></h5>
                    <div class="text-muted">
                        {{ $po->supplier->name }} · ordered {{ $po->order_date->format('d M Y') }}
                        @if ($po->expected_date) · expected {{ $po->expected_date->format('d M Y') }} @endif
                        · by {{ $po->creator->name ?? '-' }}
                    </div>
                    @if ($po->notes)<div class="mt-2">{{ $po->notes }}</div>@endif
                    @if ($po->status === 'closed')
                        <div class="alert alert-secondary mt-2 mb-0 py-2">Closed short on {{ $po->closed_at->format('d M Y') }} by {{ $po->closer->name ?? '-' }}: {{ $po->closed_reason }}</div>
                    @endif
                </div>

                <div class="jfr-table-card">
                    <div class="table-responsive">
                        <table class="table jfr-table mb-0">
                            <thead>
                                <tr><th>Item</th><th>Ordered</th><th>Received</th><th>{{ $po->status === 'closed' ? 'Closed short' : 'Outstanding' }}</th><th class="text-right">Unit cost</th><th class="text-right">Line total</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($po->items as $line)
                                    <tr>
                                        <td>{{ $line->item->name }} <small class="text-muted">{{ $line->item->code }}</small></td>
                                        <td>{{ $line->qty_ordered }}</td>
                                        <td>{{ $line->qty_received }}</td>
                                        <td>{{ $line->qty_outstanding }}</td>
                                        <td class="text-right">{{ number_format($line->unit_cost, 2) }}</td>
                                        <td class="text-right">{{ number_format($line->line_total, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr><td colspan="5" class="text-right">Subtotal</td><td class="text-right">{{ number_format($po->subtotal, 2) }}</td></tr>
                                <tr><td colspan="5" class="text-right">Discount</td><td class="text-right">- {{ number_format($po->discount, 2) }}</td></tr>
                                <tr><td colspan="5" class="text-right">Other charges</td><td class="text-right">{{ number_format($po->other_charges, 2) }}</td></tr>
                                <tr><td colspan="5" class="text-right font-weight-bold">Total</td><td class="text-right font-weight-bold">{{ number_format($po->total, 2) }}</td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                @if ($po->goodsReceipts->isNotEmpty())
                    <h5>Goods received</h5>
                    <div class="jfr-table-card">
                        <table class="table jfr-table mb-0">
                            <thead><tr><th>GRN no</th><th>Date</th><th>Bill no</th><th class="text-right">Total</th></tr></thead>
                            <tbody>
                                @foreach ($po->goodsReceipts as $receipt)
                                    <tr>
                                        <td><a href="{{ route('admin_inventory_receiving', ['grn' => $receipt->id]) }}">{{ $receipt->grn_no }}</a></td>
                                        <td>{{ $receipt->receive_date->format('d M Y') }}</td>
                                        <td>{{ $receipt->supplier_bill_no ?: '-' }}</td>
                                        <td class="text-right">{{ number_format($receipt->total, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="modal fade" id="poCloseShortModal" tabindex="-1" role="dialog" aria-hidden="true" wire:ignore.self>
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header"><h5 class="modal-title">Close {{ $po->po_no }} short</h5></div>
                            <div class="modal-body">
                                <p>The outstanding quantities will no longer be receivable. Ordered quantities are kept for reporting.</p>
                                <label>Reason *</label>
                                <textarea class="form-control" rows="2" wire:model.defer="close_reason" placeholder="e.g. Supplier discontinued the item"></textarea>
                                @error('close_reason') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-warning" wire:click="close_short({{ $po->id }})">Close short</button>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                    if (!window.__jfPoCloseBound) {
                        window.__jfPoCloseBound = true;
                        window.addEventListener('po-close-modal-hide', function () { $('#poCloseShortModal').modal('hide'); });
                    }
                </script>
            @endif
        </div>
    </div>
</div>
