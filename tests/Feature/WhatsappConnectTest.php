<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\WhatsappConnect;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsappConnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        config(['app.whatsapp' => ['url' => 'http://wa.test', 'token' => str_repeat('t', 40)]]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['email' => 'super@clinic.pk']);
    }

    public function test_the_page_follows_invoice_access_and_only_a_super_admin_gets_the_qr()
    {
        Http::fake(['wa.test/*' => Http::response(['state' => 'qr', 'number' => null, 'qr' => 'data:image/png;base64,AAAA'])]);
        $admin = User::factory()->create(['email' => 'boss@clinic.pk', 'role_id' => Role::where('name', 'Admin')->value('id')]);
        $noInvoices = User::factory()->create(['email' => 'none@clinic.pk', 'role_id' => Role::create(['name' => 'No Access', 'rank' => 99])->id]);

        $this->actingAs($noInvoices)->get(route('admin_whatsapp'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin_whatsapp'))->assertOk()->assertSee('Ask a Super Admin')->assertDontSee('Link a Device');
        $this->actingAs($this->superAdmin())->get(route('admin_whatsapp'))->assertOk()->assertSee('Link a Device');
        Livewire::actingAs($admin)->test(WhatsappConnect::class)->call('disconnect')->assertForbidden();
    }

    public function test_it_shows_the_qr_and_survives_the_service_being_down()
    {
        Http::fakeSequence('wa.test/*')
            ->push(['state' => 'qr', 'number' => null, 'qr' => 'data:image/png;base64,AAAA'])
            ->push('', 500);

        $component = Livewire::actingAs($this->superAdmin())->test(WhatsappConnect::class);
        $component->assertSee('Link a Device');
        $component->call('$refresh')->assertSee('offline');
    }

    public function test_a_test_message_goes_to_the_normalised_number_with_the_token()
    {
        Http::fake(['wa.test/*' => Http::response(['state' => 'connected', 'number' => '923001234567', 'qr' => null, 'ok' => true])]);

        Livewire::actingAs($this->superAdmin())->test(WhatsappConnect::class)
            ->set('test_number', '0317-8414882')
            ->call('send_test');

        Http::assertSent(fn ($request) => $request->url() === 'http://wa.test/send'
            && $request['to'] === '923178414882'
            && $request->hasHeader('X-Token', str_repeat('t', 40)));
    }
}
