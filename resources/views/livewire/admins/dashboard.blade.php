<div class="jf-dashboard">
    <style>
        .jf-dashboard { color: #333; }
        .jf-dashboard .jf-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
            padding: 20px;
            height: 100%;
        }
        .jf-dashboard .jf-top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .jf-dashboard .jf-welcome { color: #7b6fd6; font-weight: 700; letter-spacing: .5px; margin: 0; }
        .jf-dashboard .jf-period-tabs { background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,.06); }
        .jf-dashboard .jf-period-tabs button {
            border: none; background: #fff; padding: 8px 16px; font-size: 13px; color: #555; cursor: pointer;
        }
        .jf-dashboard .jf-period-tabs button.active { background: #4a4a68; color: #fff; }
        .jf-dashboard .jf-stat-icon { font-size: 26px; margin-bottom: 8px; }
        .jf-dashboard .jf-stat-label { color: #888; font-size: 14px; margin-bottom: 4px; }
        .jf-dashboard .jf-stat-value { font-size: 26px; font-weight: 700; }
        .jf-dashboard .jf-card h5 { font-weight: 700; font-size: 16px; margin-bottom: 15px; }
        .jf-dashboard .badge-jf { background: #4a4a68; color: #fff; font-size: 11px; padding: 3px 10px; border-radius: 20px; }
        .jf-dashboard table th { color: #888; font-weight: 600; font-size: 12px; text-transform: uppercase; border-top: none; }
        .jf-dashboard table td { font-size: 13px; vertical-align: middle; }
        .jf-dashboard .nav-tabs .nav-link { color: #888; border: none; }
        .jf-dashboard .nav-tabs .nav-link.active { color: #4a4a68; font-weight: 700; border-bottom: 2px solid #4a4a68; }
        .jf-dashboard .status-completed { background: #2ecc71; color: #fff; font-size: 11px; padding: 3px 10px; border-radius: 20px; }
        .jf-dashboard .status-partial { background: #f39c12; color: #fff; font-size: 11px; padding: 3px 10px; border-radius: 20px; }
        .jf-dashboard .status-unpaid { background: #e74c3c; color: #fff; font-size: 11px; padding: 3px 10px; border-radius: 20px; }
    </style>

    <div class="jf-top-bar">
        <h4 class="jf-welcome">WELCOME ADMIN</h4>
        <div class="jf-period-tabs">
            <button wire:click="setPeriod('today')" class="{{ $period === 'today' ? 'active' : '' }}">Today</button>
            <button wire:click="setPeriod('week')" class="{{ $period === 'week' ? 'active' : '' }}">Last 7 Days</button>
            <button wire:click="setPeriod('month')" class="{{ $period === 'month' ? 'active' : '' }}">This Month</button>
            <button wire:click="setPeriod('year')" class="{{ $period === 'year' ? 'active' : '' }}">This Year</button>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="jf-card text-center">
                <div class="jf-stat-icon text-primary"><i class="fas fa-chart-bar"></i></div>
                <div class="jf-stat-label">Revenue</div>
                <div class="jf-stat-value">{{ number_format($revenue, 2) }}</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="jf-card text-center">
                <div class="jf-stat-icon text-danger"><i class="fas fa-file-invoice-dollar"></i></div>
                <div class="jf-stat-label">Outstanding</div>
                <div class="jf-stat-value">{{ number_format($outstanding, 2) }}</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="jf-card text-center">
                <div class="jf-stat-icon text-success"><i class="fas fa-user-plus"></i></div>
                <div class="jf-stat-label">New Patients</div>
                <div class="jf-stat-value">{{ $newPatients }}</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="jf-card text-center">
                <div class="jf-stat-icon text-warning"><i class="fas fa-calendar-check"></i></div>
                <div class="jf-stat-label">Appointment Requests</div>
                <div class="jf-stat-value">{{ $appointments }}</div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8 mb-3">
            <div class="jf-card" wire:ignore>
                <h5>Cash Flow</h5>
                <canvas id="jfCashFlowChart" height="90"></canvas>
                <script>
                    new Chart(document.getElementById('jfCashFlowChart').getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: @json($cashFlowLabels),
                            datasets: [
                                {
                                    label: 'Invoiced',
                                    data: @json($cashFlowInvoiced),
                                    borderColor: '#7b6fd6',
                                    backgroundColor: 'rgba(123,111,214,0.08)',
                                    fill: true,
                                    tension: 0.35,
                                },
                                {
                                    label: 'Collected',
                                    data: @json($cashFlowCollected),
                                    borderColor: '#fd8a4b',
                                    backgroundColor: 'rgba(253,138,75,0.08)',
                                    fill: true,
                                    tension: 0.35,
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            legend: { position: 'top', align: 'end' },
                            scales: {
                                yAxes: [{ ticks: { beginAtZero: true } }],
                            },
                        },
                    });
                </script>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="jf-card" wire:ignore>
                <h5>{{ now()->format('F Y') }}</h5>
                <canvas id="jfDonutChart" height="220"></canvas>
                <script>
                    new Chart(document.getElementById('jfDonutChart').getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: ['Collected', 'Outstanding'],
                            datasets: [{
                                data: [{{ $monthCollected }}, {{ $monthOutstanding }}],
                                backgroundColor: ['#7b6fd6', '#fd8a4b'],
                            }],
                        },
                        options: {
                            responsive: true,
                            legend: { position: 'top', align: 'start' },
                        },
                    });
                </script>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="jf-card" wire:ignore>
                <h5>Yearly Report ({{ now()->year }})</h5>
                <canvas id="jfYearlyChart" height="70"></canvas>
                <script>
                    new Chart(document.getElementById('jfYearlyChart').getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: ['January','February','March','April','May','June','July','August','September','October','November','December'],
                            datasets: [
                                {
                                    label: 'Invoiced Amount',
                                    data: @json($yearlyInvoiced),
                                    backgroundColor: '#7b6fd6',
                                },
                                {
                                    label: 'Collected Amount',
                                    data: @json($yearlyCollected),
                                    backgroundColor: '#fd8a4b',
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            legend: { position: 'top', align: 'end' },
                            scales: {
                                yAxes: [{ ticks: { beginAtZero: true } }],
                            },
                        },
                    });
                </script>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-7 mb-3">
            <div class="jf-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Recent Transactions</h5>
                    <span class="badge-jf">Latest 5</span>
                </div>
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#jfInvoices">Invoices</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#jfAppointments">Appointments</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#jfPayments">Payments</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="jfInvoices">
                        <div class="table-responsive">
                            <table class="table">
                                <thead><tr><th>Date</th><th>Invoice #</th><th>Patient</th><th>Status</th><th>Total</th></tr></thead>
                                <tbody>
                                    @forelse ($recentInvoices as $invoice)
                                        <tr>
                                            <td>{{ $invoice->created_at->format('d M Y') }}</td>
                                            <td>{{ $invoice->invoice_number }}</td>
                                            <td>{{ $invoice->patient->name ?? 'N/A' }}</td>
                                            <td>
                                                @if ($invoice->unpaid_total <= 0)
                                                    <span class="status-completed">Paid</span>
                                                @elseif ($invoice->paid_total > 0)
                                                    <span class="status-partial">Partial</span>
                                                @else
                                                    <span class="status-unpaid">Unpaid</span>
                                                @endif
                                            </td>
                                            <td>{{ number_format($invoice->grand_total, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-muted">No invoices yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="jfAppointments">
                        <div class="table-responsive">
                            <table class="table">
                                <thead><tr><th>Date</th><th>Name</th><th>Doctor</th><th>Scheduled</th></tr></thead>
                                <tbody>
                                    @forelse ($recentAppointments as $appt)
                                        <tr>
                                            <td>{{ $appt->created_at->format('d M Y') }}</td>
                                            <td>{{ $appt->name }}</td>
                                            <td>{{ $appt->doctor->employ->name ?? 'N/A' }}</td>
                                            <td>{{ \Carbon\Carbon::parse($appt->stime)->format('d M Y h:i A') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-muted">No appointment requests yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="jfPayments">
                        <div class="table-responsive">
                            <table class="table">
                                <thead><tr><th>Date</th><th>Invoice #</th><th>Patient</th><th>Mode</th><th>Amount</th></tr></thead>
                                <tbody>
                                    @forelse ($recentPayments as $payment)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($payment->paid_on)->format('d M Y') }}</td>
                                            <td>{{ $payment->invoice->invoice_number ?? 'N/A' }}</td>
                                            <td>{{ $payment->invoice->patient->name ?? 'N/A' }}</td>
                                            <td>{{ $payment->payment_mode }}</td>
                                            <td>{{ number_format($payment->amount, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-muted">No payments recorded yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-5 mb-3">
            <div class="jf-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Top Doctors</h5>
                    <a href="{{ route('admin_doctor_performance_report') }}" class="badge-jf">View Report</a>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>#</th><th>Doctor</th><th>Services</th><th>Appointments</th></tr></thead>
                        <tbody>
                            @forelse ($topDoctors as $i => $doc)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $doc['name'] }}</td>
                                    <td>{{ $doc['services'] }}</td>
                                    <td>{{ $doc['appointments'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">No data yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5 mb-3">
            <div class="jf-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Top Services ({{ now()->format('F') }})</h5>
                    <span class="badge-jf">Top 5</span>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>#</th><th>Service</th><th>Qty</th></tr></thead>
                        <tbody>
                            @forelse ($topServicesThisMonth as $i => $service)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $service->service }}</td>
                                    <td>{{ $service->qty }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">No data yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="jf-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Top Services {{ now()->year }} (Qty)</h5>
                    <span class="badge-jf">Top 5</span>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>#</th><th>Service</th><th>Qty</th></tr></thead>
                        <tbody>
                            @forelse ($topServicesByQtyThisYear as $i => $service)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $service->service }}</td>
                                    <td>{{ $service->qty }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">No data yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="jf-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Top Services {{ now()->year }} (Revenue)</h5>
                    <span class="badge-jf">Top 5</span>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>#</th><th>Service</th><th>Revenue</th></tr></thead>
                        <tbody>
                            @forelse ($topServicesByRevenueThisYear as $i => $service)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $service->service }}</td>
                                    <td>{{ number_format($service->revenue, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">No data yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="jf-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Low Stock</h5>
                    <span class="badge-jf">Medicine Store</span>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Medicine</th><th>Quantity</th><th>Threshold</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($lowStockMedicines as $medicine)
                                <tr>
                                    <td>{{ $medicine->name ?: 'Medicine #' . $medicine->id . ' (needs a name)' }}</td>
                                    <td class="text-danger font-weight-bold">{{ $medicine->quantity }}</td>
                                    <td>{{ $medicine->low_stock_threshold }}</td>
                                    <td><a href="{{ route('medicinesStore') }}" class="btn btn-outline-success btn-sm">Receive Stock</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">Nothing running low.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
