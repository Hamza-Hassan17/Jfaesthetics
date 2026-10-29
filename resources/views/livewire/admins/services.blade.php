<div>
    <div class="content">
        <div class="container">
            <div class="row page-title align-items-center">
                <div class="col">
                    <h3 class="text-info">{{ env('APP_NAME') }} Services</h3>
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
                    @if (($edit_service_id && auth()->user()->hasPermission('services', 'update')) || (!$edit_service_id && auth()->user()->hasPermission('services', 'create')))
                    <form accept-charset="utf-8" class="shadow rounded p-3" wire:submit.prevent="add_service()">
                        <div class="text-capitalize bg-dark p-2 shadow mb-3 text-center text-lg text-light rounded">
                            {{ __('Add New Service') }}</div>

                        <div class="form-group">
                            <label for="name">Service Name</label>
                            <input type="text" name="name" wire:model.lazy="name"
                                placeholder="e.g. Carbon+PRP+Meso 3sessions" class="form-control">
                            @error('name')
                                <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="category">Category</label>
                            <input type="text" name="category" wire:model.lazy="category" list="service-categories"
                                placeholder="Choose or type a category" class="form-control">
                            <datalist id="service-categories">
                                @foreach ($categories as $cat)<option value="{{ $cat }}">@endforeach
                            </datalist>
                            @error('category')
                                <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="price">Default Price</label>
                            <input type="number" step="0.01" min="0" name="price" wire:model.lazy="price"
                                placeholder="Enter Default Price" class="form-control">
                            @error('price')
                                <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <input type="submit" class="btn btn-primary" value="{{ $button_text }}">
                        </div>
                    </form><br>
                    @endif
                    <hr>

                    @if ($recipeService)
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="mb-1">Recipe: {{ $recipeService->name }}</h5>
                                <p class="text-muted small">Consumables used per session. When this service is added to an invoice these become editable lines under it (qty × sessions). Changing the recipe affects new invoices only.</p>
                                @error('recipe_lines.*') <div class="text-danger text-xs">{{ $message }}</div> @enderror
                                <table class="table table-bordered table-sm">
                                    <thead><tr><th style="min-width:240px">Item</th><th style="width:150px">Qty per session</th><th style="width:110px">Billable</th>@if ($canEditRecipe)<th style="width:60px"></th>@endif</tr></thead>
                                    <tbody>
                                        @forelse ($recipe_lines as $i => $line)
                                            <tr>
                                                <td>
                                                    <select class="form-control form-control-sm" wire:model="recipe_lines.{{ $i }}.item_id" @unless ($canEditRecipe) disabled @endunless>
                                                        <option value="">Choose item</option>
                                                        @foreach ($inventoryItems as $inventoryItem)
                                                            <option value="{{ $inventoryItem->id }}">{{ $inventoryItem->name }} ({{ $inventoryItem->code }})</option>
                                                        @endforeach
                                                    </select>
                                                    @error("recipe_lines.$i.item_id") <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                                </td>
                                                <td>
                                                    <input type="number" min="1" class="form-control form-control-sm" wire:model.defer="recipe_lines.{{ $i }}.qty_per_session" @unless ($canEditRecipe) readonly @endunless>
                                                    @error("recipe_lines.$i.qty_per_session") <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                                </td>
                                                <td class="text-center"><input type="checkbox" wire:model.defer="recipe_lines.{{ $i }}.is_billable" @unless ($canEditRecipe) disabled @endunless></td>
                                                @if ($canEditRecipe)
                                                    <td><button type="button" class="btn btn-outline-danger btn-sm" wire:click="remove_recipe_line({{ $i }})"><i class="fas fa-trash"></i></button></td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-muted">No consumables yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                                @if ($canEditRecipe)
                                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="add_recipe_line">+ Add item</button>
                                    <button type="button" class="btn btn-primary btn-sm" wire:click="save_recipe">Save recipe</button>
                                @endif
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="close_recipe">Close</button>
                            </div>
                        </div>
                    @endif

                    <div class="text-capitalize bg-dark p-2 shadow mb-3 text-center text-lg text-light rounded">
                        {{ __('All Services') }}</div>
                    @if ($categories->isNotEmpty())
                        <div class="form-inline mb-2">
                            <label class="mr-2">Category</label>
                            <select class="form-control form-control-sm" wire:model="filter_category">
                                <option value="">All categories</option>
                                @foreach ($categories as $cat)<option value="{{ $cat }}">{{ $cat }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Default Price</th>
                                @if ($canSeeRecipe)<th>Recipe</th>@endif
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($services as $service)
                                <tr>
                                    <td>{{ $service->id }}</td>
                                    <td>{{ $service->name }}</td>
                                    <td>{{ $service->category ?: '—' }}</td>
                                    <td>{{ number_format($service->price, 2) }}</td>
                                    @if ($canSeeRecipe)
                                        <td>
                                            <button wire:click="show_recipe({{ $service->id }})" class="btn btn-link btn-sm p-0">
                                                {{ $service->recipe_count ? $service->recipe_count . ' item' . ($service->recipe_count > 1 ? 's' : '') : ($canEditRecipe ? 'Add recipe' : 'None') }}
                                            </button>
                                        </td>
                                    @endif
                                    <td class="text-right">
                                        @if (auth()->user()->hasPermission('services', 'update'))
                                            <button wire:click="edit({{ $service->id }})"
                                                class="btn btn-outline-info btn-rounded"><i class="fas fa-pen"></i></button>
                                        @endif
                                        @if (auth()->user()->hasPermission('services', 'delete'))
                                            <button wire:click="delete({{ $service->id }})"
                                                onclick="return confirm('{{ __('Are You Sure ?') }}')"
                                                class="btn btn-outline-danger btn-rounded"><i class="fas fa-trash"></i></button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-warning">No services yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $services->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
