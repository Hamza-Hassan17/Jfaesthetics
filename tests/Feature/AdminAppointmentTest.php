<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Appiontment;
use App\Models\doctor;
use App\Models\employee;
use App\Models\nurse;
use App\Models\patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAppointmentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function creating_an_admin_appointment_saves_intime_outtime_and_nurse_correctly()
    {
        $this->actingAs(User::factory()->create());

        $patient = patient::create([
            'name' => 'Test Patient', 'email' => 'p@example.com', 'phone' => '123',
            'address' => 'Karachi', 'gender' => 'Female', 'age' => 30, 'bloodgroup' => 'O+',
        ]);
        $employee = employee::create([
            'name' => 'Dr. Smith', 'email' => 'smith@example.com', 'phone' => '456',
            'position' => 'doctor', 'status' => 'active',
        ]);
        $doctor = doctor::create(['employee_id' => $employee->id]);
        $nurse = nurse::create([
            'name' => 'Nurse Joy', 'email' => 'joy@example.com', 'phone' => '789',
            'gender' => 'Female', 'address' => 'Karachi', 'qualification' => 'BSN',
            'position' => 'Staff Nurse', 'registered' => 1,
        ]);

        Livewire::test(Appiontment::class)
            ->call('show_create_form')
            ->set('patient_id', $patient->id)
            ->set('doctor_id', $doctor->id)
            ->set('nurse_id', $nurse->id)
            ->set('intime', '2026-09-01T09:00')
            ->set('outtime', '2026-09-01T09:30')
            ->set('description', 'Routine checkup')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'nurse_id' => $nurse->id,
            'intime' => '2026-09-01 09:00:00',
            'outtime' => '2026-09-01 09:30:00',
            'description' => 'Routine checkup',
        ]);
    }
}
