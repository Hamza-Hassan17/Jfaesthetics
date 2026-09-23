<div>
    <style>
        .jfr-title { color: #0a3535; font-weight: 800; }
        .jfr-filter-card {
            background: #fff;
            border: 1px solid #e9eef0;
            border-radius: 14px;
            padding: 22px 22px 8px;
            margin-bottom: 24px;
            box-shadow: 0 6px 18px rgba(10, 53, 53, 0.05);
        }
        .jfr-filter-card .form-control { border-radius: 8px; border-color: #e1e7e7; }
        .jfr-filter-card .form-control:focus {
            border-color: #148080;
            box-shadow: 0 0 0 3px rgba(20, 128, 128, 0.12);
        }
        .jfr-actions-row { margin-bottom: 18px; }
        .btn-jfr-teal { background: #148080; border-color: #148080; color: #fff; }
        .btn-jfr-teal:hover { background: #0d5c5c; border-color: #0d5c5c; color: #fff; }
        .btn-jfr-outline-teal { background: #fff; border: 1px solid #148080; color: #148080; }
        .btn-jfr-outline-teal:hover { background: #148080; color: #fff; }
        .btn-jfr-pdf { background: #e05353; border-color: #e05353; color: #fff; }
        .btn-jfr-pdf:hover { background: #c53d3d; border-color: #c53d3d; color: #fff; }
        .jfr-stat-card {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #fff;
            border: 1px solid #e9eef0;
            border-radius: 14px;
            padding: 18px 20px;
            height: 100%;
            box-shadow: 0 6px 18px rgba(10, 53, 53, 0.05);
        }
        .jfr-stat-icon {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }
        .jfr-stat-icon.jfr-green { background: rgba(56, 176, 105, .12); color: #38b069; }
        .jfr-stat-icon.jfr-blue { background: rgba(33, 150, 243, .12); color: #2196F3; }
        .jfr-stat-label { color: #7a8a8a; font-size: 12.5px; margin-bottom: 2px; }
        .jfr-stat-value { color: #0a3535; font-weight: 800; font-size: 19px; }
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
            background: rgba(20, 128, 128, .1);
            color: #148080;
        }
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
        .jfr-icon-btn.print { background: rgba(20, 128, 128, .1); color: #148080; }
        .jfr-entries-info { color: #7a8a8a; font-size: 13px; margin-top: 10px; }
    </style>

    <div class="content">
        <div class="container">
            <div class="row page-title row">
                <div class="col">
                    <h3 class="jfr-title">{{ env('APP_NAME') }} Reports</h3>
                </div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                </div>
            </div>

            @include('admins.partials.reports-tabs')

            <div class="box box-primary">
                <div class="box-body">
                    <div class="text-info" wire:loading>Loading..</div>

                    <div class="jfr-filter-card">
                        <form wire:submit.prevent="$refresh">
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label>Date From</label>
                                    <input type="date" wire:model.defer="filter_from" class="form-control">
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Date To</label>
                                    <input type="date" wire:model.defer="filter_to" class="form-control">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3 jfr-actions-row flex-wrap">
                                <div class="mb-2">
                                    <button type="submit" class="btn btn-jfr-teal"><i class="fas fa-search"></i> Search</button>
                                    <button type="button" wire:click="resetFilters" class="btn btn-outline-secondary"><i class="fas fa-redo"></i> Reset</button>
                                </div>
                                <div class="mb-2">
                                    <button type="button" wire:click="exportCsv" class="btn btn-jfr-outline-teal"><i class="fas fa-file-excel"></i> Export Excel</button>
                                    <a href="{{ route('admin_reports_expenses_print_list', array_filter(['from' => $filter_from, 'to' => $filter_to])) }}" target="_blank" class="btn btn-jfr-pdf"><i class="fas fa-file-pdf"></i> Export PDF</a>
                                    <a href="{{ route('admin_reports_expenses_print_list', array_filter(['from' => $filter_from, 'to' => $filter_to])) }}" target="_blank" class="btn btn-outline-dark"><i class="fas fa-print"></i> Print</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="jfr-stat-card">
                                <span class="jfr-stat-icon jfr-blue"><i class="fas fa-receipt"></i></span>
                                <div>
                                    <div class="jfr-stat-label">Total Expenses</div>
                                    <div class="jfr-stat-value">{{ $expenses->total() }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="jfr-stat-card">
                                <span class="jfr-stat-icon jfr-green"><i class="fas fa-money-bill-wave"></i></span>
                                <div>
                                    <div class="jfr-stat-label">Total Amount</div>
                                    <div class="jfr-stat-value">PKR {{ number_format($totalAmount, 2) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="jfr-table-card">
                        <div class="table-responsive">
                            <table class="table table-hover jfr-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Payment Mode</th>
                                        <th>Amount</th>
                                        <th>Recorded By</th>
                                        <th>Print</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($expenses as $expense)
                                        <tr>
                                            <td>{{ $expense->expense_date->format('d M Y') }}</td>
                                            <td><span class="jfr-badge">{{ $expense->category }}</span></td>
                                            <td>{{ $expense->description ?: '-' }}</td>
                                            <td>{{ $expense->payment_mode }}</td>
                                            <td>{{ number_format($expense->amount, 2) }}</td>
                                            <td>{{ $expense->created_by ?: 'N/A' }}</td>
                                            <td>
                                                <a href="{{ route('admin_expense_print', $expense->id) }}" target="_blank" class="jfr-icon-btn print"><i class="fas fa-print"></i></a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-warning">No expenses found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center flex-wrap px-3">
                            @if ($expenses->total() > 0)
                                <div class="jfr-entries-info">Showing {{ $expenses->firstItem() }} to {{ $expenses->lastItem() }} of {{ $expenses->total() }} entries</div>
                            @else
                                <div class="jfr-entries-info">No entries found</div>
                            @endif
                            <div>{{ $expenses->links() }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
