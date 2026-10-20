<div>
    @include('admins.inventory.partials.styles')

    <div class="content">
        <div class="container">
            <div class="row page-title">
                <div class="col"><h3 class="jfr-title">Reports</h3></div>
                <div class="col-auto">@include('admins.partials.back-to-dashboard')</div>
            </div>

            @include('admins.partials.reports-tabs')

            <div class="jfr-filter-card">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-3 mb-0">
                        <label>From</label>
                        <input type="date" wire:model.defer="filter_from" class="form-control">
                    </div>
                    <div class="form-group col-md-3 mb-0">
                        <label>To</label>
                        <input type="date" wire:model.defer="filter_to" class="form-control">
                    </div>
                    <div class="form-group col-md-2 mb-0">
                        <label>Status</label>
                        <select wire:model.defer="filter_status" class="form-control">
                            <option value="">All</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid / partial</option>
                        </select>
                    </div>
                    <div class="form-group col-md-auto mb-0">
                        <button type="button" wire:click="$refresh" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        <button type="button" wire:click="resetFilters" class="btn btn-outline-secondary"><i class="fas fa-redo"></i> Reset</button>
                        <button type="button" wire:click="exportCsv" class="btn btn-outline-success"><i class="fas fa-file-csv"></i> CSV</button>
                        <a href="{{ route('admin_reports_product_invoices_print', $filters) }}" target="_blank" class="btn btn-outline-dark"><i class="fas fa-print"></i> Print</a>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                @foreach (['count' => 'Product invoices', 'total' => 'Total', 'paid' => 'Collected', 'unpaid' => 'Unpaid'] as $key => $label)
                    <div class="col-md-3"><div class="jfr-panel mb-2"><div class="text-muted small">{{ $label }}</div><h4 class="mb-0">{{ $key === 'count' ? $summary[$key] : 'Rs. ' . number_format($summary[$key], 0) }}</h4></div></div>
                @endforeach
            </div>

            <div class="jfr-table-card">
                <div class="table-responsive">
                    <table class="table table-hover jfr-table mb-0">
                        <thead><tr><th>PI no</th><th>Date</th><th>Patient / buyer</th><th>Patient invoice</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Unpaid</th></tr></thead>
                        <tbody>
                            @forelse ($productInvoices as $pi)
                                <tr>
                                    <td><a href="{{ route('admin_inventory_product_invoices', ['pi' => $pi->id]) }}">{{ $pi->pi_no }}</a> @if ($pi->source === 'recipe')<span class="jfr-badge jfr-badge-muted">Consumables</span>@endif</td>
                                    <td>{{ $pi->created_at->format('d M Y') }}</td>
                                    <td>{{ $pi->buyer_name }}</td>
                                    <td>{{ $pi->invoice ? '#' . $pi->invoice->invoice_number : '-' }}</td>
                                    <td class="text-right">{{ number_format($pi->grand_total, 2) }}</td>
                                    <td class="text-right">{{ number_format($pi->paid_total, 2) }}</td>
                                    <td class="text-right">{{ number_format($pi->unpaid_total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-muted">No product invoices in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end px-3">{{ $productInvoices->links() }}</div>
            </div>
        </div>
    </div>
</div>
