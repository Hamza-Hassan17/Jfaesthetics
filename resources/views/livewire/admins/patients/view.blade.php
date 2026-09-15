<div>
    <div class="content">
        <div class="container">
            <div class="row page-title align-items-center">
                <div class="col">
                    <h3 class="text-info">{{ env('APP_NAME') }} Patient Details</h3>
                </div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                    <button class="btn btn-secondary" wire:click="show_index">Back to List</button>
                </div>
            </div>

            <div class="box box-primary">
                <div class="box-body">
                    <div class="text-info" wire:loading>Loading..</div>

                    <div class="row">
                        <div class="col-md-3 text-center mb-3">
                            @if ($patient->photo_path)
                                <img src="{{ env('APP_URL') . 'storage/' . $patient->photo_path }}" alt="{{ $patient->name }}"
                                    class="img-fluid rounded" style="max-height: 200px;">
                            @else
                                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 160px;">
                                    <i class="fas fa-user fa-3x text-muted"></i>
                                </div>
                            @endif
                        </div>
                        <div class="col-md-9">
                            <dl class="row mb-0">
                                <dt class="col-sm-3">Name</dt>
                                <dd class="col-sm-9">{{ $patient->name }}</dd>

                                <dt class="col-sm-3">Email</dt>
                                <dd class="col-sm-9">{{ $patient->email ?: '-' }}</dd>

                                <dt class="col-sm-3">Phone</dt>
                                <dd class="col-sm-9">{{ $patient->phone ?: '-' }}</dd>

                                <dt class="col-sm-3">Age</dt>
                                <dd class="col-sm-9">{{ $patient->age ?: '-' }}</dd>

                                <dt class="col-sm-3">Gender</dt>
                                <dd class="col-sm-9">{{ $patient->gender ?: '-' }}</dd>

                                <dt class="col-sm-3">Blood Group</dt>
                                <dd class="col-sm-9">{{ $patient->bloodgroup ?: '-' }}</dd>

                                <dt class="col-sm-3">Address</dt>
                                <dd class="col-sm-9">{{ $patient->address ?: '-' }}</dd>

                                <dt class="col-sm-3">Registered On</dt>
                                <dd class="col-sm-9">{{ $patient->created_at->format('d M Y') }}</dd>
                            </dl>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-capitalize bg-dark p-2 shadow mb-3 text-center text-light rounded">Appointments ({{ $patient->appointments->count() }})</h6>
                            @forelse ($patient->appointments->sortByDesc('created_at')->take(5) as $appointment)
                                <div class="small mb-2">
                                    <strong>{{ $appointment->case_no ?: 'N/A' }}</strong> &middot;
                                    {{ $appointment->intime ? $appointment->intime->format('d M Y') : '-' }}
                                    &middot; {{ ucfirst(str_replace('_', ' ', $appointment->status ?? 'booked')) }}
                                </div>
                            @empty
                                <div class="small text-muted">No appointments yet.</div>
                            @endforelse
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-capitalize bg-dark p-2 shadow mb-3 text-center text-light rounded">Invoices ({{ $patient->invoices->count() }})</h6>
                            @forelse ($patient->invoices->sortByDesc('created_at')->take(5) as $invoice)
                                <div class="small mb-2">
                                    <strong>{{ $invoice->invoice_number }}</strong> &middot;
                                    {{ $invoice->created_at->format('d M Y') }}
                                </div>
                            @empty
                                <div class="small text-muted">No invoices yet.</div>
                            @endforelse
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-capitalize bg-dark p-2 shadow mb-3 text-center text-light rounded">Consultation Forms ({{ $patient->consultationForms->count() }})</h6>
                            @forelse ($patient->consultationForms->sortByDesc('created_at')->take(5) as $form)
                                <div class="small mb-2">
                                    <strong>{{ $form->consultation_for ?: 'Consultation' }}</strong> &middot;
                                    {{ optional($form->consultation_date)->format('d M Y') ?? '-' }}
                                </div>
                            @empty
                                <div class="small text-muted">No consultation forms yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
