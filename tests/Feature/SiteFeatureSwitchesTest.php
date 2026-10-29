<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Invoices;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DentalServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteFeatureSwitchesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['email' => 'super@clinic.pk']);
    }

    public function test_a_disabled_module_is_hidden_and_blocked_for_everyone()
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->get(route('admin_consultation_forms'))->assertOk();

        config(['app.disabled_modules' => ['consultation_form']]);
        $user = $user->fresh();

        $this->assertFalse($user->hasAnyPermissionFor('consultation_form'));
        $this->actingAs($user)->get(route('admin_consultation_forms'))->assertForbidden();
        $this->actingAs($user)->get(route('admin_dashboard'))->assertDontSee('Consultation Form');
    }

    public function test_custom_invoice_lines_can_be_switched_off()
    {
        config(['app.custom_invoice_lines' => false]);

        Livewire::actingAs($this->superAdmin())->test(Invoices::class)
            ->call('show_create_form')
            ->assertSet('items.0.type', 'service')
            ->assertDontSee('Custom / Typed')
            ->set('items.0.type', 'custom')
            ->set('items.0.service', 'Anything typed')
            ->set('items.0.service_charges', 500)
            ->call('generate_invoice')
            ->assertHasErrors('items.0.type');
    }

    public function test_the_dental_price_list_seeds_once()
    {
        $this->seed(DentalServiceSeeder::class);
        $this->seed(DentalServiceSeeder::class);

        $this->assertSame(56, Service::count());
        $this->assertSame(12, Service::distinct()->count('category'));
        $rct = Service::where('name', 'Rotary Root Canal (Posterior Tooth)')->first();
        $this->assertEquals(25000, $rct->price);
        $this->assertSame('Root Canal', $rct->category);
        $this->assertSame(['Bone Grafts', 'Chinese Implant', 'German Implant', 'Immediate Implant with Fix Tooth in 72 Hours', 'Korean Implant', 'Membranes', 'USA Implant'],
            Service::where('category', 'Implants')->orderBy('name')->pluck('name')->all());
    }
}
