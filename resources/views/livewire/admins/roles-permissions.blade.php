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
        .jfr-icon-btn.edit { background: rgba(230, 140, 40, .1); color: #d47f1f; }
        .jfr-icon-btn.delete { background: rgba(224, 83, 83, .1); color: #d43f3f; }
        .jfr-icon-btn.toggle { background: rgba(20, 128, 128, .1); color: #148080; }
        .jfr-entries-info { color: #7a8a8a; font-size: 13px; margin-top: 10px; }
        .rbac-tabs { border-bottom: 1px solid #e6ecec; margin-bottom: 18px; }
        .rbac-tabs a {
            display: inline-block;
            padding: 10px 18px;
            color: #7a8a8a;
            font-weight: 600;
            border-bottom: 2px solid transparent;
            cursor: pointer;
        }
        .rbac-tabs a.active { color: #148080; border-color: #148080; }
        .rbac-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
        }
        .rbac-badge.system { background: rgba(20, 128, 128, .1); color: #148080; }
        .rbac-badge.custom { background: rgba(33, 150, 243, .1); color: #2196F3; }
        .rbac-badge.active { background: rgba(56, 176, 105, .12); color: #2f9c5c; }
        .rbac-badge.inactive { background: rgba(224, 83, 83, .12); color: #d43f3f; }
        .rbac-module-block { border: 1px solid #e6ecec; border-radius: 10px; padding: 12px 16px; margin-bottom: 10px; }
        .rbac-module-block h6 { font-weight: 700; color: #0a3535; margin-bottom: 8px; }
        .rbac-module-block label { font-weight: normal; margin-right: 16px; margin-bottom: 4px; }
    </style>
    <div class="content">
        <div class="container">
            <div class="row page-title row">
                <div class="col">
                    <h3 class="jfr-title">{{ env('APP_NAME') }} Roles &amp; Permissions</h3>
                </div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
                </div>
            </div>

            @if (session()->has('message'))
                <div class="alert alert-success">
                    {{ session('message') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
            @endif
            @if (session()->has('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
            @endif

            <div class="box box-primary">
                <div class="box-body">
                    <div class="text-info" wire:loading>Loading..</div>

                    @if ($_page === 'index')
                        <div class="rbac-tabs">
                            <a wire:click="show_tab('roles')" class="{{ $_tab === 'roles' ? 'active' : '' }}">Roles</a>
                            <a wire:click="show_tab('users')" class="{{ $_tab === 'users' ? 'active' : '' }}">User Accounts</a>
                            @if (auth()->user()->isSuperAdmin())
                                <a wire:click="show_tab('activity')" class="{{ $_tab === 'activity' ? 'active' : '' }}">Activity Log</a>
                            @endif
                        </div>

                        {{-- ============ ROLES TAB ============ --}}
                        @if ($_tab === 'roles')
                            @if (auth()->user()->hasPermission('roles', 'create'))
                                <button class="btn btn-jfr-teal btn-sm mb-3" wire:click="show_create_role">+ New Role</button>
                            @endif
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Role</th>
                                            <th>Rank</th>
                                            <th>Type</th>
                                            <th>Users Assigned</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($roles as $role)
                                            <tr>
                                                <td>{{ $role->name }}</td>
                                                <td>{{ $role->rank }}</td>
                                                <td><span class="rbac-badge {{ $role->is_system_role ? 'system' : 'custom' }}">{{ $role->is_system_role ? 'System' : 'Custom' }}</span></td>
                                                <td>{{ $role->users_count }}</td>
                                                <td>
                                                    @if (auth()->user()->canManageRole($role))
                                                        <button wire:click="edit_role({{ $role->id }})" class="jfr-icon-btn edit"><i class="fas fa-pen"></i></button>
                                                        @if (!$role->is_system_role)
                                                            <button wire:click="delete_role({{ $role->id }})" onclick="return confirm('Delete this role?')" class="jfr-icon-btn delete"><i class="fas fa-trash"></i></button>
                                                        @endif
                                                    @else
                                                        <span class="text-muted small">View only</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        {{-- ============ USERS TAB ============ --}}
                        @if ($_tab === 'users')
                            @if (auth()->user()->hasPermission('users', 'create'))
                                <button class="btn btn-jfr-teal btn-sm mb-3" wire:click="show_create_user">+ New Login</button>
                            @endif
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($users as $u)
                                            <tr>
                                                <td>{{ $u->name }}</td>
                                                <td>{{ $u->email }}</td>
                                                <td>{{ $u->role->name ?? 'N/A' }}</td>
                                                <td><span class="rbac-badge {{ $u->is_active ? 'active' : 'inactive' }}">{{ $u->is_active ? 'Active' : 'Inactive' }}</span></td>
                                                <td>
                                                    @if ($u->id === auth()->id())
                                                        <span class="text-muted small">This is you</span>
                                                    @elseif ($u->role && auth()->user()->canManageRole($u->role))
                                                        <button wire:click="edit_user({{ $u->id }})" class="jfr-icon-btn edit"><i class="fas fa-pen"></i></button>
                                                        <button wire:click="toggle_active({{ $u->id }})" onclick="return confirm('{{ $u->is_active ? 'Deactivate' : 'Activate' }} this user?')" class="jfr-icon-btn toggle"><i class="fas fa-power-off"></i></button>
                                                    @else
                                                        <span class="text-muted small">View only</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        {{-- ============ ACTIVITY LOG TAB ============ --}}
                        @if ($_tab === 'activity' && $activityLogs)
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>When</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Module</th>
                                            <th>Description</th>
                                            <th>IP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($activityLogs as $log)
                                            <tr>
                                                <td>{{ $log->created_at->format('d M Y h:i A') }}</td>
                                                <td>{{ $log->user->name ?? 'Unknown' }}</td>
                                                <td>{{ ucfirst($log->action) }}</td>
                                                <td>{{ \App\Http\Livewire\Admins\RolesPermissions::MODULE_LABELS[$log->module] ?? $log->module }}</td>
                                                <td>{{ $log->description }}</td>
                                                <td>{{ $log->ip_address }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6">No activity recorded yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="jfr-entries-info">
                                {{ $activityLogs->links() }}
                            </div>
                        @endif
                    @endif

                    {{-- ============ ROLE FORM ============ --}}
                    @if ($_page === 'role-form')
                        <form wire:submit.prevent="save_role">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Role Name</label>
                                    <input type="text" class="form-control" wire:model.lazy="role_name">
                                    @error('role_name') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                </div>
                                @if (!$editing_role_id)
                                    <div class="form-group col-md-6">
                                        <label>Rank (1 = just below Admin's authority; higher number = lower authority)</label>
                                        <input type="number" min="1" max="10" class="form-control" wire:model.lazy="role_rank">
                                        @error('role_rank') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                    </div>
                                @endif
                            </div>

                            @if ($editing_role_id && $role_rank <= 1)
                                <div class="form-group" style="max-width: 320px;">
                                    <label>Re-enter your password to confirm this change</label>
                                    <input type="password" class="form-control" wire:model.lazy="confirm_password">
                                    @error('confirm_password') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                </div>
                            @endif

                            <h5 class="mt-4">Permissions</h5>
                            <hr class="mt-1">
                            @foreach ($allPermissions as $module => $perms)
                                <div class="rbac-module-block">
                                    <h6>{{ \App\Http\Livewire\Admins\RolesPermissions::MODULE_LABELS[$module] ?? ucfirst($module) }}</h6>
                                    @foreach ($perms as $perm)
                                        @if (in_array($perm->id, $selectablePermissionIds))
                                            <label>
                                                <input type="checkbox" wire:model="selected_permission_ids" value="{{ $perm->id }}"> {{ ucfirst($perm->action) }}
                                            </label>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach

                            <div class="form-group mt-3">
                                <button type="submit" class="btn btn-primary">Save Role</button>
                                <button type="button" class="btn btn-secondary" wire:click="show_tab('roles')">Cancel</button>
                            </div>
                        </form>
                    @endif

                    {{-- ============ USER FORM ============ --}}
                    @if ($_page === 'user-form')
                        <form wire:submit.prevent="save_user">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Name</label>
                                    <input type="text" class="form-control" wire:model.lazy="user_name">
                                    @error('user_name') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Email</label>
                                    <input type="email" class="form-control" wire:model.lazy="user_email">
                                    @error('user_email') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Password {{ $editing_user_id ? '(leave blank to keep unchanged)' : '' }}</label>
                                    <input type="password" class="form-control" wire:model.lazy="user_password">
                                    @error('user_password') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Role</label>
                                    <select class="form-control" wire:model="user_role_id">
                                        @foreach ($allRoles as $r)
                                            <option value="{{ $r->id }}" {{ !auth()->user()->canManageRole($r) ? 'disabled' : '' }}>{{ $r->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('user_role_id') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                    <small class="text-muted">Roles at or above your own authority are disabled.</small>
                                </div>
                            </div>
                            <div class="form-group" style="max-width: 320px;">
                                <label>Linked Doctor Profile (optional — for Doctor-role logins)</label>
                                <select class="form-control" wire:model="user_doctor_id">
                                    <option value="">None</option>
                                    @foreach ($doctors as $doc)
                                        <option value="{{ $doc->id }}">{{ $doc->employ->name ?? 'Unknown' }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group mt-3">
                                <button type="submit" class="btn btn-primary">{{ $editing_user_id ? 'Update User' : 'Create Login' }}</button>
                                <button type="button" class="btn btn-secondary" wire:click="show_tab('users')">Cancel</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
