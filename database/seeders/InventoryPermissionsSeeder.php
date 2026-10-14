<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Deploy-safe seeder for the Inventory module. Unlike RoleSeeder (which
 * sync()s every system role back to its defaults), this only ADDS:
 *
 *   - creates the inventory permission rows if missing
 *   - grants them per the approved role mapping, on top of whatever each
 *     role already has (syncWithoutDetaching)
 *   - gives inventory.view to any other role that could see the old
 *     Medicines Store, then deletes the medicines_store permission rows
 *
 * Every other grant, role name and rank on live is left untouched. Safe to
 * run more than once.
 */
class InventoryPermissionsSeeder extends Seeder
{
    /** Role name => inventory actions (docs/inventory-spec.md, Permissions). */
    public const MAPPING = [
        'Admin' => RoleSeeder::INVENTORY_ACTIONS,
        'Accountant' => [
            'view', 'manage_items', 'manage_po', 'manage_grn', 'manage_suppliers', 'supplier_payments',
            'supplier_returns', 'patient_returns', 'manage_adjustments', 'view_reports', 'view_cost',
        ],
        'Doctor' => ['view', 'manage_recipes'],
        'Receptionist' => ['view', 'patient_returns'],
        'Nurse' => ['view'],
    ];

    public function run()
    {
        $ids = [];
        foreach (RoleSeeder::INVENTORY_ACTIONS as $action) {
            $ids[$action] = Permission::firstOrCreate(['module' => 'inventory', 'action' => $action])->id;
        }

        // Rank 0 (Super Admin) gets everything, whatever it is named on live.
        foreach (Role::where('rank', 0)->get() as $role) {
            $role->permissions()->syncWithoutDetaching(array_values($ids));
            $this->report($role, 'all inventory actions');
        }

        foreach (self::MAPPING as $roleName => $actions) {
            $role = Role::where('name', $roleName)->where('rank', '>', 0)->first();
            if (!$role) {
                $this->report(null, "role '{$roleName}' not found, skipped");
                continue;
            }
            $role->permissions()->syncWithoutDetaching(array_map(fn ($action) => $ids[$action], $actions));
            $this->report($role, implode(', ', $actions));
        }

        // Custom roles that could see the Medicines Store keep seeing stock.
        $oldStore = Permission::where('module', 'medicines_store')->pluck('id');
        if ($oldStore->isNotEmpty()) {
            $roles = Role::whereHas('permissions', fn ($q) => $q->whereIn('permissions.id', $oldStore))->get();
            foreach ($roles as $role) {
                $role->permissions()->syncWithoutDetaching([$ids['view']]);
                $this->report($role, 'view (had Medicines Store access)');
            }
            Permission::whereIn('id', $oldStore)->delete(); // role_permissions rows cascade
        }
    }

    private function report(?Role $role, string $message): void
    {
        if ($this->command) {
            $this->command->line(($role ? "  {$role->name}: " : '  ') . $message);
        }
    }
}
