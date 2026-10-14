<div>
    @include('admins.inventory.partials.styles')

    <div class="content">
        <div class="container">
            <div class="row page-title">
                <div class="col"><h3 class="jfr-title">Inventory</h3></div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                    @if ($canCreate)
                        <a href="{{ route('admin_invoices', ['new' => 1]) }}" class="btn btn-jfr-teal">New Product Invoice</a>
                    @endif
                </div>
            </div>

            @include('admins.inventory.partials.tabs')

            <div class="jfr-filter-card">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-4 mb-0">
                        <label>Search</label>
                        <input type="text" wire:model.defer="search" wire:keydown.enter="$refresh" autocomplete="one-time-code" class="form-control" placeholder="Invoice # or patient...">
                    </div>
                    <div class="form-group col-md-3 mb-0">
                        <label>Show</label>
                        <select wire:model.defer="filter" class="form-control">
                            <option value="">All invoices with stock</option>
                            @foreach (\App\Http\Livewire\Admins\Inventory\InvoiceStock::FILTERS as $value => $label)
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
                            <tr>
                                <th>Invoice #</th><th>Date</th><th>Patient</th><th>Type</th><th>Stock lines</th>
                                <th class="text-right">Product revenue</th>
                                @if ($canViewCost)<th class="text-right">Cost</th><th class="text-right">Margin</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invoices as $invoice)
                                @php
                                    $stockLines = $invoice->items->whereNotNull('inventory_item_id');
                                    $revenue = $stockLines->sum('after_discount');
                                    $cost = $stockLines->sum(fn ($l) => (float) $l->unit_cost * (int) ($l->stock_qty ?? $l->quantity));
                                    $hasService = $invoice->items->contains('line_type', 'service');
                                @endphp
                                <tr>
                                    <td><a href="{{ route('admin_invoices', ['invoice' => $invoice->id]) }}">#{{ $invoice->invoice_number }}</a></td>
                                    <td>{{ $invoice->created_at->format('d M Y') }}</td>
                                    <td>{{ $invoice->patient->name ?? '-' }}</td>
                                    <td><span class="jfr-badge {{ $hasService ? 'jfr-badge-ok' : 'jfr-badge-muted' }}">{{ $hasService ? 'With services' : 'Product-only' }}</span></td>
                                    <td>{{ $stockLines->count() }}</td>
                                    <td class="text-right">{{ number_format($revenue, 2) }}</td>
                                    @if ($canViewCost)
                                        <td class="text-right">{{ number_format($cost, 2) }}</td>
                                        <td class="text-right">{{ number_format($revenue - $cost, 2) }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-muted">No invoices have used stock yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end px-3">{{ $invoices->links() }}</div>
            </div>
        </div>
    </div>
</div>
