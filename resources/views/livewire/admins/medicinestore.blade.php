<div>
    <div class="content">
        <div class="container">
            <div class="row page-title align-items-center">
                <div class="col">
                    <h3 class="text-info">{{ env('APP_NAME') }} Medicines Store</h3>
                </div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
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
                    <form accept-charset="utf-8" class="shadow rounded p-3" wire:submit.prevent="add_medicine()">
                        <div class="text-capitalize bg-dark p-2 shadow mb-3 text-center text-lg text-light rounded">
                            {{ __('Add New medicine') }}</div>
                        <div class="form-group">
                            <label for="name">Medicine Name</label>
                            <input type="text" name="name" wire:model.lazy="name"
                                placeholder="Enter Medicine Name" class="form-control" required>
                        </div>
                        @error('name')
                            <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                        @enderror

                        <div class="form-group">
                            <label for="Price">Medicine Price</label>
                            <input type="number" name="Price" id="" wire:model.lazy="price"
                                placeholder="Enter Medicine Price" class="form-control" required>
                        </div>
                        @error('price')
                            <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                        @enderror

                        <div class="form-group">
                            <label for="quantity">Medicine Quantity</label>
                            <input type="number" name="quantity" wire:model.lazy="quantity"
                                placeholder="Enter Medicine Quantity" class="form-control" required>
                        </div>
                        @error('quantity')
                            <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                        @enderror

                        <div class="form-group">
                            <label for="code">Medicine Code</label>
                            <input type="text" name="code" wire:model.lazy="code"
                                placeholder="Enter Medicine Code" class="form-control" required>
                        </div>
                        @error('code')
                            <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                        @enderror

                        <div class="form-group">
                            <label for="low_stock_threshold">Low Stock Threshold</label>
                            <input type="number" name="low_stock_threshold" wire:model.lazy="low_stock_threshold"
                                placeholder="Alert when quantity falls to or below this" class="form-control">
                        </div>
                        @error('low_stock_threshold')
                            <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                        @enderror

                        <div class="form-group">
                            <input type="submit" class="btn btn-primary" value="{{ $button_text }}">
                        </div>
                    </form><br>
                    <hr>

                    <div class="text-capitalize bg-dark p-2 shadow mb-3 text-center text-lg text-light rounded">
                        {{ __('All  medicine') }}</div>
                    <table class="table table-hover" style="" id="">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Code</th>
                                <th>Status</th>
                                <th>Dated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($medicines as $medicine)
                                <tr @if ($medicine->is_low_stock) class="table-danger" @endif>
                                    <td>{{ $medicine->id }}</td>
                                    <td>{{ $medicine->name ?: '(needs a name)' }}</td>
                                    <td>{{ $medicine->price }}</td>
                                    <td>{{ $medicine->quantity }}</td>
                                    <td>{{ $medicine->code }}</td>
                                    <td>
                                        @if ($medicine->is_low_stock)
                                            <span class="badge badge-danger">Low Stock</span>
                                        @else
                                            <span class="badge badge-success">OK</span>
                                        @endif
                                    </td>
                                    <td>{{ $medicine->created_at }}</td>
                                    <td class="text-right">
                                        <button wire:click="show_stock_in_form({{ $medicine->id }})"
                                            title="Receive stock" class="btn btn-outline-success btn-rounded"><i class="fas fa-plus"></i></button>
                                        <button wire:click="edit({{ $medicine->id }})"
                                            class="btn btn-outline-info btn-rounded"><i class="fas fa-pen"></i></button>
                                        <button wire:click="delete({{ $medicine->id }})"
                                            onclick="return confirm('{{ __('Are You Sure ?') }}')"
                                            class="btn btn-outline-danger btn-rounded"><i
                                                class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                @if ($stock_in_medicine_id == $medicine->id)
                                    <tr>
                                        <td colspan="8">
                                            <form wire:submit.prevent="add_stock" class="form-inline p-2 bg-light rounded">
                                                <label class="mr-2">Receive stock for {{ $medicine->name }}:</label>
                                                <input type="number" min="1" wire:model.lazy="stock_in_quantity" placeholder="Quantity" class="form-control mr-2">
                                                <input type="number" step="0.01" min="0" wire:model.lazy="stock_in_cost" placeholder="Cost (optional)" class="form-control mr-2">
                                                <button type="submit" class="btn btn-success btn-sm">Save</button>
                                            </form>
                                            @error('stock_in_quantity') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
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
                    {{ $medicines->links() }}

                    <hr>
                    <div class="text-capitalize bg-dark p-2 shadow mb-3 text-center text-lg text-light rounded">
                        {{ __('Recent Stock Movements') }}</div>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Medicine</th>
                                <th>Change</th>
                                <th>Reason</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentMovements as $movement)
                                <tr>
                                    <td>{{ $movement->created_at->format('d M Y H:i') }}</td>
                                    <td>{{ $movement->medicine->name ?? 'Deleted medicine' }}</td>
                                    <td class="{{ $movement->change >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $movement->change >= 0 ? '+' : '' }}{{ $movement->change }}
                                    </td>
                                    <td>{{ str_replace('_', ' ', ucfirst($movement->reason)) }}</td>
                                    <td>{{ $movement->user->name ?? 'System' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-warning">No stock movements recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
