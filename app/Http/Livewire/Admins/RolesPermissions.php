<?php

namespace App\Http\Livewire\Admins;

use App\Models\ActivityLog;
use App\Models\doctor;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

class RolesPermissions extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $_tab = 'roles';
    public $_page = 'index';

    public const MODULE_LABELS = [
        'dashboard' => 'Dashboard',
        'patients' => 'Patients',
        'appointments' => 'Appointments',
        'employees' => 'Employees',
        'invoices' => 'Invoices',
        'consultation_form' => 'Consultation Form',
        'medicines_store' => 'Medicines Store',
        'services' => 'Services',
        'reports' => 'Reports',
        'roles' => 'Roles & Permissions',
        'users' => 'User Accounts',
        'settings' => 'Settings',
        'legacy_modules' => 'Other Modules',
    ];

    // ---- Role edit/create form state ----
    public $editing_role_id;
    public $role_name;
    public $role_rank = 2;
    public $selected_permission_ids = [];
    public $confirm_password;

    // ---- User form state ----
    public $editing_user_id;
    public $user_name;
    public $user_email;
    public $user_password;
    public $user_role_id;
    public $user_doctor_id;

    public function mount()
    {
        abort_unless(auth()->user()->hasAnyPermissionFor('roles'), 403);
    }

    public function show_tab($tab)
    {
        if ($tab === 'activity' && !auth()->user()->isSuperAdmin()) {
            abort(403, 'Only a Super Admin can view the activity log.');
        }
        $this->_tab = $tab;
        $this->_page = 'index';
    }

    // =========================================================
    // Roles
    // =========================================================

    public function show_create_role()
    {
        abort_unless(auth()->user()->hasPermission('roles', 'create'), 403);
        $this->editing_role_id = null;
        $this->role_name = '';
        $this->role_rank = 2;
        $this->selected_permission_ids = [];
        $this->confirm_password = '';
        $this->_page = 'role-form';
    }

    public function edit_role($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        abort_unless(auth()->user()->canManageRole($role), 403, 'You cannot manage this role.');

        $this->editing_role_id = $role->id;
        $this->role_name = $role->name;
        $this->role_rank = $role->rank;
        $this->selected_permission_ids = $role->permissions->pluck('id')->all();
        $this->confirm_password = '';
        $this->_page = 'role-form';
    }

    public function save_role()
    {
        $actingUser = auth()->user();

        $this->validate([
            'role_name' => 'required|string|max:50',
            'role_rank' => 'required|integer|min:1|max:10', // 0 (Super Admin) is reserved
            'selected_permission_ids' => 'array',
        ]);

        if ($this->editing_role_id) {
            $role = Role::findOrFail($this->editing_role_id);
            abort_unless($actingUser->canManageRole($role), 403, 'You cannot manage this role.');

            // Extra confirmation before touching an Admin-tier role's access.
            if ($role->rank <= 1 && !Hash::check($this->confirm_password ?? '', $actingUser->password)) {
                $this->addError('confirm_password', 'Please re-enter your password to confirm this change.');
                return;
            }

            $role->update(['name' => $this->role_name]); // rank of an existing role is not editable here
        } else {
            abort_unless($actingUser->hasPermission('roles', 'create'), 403);
            if ($this->role_rank < 1) {
                $this->addError('role_rank', 'Only a Super Admin can hold rank 0.');
                return;
            }
            // Rank rule applies to creation too: you can't create a role
            // ranked at or above your own authority.
            if ($this->role_rank <= $actingUser->role->rank) {
                $this->addError('role_rank', 'You cannot create a role at or above your own rank.');
                return;
            }

            $role = Role::create([
                'name' => $this->role_name,
                'rank' => $this->role_rank,
                'is_system_role' => false,
            ]);
        }

        $selectableIds = $this->selectablePermissionIdsFor($actingUser, $role);
        $toSync = array_values(array_intersect($this->selected_permission_ids, $selectableIds));
        $role->permissions()->sync($toSync);

        $this->log('permission_changed', 'roles', $role->id, "Updated permissions for role '{$role->name}'.");

        session()->flash('message', 'Role saved successfully.');
        $this->_page = 'index';
    }

    public function delete_role($id)
    {
        $role = Role::findOrFail($id);
        $actingUser = auth()->user();
        abort_unless($actingUser->canManageRole($role), 403, 'You cannot manage this role.');
        abort_unless($actingUser->hasPermission('roles', 'delete'), 403);

        if ($role->is_system_role) {
            session()->flash('error', 'Built-in roles cannot be deleted.');
            return;
        }

        $assignedUsers = $role->users()->pluck('name');
        if ($assignedUsers->isNotEmpty()) {
            session()->flash('error', 'Cannot delete this role — reassign these users first: ' . $assignedUsers->implode(', '));
            return;
        }

        $roleName = $role->name;
        $role->delete();
        $this->log('deleted', 'roles', $id, "Deleted role '{$roleName}'.");
        session()->flash('message', 'Role deleted successfully.');
    }

    /**
     * A role can only ever be granted permissions the acting user's own
     * role already has (prevents an Admin handing out access they don't
     * themselves possess), further limited by which permissions exist for
     * modules they're allowed to configure at all.
     */
    protected function selectablePermissionIdsFor(User $actingUser, Role $targetRole)
    {
        if ($actingUser->isSuperAdmin()) {
            return Permission::pluck('id')->all();
        }

        return $actingUser->role->permissions->pluck('id')->all();
    }

    // =========================================================
    // Users
    // =========================================================

    public function show_create_user()
    {
        abort_unless(auth()->user()->hasPermission('users', 'create'), 403);
        $this->editing_user_id = null;
        $this->user_name = '';
        $this->user_email = '';
        $this->user_password = '';
        // Per spec 8.7: never default a new login to Admin.
        $this->user_role_id = optional(Role::where('name', 'Receptionist')->first())->id;
        $this->user_doctor_id = '';
        $this->_page = 'user-form';
    }

    public function edit_user($id)
    {
        $user = User::findOrFail($id);
        abort_unless(auth()->user()->canManageRole($user->role), 403, 'You cannot manage this user.');

        $this->editing_user_id = $user->id;
        $this->user_name = $user->name;
        $this->user_email = $user->email;
        $this->user_password = '';
        $this->user_role_id = $user->role_id;
        $this->user_doctor_id = $user->doctor_id;
        $this->_page = 'user-form';
    }

    public function save_user()
    {
        $actingUser = auth()->user();

        $this->validate([
            'user_name' => 'required|string|max:100',
            'user_email' => 'required|email' . ($this->editing_user_id ? ',unique:users,email,' . $this->editing_user_id : '|unique:users,email'),
            'user_password' => $this->editing_user_id ? 'nullable|min:6' : 'required|min:6',
            'user_role_id' => 'required|exists:roles,id',
            'user_doctor_id' => 'nullable|exists:doctors,id',
        ]);

        $targetRole = Role::findOrFail($this->user_role_id);
        abort_unless($actingUser->canManageRole($targetRole), 403, 'You cannot assign this role.');

        if ($this->editing_user_id && (int) $this->editing_user_id === (int) $actingUser->id) {
            session()->flash('error', 'You cannot change your own role.');
            return;
        }

        $data = [
            'name' => $this->user_name,
            'email' => $this->user_email,
            'role_id' => $this->user_role_id,
            'doctor_id' => $this->user_doctor_id ?: null,
        ];
        if ($this->user_password) {
            $data['password'] = bcrypt($this->user_password);
        }

        if ($this->editing_user_id) {
            $existing = User::findOrFail($this->editing_user_id);
            abort_unless($actingUser->canManageRole($existing->role), 403, 'You cannot manage this user.');
            $existing->update($data);
            $this->log('updated', 'users', $existing->id, "Updated user '{$existing->name}'.");
            session()->flash('message', 'User updated successfully.');
        } else {
            $data['is_active'] = true;
            $newUser = User::create($data);
            $this->log('created', 'users', $newUser->id, "Created user '{$newUser->name}' with role '{$targetRole->name}'.");
            session()->flash('message', 'User created successfully.');
        }

        $this->_page = 'index';
    }

    public function toggle_active($id)
    {
        $user = User::findOrFail($id);
        $actingUser = auth()->user();

        if ((int) $id === (int) $actingUser->id) {
            session()->flash('error', 'You cannot deactivate your own account.');
            return;
        }

        abort_unless($actingUser->canManageRole($user->role), 403, 'You cannot manage this user.');

        $user->is_active = !$user->is_active;
        $user->save();

        if (!$user->is_active) {
            // Kill any of this user's active sessions immediately, rather
            // than relying only on their next permission-gated request
            // being rejected.
            \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $this->log($user->is_active ? 'activated' : 'deactivated', 'users', $user->id, ($user->is_active ? 'Activated' : 'Deactivated') . " user '{$user->name}'.");
        session()->flash('message', 'User ' . ($user->is_active ? 'activated' : 'deactivated') . ' successfully.');
    }

    protected function log($action, $module, $recordId, $description)
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
    }

    public function render()
    {
        $actingUser = auth()->user();

        return view('livewire.admins.roles-permissions', [
            'roles' => Role::withCount('users')->orderBy('rank')->get(),
            'users' => User::with('role')->orderBy('name')->get(),
            'allRoles' => Role::orderBy('rank')->get(),
            'allPermissions' => Permission::orderBy('module')->orderBy('action')->get()->groupBy('module'),
            'selectablePermissionIds' => $this->editing_role_id
                ? $this->selectablePermissionIdsFor($actingUser, Role::find($this->editing_role_id) ?? new Role(['rank' => 99]))
                : $this->selectablePermissionIdsFor($actingUser, new Role(['rank' => $this->role_rank])),
            'doctors' => doctor::with('employ')->whereHas('employ')->get(),
            'activityLogs' => $this->_tab === 'activity' && $actingUser->isSuperAdmin()
                ? ActivityLog::with('user')->latest()->paginate(25)
                : null,
        ])->layout('admins.layouts.app');
    }
}
