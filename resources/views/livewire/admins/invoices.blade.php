<div>
    <div class="content">
        <div class="container">
            <div class="row page-title row">
                <div class="col">
                    <h3 class="text-info">{{ env('APP_NAME') }} Invoices</h3>
                </div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                    @if ($_page !== 'create')
                        <button class="btn btn-primary" wire:click="show_create_form">Generate New Invoice</button>
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
                        <form wire:submit.prevent="$refresh">
                            <div class="form-group">
                                <label>Search</label>
                                <input type="text" wire:model.defer="search" class="form-control" placeholder="Search Invoice #, Patient, Doctor...">
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label>Date From</label>
                                    <input type="date" wire:model.defer="filter_from" class="form-control">
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Date To</label>
                                    <input type="date" wire:model.defer="filter_to" class="form-control">
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Doctor</label>
                                    <select wire:model.defer="filter_doctor_id" class="form-control">
                                        <option value="">All Doctors</option>
                                        @foreach ($doctors as $doc)
                                            <option value="{{ $doc->id }}">{{ $doc->employ->name ?? 'Unknown' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Patient</label>
                                    <select wire:model.defer="filter_patient_id" class="form-control">
                                        <option value="">All Patients</option>
                                        @foreach ($patients as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label>Payment Status</label>
                                    <select wire:model.defer="filter_status" class="form-control">
                                        <option value="">All Status</option>
                                        <option value="paid">Paid</option>
                                        <option value="partial">Partial</option>
                                        <option value="unpaid">Unpaid</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Service</label>
                                    <select wire:model.defer="filter_service" class="form-control">
                                        <option value="">All Services</option>
                                        @foreach ($services as $svc)
                                            <option value="{{ $svc->name }}">{{ $svc->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                                    <button type="button" wire:click="resetFilters" class="btn btn-outline-secondary"><i class="fas fa-redo"></i> Reset</button>
                                </div>
                                <div>
                                    <button type="button" wire:click="exportCsv" class="btn btn-outline-success"><i class="fas fa-file-excel"></i> Export Excel</button>
                                    <a href="{{ route('admin_invoices_print_list', array_filter(['search' => $search, 'from' => $filter_from, 'to' => $filter_to, 'doctor_id' => $filter_doctor_id, 'patient_id' => $filter_patient_id, 'status' => $filter_status, 'service' => $filter_service])) }}" target="_blank" class="btn btn-outline-danger"><i class="fas fa-file-pdf"></i> Export PDF</a>
                                    <a href="{{ route('admin_invoices_print_list', array_filter(['search' => $search, 'from' => $filter_from, 'to' => $filter_to, 'doctor_id' => $filter_doctor_id, 'patient_id' => $filter_patient_id, 'status' => $filter_status, 'service' => $filter_service])) }}" target="_blank" class="btn btn-outline-dark"><i class="fas fa-print"></i> Print</a>
                                </div>
                            </div>
                        </form>

                        <div class="row mb-4">
                            <div class="col-md-3 mb-2">
                                <div class="card p-3 text-center">
                                    <div class="text-muted" style="font-size: 13px;">Total Invoices</div>
                                    <div class="h4 mb-0">{{ $summary['total_invoices'] }}</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="card p-3 text-center">
                                    <div class="text-muted" style="font-size: 13px;">Total Revenue</div>
                                    <div class="h4 mb-0">PKR {{ number_format($summary['total_revenue'], 2) }}</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="card p-3 text-center">
                                    <div class="text-muted" style="font-size: 13px;">Total Paid</div>
                                    <div class="h4 mb-0">PKR {{ number_format($summary['total_paid'], 2) }}</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="card p-3 text-center">
                                    <div class="text-muted" style="font-size: 13px;">Outstanding</div>
                                    <div class="h4 mb-0">PKR {{ number_format($summary['outstanding'], 2) }}</div>
                                </div>
                            </div>
                        </div>

                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Services</th>
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
                                        <td>{{ $invoice->items->pluck('service')->implode(', ') }}</td>
                                        <td>{{ number_format($invoice->grand_total, 2) }}</td>
                                        <td>{{ number_format($invoice->paid_total, 2) }}</td>
                                        <td>{{ number_format($invoice->unpaid_total, 2) }}</td>
                                        <td>{{ $invoice->created_at->format('d M Y') }}</td>
                                        <td>
                                            @if ($invoice->unpaid_total <= 0)
                                                <span class="badge badge-success">Paid</span>
                                            @elseif ($invoice->paid_total > 0)
                                                <span class="badge badge-warning">Partial</span>
                                            @else
                                                <span class="badge badge-danger">Unpaid</span>
                                            @endif
                                        </td>
                                        <td>
                                            <button wire:click="view({{ $invoice->id }})" class="btn btn-outline-info btn-rounded"><i class="fas fa-eye"></i></button>
                                            <a href="{{ route('admin_invoice_print', $invoice->id) }}" target="_blank" class="btn btn-outline-success btn-rounded"><i class="fas fa-print"></i></a>
                                            <button wire:click="delete({{ $invoice->id }})" onclick="return confirm('Are You Sure?')" class="btn btn-outline-danger btn-rounded"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="10" class="text-warning">No invoices found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        {{ $invoices->links() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
