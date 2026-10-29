<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\RolesPermissions;
use App\Models\Role;
use App\Models\User;
use App\Support\AppSwitch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AppSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        config(['app.switch' => [
            'url' => 'https://dental.example.com/login',
            'label' => 'Dental',
            'secret' => str_repeat('k', 48),
            'this_label' => 'Aesthetics',
        ]]);
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['email' => 'admin@clinic.pk', 'can_switch_app' => true], $attributes));
    }

    public function test_the_button_shows_only_for_users_with_access()
    {
        $this->actingAs($this->user())->get(route('admin_dashboard'))->assertSee('Switch to Dental');
        $this->actingAs($this->user(['email' => 'other@clinic.pk', 'can_switch_app' => false]))->get(route('admin_dashboard'))->assertDontSee('Switch to Dental');
    }

    public function test_go_redirects_to_the_other_app_with_a_signed_token()
    {
        $response = $this->actingAs($this->user())->get(route('app_switch_go'));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://dental.example.com/switch/receive?token=', $response->headers->get('Location'));
    }

    public function test_go_is_refused_without_access()
    {
        $this->actingAs($this->user(['can_switch_app' => false]))->get(route('app_switch_go'))
            ->assertForbidden()->assertSee("You don't have access to JF Aesthetics Dental", false);
    }

    public function test_a_valid_token_logs_in_the_matching_account_without_a_password()
    {
        $user = $this->user(['email' => 'Admin@Clinic.pk']);
        $token = AppSwitch::makeToken($user);

        $this->get(route('app_switch_receive', ['token' => $token]))->assertRedirect(route($user->landingRouteName()));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_token_works_only_once()
    {
        $user = $this->user();
        $token = AppSwitch::makeToken($user);

        $this->get(route('app_switch_receive', ['token' => $token]))->assertRedirect();
        auth()->logout();

        $this->get(route('app_switch_receive', ['token' => $token]))->assertForbidden();
        $this->assertGuest();
    }

    public function test_expired_forged_or_wrong_secret_tokens_are_refused()
    {
        $user = $this->user();

        $expired = AppSwitch::makeToken($user, time() - 120);
        $this->get(route('app_switch_receive', ['token' => $expired]))->assertForbidden();

        [$payload] = explode('.', AppSwitch::makeToken($user));
        $this->get(route('app_switch_receive', ['token' => $payload . '.forged']))->assertForbidden();

        $token = AppSwitch::makeToken($user);
        config(['app.switch.secret' => str_repeat('x', 48)]);
        $this->get(route('app_switch_receive', ['token' => $token]))->assertForbidden();

        $this->assertGuest();
    }

    public function test_no_account_or_an_inactive_account_on_this_site_gets_no_access()
    {
        $nobody = new User(['email' => 'not-here@clinic.pk']);
        $this->get(route('app_switch_receive', ['token' => AppSwitch::makeToken($nobody)]))
            ->assertForbidden()->assertSee("You don't have access to JF Aesthetics Aesthetics", false);

        $inactive = $this->user(['is_active' => false]);
        $this->get(route('app_switch_receive', ['token' => AppSwitch::makeToken($inactive)]))->assertForbidden();

        $this->assertGuest();
    }

    public function test_switching_is_off_when_the_secret_is_missing_or_short()
    {
        config(['app.switch.secret' => 'short']);
        $user = $this->user();

        $this->actingAs($user)->get(route('app_switch_go'))->assertForbidden();
        $this->actingAs($user)->get(route('admin_dashboard'))->assertDontSee('Switch to Dental');
    }

    public function test_a_super_admin_can_toggle_access_in_the_users_list_including_their_own()
    {
        $super = User::factory()->create(['email' => 'super@clinic.pk', 'can_switch_app' => false]);

        Livewire::actingAs($super)->test(RolesPermissions::class)->call('toggle_switch_access', $super->id);
        $this->assertTrue($super->fresh()->can_switch_app);

        $admin = User::factory()->create(['email' => 'boss@clinic.pk', 'role_id' => Role::where('name', 'Admin')->value('id')]);
        Livewire::actingAs($admin)->test(RolesPermissions::class)->call('toggle_switch_access', $admin->id)->assertForbidden();
        $this->assertFalse($admin->fresh()->can_switch_app);
    }

    public function test_only_a_super_admin_can_grant_access()
    {
        $target = $this->user(['email' => 'reception@clinic.pk', 'can_switch_app' => false, 'role_id' => Role::where('name', 'Receptionist')->value('id')]);
        $admin = User::factory()->create(['email' => 'boss@clinic.pk', 'role_id' => Role::where('name', 'Admin')->value('id')]);

        Livewire::actingAs($admin)->test(RolesPermissions::class)->call('edit_user', $target->id)->set('user_can_switch_app', true)->call('save_user');
        $this->assertFalse($target->fresh()->can_switch_app);

        Livewire::actingAs(User::factory()->create(['email' => 'super@clinic.pk']))->test(RolesPermissions::class)
            ->call('edit_user', $target->id)->set('user_can_switch_app', true)->call('save_user');
        $this->assertTrue($target->fresh()->can_switch_app);
    }
}
