<div>
    <style>
        .jfr-title { color: #0a3535; font-weight: 800; }
        .jfr-filter-card {
            background: #fff;
            border: 1px solid #e9eef0;
            border-radius: 14px;
            padding: 22px 22px 22px;
            margin-bottom: 24px;
            box-shadow: 0 6px 18px rgba(10, 53, 53, 0.05);
        }
        .jfr-filter-card .form-control {
            border-radius: 8px;
            border-color: #e1e7e7;
        }
        .jfr-filter-card .form-control:focus {
            border-color: #148080;
            box-shadow: 0 0 0 3px rgba(20, 128, 128, 0.12);
        }
        .btn-jfr-teal { background: #148080; border-color: #148080; color: #fff; }
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
        .jfr-icon-btn.edit { background: rgba(230, 140, 40, .1); color: #d47f1f; }
        .jfr-icon-btn.print { background: rgba(20, 128, 128, .1); color: #148080; }
        .jfr-icon-btn.delete { background: rgba(224, 83, 83, .1); color: #d43f3f; }
        .jfr-entries-info { color: #7a8a8a; font-size: 13px; margin-top: 10px; }
        .jfr-stat-card {
            background: #fff;
            border: 1px solid #e9eef0;
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 24px;
            box-shadow: 0 6px 18px rgba(10, 53, 53, 0.05);
            display: inline-flex;
            align-items: center;
            gap: 14px;
        }
        .jfr-stat-card .jfr-stat-label { color: #7a8a8a; font-size: 12.5px; }
        .jfr-stat-card .jfr-stat-value { color: #0a3535; font-size: 22px; font-weight: 800; }
    </style>
    <div class="content">
        <div class="container">
            <div class="row page-title row">
                <div class="col">
                    <h3 class="jfr-title">{{ env('APP_NAME') }} Expenses</h3>
                </div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                    @if (auth()->user()->hasPermission('expenses', 'create'))
                        <button class="btn btn-jfr-teal" wire:click="show_create_modal" data-toggle="modal" data-target="#expenseModal">Add Expense</button>
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

                    <div class="jfr-stat-card">
                        <div>
                            <div class="jfr-stat-label">Total Expenses (filtered)</div>
                            <div class="jfr-stat-value">PKR {{ number_format($totalAmount, 2) }}</div>
                        </div>
                    </div>

                    <div class="jfr-filter-card">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-3 mb-0">
                                <label>Date From</label>
                                <input type="date" wire:model.defer="filter_from" class="form-control">
                            </div>
                            <div class="form-group col-md-3 mb-0">
                                <label>Date To</label>
                                <input type="date" wire:model.defer="filter_to" class="form-control">
                            </div>
                            <div class="form-group col-md-auto mb-0">
                                <button type="button" wire:click="$refresh" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                                <button type="button" wire:click="resetFilters" class="btn btn-outline-secondary"><i class="fas fa-redo"></i> Reset</button>
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
                                        <th>Actions</th>
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
                                                @if (auth()->user()->hasPermission('expenses', 'update'))
                                                    <button wire:click="edit({{ $expense->id }})" data-toggle="modal" data-target="#expenseModal" class="jfr-icon-btn edit"><i class="fas fa-pen"></i></button>
                                                @endif
                                                @if (auth()->user()->hasPermission('expenses', 'delete'))
                                                    <button wire:click="delete({{ $expense->id }})" onclick="return confirm('Delete this expense? This cannot be undone.')" class="jfr-icon-btn delete"><i class="fas fa-trash"></i></button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-warning">No expenses recorded yet.</td></tr>
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

                    <div class="modal fade" id="expenseModal" tabindex="-1" role="dialog" aria-hidden="true" wire:ignore.self>
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <form wire:submit.prevent="save">
                                    <div class="modal-header">
                                        <h5 class="modal-title">{{ $editing_id ? 'Edit Expense' : 'Add Expense' }}</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label>Date</label>
                                            <input type="date" class="form-control" wire:model.defer="expense_date">
                                            @error('expense_date') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Expense Category</label>
                                            <input type="text" class="form-control" wire:model.defer="category" placeholder="e.g. Petrol, Chai, Coffee, Tea...">
                                            @error('category') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Description</label>
                                            <textarea class="form-control" rows="3" wire:model.defer="description" placeholder="Optional notes..."></textarea>
                                            @error('description') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Payment Mode</label>
                                            <select class="form-control" wire:model.defer="payment_mode">
                                                @foreach (\App\Http\Livewire\Admins\Expenses::PAYMENT_MODE_OPTIONS as $mode)
                                                    <option value="{{ $mode }}">{{ $mode }}</option>
                                                @endforeach
                                            </select>
                                            @error('payment_mode') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Amount</label>
                                            <input type="number" step="0.01" min="0" class="form-control" wire:model.defer="amount">
                                            @error('amount') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-jfr-teal">{{ $editing_id ? 'Update Expense' : 'Save Expense' }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <script>
                        if (!window.__jfExpenseModalBound) {
                            window.__jfExpenseModalBound = true;
                            window.addEventListener('expense-modal-open', function () {
                                $('#expenseModal').modal('show');
                            });
                            window.addEventListener('expense-modal-close', function () {
                                $('#expenseModal').modal('hide');
                            });
                        }
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>
