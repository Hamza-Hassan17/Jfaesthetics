<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use App\Http\Livewire\Admins\Profile;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_any_authenticated_user_can_reach_their_own_profile_page()
    {
        $nurseRole = Role::where('name', 'Nurse')->first();
        $user = User::factory()->create(['role_id' => $nurseRole->id, 'is_active' => true, 'name' => 'Test Nurse']);

        $response = $this->actingAs($user)->get(route('admin_profile'));

        $response->assertStatus(200);
        $response->assertSee('Test Nurse');
    }

    public function test_a_user_can_update_their_own_name_and_email()
    {
        $superAdmin = Role::where('rank', 0)->first();
        $user = User::factory()->create(['role_id' => $superAdmin->id, 'is_active' => true, 'name' => 'Old Name']);

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->call('edit')
            ->set('name', 'New Name')
            ->set('email', 'new-email@example.com')
            ->call('save')
            ->assertSet('editing', false);

        $this->assertEquals('New Name', $user->fresh()->name);
        $this->assertEquals('new-email@example.com', $user->fresh()->email);
        $this->assertTrue(
            ActivityLog::where('user_id', $user->id)->where('description', 'Updated own profile.')->exists()
        );
    }

    public function test_a_user_cannot_take_another_users_email()
    {
        $superAdmin = Role::where('rank', 0)->first();
        $user = User::factory()->create(['role_id' => $superAdmin->id, 'is_active' => true]);
        $other = User::factory()->create(['role_id' => $superAdmin->id, 'is_active' => true, 'email' => 'taken@example.com']);

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->call('edit')
            ->set('email', 'taken@example.com')
            ->call('save')
            ->assertHasErrors(['email']);
    }

    public function test_a_user_can_change_their_own_password_with_the_correct_current_password()
    {
        $superAdmin = Role::where('rank', 0)->first();
        $user = User::factory()->create(['role_id' => $superAdmin->id, 'is_active' => true]);

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->call('showChangePassword')
            ->set('current_password', 'password')
            ->set('new_password', 'newpass123')
            ->set('new_password_confirmation', 'newpass123')
            ->call('changePassword')
            ->assertSet('changing_password', false);

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }

    public function test_changing_password_fails_with_wrong_current_password()
    {
        $superAdmin = Role::where('rank', 0)->first();
        $user = User::factory()->create(['role_id' => $superAdmin->id, 'is_active' => true]);

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->call('showChangePassword')
            ->set('current_password', 'wrong-password')
            ->set('new_password', 'newpass123')
            ->set('new_password_confirmation', 'newpass123')
            ->call('changePassword')
            ->assertHasErrors(['current_password']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
