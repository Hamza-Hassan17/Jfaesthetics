<div>
    @include('admins.inventory.partials.styles')

    <div class="content">
        <div class="container">
            <div class="row page-title">
                <div class="col"><h3 class="jfr-title">Inventory</h3></div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                    @if ($_page !== 'form' && $canManage)
                        <button class="btn btn-jfr-teal" wire:click="show_create_form">Record Payment</button>
                    @endif
                </div>
            </div>

            @include('admins.inventory.partials.tabs')

            @if (session()->has('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
            <div class="text-info" wire:loading>Loading..</div>

            {{-- ============ INDEX ============ --}}
            @if ($_page === 'index')
                <div class="jfr-filter-card">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4 mb-0">
                            <label>Supplier</label>
                            <select wire:model.defer="filter_supplier" class="form-control">
                                <option value="">All suppliers</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-auto mb-0">
                            <button type="button" wire:click="$refresh" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                        </div>
                    </div>
                </div>

                <div class="jfr-table-card">
                    <div class="table-responsive">
                        <table class="table table-hover jfr-table mb-0">
                            <thead><tr><th>Payment no</th><th>Date</th><th>Supplier</th><th class="text-right">Amount</th><th>Mode</th><th>Applied to</th><th class="text-right">Advance</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($payments as $payment)
                                    <tr>
                                        <td>{{ $payment->payment_no }}</td>
                                        <td>{{ $payment->payment_date->format('d M Y') }}</td>
                                        <td>{{ $payment->supplier->name }}</td>
                                        <td class="text-right">{{ number_format($payment->amount, 2) }}</td>
                                        <td>{{ \App\Models\SupplierPayment::MODES[$payment->mode] ?? $payment->mode }}</td>
                                        <td>{{ $payment->allocations->map(fn ($a) => $a->goodsReceipt->grn_no ?? 'Opening balance')->implode(', ') ?: '-' }}</td>
                                        <td class="text-right">{{ $payment->unallocated > 0 ? number_format($payment->unallocated, 2) : '-' }}</td>
                                        <td><button wire:click="view({{ $payment->id }})" class="jfr-icon-btn view" title="View"><i class="fas fa-eye"></i></button></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-muted">No payments yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end px-3">{{ $payments->links() }}</div>
                </div>
            @endif

            {{-- ============ FORM ============ --}}
            @if ($_page === 'form')
                <form wire:submit.prevent="save">
                    <div class="jfr-panel">
                        <h5>{{ $editing_id ? 'Edit payment' : 'Record supplier payment' }}</h5>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Supplier *</label>
                                <select class="form-control" wire:model="supplier_id" @if ($editing_id) disabled @endif>
                                    <option value="">Choose supplier</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                                @error('supplier_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-2">
                                <label>Date *</label>
                                <input type="date" class="form-control" wire:model.defer="payment_date">
                            </div>
                            <div class="form-group col-md-2">
                                <label>Amount *</label>
                                <input type="number" step="0.01" min="0" class="form-control" wire:model.lazy="amount">
                                @error('amount') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-2">
                                <label>Mode *</label>
                                <select class="form-control" wire:model.defer="mode">
                                    @foreach (\App\Models\SupplierPayment::MODES as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label>Reference no</label>
                                <input type="text" class="form-control" wire:model.defer="reference_no" placeholder="Cheque / txn no">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <input type="text" class="form-control" wire:model.defer="notes">
                        </div>

                        <h6 class="mt-3">Allocate to bills <small class="text-muted">(oldest first; change if needed — anything left over stays as an advance)</small></h6>
                        @error('allocations') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
                        @if (empty($bills))
                            <p class="text-muted">{{ $supplier_id ? 'Nothing outstanding for this supplier. The whole amount will be an advance.' : 'Choose a supplier.' }}</p>
                        @else
                            <table class="table table-bordered">
                                <thead><tr><th>Bill</th><th>Date</th><th>Due</th><th class="text-right">Total</th><th class="text-right">Outstanding</th><th style="width:170px">Allocate</th></tr></thead>
                                <tbody>
                                    @foreach ($bills as $bill)
                                        <tr>
                                            <td>{{ $bill['label'] }}</td>
                                            <td>{{ $bill['date'] ? \Carbon\Carbon::parse($bill['date'])->format('d M Y') : '-' }}</td>
                                            <td>{{ $bill['due_date'] ? \Carbon\Carbon::parse($bill['due_date'])->format('d M Y') : '-' }}</td>
                                            <td class="text-right">{{ number_format($bill['total'], 2) }}</td>
                                            <td class="text-right">{{ number_format($bill['outstanding'], 2) }}</td>
                                            <td><input type="number" step="0.01" min="0" max="{{ $bill['outstanding'] }}" class="form-control" wire:model.lazy="allocations.{{ $bill['ui_key'] }}"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="text-right">
                                Allocated: <strong>{{ number_format($allocatedTotal, 2) }}</strong>
                                · Advance: <strong>{{ number_format(max(0, (float) $amount - $allocatedTotal), 2) }}</strong>
                                <button type="button" class="btn btn-sm btn-outline-secondary ml-2" wire:click="autoAllocate">Reset to oldest first</button>
                            </div>
                        @endif
                    </div>
                    <button type="submit" class="btn btn-jfr-teal">Save Payment</button>
                    <button type="button" class="btn btn-secondary" wire:click="show_index">Cancel</button>
                </form>
            @endif

            {{-- ============ VIEW ============ --}}
            @if ($_page === 'view')
                <div class="mb-3">
                    <button class="btn btn-secondary" wire:click="show_index"><i class="fas fa-arrow-left"></i> Back</button>
                    @if ($canManage && !$payment->trashed())
                        <button class="btn btn-outline-primary" wire:click="edit({{ $payment->id }})"><i class="fas fa-pen"></i> Edit</button>
                        <button class="btn btn-outline-danger" wire:click="delete({{ $payment->id }})" onclick="return confirm('Delete payment {{ $payment->payment_no }}? The bills it paid become outstanding again.')"><i class="fas fa-trash"></i> Delete</button>
                    @endif
                    <a href="{{ route('admin_inventory_suppliers', ['ledger' => $payment->supplier_id]) }}" class="btn btn-outline-dark">Supplier ledger</a>
                </div>
                <div class="jfr-panel">
                    <h5 class="mb-1">{{ $payment->payment_no }} @if ($payment->trashed())<span class="jfr-badge jfr-badge-negative">Deleted</span>@endif</h5>
                    <div class="text-muted">
                        {{ $payment->supplier->name }} · {{ $payment->payment_date->format('d M Y') }}
                        · {{ \App\Models\SupplierPayment::MODES[$payment->mode] ?? $payment->mode }}
                        @if ($payment->reference_no) · ref {{ $payment->reference_no }} @endif
                        · by {{ $payment->creator->name ?? '-' }}
                    </div>
                    <h4 class="mt-2 mb-0">PKR {{ number_format($payment->amount, 2) }}</h4>
                    @if ($payment->notes)<div class="mt-2">{{ $payment->notes }}</div>@endif
                </div>
                <div class="jfr-table-card">
                    <table class="table jfr-table mb-0">
                        <thead><tr><th>Applied to</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>
                            @forelse ($payment->allocations as $allocation)
                                <tr>
                                    <td>
                                        @if ($allocation->goodsReceipt)
                                            <a href="{{ route('admin_inventory_receiving', ['grn' => $allocation->goods_receipt_id]) }}">{{ $allocation->goodsReceipt->grn_no }}</a>
                                        @else
                                            Opening balance
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format($allocation->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-muted">Not applied to any bill.</td></tr>
                            @endforelse
                            @if ($payment->unallocated > 0)
                                <tr><td><em>Advance (applies to the next GRN)</em></td><td class="text-right">{{ number_format($payment->unallocated, 2) }}</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
