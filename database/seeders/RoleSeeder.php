<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Rank: 0 = highest authority (Super Admin), larger = lower authority.
     * A role can only manage (edit permissions of / assign / delete) a role
     * with a strictly larger rank than its own (see Role::outranks()).
     *
     * The two roles that already existed in this app ('admin', id 1 and
     * 'accountant', id 2) are updated IN PLACE rather than recreated, so
     * existing users' role_id foreign keys keep working.
     */
    public function run()
    {
        $superAdmin = Role::updateOrCreate(
            ['id' => 1],
            ['name' => 'Super Admin', 'rank' => 0, 'is_system_role' => true]
        );

        $accountant = Role::updateOrCreate(
            ['id' => 2],
            ['name' => 'Accountant', 'rank' => 2, 'is_system_role' => true]
        );

        $admin = Role::updateOrCreate(
            ['name' => 'Admin'],
            ['rank' => 1, 'is_system_role' => true]
        );

        $doctor = Role::updateOrCreate(
            ['name' => 'Doctor'],
            ['rank' => 2, 'is_system_role' => true]
        );

        $receptionist = Role::updateOrCreate(
            ['name' => 'Receptionist'],
            ['rank' => 2, 'is_system_role' => true]
        );

        $nurse = Role::updateOrCreate(
            ['name' => 'Nurse'],
            ['rank' => 2, 'is_system_role' => true]
        );

        // module => ['view', 'create', 'update', 'delete', ...]
        $allActions = ['view', 'create', 'update', 'delete'];
        $modules = [
            'dashboard' => ['view'],
            'patients' => $allActions,
            'appointments' => $allActions,
            'employees' => $allActions,
            'invoices' => $allActions,
            'consultation_form' => $allActions,
            'medicines_store' => $allActions,
            'services' => $allActions,
            'reports' => ['view', 'export'],
            'roles' => ['view', 'create', 'update', 'delete', 'assign'],
            'users' => $allActions,
            'settings' => ['view', 'update'],
            // Legacy / not-yet-productized modules: kept restricted to
            // Super Admin + Admin so they stay data-driven instead of
            // hardcoded, without cluttering the spec's core matrix.
            'legacy_modules' => ['view'],
        ];

        $permissionIds = []; // "module.action" => id
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $permission = Permission::updateOrCreate(['module' => $module, 'action' => $action]);
                $permissionIds["$module.$action"] = $permission->id;
            }
        }

        $grant = function (Role $role, array $keys) use ($permissionIds) {
            $ids = collect($keys)
                ->map(fn ($key) => $permissionIds[$key] ?? null)
                ->filter()
                ->values()
                ->all();
            $role->permissions()->sync($ids);
        };

        $everyPermission = array_values($permissionIds);

        // ---- Super Admin: everything. ----
        $superAdmin->permissions()->sync($everyPermission);

        // ---- Admin: near-full, excludes irreversible/system-level actions. ----
        $grant($admin, [
            'dashboard.view',
            'patients.view', 'patients.create', 'patients.update', 'patients.delete',
            'appointments.view', 'appointments.create', 'appointments.update', 'appointments.delete',
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete',
            'consultation_form.view', 'consultation_form.create', 'consultation_form.update', 'consultation_form.delete',
            'medicines_store.view', 'medicines_store.create', 'medicines_store.update', 'medicines_store.delete',
            'services.view', 'services.create', 'services.update', 'services.delete',
            'reports.view', // no 'reports.export' — "no export of raw financials" per spec
            'roles.view', 'roles.assign', // limited: view + assign only (Section 5)
            'users.view', 'users.create', 'users.update', // cannot delete; rank rule blocks Admin/Super Admin account creation
            'settings.view', 'settings.update',
            'legacy_modules.view',
        ]);

        // ---- Doctor: scoped to own patients/appointments/consultations. ----
        // "Own" scoping is enforced in query logic (Section 7), not here —
        // these permission rows just grant the module itself.
        $grant($doctor, [
            'patients.view', 'patients.update', // own patients, view/edit only
            'appointments.view', 'appointments.create', 'appointments.update', 'appointments.delete', // own
            'consultation_form.view', 'consultation_form.create', 'consultation_form.update', 'consultation_form.delete', // own
            'medicines_store.view',
            'services.view',
        ]);

        // ---- Receptionist: front-desk. ----
        $grant($receptionist, [
            'patients.view', 'patients.create', 'patients.update', // no delete
            'appointments.view', 'appointments.create', 'appointments.update', 'appointments.delete',
            'invoices.view', 'invoices.create', // limited: create/view only
            'services.view',
        ]);

        // ---- Accountant: billing/finance. ----
        $grant($accountant, [
            'dashboard.view',
            'patients.view', // read-only for billing context
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete',
            'medicines_store.view',
            'services.view',
            'reports.view', 'reports.export',
        ]);

        // ---- Nurse: clinical support staff (prep/assist, no caseload of their own). ----
        $grant($nurse, [
            'patients.view',
            'appointments.view', 'appointments.update',
            'consultation_form.view',
            'medicines_store.view',
            'services.view',
        ]);
    }
}
