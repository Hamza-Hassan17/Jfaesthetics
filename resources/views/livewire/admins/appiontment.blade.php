<div>
    <style>
        .jfr-title { color: #0a3535; font-weight: 800; }
        .btn-jfr-teal {
            background: #148080;
            border-color: #148080;
            color: #fff;
        }
        .btn-jfr-teal:hover { background: #0d5c5c; border-color: #0d5c5c; color: #fff; }
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
        .cf-view dt { color: #7a8a8a; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; margin-top: 14px; }
        .cf-view dd { font-size: 15px; margin-bottom: 0; }
    </style>
    <div class="content">
        <div class="container">
            <div class="row page-title row">
                <div class="col">
                    <h3 class="jfr-title">{{ env('APP_NAME') }}
                        @if ($_page === 'index')
                            Appointments
                        @elseif ($_page === 'create')
                            {{ $editing_id ? 'Edit Appointment' : 'New Appointment' }}
                        @else
                            Appointment Details
                        @endif
                    </h3>
                </div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                    @if ($_page !== 'create')
                        <button class="btn btn-jfr-teal" wire:click="show_create_form">New Appointment</button>
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

                    {{-- ============ CREATE / EDIT ============ --}}
                    @if ($_page === 'create')
                        <form wire:submit.prevent="save">
                            <div class="form-row">
                                <div class="form-group col-md-8">
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
                                <div class="form-group col-md-4">
                                    <label>Case No.</label>
                                    <input type="text" class="form-control" wire:model.lazy="case_no" placeholder="e.g. C-0001">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Appointment Date</label>
                                    <input type="datetime-local" class="form-control" wire:model.lazy="intime">
                                    @error('intime') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group col-md-4">
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
                                <div class="form-group col-md-4">
                                    <label>Prep Nurse (optional)</label>
                                    <select wire:model="nurse_id" class="form-control">
                                        <option value="">Choose Nurse</option>
                                        @forelse ($nurses as $nurse)
                                            <option value="{{ $nurse->id }}">{{ $nurse->name }}</option>
                                        @empty
                                            <option value="">No Nurse Found!</option>
                                        @endforelse
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Age</label>
                                    <input type="number" min="0" class="form-control" wire:model.lazy="age">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Location</label>
                                    <input type="text" class="form-control" wire:model.lazy="location" placeholder="e.g. Main Clinic - DHA Phase 5">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>End Time (optional)</label>
                                    <input type="datetime-local" class="form-control" wire:model.lazy="outtime">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Purpose of Visit</label>
                                <textarea class="form-control" wire:model.lazy="description" rows="2" placeholder="Reason for this appointment"></textarea>
                                @error('description') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-group">
                                <label>Notes / Rx</label>
                                <textarea class="form-control" wire:model.lazy="prescription" rows="5" placeholder="Consultation notes / prescription"></textarea>
                            </div>

                            <div class="form-group mt-3">
                                <button type="submit" class="btn btn-primary">{{ $editing_id ? 'Update Appointment' : 'Save Appointment' }}</button>
                            </div>
                        </form>

                        <div class="modal fade" id="quickAddPatientModal" tabindex="-1" role="dialog" aria-hidden="true">
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
                    @endif

                    {{-- ============ VIEW ============ --}}
                    @if ($_page === 'view' && isset($appointment))
                        <div class="cf-view">
                            <dl class="w-100">
                                <div class="row">
                                    <div class="col-md-3">
                                        <dt>Patient</dt>
                                        <dd>{{ $appointment->patient->name ?? 'N/A' }}</dd>
                                    </div>
                                    <div class="col-md-3">
                                        <dt>Case No.</dt>
                                        <dd>{{ $appointment->case_no ?: '-' }}</dd>
                                    </div>
                                    <div class="col-md-3">
                                        <dt>Age</dt>
                                        <dd>{{ $appointment->age ?: ($appointment->patient->age ?? 'N/A') }}</dd>
                                    </div>
                                    <div class="col-md-3">
                                        <dt>Phone</dt>
                                        <dd>{{ $appointment->patient->phone ?? 'N/A' }}</dd>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <dt>Appointment Date</dt>
                                        <dd>{{ $appointment->intime->format('d M Y h:i A') }}</dd>
                                    </div>
                                    <div class="col-md-3">
                                        <dt>Doctor</dt>
                                        <dd>{{ $appointment->doctor->employ->name ?? 'N/A' }}</dd>
                                    </div>
                                    <div class="col-md-3">
                                        <dt>Prep Nurse</dt>
                                        <dd>{{ $appointment->nurse->name ?? '-' }}</dd>
                                    </div>
                                    <div class="col-md-3">
                                        <dt>Location</dt>
                                        <dd>{{ $appointment->location ?: '-' }}</dd>
                                    </div>
                                </div>
                                <dt>Purpose of Visit</dt>
                                <dd>{{ $appointment->description ?: '-' }}</dd>

                                <dt>Notes / Rx</dt>
                                <dd>{{ $appointment->prescription ?: '-' }}</dd>
                            </dl>
                            <div class="form-group mt-4">
                                <button class="btn btn-jfr-teal" wire:click="edit({{ $appointment->id }})"><i class="fas fa-pen"></i> Edit</button>
                                <a href="{{ route('admin_appointment_print', $appointment->id) }}" target="_blank" class="btn btn-success"><i class="fas fa-print"></i> Print / Download A4</a>
                            </div>
                        </div>
                    @endif

                    {{-- ============ INDEX ============ --}}
                    @if ($_page === 'index')
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Case No.</th>
                                        <th>Doctor</th>
                                        <th>Appointment Date</th>
                                        <th>Purpose</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($appointments as $item)
                                        <tr>
                                            <td>{{ $item->patient->name ?? 'N/A' }}</td>
                                            <td>{{ $item->case_no ?: '-' }}</td>
                                            <td>{{ $item->doctor->employ->name ?? 'N/A' }}</td>
                                            <td>{{ $item->intime ? $item->intime->format('d M Y h:i A') : '-' }}</td>
                                            <td>{{ \Illuminate\Support\Str::limit($item->description, 40) }}</td>
                                            <td>
                                                <button wire:click="view({{ $item->id }})" class="jfr-icon-btn view"><i class="fas fa-eye"></i></button>
                                                <button wire:click="edit({{ $item->id }})" class="jfr-icon-btn edit"><i class="fas fa-pen"></i></button>
                                                <a href="{{ route('admin_appointment_print', $item->id) }}" target="_blank" class="jfr-icon-btn print"><i class="fas fa-print"></i></a>
                                                <button wire:click="delete({{ $item->id }})" onclick="return confirm('Are You Sure?')" class="jfr-icon-btn delete"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6">No appointments found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="jfr-entries-info">
                            {{ $appointments->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
