<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\RolesPermissions;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeleteUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_super_admin_can_permanently_delete_a_user_with_any_role()
    {
        $superAdmin = Role::where('rank', 0)->first();
        $otherSuperAdminRole = Role::where('rank', 0)->first();
        $actingUser = User::factory()->create(['role_id' => $superAdmin->id, 'is_active' => true]);

        $nurseRole = Role::where('name', 'Nurse')->first();
        $target = User::factory()->create(['role_id' => $nurseRole->id, 'is_active' => true]);

        Auth::login($actingUser);
        $component = new RolesPermissions();
        $component->delete_user($target->id);

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertTrue(
            ActivityLog::where('module', 'users')->where('action', 'deleted')->where('record_id', $target->id)->exists()
        );
    }

    public function test_super_admin_can_delete_another_super_admin()
    {
        $superAdminRole = Role::where('rank', 0)->first();
        $actingUser = User::factory()->create(['role_id' => $superAdminRole->id, 'is_active' => true]);
        $otherSuperAdmin = User::factory()->create(['role_id' => $superAdminRole->id, 'is_active' => true]);

        Auth::login($actingUser);
        $component = new RolesPermissions();
        $component->delete_user($otherSuperAdmin->id);

        $this->assertDatabaseMissing('users', ['id' => $otherSuperAdmin->id]);
    }

    public function test_a_user_cannot_delete_their_own_account()
    {
        $superAdminRole = Role::where('rank', 0)->first();
        $actingUser = User::factory()->create(['role_id' => $superAdminRole->id, 'is_active' => true]);

        Auth::login($actingUser);
        $component = new RolesPermissions();
        $component->delete_user($actingUser->id);

        $this->assertDatabaseHas('users', ['id' => $actingUser->id]);
    }

    public function test_admin_cannot_delete_users_lacking_the_users_delete_permission()
    {
        $adminRole = Role::where('name', 'Admin')->first();
        $this->assertFalse($adminRole->hasPermission('users', 'delete'), 'Test setup invalid: Admin should not have users.delete.');

        $actingUser = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $nurseRole = Role::where('name', 'Nurse')->first();
        $target = User::factory()->create(['role_id' => $nurseRole->id, 'is_active' => true]);

        Auth::login($actingUser);
        $component = new RolesPermissions();

        try {
            $component->delete_user($target->id);
            $this->fail('Admin should not be able to delete a user without the users.delete permission.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_deleting_a_user_does_not_delete_their_activity_log_entries()
    {
        $superAdminRole = Role::where('rank', 0)->first();
        $actingUser = User::factory()->create(['role_id' => $superAdminRole->id, 'is_active' => true]);
        $target = User::factory()->create(['role_id' => $superAdminRole->id, 'is_active' => true]);

        ActivityLog::create([
            'user_id' => $target->id,
            'action' => 'created',
            'module' => 'patients',
            'record_id' => 1,
            'description' => 'Created patient before being deleted.',
            'ip_address' => '127.0.0.1',
        ]);

        Auth::login($actingUser);
        $component = new RolesPermissions();
        $component->delete_user($target->id);

        $log = ActivityLog::where('description', 'Created patient before being deleted.')->first();
        $this->assertNotNull($log, 'The activity log row should survive the user being deleted (nullOnDelete).');
        $this->assertNull($log->user_id);
    }
}
