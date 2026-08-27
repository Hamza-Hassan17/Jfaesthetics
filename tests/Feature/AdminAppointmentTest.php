<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Appiontment;
use App\Models\patient;
use App\Models\User;
use Database\Seeders\MediLifeDoctorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAppointmentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function creating_an_appointment_auto_assigns_the_fixed_medilife_doctor_and_fee()
    {
        $this->actingAs(User::factory()->create());
        (new MediLifeDoctorSeeder())->run();

        $patient = patient::create([
            'name' => 'Test Patient', 'email' => 'p@example.com', 'phone' => '923001112233',
            'address' => 'Karachi', 'gender' => 'Female', 'age' => 30, 'bloodgroup' => 'O+',
        ]);

        Livewire::test(Appiontment::class)
            ->call('show_create_form')
            ->set('patient_id', $patient->id)
            ->set('intime', '2026-09-01T09:00')
            ->set('description', 'Routine checkup')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'intime' => '2026-09-01 09:00:00',
            'description' => 'Routine checkup',
            'consultation_fee' => Appiontment::CONSULTATION_FEE,
        ]);

        $appointment = \App\Models\appointment::where('patient_id', $patient->id)->first();
        $this->assertEquals('Dr. Fabreen Naz', $appointment->doctor->employ->name);
        $this->assertNotEmpty($appointment->case_no);
    }
}
