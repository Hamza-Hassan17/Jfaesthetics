<div>
    @include('admins.inventory.partials.styles')

    <div class="content">
        <div class="container">
            <div class="row page-title">
                <div class="col"><h3 class="jfr-title">Inventory</h3></div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                    @if ($_page !== 'form' && $canCreate)
                        <button class="btn btn-jfr-teal" wire:click="show_create_form">New Product Invoice</button>
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
                            <label>Search</label>
                            <input type="text" wire:model.defer="search" wire:keydown.enter="$refresh" autocomplete="one-time-code" class="form-control" placeholder="PI no, invoice no or patient...">
                        </div>
                        <div class="form-group col-md-3 mb-0">
                            <label>Show</label>
                            <select wire:model.defer="filter_link" class="form-control">
                                <option value="">All</option>
                                <option value="linked">Linked to a patient invoice</option>
                                <option value="standalone">Without patient invoice</option>
                                <option value="recipe">Consumables for services</option>
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
                                <tr><th>PI no</th><th>Date</th><th>Patient / buyer</th><th>Patient invoice</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Unpaid</th><th></th></tr>
                            </thead>
                            <tbody>
                                @forelse ($productInvoices as $row)
                                    <tr>
                                        <td>{{ $row->pi_no }} @if ($row->source === 'recipe')<span class="jfr-badge jfr-badge-muted">Consumables</span>@endif</td>
                                        <td>{{ $row->created_at->format('d M Y') }}</td>
                                        <td>{{ $row->buyer_name }}</td>
                                        <td>
                                            @if ($row->invoice)
                                                <a href="{{ route('admin_invoices', ['invoice' => $row->invoice_id]) }}">#{{ $row->invoice->invoice_number }}</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-right">{{ number_format($row->grand_total, 2) }}</td>
                                        <td class="text-right">{{ number_format($row->paid_total, 2) }}</td>
                                        <td class="text-right">{{ number_format($row->unpaid_total, 2) }}</td>
                                        <td><button wire:click="view({{ $row->id }})" class="jfr-icon-btn view" title="View"><i class="fas fa-eye"></i></button></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-muted">No product invoices yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end px-3">{{ $productInvoices->links() }}</div>
                </div>
            @endif

            {{-- ============ FORM ============ --}}
            @if ($_page === 'form')
                <form wire:submit.prevent="save">
                    <div class="jfr-panel">
                        <h5>{{ $editing_id ? 'Edit product invoice' : 'New product invoice' }}</h5>
                        @error('lines') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

                        <div class="form-group">
                            <label class="mr-3"><input type="radio" wire:model="link_mode" value="linked"> With patient invoice</label>
                            <label><input type="radio" wire:model="link_mode" value="standalone"> Without patient invoice</label>
                        </div>

                        @if ($link_mode === 'linked')
                            <div class="form-group">
                                <label>Patient invoice *</label>
                                <select class="form-control" wire:model="invoice_id">
                                    <option value="">Choose invoice</option>
                                    @foreach ($invoices as $inv)
                                        <option value="{{ $inv->id }}">#{{ $inv->invoice_number }} — {{ $inv->patient->name ?? 'N/A' }} ({{ $inv->created_at->format('d M Y') }})</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">The patient comes from this invoice. The product invoice keeps its own total and payments.</small>
                                @error('invoice_id') <span class="text-danger text-xs d-block">{{ $message }}</span> @enderror
                            </div>
                        @else
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Patient (optional)</label>
                                    <select class="form-control" wire:model="patient_id">
                                        <option value="">Walk-in / not registered</option>
                                        @foreach ($patients as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->phone }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                @unless ($patient_id)
                                    <div class="form-group col-md-4">
                                        <label>Walk-in name</label>
                                        <input type="text" class="form-control" wire:model.defer="walkin_name">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Walk-in phone</label>
                                        <input type="text" class="form-control" wire:model.defer="walkin_phone">
                                    </div>
                                @endunless
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr><th style="min-width:240px">Product *</th><th style="min-width:90px">Qty *</th><th>Billable</th><th style="min-width:120px">Unit price</th><th style="min-width:170px">Discount</th><th class="text-right">Total</th><th></th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($lines as $index => $line)
                                        <tr>
                                            <td>
                                                <select class="form-control" wire:model="lines.{{ $index }}.item_id">
                                                    <option value="">Choose product</option>
                                                    @foreach ($products as $product)
                                                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->code }}) — PKR {{ number_format($product->sale_price, 2) }}</option>
                                                    @endforeach
                                                </select>
                                                @if ($line['item_id'] !== '' && isset($available[$line['item_id']]))
                                                    @php $avail = $available[$line['item_id']]; @endphp
                                                    <small class="{{ $avail < (int) $line['quantity'] ? 'text-danger font-weight-bold' : 'text-muted' }}">Available: {{ $avail }}</small>
                                                @endif
                                                @if ($line['service_line_id'])<small class="text-muted d-block">Consumable for a service</small>@endif
                                                @error("lines.$index.item_id") <span class="text-danger text-xs d-block">{{ $message }}</span> @enderror
                                            </td>
                                            <td>
                                                <input type="number" min="1" class="form-control" wire:model.lazy="lines.{{ $index }}.quantity">
                                                @error("lines.$index.quantity") <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                            </td>
                                            <td class="text-center align-middle"><input type="checkbox" wire:model="lines.{{ $index }}.is_billable"></td>
                                            <td>
                                                <input type="number" step="0.01" min="0" class="form-control" wire:model.lazy="lines.{{ $index }}.unit_price"
                                                    @if (!$canOverridePrice || !$line['is_billable']) readonly style="background:#f4f6f6" @endif>
                                            </td>
                                            <td>
                                                <div class="input-group">
                                                    <input type="number" step="0.01" min="0" class="form-control" wire:model.lazy="lines.{{ $index }}.discount_value" @unless ($line['is_billable']) readonly style="background:#f4f6f6" @endunless>
                                                    <select class="form-control" wire:model.lazy="lines.{{ $index }}.discount_type" style="max-width:90px">
                                                        <option value="flat">PKR</option>
                                                        <option value="percent">%</option>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="text-right align-middle">{{ number_format($this->totals['lines'][$index] ?? 0, 2) }}</td>
                                            <td class="align-middle"><button type="button" class="btn btn-outline-danger btn-sm" wire:click="remove_line({{ $index }})"><i class="fas fa-trash"></i></button></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mb-3" wire:click="add_line">+ Add product</button>

                        <h6 class="mt-2">Payments</h6>
                        <table class="table table-bordered">
                            <thead><tr><th>Date</th><th>Amount</th><th>Mode</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($payments as $index => $payment)
                                    <tr>
                                        <td><input type="date" class="form-control" wire:model.lazy="payments.{{ $index }}.paid_on"></td>
                                        <td><input type="number" step="0.01" min="0" class="form-control" wire:model.lazy="payments.{{ $index }}.amount"></td>
                                        <td>
                                            <select class="form-control" wire:model.lazy="payments.{{ $index }}.payment_mode">
                                                @foreach (\App\Http\Livewire\Admins\Inventory\ProductInvoices::PAYMENT_MODES as $mode)<option value="{{ $mode }}">{{ $mode }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td><button type="button" class="btn btn-outline-danger btn-sm" wire:click="remove_payment({{ $index }})"><i class="fas fa-trash"></i></button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-outline-primary btn-sm mb-3" wire:click="add_payment">+ Add payment</button>

                        <div class="form-group">
                            <label>Notes</label>
                            <input type="text" class="form-control" wire:model.defer="notes">
                        </div>
                        <div class="text-right">
                            <h5>Total: PKR {{ number_format($this->totals['total'], 2) }}</h5>
                            <div>Paid: {{ number_format($this->totals['paid'], 2) }} · Unpaid: {{ number_format(max(0, $this->totals['total'] - $this->totals['paid']), 2) }}</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-jfr-teal">Save Product Invoice</button>
                    <button type="button" class="btn btn-secondary" wire:click="{{ $editing_id ? 'view(' . $editing_id . ')' : 'show_index' }}">Cancel</button>
                </form>

                <div class="modal fade" id="piShortfallModal" tabindex="-1" role="dialog" aria-hidden="true" wire:ignore.self>
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header"><h5 class="modal-title">Not enough stock</h5></div>
                            <div class="modal-body">
                                <p>These products will go into negative stock. The shortfall is settled automatically when stock is next received.</p>
                                <table class="table table-sm">
                                    <thead><tr><th>Product</th><th>Needed</th><th>Available</th><th>Short by</th></tr></thead>
                                    <tbody>
                                        @foreach ($shortfalls as $shortfall)
                                            <tr><td>{{ $shortfall['name'] }}</td><td>{{ $shortfall['needed'] }}</td><td>{{ $shortfall['available'] }}</td><td class="text-danger font-weight-bold">{{ $shortfall['short'] }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal" wire:click="cancel_shortfall">Cancel</button>
                                <button type="button" class="btn btn-danger" data-dismiss="modal" wire:click="save(true)">Save anyway</button>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                    if (!window.__jfPiShortfallBound) {
                        window.__jfPiShortfallBound = true;
                        window.addEventListener('pi-shortfall-open', function () { $('#piShortfallModal').modal('show'); });
                    }
                </script>
            @endif

            {{-- ============ VIEW ============ --}}
            @if ($_page === 'view')
                <div class="mb-3">
                    <button class="btn btn-secondary" wire:click="show_index"><i class="fas fa-arrow-left"></i> Back</button>
                    <a href="{{ route('admin_inventory_product_invoice_print', $pi->id) }}" target="_blank" class="btn btn-outline-dark"><i class="fas fa-print"></i> Print products only</a>
                    @if ($pi->invoice)
                        <a href="{{ route('admin_inventory_product_invoice_print', ['productInvoice' => $pi->id, 'combined' => 1]) }}" target="_blank" class="btn btn-outline-dark"><i class="fas fa-print"></i> Print with invoice #{{ $pi->invoice->invoice_number }}</a>
                    @endif
                    @if (!$pi->trashed())
                        @if ($canEdit)<button class="btn btn-outline-primary" wire:click="edit({{ $pi->id }})"><i class="fas fa-pen"></i> Edit</button>@endif
                        @if ($canDelete)<button class="btn btn-outline-danger" data-toggle="modal" data-target="#piCancelModal"><i class="fas fa-trash"></i> Cancel</button>@endif
                    @endif
                </div>

                <div class="jfr-panel">
                    <h5 class="mb-1">{{ $pi->pi_no }}
                        @if ($pi->source === 'recipe')<span class="jfr-badge jfr-badge-muted">Consumables for services</span>@endif
                        @if ($pi->trashed())<span class="jfr-badge jfr-badge-negative">Cancelled</span>@endif
                    </h5>
                    <div class="text-muted">
                        {{ $pi->buyer_name }} {{ $pi->buyer_phone ? '· ' . $pi->buyer_phone : '' }}
                        · {{ $pi->created_at->format('d M Y') }}
                        @if ($pi->invoice) · linked to <a href="{{ route('admin_invoices', ['invoice' => $pi->invoice_id]) }}">invoice #{{ $pi->invoice->invoice_number }}</a> @endif
                        · by {{ $pi->creator->name ?? '-' }}
                    </div>
                    @if ($pi->notes)<div class="mt-2">{{ $pi->notes }}</div>@endif
                </div>

                <div class="jfr-table-card">
                    <div class="table-responsive">
                        <table class="table jfr-table mb-0">
                            <thead><tr><th>Product</th><th>Qty</th><th>Billable</th><th class="text-right">Unit price</th><th class="text-right">Discount</th><th class="text-right">Total</th>@if ($canViewCost)<th class="text-right">Unit cost</th>@endif</tr></thead>
                            <tbody>
                                @foreach ($pi->items as $line)
                                    <tr>
                                        <td>
                                            {{ $line->name }}
                                            @if ($line->stock_qty !== null && $line->stock_qty < $line->quantity)<small class="text-muted d-block">billed {{ $line->quantity }} · used {{ $line->stock_qty }}</small>@endif
                                            @if ($canEdit && !$pi->trashed() && $line->stock_qty > 0)
                                                <button class="btn btn-link btn-sm p-0" wire:click="prompt_reduce({{ $line->id }})" data-toggle="modal" data-target="#piReduceModal">Reduce stock used</button>
                                            @endif
                                        </td>
                                        <td>{{ $line->quantity }}</td>
                                        <td>{{ $line->is_billable ? 'Yes' : 'No' }}</td>
                                        <td class="text-right">{{ number_format($line->unit_price, 2) }}</td>
                                        <td class="text-right">{{ number_format($line->discount, 2) }}</td>
                                        <td class="text-right">{{ number_format($line->after_discount, 2) }}</td>
                                        @if ($canViewCost)<td class="text-right">{{ $line->unit_cost !== null ? number_format($line->unit_cost, 2) : '-' }}</td>@endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <h5>Payments</h5>
                <div class="jfr-table-card">
                    <table class="table jfr-table mb-0">
                        <thead><tr><th>Date</th><th>Mode</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>
                            @forelse ($pi->payments as $payment)
                                <tr><td>{{ $payment->paid_on->format('d M Y') }}</td><td>{{ $payment->payment_mode }}</td><td class="text-right">{{ number_format($payment->amount, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">No payments yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($canEdit && !$pi->trashed())
                    <form wire:submit.prevent="add_payment_to_pi" class="form-inline mb-4">
                        <input type="date" wire:model.lazy="new_paid_on" class="form-control mr-2">
                        <input type="number" step="0.01" min="0.01" wire:model.lazy="new_amount" placeholder="Amount" class="form-control mr-2">
                        <select wire:model.lazy="new_payment_mode" class="form-control mr-2">
                            @foreach (\App\Http\Livewire\Admins\Inventory\ProductInvoices::PAYMENT_MODES as $mode)<option value="{{ $mode }}">{{ $mode }}</option>@endforeach
                        </select>
                        <button type="submit" class="btn btn-primary">Record payment</button>
                    </form>
                @endif

                <div class="text-right p-3 bg-light rounded">
                    <h5>Total: PKR {{ number_format($pi->grand_total, 2) }}</h5>
                    <h5>Paid: PKR {{ number_format($pi->paid_total, 2) }}</h5>
                    <h5>Unpaid: PKR {{ number_format($pi->unpaid_total, 2) }}</h5>
                </div>

                <div class="modal fade" id="piReduceModal" tabindex="-1" role="dialog" aria-hidden="true" wire:ignore.self>
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header"><h5 class="modal-title">Reduce stock used</h5></div>
                            <div class="modal-body">
                                @if ($reduceLine)<p><strong>{{ $reduceLine->name }}</strong>: billed {{ $reduceLine->quantity }}, used {{ $reduceLine->stock_qty }}. Unused stock goes back to its batches; the billed amount doesn't change.</p>@endif
                                <div class="form-group">
                                    <label>Qty to return *</label>
                                    <input type="number" min="1" class="form-control" wire:model.defer="reduce_qty">
                                    @error('reduce_qty') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group mb-0">
                                    <label>Reason *</label>
                                    <input type="text" class="form-control" wire:model.defer="reduce_reason" placeholder="e.g. Patient stopped after 2 of 5 sessions">
                                    @error('reduce_reason') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" wire:click="reduce_stock">Return to stock</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="piCancelModal" tabindex="-1" role="dialog" aria-hidden="true" wire:ignore.self>
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header"><h5 class="modal-title">Cancel {{ $pi->pi_no }}</h5></div>
                            <div class="modal-body">
                                <p>All its stock goes back to the batches it came from. Re-enter your password to confirm.</p>
                                <input type="password" class="form-control" wire:model.lazy="cancel_password">
                                @error('cancel_password') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Back</button>
                                <button type="button" class="btn btn-danger" wire:click="cancel_pi">Cancel product invoice</button>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                    if (!window.__jfPiViewBound) {
                        window.__jfPiViewBound = true;
                        window.addEventListener('pi-reduce-closed', function () { $('#piReduceModal').modal('hide'); });
                        window.addEventListener('pi-cancel-closed', function () { $('#piCancelModal').modal('hide'); });
                    }
                </script>
            @endif
        </div>
    </div>
</div>
