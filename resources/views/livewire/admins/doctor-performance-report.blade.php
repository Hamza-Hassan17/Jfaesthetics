<div>
    <div class="content">
        <div class="container">
            <div class="row page-title row">
                <div class="col">
                    <h3 class="text-info">{{ env('APP_NAME') }} Doctor Performance Report</h3>
                </div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                </div>
            </div>

            <div class="box box-primary">
                <div class="box-body">
                    <div class="text-info" wire:loading>Loading..</div>

                    <div class="btn-group mb-3" role="group">
                        <button type="button" wire:click="setPeriod('today')" class="btn btn-sm {{ $period === 'today' ? 'btn-dark' : 'btn-outline-dark' }}">Today</button>
                        <button type="button" wire:click="setPeriod('week')" class="btn btn-sm {{ $period === 'week' ? 'btn-dark' : 'btn-outline-dark' }}">Last 7 Days</button>
                        <button type="button" wire:click="setPeriod('month')" class="btn btn-sm {{ $period === 'month' ? 'btn-dark' : 'btn-outline-dark' }}">This Month</button>
                        <button type="button" wire:click="setPeriod('year')" class="btn btn-sm {{ $period === 'year' ? 'btn-dark' : 'btn-outline-dark' }}">This Year</button>
                    </div>

                    <form wire:submit.prevent="$refresh" class="form-inline mb-4">
                        <div class="form-group mr-2">
                            <label class="mr-2">From</label>
                            <input type="date" wire:model="from" class="form-control">
                        </div>
                        <div class="form-group mr-2">
                            <label class="mr-2">To</label>
                            <input type="date" wire:model="to" class="form-control">
                        </div>
                        <div class="form-group mr-2">
                            <label class="mr-2">Doctor</label>
                            <select wire:model="doctor_id" class="form-control">
                                <option value="">All Doctors</option>
                                @foreach ($doctors as $doc)
                                    <option value="{{ $doc->id }}">{{ $doc->employ->name ?? 'Unknown' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Apply Filter</button>
                    </form>

                    @forelse ($report as $row)
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">{{ $row['doctor_name'] }}</h5>
                                <div>
                                    <span class="badge badge-info mr-2">{{ $row['services_count'] }} services sold</span>
                                    <span class="badge badge-secondary">{{ $row['appointments_count'] }} appointments</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Services Sold</h6>
                                        @if ($row['service_lines']->isEmpty())
                                            <p class="text-warning">No services sold in this period.</p>
                                        @else
                                            <table class="table table-sm table-bordered">
                                                <thead>
                                                    <tr><th>Service</th><th>Patient</th><th>Qty</th></tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($row['service_lines'] as $line)
                                                        <tr>
                                                            <td>{{ $line['service'] }}</td>
                                                            <td>{{ $line['patient_name'] }}</td>
                                                            <td>{{ $line['quantity'] }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @endif
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Appointments</h6>
                                        @if ($row['appointments']->isEmpty())
                                            <p class="text-warning">No appointments in this period.</p>
                                        @else
                                            <table class="table table-sm table-bordered">
                                                <thead>
                                                    <tr><th>Patient</th><th>Date</th></tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($row['appointments'] as $appt)
                                                        <tr>
                                                            <td>{{ $appt['patient_name'] }}</td>
                                                            <td>{{ \Carbon\Carbon::parse($appt['date'])->format('d M Y') }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-warning">No doctors found.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
