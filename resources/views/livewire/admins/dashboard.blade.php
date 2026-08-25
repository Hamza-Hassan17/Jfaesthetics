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

        /* ---- New JF Aesthetics dashboard header / cards ---- */
        .jfd-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 14px;
        }
        .jfd-header h2 { font-weight: 800; color: #0a3535; margin-bottom: 4px; font-size: 22px; }
        .jfd-header .sub { color: #64777a; font-size: 14px; margin-bottom: 0; }
        .jfd-date-box {
            background: #fff;
            border: 1px solid #e9eef0;
            border-radius: 12px;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 6px 18px rgba(10, 53, 53, 0.05);
        }
        .jfd-date-box i { color: #148080; font-size: 18px; }
        .jfd-date-box .day { font-weight: 700; font-size: 13.5px; color: #0a3535; }
        .jfd-date-box .time { font-size: 12px; color: #97a5a5; }

        .jfd-stat-card {
            background: #fff;
            border: 1px solid #e9eef0;
            border-radius: 14px;
            padding: 14px 18px;
            height: 100%;
            box-shadow: 0 6px 18px rgba(10, 53, 53, 0.05);
        }
        .jfd-stat-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; margin-bottom: 8px;
        }
        .jfd-stat-icon.blue { background: rgba(33, 150, 243, .12); color: #2196F3; }
        .jfd-stat-icon.green { background: rgba(56, 176, 105, .12); color: #38b069; }
        .jfd-stat-icon.purple { background: rgba(148, 92, 214, .12); color: #945ad6; }
        .jfd-stat-icon.orange { background: rgba(230, 140, 40, .12); color: #e68c28; }
        .jfd-stat-icon.teal { background: rgba(20, 128, 128, .12); color: #148080; }
        .jfd-stat-label { color: #7a8a8a; font-size: 12.5px; margin-bottom: 4px; }
        .jfd-stat-value { color: #0a3535; font-weight: 800; font-size: 21px; margin-bottom: 6px; }
        .jfd-trend { font-size: 12px; font-weight: 700; }
        .jfd-trend.good { color: #2f9c5c; }
        .jfd-trend.bad { color: #d43f3f; }
        .jfd-trend .muted { color: #97a5a5; font-weight: 400; }
        .jfd-appt-split { font-size: 12px; color: #97a5a5; }

        .jfd-card {
            background: #fff;
            border: 1px solid #e9eef0;
            border-radius: 14px;
            padding: 16px;
            height: 100%;
            box-shadow: 0 6px 18px rgba(10, 53, 53, 0.05);
        }
        .jfd-card h5 { font-weight: 800; color: #0a3535; font-size: 15px; margin-bottom: 0; }
        .jfd-card .jfd-card-head {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;
        }
        .jfd-range-dropdown .btn {
            font-size: 12px; border: 1px solid #e6ecec; background: #fff; color: #4a5a5a;
            border-radius: 20px; padding: 4px 14px;
        }
        .jfd-view-all { font-size: 12px; font-weight: 700; color: #148080; text-decoration: none; }
        .jfd-view-all:hover { color: #0d5c5c; }

        .jfd-doctor-row { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .jfd-doctor-row:last-child { margin-bottom: 0; }
        .jfd-doctor-avatar {
            width: 42px; height: 42px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
            background: rgba(20, 128, 128, .12); color: #148080; display: flex; align-items: center;
            justify-content: center; font-weight: 700; font-size: 14px;
        }
        .jfd-doctor-name { font-weight: 700; font-size: 13.5px; color: #0a3535; }
        .jfd-doctor-meta { font-size: 11.5px; color: #97a5a5; }
        .jfd-doctor-revenue { font-weight: 800; font-size: 13.5px; color: #0a3535; text-align: right; }

        .jfd-appt-row { display: flex; align-items: flex-start; gap: 12px; padding: 7px 0; border-bottom: 1px dashed #eef2f2; }
        .jfd-appt-row:last-child { border-bottom: none; }
        .jfd-appt-time { font-size: 12px; font-weight: 700; color: #148080; width: 66px; flex-shrink: 0; }
        .jfd-appt-avatar {
            width: 34px; height: 34px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
            background: rgba(148, 92, 214, .12); color: #945ad6; display: flex; align-items: center;
            justify-content: center; font-weight: 700; font-size: 12px;
        }
        .jfd-appt-name { font-weight: 700; font-size: 13px; color: #0a3535; }
        .jfd-appt-doctor { font-size: 11.5px; color: #97a5a5; }
        .jfd-appt-type {
            font-size: 10.5px; font-weight: 700; background: rgba(20, 128, 128, .1); color: #148080;
            padding: 3px 10px; border-radius: 20px; white-space: nowrap;
        }

        .jfd-quick-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .jfd-quick-action {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 6px; padding: 13px 10px; border-radius: 12px; text-decoration: none; text-align: center;
            font-size: 12.5px; font-weight: 700; transition: transform .15s ease;
        }
        .jfd-quick-action:hover { transform: translateY(-2px); text-decoration: none; }
        .jfd-quick-action i { font-size: 20px; }
        .jfd-quick-action.blue { background: rgba(33, 150, 243, .1); color: #2196F3; }
        .jfd-quick-action.green { background: rgba(56, 176, 105, .1); color: #38b069; }
        .jfd-quick-action.orange { background: rgba(230, 140, 40, .1); color: #e68c28; }
        .jfd-quick-action.purple { background: rgba(148, 92, 214, .1); color: #945ad6; }
    </style>

    <div class="jfd-header">
        <div>
            <h2>&#128075; Welcome Back, Admin!</h2>
            <p class="sub">Here's what's happening at {{ env('APP_NAME') }} today.</p>
        </div>
        <div class="jfd-date-box">
            <i class="fas fa-calendar-alt"></i>
            <div>
                <div class="day">{{ now()->format('l, d M Y') }}</div>
                <div class="time">{{ now()->format('h:i A') }}</div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4 col-xl mb-3">
            <div class="jfd-stat-card">
                <div class="jfd-stat-icon blue"><i class="fas fa-chart-bar"></i></div>
                <div class="jfd-stat-label">Total Revenue</div>
                <div class="jfd-stat-value">Rs. {{ number_format($revenueThisMonth, 0) }}</div>
                @php $revenueGood = $revenueTrend >= 0; @endphp
                <div class="jfd-trend {{ $revenueGood ? 'good' : 'bad' }}">
                    <i class="fas fa-arrow-{{ $revenueTrend >= 0 ? 'up' : 'down' }}"></i> {{ number_format(abs($revenueTrend), 1) }}% <span class="muted">vs last month</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl mb-3">
            <div class="jfd-stat-card">
                <div class="jfd-stat-icon green"><i class="fas fa-wallet"></i></div>
                <div class="jfd-stat-label">Outstanding</div>
                <div class="jfd-stat-value">Rs. {{ number_format($totalOutstanding, 0) }}</div>
                @php $outstandingGood = $outstandingTrend <= 0; @endphp
                <div class="jfd-trend {{ $outstandingGood ? 'good' : 'bad' }}">
                    <i class="fas fa-arrow-{{ $outstandingTrend >= 0 ? 'up' : 'down' }}"></i> {{ number_format(abs($outstandingTrend), 1) }}% <span class="muted">vs last month</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl mb-3">
            <div class="jfd-stat-card">
                <div class="jfd-stat-icon purple"><i class="fas fa-user-friends"></i></div>
                <div class="jfd-stat-label">Total Patients</div>
                <div class="jfd-stat-value">{{ number_format($totalPatients) }}</div>
                @php $patientsGood = $totalPatientsTrend >= 0; @endphp
                <div class="jfd-trend {{ $patientsGood ? 'good' : 'bad' }}">
                    <i class="fas fa-arrow-{{ $totalPatientsTrend >= 0 ? 'up' : 'down' }}"></i> {{ number_format(abs($totalPatientsTrend), 1) }}% <span class="muted">vs last month</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl mb-3">
            <div class="jfd-stat-card">
                <div class="jfd-stat-icon orange"><i class="fas fa-user-plus"></i></div>
                <div class="jfd-stat-label">New Patients</div>
                <div class="jfd-stat-value">{{ $newPatientsThisMonth }}</div>
                @php $newPatientsGood = $newPatientsTrend >= 0; @endphp
                <div class="jfd-trend {{ $newPatientsGood ? 'good' : 'bad' }}">
                    <i class="fas fa-arrow-{{ $newPatientsTrend >= 0 ? 'up' : 'down' }}"></i> {{ number_format(abs($newPatientsTrend), 1) }}% <span class="muted">vs last month</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl mb-3">
            <div class="jfd-stat-card">
                <div class="jfd-stat-icon teal"><i class="fas fa-calendar-check"></i></div>
                <div class="jfd-stat-label">Today's Appointments</div>
                <div class="jfd-stat-value">{{ $todayAppointmentsCount }}</div>
                <div class="jfd-appt-split">{{ $todayCompletedCount }} Completed | {{ $todayPendingCount }} Pending</div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-lg-5 mb-3">
            <div class="jfd-card" wire:ignore>
                <div class="jfd-card-head">
                    <h5>Revenue &amp; Collections</h5>
                    <div class="dropdown jfd-range-dropdown">
                        <button class="btn dropdown-toggle" type="button" data-toggle="dropdown">
                            {{ $revenueChartRange === 'year' ? 'This Year' : 'Last 7 Months' }}
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="#" wire:click.prevent="setRevenueChartRange('year')">This Year</a>
                            <a class="dropdown-item" href="#" wire:click.prevent="setRevenueChartRange('6months')">Last 7 Months</a>
                        </div>
                    </div>
                </div>
                <canvas id="jfRevenueChart" height="130"></canvas>
                <script>
                    (function () {
                        var revenueChart = new Chart(document.getElementById('jfRevenueChart').getContext('2d'), {
                            type: 'line',
                            data: {
                                labels: @json($revenueChartLabels),
                                datasets: [
                                    {
                                        label: 'Invoiced',
                                        data: @json($revenueChartInvoiced),
                                        borderColor: '#148080',
                                        backgroundColor: 'rgba(20,128,128,0.08)',
                                        fill: true,
                                        tension: 0.35,
                                    },
                                    {
                                        label: 'Collected',
                                        data: @json($revenueChartCollected),
                                        borderColor: '#e68c28',
                                        backgroundColor: 'rgba(230,140,40,0.08)',
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
                        window.addEventListener('revenue-chart-updated', function (e) {
                            revenueChart.data.labels = e.detail.labels;
                            revenueChart.data.datasets[0].data = e.detail.invoiced;
                            revenueChart.data.datasets[1].data = e.detail.collected;
                            revenueChart.update();
                        });
                    })();
                </script>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="jfd-card" wire:ignore>
                <div class="jfd-card-head">
                    <h5>Payment Status</h5>
                    <div class="dropdown jfd-range-dropdown">
                        <button class="btn dropdown-toggle" type="button" data-toggle="dropdown">
                            {{ $donutRange === 'year' ? 'This Year' : 'This Month' }}
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="#" wire:click.prevent="setDonutRange('month')">This Month</a>
                            <a class="dropdown-item" href="#" wire:click.prevent="setDonutRange('year')">This Year</a>
                        </div>
                    </div>
                </div>
                <canvas id="jfDonutChart" height="160"></canvas>
                <script>
                    (function () {
                        var donutChart = new Chart(document.getElementById('jfDonutChart').getContext('2d'), {
                            type: 'doughnut',
                            data: {
                                labels: ['Collected', 'Outstanding', 'Overdue'],
                                datasets: [{
                                    data: [{{ $donutCollected }}, {{ $donutOutstanding }}, {{ $donutOverdue }}],
                                    backgroundColor: ['#148080', '#e68c28', '#e05353'],
                                }],
                            },
                            options: {
                                responsive: true,
                                legend: { position: 'bottom', align: 'start', labels: { boxWidth: 12 } },
                            },
                        });
                        window.addEventListener('donut-chart-updated', function (e) {
                            donutChart.data.datasets[0].data = [e.detail.collected, e.detail.outstanding, e.detail.overdue];
                            donutChart.update();
                        });
                    })();
                </script>
            </div>
        </div>
        <div class="col-lg-3 mb-3">
            <div class="jfd-card">
                <div class="jfd-card-head">
                    <h5>Top Performing Doctors</h5>
                    <a href="{{ route('admin_doctor_performance_report') }}" class="jfd-view-all">View All</a>
                </div>
                @forelse ($topPerformingDoctors as $doc)
                    <div class="jfd-doctor-row">
                        @if (!empty($doc['image']))
                            <img src="{{ asset('storage/' . $doc['image']) }}" class="jfd-doctor-avatar" alt="{{ $doc['name'] }}">
                        @else
                            <span class="jfd-doctor-avatar">{{ strtoupper(substr($doc['name'], 0, 1)) }}</span>
                        @endif
                        <div class="flex-grow-1">
                            <div class="jfd-doctor-name">{{ $doc['name'] }}</div>
                            <div class="jfd-doctor-meta">{{ $doc['appointments'] }} Appointments</div>
                        </div>
                        <div>
                            <div class="jfd-doctor-revenue">Rs. {{ number_format($doc['revenue'], 0) }}</div>
                            <div class="jfd-trend {{ $doc['trend'] >= 0 ? 'good' : 'bad' }}" style="text-align: right;">
                                <i class="fas fa-arrow-{{ $doc['trend'] >= 0 ? 'up' : 'down' }}"></i> {{ number_format(abs($doc['trend']), 1) }}%
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0" style="font-size: 13px;">No doctor activity yet this month.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-lg-5 mb-3">
            <div class="jfd-card">
                <div class="jfd-card-head">
                    <h5>Recent Invoices</h5>
                    <a href="{{ route('admin_invoices') }}" class="jfd-view-all">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Invoice #</th><th>Patient</th><th>Doctor</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            @forelse ($recentInvoices as $invoice)
                                <tr>
                                    <td>{{ $invoice->invoice_number }}</td>
                                    <td>{{ $invoice->patient->name ?? 'N/A' }}</td>
                                    <td>{{ $invoice->doctor->employ->name ?? 'N/A' }}</td>
                                    <td>{{ number_format($invoice->grand_total, 0) }}</td>
                                    <td>
                                        @if ($invoice->unpaid_total <= 0)
                                            <span class="status-completed">Paid</span>
                                        @elseif ($invoice->paid_total > 0)
                                            <span class="status-partial">Partial</span>
                                        @else
                                            <span class="status-unpaid">Unpaid</span>
                                        @endif
                                    </td>
                                    <td>{{ $invoice->created_at->format('d M') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">No invoices yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="jfd-card">
                <div class="jfd-card-head">
                    <h5>Upcoming Appointments</h5>
                    <a href="{{ route('appointment') }}" class="jfd-view-all">View All</a>
                </div>
                @forelse ($upcomingAppointments as $appt)
                    <div class="jfd-appt-row">
                        <div class="jfd-appt-time">{{ \Carbon\Carbon::parse($appt->intime)->format('h:i A') }}</div>
                        @if (!empty($appt->patient?->photo_path))
                            <img src="{{ asset('storage/' . $appt->patient->photo_path) }}" class="jfd-appt-avatar" alt="">
                        @else
                            <span class="jfd-appt-avatar">{{ strtoupper(substr($appt->patient->name ?? '?', 0, 1)) }}</span>
                        @endif
                        <div class="flex-grow-1">
                            <div class="jfd-appt-name">{{ $appt->patient->name ?? 'N/A' }}</div>
                            <div class="jfd-appt-doctor">{{ $appt->doctor->employ->name ?? 'N/A' }}</div>
                        </div>
                        <span class="jfd-appt-type">{{ \Illuminate\Support\Str::limit($appt->description ?: 'Consultation', 16) }}</span>
                    </div>
                @empty
                    <p class="text-muted mb-0" style="font-size: 13px;">No upcoming appointments scheduled.</p>
                @endforelse
            </div>
        </div>
        <div class="col-lg-3 mb-3">
            <div class="jfd-card">
                <div class="jfd-card-head">
                    <h5><i class="fas fa-bolt text-warning"></i> Quick Actions</h5>
                </div>
                <div class="jfd-quick-actions">
                    <a href="{{ route('admin_patients') }}" class="jfd-quick-action blue"><i class="fas fa-user-plus"></i> New Patient</a>
                    <a href="{{ route('admin_invoices') }}" class="jfd-quick-action green"><i class="fas fa-file-invoice"></i> New Invoice</a>
                    <a href="{{ route('appointment') }}" class="jfd-quick-action orange"><i class="fas fa-calendar-plus"></i> Appointment</a>
                    <a href="{{ route('admin_doctor_performance_report') }}" class="jfd-quick-action purple"><i class="fas fa-chart-bar"></i> View Reports</a>
                </div>
            </div>
        </div>
    </div>

    <hr class="my-5">
    <h5 class="text-muted mb-3" style="font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: .04em;">More Insights</h5>

    <div class="jf-top-bar">
        <h4 class="jf-welcome">DETAILED BREAKDOWN</h4>
        <div class="jf-period-tabs">
            <button wire:click="setPeriod('today')" class="{{ $period === 'today' ? 'active' : '' }}">Today</button>
            <button wire:click="setPeriod('week')" class="{{ $period === 'week' ? 'active' : '' }}">Last 7 Days</button>
            <button wire:click="setPeriod('month')" class="{{ $period === 'month' ? 'active' : '' }}">This Month</button>
            <button wire:click="setPeriod('year')" class="{{ $period === 'year' ? 'active' : '' }}">This Year</button>
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
