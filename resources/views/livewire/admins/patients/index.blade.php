<div>
    <div class="content">
        <div class="container">
            <div class="row page-title row">
                <div class="col">
                    <h3 class="text-info">{{ env('APP_NAME') }} Patients</h3>
                </div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                    <button class="btn btn-primary" wire:click="show_create_form">Add New</button>
                </div>
            </div>
            <div>
                @if (session()->has('message'))
                    <div class="alert alert-success">
                        {{ session('message') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif
            </div>
            <div class="box box-primary">
                <div class="box-body">
                    <div class="text-info" wire:loading>Loading..</div>
                    <hr>
                    <div class="text-capitalize bg-dark p-2 shadow mb-3 text-center text-lg text-light rounded">
                        {{ __('All  patients') }}</div>
                    <table width="100%" class="table table-hover" id="">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Dated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($patients as $patient)
                                <tr>
                                    <td>{{ $patient->name }}</td>
                                    <td>{{ $patient->email }}</td>
                                    <td>{{ $patient->age ?: 'Null' }}</td>
                                    <td>{{ $patient->gender ?: 'Null' }}</td>
                                    <td>{{ $patient->created_at }}</td>
                                    <td class="text-right">
                                        <button wire:click="show_edit_form({{ $patient->id }})"
                                            class="btn btn-outline-info btn-rounded"><i class="fas fa-pen"></i></button>
                                        <button wire:click="prompt_delete({{ $patient->id }})"
                                            data-toggle="modal" data-target="#confirmDeletePatientModal"
                                            class="btn btn-outline-danger btn-rounded"><i
                                                class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            @empty
                                <td class="text-warning">{{ __('Null') }}</td>
                                <td class="text-warning">{{ __('Null') }}</td>
                                <td class="text-warning">{{ __('Null') }}</td>
                                <td class="text-warning">{{ __('Null') }}</td>
                                <td class="text-warning">{{ __('Null') }}</td>
                                <td class="text-warning">{{ __('Null') }}</td>
                                <td class="text-warning">{{ __('Null') }}</td>
                                <td class="text-warning">{{ __('Null') }}</td>
                                <td class="text-warning">{{ __('Null') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $patients->links() }}
                </div>
            </div>
        </div>

        <div class="modal fade" id="confirmDeletePatientModal" tabindex="-1" role="dialog" aria-hidden="true" wire:ignore.self>
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Deletion</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p>Deleting a patient record cannot be undone. Re-enter your password to confirm.</p>
                        <div class="form-group">
                            <label>Your Password</label>
                            <input type="password" class="form-control" wire:model.lazy="confirm_delete_password">
                            @error('confirm_delete_password') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" wire:click="delete">Delete Patient</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            if (!window.__jfPatientDeleteConfirmedBound) {
                window.__jfPatientDeleteConfirmedBound = true;
                window.addEventListener('patient-delete-confirmed', function () {
                    $('#confirmDeletePatientModal').modal('hide');
                });
            }
        </script>
    </div>
</div>
