<div>
    <div class="content">
        <div class="container">
            <div class="row page-title align-items-center">
                <div class="col">
                    <h3 class="text-info">{{ env('APP_NAME') }} Employee Details</h3>
                </div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                    <button class="btn btn-secondary" wire:click="show_index">Back to List</button>
                </div>
            </div>

            <div class="box box-primary">
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-3 text-center mb-3">
                            @if ($employee->image)
                                <img src="{{ asset('storage/' . $employee->image) }}" alt="{{ $employee->name }}"
                                    class="img-fluid rounded-circle" style="max-height: 200px;">
                            @else
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto" style="height: 160px; width: 160px;">
                                    <i class="fas fa-user fa-3x text-muted"></i>
                                </div>
                            @endif
                        </div>
                        <div class="col-md-9">
                            <dl class="row mb-0">
                                <dt class="col-sm-3">Name</dt>
                                <dd class="col-sm-9">{{ $employee->name }}</dd>

                                <dt class="col-sm-3">Email</dt>
                                <dd class="col-sm-9">{{ $employee->email ?: '-' }}</dd>

                                <dt class="col-sm-3">Phone</dt>
                                <dd class="col-sm-9">{{ $employee->phone ?: '-' }}</dd>

                                <dt class="col-sm-3">Position</dt>
                                <dd class="col-sm-9">{{ \Str::title($employee->position) }}</dd>

                                <dt class="col-sm-3">Qualification</dt>
                                <dd class="col-sm-9">{{ $employee->qualification ?: '-' }}</dd>

                                <dt class="col-sm-3">Gender</dt>
                                <dd class="col-sm-9">{{ $employee->gender ?: '-' }}</dd>

                                <dt class="col-sm-3">Salary</dt>
                                <dd class="col-sm-9">PKR {{ number_format($employee->salary, 2, '.', ',') }}</dd>

                                <dt class="col-sm-3">Status</dt>
                                <dd class="col-sm-9">{{ \Str::title($employee->status) }}</dd>

                                <dt class="col-sm-3">Address</dt>
                                <dd class="col-sm-9">{{ $employee->address ?: '-' }}</dd>

                                <dt class="col-sm-3">Joined On</dt>
                                <dd class="col-sm-9">{{ $employee->created_at->format('d M Y') }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
