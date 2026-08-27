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
        .cf-check-group label {
            font-weight: normal;
            margin-right: 18px;
            margin-bottom: 6px;
        }
        .cf-view dt { color: #7a8a8a; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; margin-top: 14px; }
        .cf-view dd { font-size: 15px; margin-bottom: 0; }
    </style>
    <div class="content">
        <div class="container">
            <div class="row page-title row">
                <div class="col">
                    <h3 class="jfr-title">{{ env('APP_NAME') }}
                        @if ($_page === 'index')
                            Consultation Forms
                        @elseif ($_page === 'create')
                            {{ $editing_id ? 'Edit Consultation Form' : 'New Consultation Form' }}
                        @else
                            Consultation Form Details
                        @endif
                    </h3>
                </div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                    @if ($_page !== 'create')
                        <button class="btn btn-jfr-teal" wire:click="show_create_form">New Consultation Form</button>
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
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" class="form-control" wire:model.lazy="consultation_date" style="max-width: 220px;">
                                @error('consultation_date') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                            </div>

                            <h5 class="mt-4">Patient Details</h5>
                            <hr class="mt-1">
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

                            <h5 class="mt-4">Consultation For</h5>
                            <hr class="mt-1">
                            <div class="form-group cf-check-group">
                                @foreach (\App\Http\Livewire\Admins\ConsultationForms::CONSULTATION_FOR_OPTIONS as $option)
                                    <label>
                                        <input type="checkbox" wire:model="consultation_for" value="{{ $option }}"> {{ $option }}
                                    </label>
                                @endforeach
                            </div>

                            <h5 class="mt-4">Medical History</h5>
                            <hr class="mt-1">
                            <div class="form-group cf-check-group">
                                @foreach (\App\Http\Livewire\Admins\ConsultationForms::MEDICAL_HISTORY_OPTIONS as $option)
                                    <label>
                                        <input type="checkbox" wire:model="medical_history" value="{{ $option }}"> {{ $option }}
                                    </label>
                                @endforeach
                            </div>
                            @if (in_array('Other', $medical_history))
                                <div class="form-group" style="max-width: 400px;">
                                    <input type="text" class="form-control" wire:model.lazy="medical_history_other" placeholder="Please specify">
                                </div>
                            @endif

                            <h5 class="mt-4">For Female Patients</h5>
                            <hr class="mt-1">
                            <div class="form-group cf-check-group">
                                @foreach (\App\Http\Livewire\Admins\ConsultationForms::FEMALE_STATUS_OPTIONS as $option)
                                    <label>
                                        <input type="checkbox" wire:model="female_status" value="{{ $option }}"> {{ $option }}
                                    </label>
                                @endforeach
                            </div>

                            <h5 class="mt-4">Declaration</h5>
                            <hr class="mt-1">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" wire:model="declaration_confirmed">
                                    I confirm that the information provided above is true and complete to the best of my knowledge.
                                </label>
                            </div>
                            <div class="form-group" style="max-width: 400px;">
                                <label>Patient Signature (typed full name)</label>
                                <input type="text" class="form-control" wire:model.lazy="patient_signature_name">
                            </div>
                            <div class="form-group" style="max-width: 400px;">
                                <label>Consultant</label>
                                <select wire:model="consultant_id" class="form-control">
                                    <option value="">Choose Consultant</option>
                                    @forelse ($doctors as $doc)
                                        <option value="{{ $doc->id }}">{{ $doc->employ->name ?? 'Unknown' }}</option>
                                    @empty
                                        <option value="">No Doctor Found!</option>
                                    @endforelse
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Recommended Treatment</label>
                                <textarea class="form-control" wire:model.lazy="recommended_treatment" rows="3"></textarea>
                            </div>

                            <h5 class="mt-4">Notes</h5>
                            <hr class="mt-1">
                            <div class="form-group">
                                <textarea class="form-control" wire:model.lazy="notes" rows="3" placeholder="Any additional notes"></textarea>
                            </div>

                            <div class="form-group mt-3">
                                <button type="submit" class="btn btn-primary">{{ $editing_id ? 'Update Form' : 'Save Consultation Form' }}</button>
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
                    @endif

                    {{-- ============ VIEW ============ --}}
                    @if ($_page === 'view' && isset($form))
                        <div class="cf-view">
                            <div class="d-flex justify-content-between align-items-start">
                                <dl class="w-100">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <dt>Patient</dt>
                                            <dd>{{ $form->patient->name ?? 'N/A' }}</dd>
                                        </div>
                                        <div class="col-md-4">
                                            <dt>Phone</dt>
                                            <dd>{{ $form->patient->phone ?? 'N/A' }}</dd>
                                        </div>
                                        <div class="col-md-4">
                                            <dt>Date</dt>
                                            <dd>{{ optional($form->consultation_date)->format('d M Y') }}</dd>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <dt>Age</dt>
                                            <dd>{{ $form->patient->age ?? 'N/A' }}</dd>
                                        </div>
                                        <div class="col-md-4">
                                            <dt>Gender</dt>
                                            <dd>{{ $form->patient->gender ?? 'N/A' }}</dd>
                                        </div>
                                        <div class="col-md-4">
                                            <dt>Consultant</dt>
                                            <dd>{{ $form->consultant->employ->name ?? 'N/A' }}</dd>
                                        </div>
                                    </div>
                                    <dt>Consultation For</dt>
                                    <dd>{{ !empty($form->consultation_for) ? implode(', ', $form->consultation_for) : '-' }}</dd>

                                    <dt>Medical History</dt>
                                    <dd>
                                        {{ !empty($form->medical_history) ? implode(', ', $form->medical_history) : '-' }}
                                        @if ($form->medical_history_other) ({{ $form->medical_history_other }}) @endif
                                    </dd>

                                    <dt>For Female Patients</dt>
                                    <dd>{{ !empty($form->female_status) ? implode(', ', $form->female_status) : '-' }}</dd>

                                    <dt>Declaration</dt>
                                    <dd>{{ $form->declaration_confirmed ? 'Confirmed' : 'Not confirmed' }}</dd>

                                    <dt>Patient Signature</dt>
                                    <dd>{{ $form->patient_signature_name ?: '-' }}</dd>

                                    <dt>Recommended Treatment</dt>
                                    <dd>{{ $form->recommended_treatment ?: '-' }}</dd>

                                    <dt>Notes</dt>
                                    <dd>{{ $form->notes ?: '-' }}</dd>
                                </dl>
                            </div>
                            <div class="form-group mt-4">
                                <button class="btn btn-jfr-teal" wire:click="edit({{ $form->id }})"><i class="fas fa-pen"></i> Edit</button>
                                <a href="{{ route('admin_consultation_form_print', $form->id) }}" target="_blank" class="btn btn-success"><i class="fas fa-print"></i> Print / Download A4</a>
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
                                        <th>Consultant</th>
                                        <th>Consultation For</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($forms as $item)
                                        <tr>
                                            <td>{{ $item->patient->name ?? 'N/A' }}</td>
                                            <td>{{ $item->consultant->employ->name ?? '-' }}</td>
                                            <td>{{ !empty($item->consultation_for) ? implode(', ', $item->consultation_for) : '-' }}</td>
                                            <td>{{ optional($item->consultation_date)->format('d M Y') }}</td>
                                            <td>
                                                <button wire:click="view({{ $item->id }})" class="jfr-icon-btn view"><i class="fas fa-eye"></i></button>
                                                <button wire:click="edit({{ $item->id }})" class="jfr-icon-btn edit"><i class="fas fa-pen"></i></button>
                                                <a href="{{ route('admin_consultation_form_print', $item->id) }}" target="_blank" class="jfr-icon-btn print"><i class="fas fa-print"></i></a>
                                                <button wire:click="delete({{ $item->id }})" onclick="return confirm('Are You Sure?')" class="jfr-icon-btn delete"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5">No consultation forms found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="jfr-entries-info">
                            {{ $forms->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
