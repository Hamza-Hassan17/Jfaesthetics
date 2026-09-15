<div>
    <style>
        .jfp-title { color: #0a3535; font-weight: 800; }
        .jfp-card {
            background: #fff;
            border: 1px solid #e9eef0;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 24px;
            box-shadow: 0 6px 18px rgba(10, 53, 53, 0.05);
        }
        .jfp-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(20, 128, 128, 0.12);
            color: #148080;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 800;
            flex-shrink: 0;
        }
        .jfp-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 30px;
            font-size: 11.5px;
            font-weight: 700;
            margin-right: 6px;
        }
        .jfp-badge-role { background: rgba(20, 128, 128, .12); color: #148080; }
        .jfp-badge-active { background: rgba(56, 176, 105, .12); color: #2f9c5c; }
        .jfp-badge-inactive { background: rgba(224, 83, 83, .12); color: #d43f3f; }
        .jfp-card h6 { color: #0a3535; font-weight: 800; margin-bottom: 4px; }
        .jfp-card .subtitle { color: #7a8a8a; font-size: 12.5px; margin-bottom: 16px; }
        .btn-jfp-teal { background: #148080; border-color: #148080; color: #fff; }
        .btn-jfp-teal:hover { background: #0d5c5c; border-color: #0d5c5c; color: #fff; }
        .jfp-activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #f2f5f5;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 12px;
        }
        .jfp-activity-item:last-child { border-bottom: none; }
        .jfp-activity-desc { font-size: 13.5px; color: #222; }
        .jfp-activity-time { font-size: 12px; color: #97a5a5; white-space: nowrap; }
    </style>

    <div class="content">
        <div class="container">
            <div class="row page-title align-items-center">
                <div class="col">
                    <h3 class="jfp-title">Profile</h3>
                    <div class="subtitle" style="color:#7a8a8a;">Manage your personal information and account settings.</div>
                </div>
                <div class="col-auto">
                    @include('admins.partials.back-to-dashboard')
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

            <div class="jfp-card d-flex align-items-center flex-wrap" style="gap: 20px;">
                <div class="jfp-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                <div class="flex-grow-1">
                    <h5 class="mb-1" style="font-weight: 800; color: #0a3535;">{{ $user->name }}</h5>
                    <div class="mb-2" style="color: #64777a; font-size: 13.5px;">{{ $user->email }}</div>
                    <span class="jfp-badge jfp-badge-role">{{ $user->role->name ?? 'N/A' }}</span>
                    <span class="jfp-badge {{ $user->is_active ? 'jfp-badge-active' : 'jfp-badge-inactive' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-secondary" wire:click="showChangePassword">Change Password</button>
                    @if (!$editing)
                        <button type="button" class="btn btn-jfp-teal" wire:click="edit">Edit Profile</button>
                    @endif
                </div>
            </div>

            @if ($changing_password)
                <div class="jfp-card">
                    <h6>Change Password</h6>
                    <div class="subtitle">Update your account password.</div>
                    <form wire:submit.prevent="changePassword">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Current Password</label>
                                <input type="password" class="form-control" wire:model.defer="current_password">
                                @error('current_password') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label>New Password</label>
                                <input type="password" class="form-control" wire:model.defer="new_password">
                                @error('new_password') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label>Confirm New Password</label>
                                <input type="password" class="form-control" wire:model.defer="new_password_confirmation">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-jfp-teal">Save Password</button>
                        <button type="button" class="btn btn-outline-secondary" wire:click="cancelChangePassword">Cancel</button>
                    </form>
                </div>
            @endif

            <div class="row">
                <div class="col-md-6">
                    <div class="jfp-card">
                        <h6>Basic Information</h6>
                        <div class="subtitle">Your personal information</div>

                        @if ($editing)
                            <form wire:submit.prevent="save">
                                <div class="form-group">
                                    <label>Name</label>
                                    <input type="text" class="form-control" wire:model.defer="name">
                                    @error('name') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" class="form-control" wire:model.defer="email">
                                    @error('email') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                <button type="submit" class="btn btn-jfp-teal">Save Changes</button>
                                <button type="button" class="btn btn-outline-secondary" wire:click="cancelEdit">Cancel</button>
                            </form>
                        @else
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Name</dt>
                                <dd class="col-sm-8">{{ $user->name }}</dd>
                                <dt class="col-sm-4">Email Address</dt>
                                <dd class="col-sm-8">{{ $user->email }}</dd>
                            </dl>
                        @endif
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="jfp-card">
                        <h6>Account Information</h6>
                        <div class="subtitle">System account details</div>
                        <dl class="row mb-0">
                            <dt class="col-sm-5">User ID</dt>
                            <dd class="col-sm-7">{{ $user->id }}</dd>

                            <dt class="col-sm-5">Role</dt>
                            <dd class="col-sm-7">{{ $user->role->name ?? 'N/A' }}</dd>

                            <dt class="col-sm-5">Account Status</dt>
                            <dd class="col-sm-7">
                                <span class="jfp-badge {{ $user->is_active ? 'jfp-badge-active' : 'jfp-badge-inactive' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                            </dd>

                            <dt class="col-sm-5">Created On</dt>
                            <dd class="col-sm-7">{{ $user->created_at->format('d M Y') }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="jfp-card">
                <h6>Recent Activity</h6>
                <div class="subtitle">Your latest account activity</div>
                @forelse ($recentActivity as $log)
                    <div class="jfp-activity-item">
                        <span class="jfp-activity-desc">{{ $log->description }}</span>
                        <span class="jfp-activity-time">{{ $log->created_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <div class="text-muted small">No recent activity recorded.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
