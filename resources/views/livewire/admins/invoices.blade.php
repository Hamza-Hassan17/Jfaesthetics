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
        .jfr-icon-btn.edit { background: rgba(230, 140, 40, .1); color: #d47f1f; }
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
                            {{ $editing_invoice_id ? 'Edit Invoice' : 'Generate New Invoice' }}
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
                                <div class="input-group">
                                    <select wire:model="patient_id" class="form-control">
                                        <option value="">Choose Patient</option>
                                        @forelse ($patients as $patient)
                                            <option value="{{ $patient->id }}">{{ $patient->name }} (MR-{{ str_pad($patient->id, 5, '0', STR_PAD_LEFT) }})</option>
                                        @empty
                                            <option value="">No Patient Found!</option>
                                        @endforelse
                                    </select>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#quickAddPatientModal"><i class="fas fa-plus"></i> New Patient</button>
                                    </div>
                                </div>
                                @error('patient_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-group">
                                <label>Doctor</label>
                                <div class="input-group">
                                    <select wire:model="doctor_id" class="form-control">
                                        <option value="">Choose Doctor</option>
                                        @forelse ($doctors as $doc)
                                            <option value="{{ $doc->id }}">{{ $doc->employ->name ?? 'Unknown' }}</option>
                                        @empty
                                            <option value="">No Doctor Found!</option>
                                        @endforelse
                                    </select>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#quickAddDoctorModal"><i class="fas fa-plus"></i> New Doctor</button>
                                    </div>
                                </div>
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
                                            <th style="min-width: 90px;">Quantity</th>
                                            <th style="min-width: 80px;">Session</th>
                                            <th style="min-width: 120px;">Service Charges</th>
                                            <th style="min-width: 170px;">Discount</th>
                                            <th style="min-width: 100px;">Sub Total</th>
                                            <th style="min-width: 100px;">Discount Amt</th>
                                            <th style="min-width: 110px;">After Discount</th>
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
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($item['type'] === 'service')
                                                        <input type="text" class="form-control" value="" readonly style="background: #f4f6f6;" title="Not applicable for services — tracked by session instead.">
                                                    @else
                                                        <input type="number" min="1" class="form-control" wire:model.lazy="items.{{ $index }}.quantity">
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($item['type'] === 'medicine')
                                                        <input type="text" class="form-control" value="" readonly style="background: #f4f6f6;" title="Not applicable for medicines.">
                                                    @else
                                                        <select class="form-control" wire:model.lazy="items.{{ $index }}.session">
                                                            <option value="">-</option>
                                                            @for ($s = 1; $s <= 10; $s++)
                                                                <option value="{{ $s }}">{{ $s }}</option>
                                                            @endfor
                                                        </select>
                                                    @endif
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" class="form-control"
                                                        wire:model.lazy="items.{{ $index }}.service_charges"
                                                        @if ($item['type'] !== 'custom') readonly style="background: #f4f6f6;" title="Price comes from the catalog. Use Discount to adjust." @endif>
                                                </td>
                                                <td>
                                                    <div class="input-group">
                                                        <input type="number" step="0.01" min="0" class="form-control" wire:model.lazy="items.{{ $index }}.discount_value">
                                                        <select class="form-control" wire:model.lazy="items.{{ $index }}.discount_type" style="min-width: 70px; max-width: 100px;">
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
                                <button type="submit" class="btn btn-primary">{{ $editing_invoice_id ? 'Update Invoice' : 'Generate Invoice' }}</button>
                            </div>
                        </form>

                        <div class="modal fade" id="quickAddPatientModal" tabindex="-1" role="dialog" aria-hidden="true" wire:ignore.self>
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Quick Add Patient</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label>Name *</label>
                                            <input type="text" class="form-control" wire:model.lazy="quick_patient_name">
                                            @error('quick_patient_name') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Phone *</label>
                                            <input type="text" class="form-control" wire:model.lazy="quick_patient_phone">
                                            @error('quick_patient_phone') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Address</label>
                                            <input type="text" class="form-control" wire:model.lazy="quick_patient_address">
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label>Gender</label>
                                                <select class="form-control" wire:model.lazy="quick_patient_gender">
                                                    <option value="">-</option>
                                                    <option value="Male">Male</option>
                                                    <option value="Female">Female</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label>Age</label>
                                                <input type="number" min="0" class="form-control" wire:model.lazy="quick_patient_age">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Blood Group</label>
                                            <select class="form-control" wire:model.lazy="quick_patient_bloodgroup">
                                                <option value="">-</option>
                                                <option>A+</option>
                                                <option>A-</option>
                                                <option>B+</option>
                                                <option>B-</option>
                                                <option>O+</option>
                                                <option>O-</option>
                                                <option>AB+</option>
                                                <option>AB-</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary" wire:click="add_quick_patient">Save &amp; Select</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            if (!window.__jfPatientQuickAddedBound) {
                                window.__jfPatientQuickAddedBound = true;
                                window.addEventListener('patient-quick-added', function () {
                                    $('#quickAddPatientModal').modal('hide');
                                });
                            }
                        </script>

                        <div class="modal fade" id="quickAddDoctorModal" tabindex="-1" role="dialog" aria-hidden="true" wire:ignore.self>
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Quick Add Doctor</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label>Name *</label>
                                            <input type="text" class="form-control" wire:model.lazy="quick_doctor_name">
                                            @error('quick_doctor_name') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" class="form-control" wire:model.lazy="quick_doctor_email">
                                            @error('quick_doctor_email') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Phone *</label>
                                            <input type="text" class="form-control" wire:model.lazy="quick_doctor_phone">
                                            @error('quick_doctor_phone') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary" wire:click="add_quick_doctor">Save &amp; Select</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            if (!window.__jfDoctorQuickAddedBound) {
                                window.__jfDoctorQuickAddedBound = true;
                                window.addEventListener('doctor-quick-added', function () {
                                    $('#quickAddDoctorModal').modal('hide');
                                });
                            }
                        </script>
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
                                            <th>Patient Phone Number</th>
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
                                                <td>{{ $invoice->patient->phone ?? 'N/A' }}</td>
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
                                                    <button wire:click="edit_invoice({{ $invoice->id }})" class="jfr-icon-btn edit"><i class="fas fa-pen"></i></button>
                                                    <a href="{{ route('admin_invoice_print', $invoice->id) }}" target="_blank" class="jfr-icon-btn print"><i class="fas fa-print"></i></a>
                                                    <button wire:click="prompt_delete({{ $invoice->id }})" data-toggle="modal" data-target="#confirmDeleteInvoiceModal" class="jfr-icon-btn delete"><i class="fas fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="10" class="text-warning">No invoices generated yet.</td></tr>
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

                    <div class="modal fade" id="confirmDeleteInvoiceModal" tabindex="-1" role="dialog" aria-hidden="true" wire:ignore.self>
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Confirm Deletion</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <p>Deleting an invoice cannot be undone. Re-enter your password to confirm.</p>
                                    <div class="form-group">
                                        <label>Your Password</label>
                                        <input type="password" class="form-control" wire:model.lazy="confirm_delete_password">
                                        @error('confirm_delete_password') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-danger" wire:click="delete">Delete Invoice</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                        if (!window.__jfInvoiceDeleteConfirmedBound) {
                            window.__jfInvoiceDeleteConfirmedBound = true;
                            window.addEventListener('invoice-delete-confirmed', function () {
                                $('#confirmDeleteInvoiceModal').modal('hide');
                            });
                        }
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>
