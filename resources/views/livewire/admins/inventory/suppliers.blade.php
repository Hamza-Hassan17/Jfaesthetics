<div>
    @include('admins.inventory.partials.styles')

    <div class="content">
        <div class="container">
            <div class="row page-title">
                <div class="col"><h3 class="jfr-title">Inventory</h3></div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                    @if ($_page === 'index' && $canManage)
                        <button class="btn btn-jfr-teal" wire:click="show_create">Add Supplier</button>
                    @endif
                </div>
            </div>

            @include('admins.inventory.partials.tabs')

            @if (session()->has('message'))
                <div class="alert alert-success">{{ session('message') }}</div>
            @endif

            {{-- ============ INDEX ============ --}}
            @if ($_page === 'index')
                <div class="jfr-filter-card">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-5 mb-0">
                            <label>Search</label>
                            <input type="text" wire:model.defer="search" wire:keydown.enter="$refresh" autocomplete="one-time-code" class="form-control" placeholder="Name or phone...">
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
                                <tr>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Phone</th>
                                    <th>Credit days</th>
                                    @if ($canViewCost)<th>Balance owed</th>@endif
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($suppliers as $supplier)
                                    <tr>
                                        <td>{{ $supplier->name }}</td>
                                        <td>{{ $supplier->contact_person ?: '-' }}</td>
                                        <td>{{ $supplier->phone ?: '-' }}</td>
                                        <td>{{ $supplier->credit_days }}</td>
                                        @if ($canViewCost)
                                            <td class="{{ $supplier->balance_owed > 0 ? 'font-weight-bold' : '' }}">{{ number_format($supplier->balance_owed, 2) }}</td>
                                        @endif
                                        <td><span class="jfr-badge {{ $supplier->is_active ? 'jfr-badge-ok' : 'jfr-badge-muted' }}">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span></td>
                                        <td>
                                            @if ($canViewCost)
                                                <button wire:click="ledger({{ $supplier->id }})" class="jfr-icon-btn view" title="Ledger"><i class="fas fa-book"></i></button>
                                            @endif
                                            @if ($canManage)
                                                <button wire:click="edit({{ $supplier->id }})" class="jfr-icon-btn edit" title="Edit"><i class="fas fa-pen"></i></button>
                                            @endif
                                            @if ($canPay)
                                                <a href="{{ route('admin_inventory_supplier_payments', ['supplier' => $supplier->id]) }}" class="btn btn-sm btn-outline-success">Record payment</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-muted">No suppliers yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end px-3">{{ $suppliers->links() }}</div>
                </div>
            @endif

            {{-- ============ LEDGER ============ --}}
            @if ($_page === 'ledger')
                <div class="mb-3">
                    <button class="btn btn-secondary" wire:click="show_index"><i class="fas fa-arrow-left"></i> Back to Suppliers</button>
                    @if ($canPay)
                        <a href="{{ route('admin_inventory_supplier_payments', ['supplier' => $supplier->id]) }}" class="btn btn-outline-success">Record payment</a>
                    @endif
                </div>

                <div class="jfr-panel">
                    <h5 class="mb-1">{{ $supplier->name }}</h5>
                    <div class="text-muted">
                        {{ $supplier->contact_person ?: '' }} {{ $supplier->phone ? '· ' . $supplier->phone : '' }} · Credit days: {{ $supplier->credit_days }}
                    </div>
                </div>

                <div class="jfr-filter-card">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3 mb-0">
                            <label>From</label>
                            <input type="date" wire:model.defer="ledger_from" class="form-control">
                        </div>
                        <div class="form-group col-md-3 mb-0">
                            <label>To</label>
                            <input type="date" wire:model.defer="ledger_to" class="form-control">
                        </div>
                        <div class="form-group col-md-auto mb-0">
                            <button type="button" wire:click="$refresh" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                            <a href="{{ route('admin_inventory_supplier_ledger_print', ['supplier' => $supplier->id] + $filters) }}" target="_blank" class="btn btn-outline-dark"><i class="fas fa-print"></i> Print</a>
                            <a href="{{ route('admin_inventory_supplier_ledger_csv', ['supplier' => $supplier->id] + $filters) }}" class="btn btn-outline-success"><i class="fas fa-file-csv"></i> CSV</a>
                        </div>
                    </div>
                </div>

                <div class="jfr-table-card">
                    <div class="table-responsive">
                        <table class="table jfr-table mb-0">
                            <thead>
                                <tr><th>Date</th><th>Document</th><th class="text-right">Debit (we owe more)</th><th class="text-right">Credit (we owe less)</th><th class="text-right">Balance</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td>{{ $row['date'] ? \Carbon\Carbon::parse($row['date'])->format('d M Y') : '' }}</td>
                                        <td>
                                            @if ($row['kind'] === 'grn')
                                                <a href="{{ route('admin_inventory_receiving', ['grn' => $row['id']]) }}">{{ $row['document'] }}</a>
                                            @elseif ($row['kind'] === 'payment')
                                                <a href="{{ route('admin_inventory_supplier_payments', ['payment' => $row['id']]) }}">{{ $row['document'] }}</a>
                                            @else
                                                <strong>{{ $row['document'] }}</strong>
                                            @endif
                                        </td>
                                        <td class="text-right">{{ $row['debit'] ? number_format($row['debit'], 2) : '' }}</td>
                                        <td class="text-right">{{ $row['credit'] ? number_format($row['credit'], 2) : '' }}</td>
                                        <td class="text-right font-weight-bold">{{ number_format($row['balance'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="modal fade" id="supplierModal" tabindex="-1" role="dialog" aria-hidden="true" wire:ignore.self>
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <form wire:submit.prevent="save">
                            <div class="modal-header">
                                <h5 class="modal-title">{{ $editing_id ? 'Edit Supplier' : 'Add Supplier' }}</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <label>Name *</label>
                                    <input type="text" class="form-control" wire:model.defer="name">
                                    @error('name') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Contact person</label>
                                        <input type="text" class="form-control" wire:model.defer="contact_person">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Phone</label>
                                        <input type="text" class="form-control" wire:model.defer="phone">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" class="form-control" wire:model.defer="email">
                                    @error('email') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group">
                                    <label>Address</label>
                                    <textarea class="form-control" rows="2" wire:model.defer="address"></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Opening balance (we owe)</label>
                                        <input type="number" step="0.01" class="form-control" wire:model.defer="opening_balance">
                                        <small class="text-muted">Negative = supplier owes us / advance.</small>
                                        @error('opening_balance') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Credit days *</label>
                                        <input type="number" min="0" class="form-control" wire:model.defer="credit_days">
                                        <small class="text-muted">GRN due date = receive date + credit days.</small>
                                        @error('credit_days') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="supplierActive" wire:model.defer="is_active">
                                    <label class="form-check-label" for="supplierActive">Active</label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-jfr-teal">{{ $editing_id ? 'Update Supplier' : 'Save Supplier' }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <script>
                if (!window.__jfSupplierModalBound) {
                    window.__jfSupplierModalBound = true;
                    window.addEventListener('supplier-modal-open', function () { $('#supplierModal').modal('show'); });
                    window.addEventListener('supplier-modal-close', function () { $('#supplierModal').modal('hide'); });
                }
            </script>
        </div>
    </div>
</div>
