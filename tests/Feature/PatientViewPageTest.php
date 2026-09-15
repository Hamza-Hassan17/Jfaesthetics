<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Patients;
use App\Models\Role;
use App\Models\User;
use App\Models\patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PatientViewPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function patient(): patient
    {
        return patient::create([
            'name' => 'View Test Patient',
            'email' => 'viewtest@example.com',
            'phone' => '923001112233',
            'address' => 'Karachi',
            'gender' => 'Female',
            'age' => 33,
            'bloodgroup' => 'A+',
        ]);
    }

    public function test_a_user_with_patients_view_permission_can_render_the_patient_view_page()
    {
        $accountantRole = Role::where('name', 'Accountant')->first();
        $this->assertTrue($accountantRole->hasPermission('patients', 'view'));

        $user = User::factory()->create(['role_id' => $accountantRole->id, 'is_active' => true]);
        $patient = $this->patient();

        Auth::login($user);
        $component = new Patients();
        $component->view($patient->id);

        $this->assertEquals('view', $component->_page);

        $html = $component->render()->render();
        $this->assertStringContainsString('View Test Patient', $html);
    }

    public function test_patients_index_search_filters_by_name_and_sorts_alphabetically()
    {
        $superAdmin = Role::where('rank', 0)->first();
        $user = User::factory()->create(['role_id' => $superAdmin->id, 'is_active' => true]);

        patient::create(['name' => 'Zara Khan', 'phone' => '923001110001', 'address' => 'Karachi', 'gender' => 'Female', 'age' => 25]);
        patient::create(['name' => 'Amna Malik', 'phone' => '923001110002', 'address' => 'Karachi', 'gender' => 'Female', 'age' => 28]);
        patient::create(['name' => 'Bilal Ahmed', 'phone' => '923001110003', 'address' => 'Karachi', 'gender' => 'Male', 'age' => 30]);

        $response = $this->actingAs($user)->get(route('admin_patients'));
        $html = $response->getContent();
        $amnaPos = strpos($html, 'Amna Malik');
        $bilalPos = strpos($html, 'Bilal Ahmed');
        $zaraPos = strpos($html, 'Zara Khan');
        $this->assertTrue($amnaPos < $bilalPos && $bilalPos < $zaraPos, 'Patients should be sorted alphabetically by name.');

        Livewire::test(Patients::class)
            ->set('search', 'Amna')
            ->assertSee('Amna Malik')
            ->assertDontSee('Zara Khan');
    }

    public function test_a_user_without_patients_view_permission_is_blocked()
    {
        // No role has patients.view revoked while everything else is
        // granted in RoleSeeder, so build a bespoke role for this case.
        $role = Role::create(['name' => 'NoPatientView', 'rank' => 5]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $patient = $this->patient();

        Auth::login($user);
        $component = new Patients();

        try {
            $component->view($patient->id);
            $this->fail('Expected a 403 for a role without patients.view.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
