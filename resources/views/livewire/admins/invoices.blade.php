<div>
    <style>
        .jfr-title { color: #0a3535; font-weight: 800; }
        .btn-jfr-teal {
            background: #148080;
            border-color: #148080;
            color: #fff;
        }
        .btn-jfr-teal:hover { background: #0d5c5c; border-color: #0d5c5c; color: #fff; }
        .jfr-table-card {
            background: #fff;
            border: 1px solid #e9eef0;
            border-radius: 14px;
            padding: 6px 6px 14px;
            box-shadow: 0 6px 18px rgba(10, 53, 53, 0.05);
        }
        .jfr-table thead th {
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: .04em;
            color: #7a8a8a;
            background: #f4f8f8;
            border-bottom: none;
            white-space: nowrap;
        }
        .jfr-table td { vertical-align: middle; font-size: 13.5px; }
        .jfr-table tbody tr:hover { background: rgba(20, 128, 128, .04); }
        .jfr-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11.5px;
            font-weight: 700;
        }
        .jfr-badge-paid { background: rgba(56, 176, 105, .12); color: #2f9c5c; }
        .jfr-badge-partial { background: rgba(230, 140, 40, .12); color: #d47f1f; }
        .jfr-badge-unpaid { background: rgba(224, 83, 83, .12); color: #d43f3f; }
        .jfr-icon-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            margin-right: 4px;
        }
        .jfr-icon-btn.view { background: rgba(33, 150, 243, .1); color: #2196F3; }
        .jfr-icon-btn.print { background: rgba(20, 128, 128, .1); color: #148080; }
        .jfr-icon-btn.delete { background: rgba(224, 83, 83, .1); color: #d43f3f; }
        .jfr-entries-info { color: #7a8a8a; font-size: 13px; margin-top: 10px; }
    </style>
    <div class="content">
        <div class="container">
            <div class="row page-title row">
                <div class="col">
                    <h3 class="jfr-title">{{ env('APP_NAME') }}
                        @if ($_page === 'index')
                            Invoices
                        @elseif ($_page === 'create')
                            Generate New Invoice
                        @else
                            Invoice Details
                        @endif
                    </h3>
                </div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                    @if ($_page !== 'create')
                        <button class="btn btn-jfr-teal" wire:click="show_create_form">Generate New Invoice</button>
                    @else
                        <button class="btn btn-secondary" wire:click="show_index">Back to List</button>
                    @endif
                </div>
            </div>

            @if (session()->has('message'))
                <div class="alert alert-success">
                    {{ session('message') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <div class="box box-primary">
                <div class="box-body">
                    <div class="text-info" wire:loading>Loading..</div>

                    {{-- ============ CREATE ============ --}}
                    @if ($_page === 'create')
                        <form wire:submit.prevent="generate_invoice">
                            <div class="form-group">
                                <label>Patient</label>
                                <select wire:model="patient_id" class="form-control">
                                    <option value="">Choose Patient</option>
                                    @forelse ($patients as $patient)
                                        <option value="{{ $patient->id }}">{{ $patient->name }} (MR-{{ str_pad($patient->id, 5, '0', STR_PAD_LEFT) }})</option>
                                    @empty
                                        <option value="">No Patient Found!</option>
                                    @endforelse
                                </select>
                                @error('patient_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-group">
                                <label>Doctor</label>
                                <select wire:model="doctor_id" class="form-control">
                                    <option value="">Choose Doctor</option>
                                    @forelse ($doctors as $doc)
                                        <option value="{{ $doc->id }}">{{ $doc->employ->name ?? 'Unknown' }}</option>
                                    @empty
                                        <option value="">No Doctor Found!</option>
                                    @endforelse
                                </select>
                                @error('doctor_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>

                            @error('items') <div class="alert alert-danger">{{ $message }}</div> @enderror

                            <hr>
                            <h5>Services / Products</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th style="min-width: 260px;">Service/Product</th>
                                            <th>Quantity</th>
                                            <th>Session</th>
                                            <th>Service Charges</th>
                                            <th>Discount</th>
                                            <th>Sub Total</th>
                                            <th>Discount Amt</th>
                                            <th>After Discount</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($items as $index => $item)
                                            <tr>
                                                <td>
                                                    <div class="form-inline mb-1">
                                                        <select class="form-control form-control-sm mr-1" wire:model="items.{{ $index }}.type">
                                                            <option value="custom">Custom / Typed</option>
                                                            <option value="service">Service</option>
                                                            <option value="medicine">Medicine</option>
                                                        </select>
                                                    </div>

                                                    @if ($item['type'] === 'custom')
                                                        <input type="text" class="form-control" wire:model.lazy="items.{{ $index }}.service" placeholder="e.g. Carbon+PRP+Meso 3sessions">
                                                    @else
                                                        <select class="form-control" wire:model="items.{{ $index }}.catalog_id">
                                                            <option value="">Choose {{ $item['type'] === 'service' ? 'Service' : 'Medicine' }}</option>
                                                            @if ($item['type'] === 'service')
                                                                @foreach ($services as $svc)
                                                                    <option value="{{ $svc->id }}">{{ $svc->name }} (PKR {{ number_format($svc->price, 2) }})</option>
                                                                @endforeach
                                                            @else
                                                                @foreach ($medicines as $med)
                                                                    <option value="{{ $med->id }}">{{ $med->name }} — stock: {{ $med->quantity }} (PKR {{ number_format($med->price, 2) }})</option>
                                                                @endforeach
                                                            @endif
                                                        </select>
                                                        <button type="button" class="btn btn-link btn-sm p-0 mt-1" wire:click="show_add_new({{ $index }})">+ Add new {{ $item['type'] }}</button>
                                                    @endif
                                                </td>
                                                <td><input type="number" min="1" class="form-control" wire:model.lazy="items.{{ $index }}.quantity"></td>
                                                <td>
                                                    <select class="form-control" wire:model.lazy="items.{{ $index }}.session">
                                                        <option value="">-</option>
                                                        @for ($s = 1; $s <= 10; $s++)
                                                            <option value="{{ $s }}">{{ $s }}</option>
                                                        @endfor
                                                    </select>
                                                </td>
                                                <td><input type="number" step="0.01" min="0" class="form-control" wire:model.lazy="items.{{ $index }}.service_charges"></td>
                                                <td>
                                                    <div class="input-group">
                                                        <input type="number" step="0.01" min="0" class="form-control" wire:model.lazy="items.{{ $index }}.discount_value">
                                                        <select class="form-control" wire:model.lazy="items.{{ $index }}.discount_type" style="max-width: 90px;">
                                                            <option value="flat">PKR</option>
                                                            <option value="percent">%</option>
                                                        </select>
                                                    </div>
                                                </td>
                                                <td class="align-middle">{{ number_format($this->item_totals[$index]['sub_total'] ?? 0, 2) }}</td>
                                                <td class="align-middle">{{ number_format($this->item_totals[$index]['discount_amount'] ?? 0, 2) }}</td>
                                                <td class="align-middle">{{ number_format($this->item_totals[$index]['after_discount'] ?? 0, 2) }}</td>
                                                <td class="align-middle">
                                                    <button type="button" class="btn btn-outline-danger btn-sm" wire:click="remove_item({{ $index }})"><i class="fas fa-trash"></i></button>
                                                </td>
                                            </tr>
                                            @error("items.$index.service") <tr><td colspan="9"><span class="text-danger text-xs">{{ $message }}</span></td></tr> @enderror
                                            @error("items.$index.quantity") <tr><td colspan="9"><span class="text-danger text-xs">{{ $message }}</span></td></tr> @enderror

                                            @if ($adding_new_for_index === $index)
                                                <tr class="bg-light">
                                                    <td colspan="9">
                                                        <div class="form-inline">
                                                            <input type="text" class="form-control mr-2" wire:model.lazy="new_catalog_name" placeholder="Name">
                                                            <input type="number" step="0.01" min="0" class="form-control mr-2" wire:model.lazy="new_catalog_price" placeholder="Price">
                                                            @if ($item['type'] === 'medicine')
                                                                <input type="text" class="form-control mr-2" wire:model.lazy="new_catalog_code" placeholder="Code">
                                                                <input type="number" min="0" class="form-control mr-2" wire:model.lazy="new_catalog_quantity" placeholder="Starting quantity">
                                                            @endif
                                                            <button type="button" class="btn btn-success btn-sm mr-2" wire:click="save_new_catalog_item">Save</button>
                                                            <button type="button" class="btn btn-secondary btn-sm" wire:click="cancel_add_new">Cancel</button>
                                                        </div>
                                                        @error('new_catalog_name') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                                        @error('new_catalog_price') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                                        @error('new_catalog_code') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                                        @error('new_catalog_quantity') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mb-3" wire:click="add_item">+ Add Line Item</button>

                            <hr>
                            <h5>Payment History</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Payment Mode</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($payments as $index => $payment)
                                            <tr>
                                                <td><input type="date" class="form-control" wire:model.lazy="payments.{{ $index }}.paid_on"></td>
                                                <td><input type="number" step="0.01" min="0" class="form-control" wire:model.lazy="payments.{{ $index }}.amount"></td>
                                                <td>
                                                    <select class="form-control" wire:model.lazy="payments.{{ $index }}.payment_mode">
                                                        <option value="Cash">Cash</option>
                                                        <option value="Card">Card</option>
                                                        <option value="Bank Transfer">Bank Transfer</option>
                                                        <option value="Other">Other</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" wire:click="remove_payment({{ $index }})"><i class="fas fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mb-3" wire:click="add_payment">+ Add Payment</button>

                            <hr>
                            <div class="form-group">
                                <label>Notes</label>
                                <textarea wire:model.lazy="notes" class="form-control" rows="2"></textarea>
                            </div>

                            <div class="text-right p-3 bg-light rounded">
                                <h5>Grand Total: {{ number_format($this->grand_total, 2) }}</h5>
                                <h5>Paid: {{ number_format($this->paid_total, 2) }}</h5>
                                <h5>Unpaid: {{ number_format(max($this->grand_total - $this->paid_total, 0), 2) }}</h5>
                                @if ($this->paid_total > $this->grand_total)
                                    <h5>Overpaid: {{ number_format($this->paid_total - $this->grand_total, 2) }}</h5>
                                @endif
                            </div>

                            <div class="form-group mt-3">
                                <button type="submit" class="btn btn-primary">Generate Invoice</button>
                            </div>
                        </form>
                    @endif

                    {{-- ============ VIEW / PRINT PREVIEW ============ --}}
                    @if ($_page === 'view')
                        <div class="mb-3">
                            <a href="{{ route('admin_invoice_print', $invoice->id) }}" target="_blank" class="btn btn-success"><i class="fas fa-print"></i> Print / Download A4 Invoice</a>
                            <button class="btn btn-secondary" wire:click="show_index">Back to List</button>
                        </div>

                        <h4>Invoice #{{ $invoice->invoice_number }}</h4>
                        <p><strong>Patient:</strong> {{ $invoice->patient->name }} &nbsp; <strong>MR#:</strong> MR-{{ str_pad($invoice->patient_id, 5, '0', STR_PAD_LEFT) }} &nbsp; <strong>Doctor:</strong> {{ $invoice->doctor->employ->name ?? 'N/A' }}</p>

                        <table class="table table-bordered">
                            <thead>
                                <tr><th>Service/Product</th><th>Qty</th><th>Session</th><th>Charges</th><th>Sub Total</th><th>Discount</th><th>After Discount</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($invoice->items as $item)
                                    <tr>
                                        <td>{{ $item->service }}</td>
                                        <td>{{ $item->quantity }}</td>
                                        <td>{{ $item->session_number ?? '-' }}</td>
                                        <td>{{ number_format($item->service_charges, 2) }}</td>
                                        <td>{{ number_format($item->sub_total, 2) }}</td>
                                        <td>
                                            {{ number_format($item->discount, 2) }}
                                            @if ($item->discount_type === 'percent')
                                                <small class="text-muted">({{ rtrim(rtrim(number_format($item->discount_value, 2), '0'), '.') }}%)</small>
                                            @endif
                                        </td>
                                        <td>{{ number_format($item->after_discount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <h5>Payment History</h5>
                        <table class="table table-bordered">
                            <thead><tr><th>Date</th><th>Amount</th><th>Mode</th></tr></thead>
                            <tbody>
                                @forelse ($invoice->payments as $payment)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($payment->paid_on)->format('d M Y') }}</td>
                                        <td>{{ number_format($payment->amount, 2) }}</td>
                                        <td>{{ $payment->payment_mode }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-warning">No payments recorded yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>

                        <form wire:submit.prevent="add_payment_to_invoice" class="form-inline mb-4">
                            <input type="date" wire:model.lazy="new_paid_on" class="form-control mr-2">
                            <input type="number" step="0.01" min="0.01" wire:model.lazy="new_amount" placeholder="Amount" class="form-control mr-2">
                            <select wire:model.lazy="new_payment_mode" class="form-control mr-2">
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Other">Other</option>
                            </select>
                            <button type="submit" class="btn btn-primary">Record Payment</button>
                        </form>

                        <div class="text-right p-3 bg-light rounded">
                            <h5>Grand Total: PKR {{ number_format($invoice->grand_total, 2) }}</h5>
                            <h5>Paid: PKR {{ number_format($invoice->paid_total, 2) }}</h5>
                            <h5>Unpaid: PKR {{ number_format($invoice->unpaid_total, 2) }}</h5>
                            @if ($invoice->overpaid_total > 0)
                                <h5>Overpaid: PKR {{ number_format($invoice->overpaid_total, 2) }}</h5>
                            @endif
                        </div>
                    @endif

                    {{-- ============ INDEX ============ --}}
                    @if ($_page === 'index')
                        <div class="jfr-table-card">
                            <div class="table-responsive">
                                <table class="table table-hover jfr-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Patient</th>
                                            <th>Doctor</th>
                                            <th>Grand Total</th>
                                            <th>Paid</th>
                                            <th>Unpaid</th>
                                            <th>Created On</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($invoices as $invoice)
                                            <tr>
                                                <td>{{ $invoice->invoice_number }}</td>
                                                <td>{{ $invoice->patient->name ?? 'N/A' }}</td>
                                                <td>{{ $invoice->doctor->employ->name ?? 'N/A' }}</td>
                                                <td>{{ number_format($invoice->grand_total, 2) }}</td>
                                                <td>{{ number_format($invoice->paid_total, 2) }}</td>
                                                <td>{{ number_format($invoice->unpaid_total, 2) }}</td>
                                                <td>{{ $invoice->created_at->format('d M Y') }}</td>
                                                <td>
                                                    @if ($invoice->unpaid_total <= 0)
                                                        <span class="jfr-badge jfr-badge-paid">Paid</span>
                                                    @elseif ($invoice->paid_total > 0)
                                                        <span class="jfr-badge jfr-badge-partial">Partial</span>
                                                    @else
                                                        <span class="jfr-badge jfr-badge-unpaid">Unpaid</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <button wire:click="view({{ $invoice->id }})" class="jfr-icon-btn view"><i class="fas fa-eye"></i></button>
                                                    <a href="{{ route('admin_invoice_print', $invoice->id) }}" target="_blank" class="jfr-icon-btn print"><i class="fas fa-print"></i></a>
                                                    <button wire:click="delete({{ $invoice->id }})" onclick="return confirm('Are You Sure?')" class="jfr-icon-btn delete"><i class="fas fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="9" class="text-warning">No invoices generated yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-between align-items-center flex-wrap px-3">
                                @if ($invoices->total() > 0)
                                    <div class="jfr-entries-info">Showing {{ $invoices->firstItem() }} to {{ $invoices->lastItem() }} of {{ $invoices->total() }} entries</div>
                                @else
                                    <div class="jfr-entries-info">No entries found</div>
                                @endif
                                <div>{{ $invoices->links() }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
