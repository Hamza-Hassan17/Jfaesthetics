<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Invoices;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use App\Models\patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReceptionistInvoiceVisibilityTest extends TestCase
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
            'name' => 'Receptionist Visibility Patient',
            'email' => 'reception-visibility@example.com',
            'phone' => '923000000222',
            'address' => 'Karachi',
            'gender' => 'Female',
            'age' => 30,
            'bloodgroup' => 'O+',
        ]);
    }

    public function test_a_receptionist_can_see_invoices_created_by_other_users()
    {
        $patient = $this->patient();

        $receptionistRole = Role::where('name', 'Receptionist')->first();
        $otherReceptionistRole = Role::where('name', 'Receptionist')->first();

        $creator = User::factory()->create(['role_id' => $otherReceptionistRole->id, 'is_active' => true]);
        $viewer = User::factory()->create(['role_id' => $receptionistRole->id, 'is_active' => true]);

        $invoice = Invoice::create([
            'invoice_number' => 'RECEP-1',
            'patient_id' => $patient->id,
            'created_by' => $creator->id,
        ]);

        Livewire::actingAs($viewer)
            ->test(Invoices::class)
            ->assertSee('RECEP-1');
    }

    public function test_a_doctor_still_only_sees_their_own_invoices()
    {
        $patient = $this->patient();

        $doctorRole = Role::where('name', 'Doctor')->first();
        $creator = User::factory()->create(['role_id' => $doctorRole->id, 'is_active' => true]);
        $viewer = User::factory()->create(['role_id' => $doctorRole->id, 'is_active' => true]);

        Invoice::create([
            'invoice_number' => 'DOC-OTHER',
            'patient_id' => $patient->id,
            'created_by' => $creator->id,
        ]);

        Livewire::actingAs($viewer)
            ->test(Invoices::class)
            ->assertDontSee('DOC-OTHER');
    }
}
