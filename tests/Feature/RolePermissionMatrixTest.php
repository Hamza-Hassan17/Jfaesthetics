<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * One representative GET route per permission module, gated by
     * ->middleware('permission:<module>') in routes/web.php. Each is
     * hit as every existing role - the response must be 200 if that
     * role has 'view' on the module, and 403 otherwise. This catches
     * any route whose middleware doesn't match what the Roles &
     * Permissions screen actually grants, across every role at once,
     * rather than relying on manually clicking through each one.
     */
    private const MODULE_ROUTES = [
        'dashboard' => '/admin/dashboard',
        'settings' => '/admin/settings',
        'roles' => '/admin/roles-permissions',
        'legacy_modules' => '/admin/nurses',
        'patients' => '/admin/patients',
        'medicines_store' => '/admin/medicinesStore',
        'employees' => '/admin/employees',
        'appointments' => '/admin/appointment',
        'services' => '/admin/services',
        'invoices' => '/admin/invoices',
        'consultation_form' => '/admin/consultation-forms',
        'reports' => '/admin/reports',
    ];

    public function test_every_role_can_only_reach_the_modules_it_has_view_permission_for()
    {
        $roles = Role::with('permissions')->get();
        $this->assertNotEmpty($roles, 'No roles found - seed the RBAC roles before running this test.');

        $failures = [];

        foreach ($roles as $role) {
            $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
            $viewableModules = $role->permissions->where('action', 'view')->pluck('module')->all();

            foreach (self::MODULE_ROUTES as $module => $path) {
                $canView = $role->rank === 0 || in_array($module, $viewableModules, true);

                $response = $this->actingAs($user)->get($path);
                $status = $response->getStatusCode();

                if ($canView && $status !== 200) {
                    $failures[] = "{$role->name} SHOULD access {$module} ({$path}) but got {$status}";
                } elseif (!$canView && $status !== 403) {
                    $failures[] = "{$role->name} should NOT access {$module} ({$path}) but got {$status} (expected 403)";
                }
            }
        }

        $this->assertEmpty($failures, "Permission mismatches found:\n" . implode("\n", $failures));
    }

    /**
     * Super Admin isn't a code-level bypass in Role::hasPermission() -
     * it's granted every permission ROW that actually exists (per
     * RoleSeeder). So "has everything" means "has every real
     * permission that was ever seeded", not "true for any string you
     * ask about" - dashboard.create was never seeded for anyone
     * because dashboard isn't a CRUD resource.
     */
    public function test_super_admin_has_every_permission_that_exists()
    {
        $superAdmin = Role::with('permissions')->where('rank', 0)->first();
        $this->assertNotNull($superAdmin, 'No rank-0 Super Admin role found.');

        $user = User::factory()->create(['role_id' => $superAdmin->id, 'is_active' => true]);
        $allPermissions = \App\Models\Permission::all();
        $this->assertNotEmpty($allPermissions, 'No permissions were seeded.');

        foreach ($allPermissions as $permission) {
            $this->assertTrue(
                $user->hasPermission($permission->module, $permission->action),
                "Super Admin should have {$permission->action} on {$permission->module}"
            );
        }

        // And a module/action that was never seeded for anyone is
        // correctly false even for Super Admin - confirming there is
        // no blanket string-match bypass, just exhaustive grants.
        $this->assertFalse($user->hasPermission('dashboard', 'delete'));
        $this->assertFalse($user->hasPermission('nonexistent_module', 'view'));
    }

    /**
     * An inactive user must be blocked regardless of role/permissions -
     * the CheckPermission middleware checks is_active before hasPermission().
     */
    public function test_inactive_user_is_blocked_even_with_full_permissions()
    {
        $superAdmin = Role::where('rank', 0)->first();
        $user = User::factory()->create(['role_id' => $superAdmin->id, 'is_active' => false]);

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertStatus(403);
    }

    /**
     * Every abort_unless(hasPermission($module, $action)) guard found in
     * app/Http/Livewire/Admins, called directly on a fresh component
     * instance by a user who lacks that specific permission. Each of
     * these guards is written to run before touching any other state,
     * so a dummy id (999999) is safe even for methods that would
     * otherwise do a findOrFail - the abort fires first.
     */
    public static function writeActionGuards(): array
    {
        // delete_role() does Role::findOrFail() before its permission
        // check (to verify role-hierarchy first), so it needs a real
        // role id - a dummy one 404s before ever reaching the guard.
        // Data providers run before setUp()/seeding, so this can't
        // query the DB here - resolved to a real id inside the test
        // body instead, via this placeholder.
        $doctorRoleId = 'DOCTOR_ROLE_ID_PLACEHOLDER';

        return [
            'Services::add_service (create)' => [\App\Http\Livewire\Admins\Services::class, 'add_service', [], 'services', 'create'],
            'Services::update (update)' => [\App\Http\Livewire\Admins\Services::class, 'update', [999999], 'services', 'update'],
            'Services::delete (delete)' => [\App\Http\Livewire\Admins\Services::class, 'delete', [999999], 'services', 'delete'],

            'Employees::add_employee (create)' => [\App\Http\Livewire\Admins\Employees::class, 'add_employee', [], 'employees', 'create'],
            'Employees::update_employee (update)' => [\App\Http\Livewire\Admins\Employees::class, 'update_employee', [], 'employees', 'update'],
            'Employees::delete (delete)' => [\App\Http\Livewire\Admins\Employees::class, 'delete', [999999], 'employees', 'delete'],

            'Medicinestore::add_medicine (create)' => [\App\Http\Livewire\Admins\Medicinestore::class, 'add_medicine', [], 'medicines_store', 'create'],
            'Medicinestore::update (update)' => [\App\Http\Livewire\Admins\Medicinestore::class, 'update', [999999], 'medicines_store', 'update'],
            'Medicinestore::delete (delete)' => [\App\Http\Livewire\Admins\Medicinestore::class, 'delete', [999999], 'medicines_store', 'delete'],
            'Medicinestore::add_stock (update)' => [\App\Http\Livewire\Admins\Medicinestore::class, 'add_stock', [], 'medicines_store', 'update'],

            'RolesPermissions::show_create_role (create)' => [\App\Http\Livewire\Admins\RolesPermissions::class, 'show_create_role', [], 'roles', 'create'],
            'RolesPermissions::delete_role (delete)' => [\App\Http\Livewire\Admins\RolesPermissions::class, 'delete_role', [$doctorRoleId], 'roles', 'delete'],
            'RolesPermissions::show_create_user (create)' => [\App\Http\Livewire\Admins\RolesPermissions::class, 'show_create_user', [], 'users', 'create'],

            'Appiontment::save (create)' => [\App\Http\Livewire\Admins\Appiontment::class, 'save', [], 'appointments', 'create'],
            'Appiontment::delete (delete)' => [\App\Http\Livewire\Admins\Appiontment::class, 'delete', [999999], 'appointments', 'delete'],

            'Patients::add_patient (create)' => [\App\Http\Livewire\Admins\Patients::class, 'add_patient', [], 'patients', 'create'],
            'Patients::update (update)' => [\App\Http\Livewire\Admins\Patients::class, 'update', [999999], 'patients', 'update'],
            'Patients::prompt_delete (delete)' => [\App\Http\Livewire\Admins\Patients::class, 'prompt_delete', [999999], 'patients', 'delete'],

            'ConsultationForms::save (create)' => [\App\Http\Livewire\Admins\ConsultationForms::class, 'save', [], 'consultation_form', 'create'],
            'ConsultationForms::delete (delete)' => [\App\Http\Livewire\Admins\ConsultationForms::class, 'delete', [999999], 'consultation_form', 'delete'],

            'Invoices::generate_invoice (create)' => [\App\Http\Livewire\Admins\Invoices::class, 'generate_invoice', [], 'invoices', 'create'],
            'Invoices::prompt_delete (delete)' => [\App\Http\Livewire\Admins\Invoices::class, 'prompt_delete', [999999], 'invoices', 'delete'],
        ];
    }

    /** @dataProvider writeActionGuards */
    public function test_write_action_is_blocked_for_a_role_without_that_permission($componentClass, $method, $args, $module, $action)
    {
        $args = array_map(function ($arg) {
            return $arg === 'DOCTOR_ROLE_ID_PLACEHOLDER' ? Role::where('name', 'Doctor')->value('id') : $arg;
        }, $args);

        // Nurse has no create/update/delete on any of these modules per
        // RoleSeeder (view-only or nothing at all) - a safe "definitely
        // lacks this permission" role for every guard in the list.
        $nurse = Role::with('permissions')->where('name', 'Nurse')->first();
        $this->assertNotNull($nurse, 'Nurse role not found.');
        $this->assertFalse(
            $nurse->hasPermission($module, $action),
            "Test setup invalid: Nurse actually has {$action} on {$module}, so this isn't a valid 'lacks permission' case."
        );

        $user = User::factory()->create(['role_id' => $nurse->id, 'is_active' => true]);
        \Illuminate\Support\Facades\Auth::login($user);

        $component = new $componentClass();

        try {
            $component->$method(...$args);
            $this->fail("{$componentClass}::{$method}() should have aborted for a role without {$module}.{$action}, but it did not throw.");
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
