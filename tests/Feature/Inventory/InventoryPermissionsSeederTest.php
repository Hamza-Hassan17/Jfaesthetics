<?php

namespace Tests\Feature\Inventory;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\InventoryPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Simulates live before the inventory deploy: inventory permissions
     * don't exist yet, medicines_store does, and an admin has customised
     * roles through the Roles & Permissions screen.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        Permission::where('module', 'inventory')->delete();
        $store = Permission::create(['module' => 'medicines_store', 'action' => 'view']);

        $doctor = Role::where('name', 'Doctor')->first();
        $doctor->permissions()->detach(Permission::where('module', 'patients')->where('action', 'update')->value('id'));
        $doctor->permissions()->attach(Permission::where('module', 'invoices')->where('action', 'view')->value('id'));

        $custom = Role::create(['name' => 'Pharmacist', 'rank' => 2, 'is_system_role' => false]);
        $custom->permissions()->attach([$store->id, Permission::where('module', 'patients')->where('action', 'view')->value('id')]);
    }

    private function actions(string $roleName): array
    {
        return Role::where('name', $roleName)->first()->permissions()->where('module', 'inventory')->pluck('action')->sort()->values()->all();
    }

    private function has(string $roleName, string $module, string $action): bool
    {
        return Role::where('name', $roleName)->first()->permissions()->where('module', $module)->where('action', $action)->exists();
    }

    public function test_it_grants_the_approved_mapping()
    {
        $this->seed(InventoryPermissionsSeeder::class);

        $this->assertCount(13, $this->actions('Super Admin'));
        $this->assertCount(13, $this->actions('Admin'));
        $this->assertEquals(collect(InventoryPermissionsSeeder::MAPPING['Accountant'])->sort()->values()->all(), $this->actions('Accountant'));
        $this->assertEquals(['manage_recipes', 'view'], $this->actions('Doctor'));
        $this->assertEquals(['patient_returns', 'view'], $this->actions('Receptionist'));
        $this->assertEquals(['view'], $this->actions('Nurse'));
    }

    public function test_it_leaves_live_customisations_alone()
    {
        $this->seed(InventoryPermissionsSeeder::class);

        $this->assertFalse($this->has('Doctor', 'patients', 'update'), 'A grant removed on live must not come back.');
        $this->assertTrue($this->has('Doctor', 'invoices', 'view'), 'A grant added on live must be kept.');
        $this->assertTrue($this->has('Pharmacist', 'patients', 'view'));
    }

    public function test_roles_that_could_see_the_medicines_store_keep_inventory_view_and_the_old_rows_go()
    {
        $this->seed(InventoryPermissionsSeeder::class);

        $this->assertEquals(['view'], $this->actions('Pharmacist'));
        $this->assertEquals(0, Permission::where('module', 'medicines_store')->count());
    }

    public function test_running_it_twice_changes_nothing()
    {
        $this->seed(InventoryPermissionsSeeder::class);
        $snapshot = Role::with('permissions')->get()->mapWithKeys(fn ($r) => [$r->name => $r->permissions->pluck('id')->sort()->values()->all()]);

        $this->seed(InventoryPermissionsSeeder::class);
        $again = Role::with('permissions')->get()->mapWithKeys(fn ($r) => [$r->name => $r->permissions->pluck('id')->sort()->values()->all()]);

        $this->assertEquals($snapshot, $again);
        $this->assertEquals(13, Permission::where('module', 'inventory')->count());
    }

    public function test_it_does_not_rename_or_rerank_roles()
    {
        Role::where('id', 1)->update(['name' => 'Owner']);

        $this->seed(InventoryPermissionsSeeder::class);

        $this->assertEquals('Owner', Role::find(1)->name);
        $this->assertCount(13, $this->actions('Owner'), 'Rank 0 gets everything whatever it is called.');
    }
}
