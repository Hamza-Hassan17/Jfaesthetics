<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\patient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_a_user_with_reports_export_permission_can_download_the_csv()
    {
        $accountantRole = Role::where('name', 'Accountant')->first();
        $this->assertTrue($accountantRole->hasPermission('reports', 'export'));

        $user = User::factory()->create(['role_id' => $accountantRole->id, 'is_active' => true]);

        $patient = patient::create([
            'name' => 'Export Test Patient', 'email' => 'export@example.com', 'phone' => '923005556677',
            'address' => 'Karachi', 'gender' => 'Female', 'age' => 30, 'bloodgroup' => 'O+',
        ]);
        Invoice::create(['invoice_number' => 'EXP-1', 'patient_id' => $patient->id]);

        $response = $this->actingAs($user)->get(route('admin_reports_export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('EXP-1', $response->streamedContent());
    }

    public function test_a_user_without_reports_export_permission_is_blocked()
    {
        $doctorRole = Role::where('name', 'Doctor')->first();
        $this->assertFalse($doctorRole->hasPermission('reports', 'export'));

        $user = User::factory()->create(['role_id' => $doctorRole->id, 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('admin_reports_export'));

        $response->assertStatus(403);
    }
}
