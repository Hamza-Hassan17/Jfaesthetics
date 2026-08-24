<?php

namespace Tests\Feature;

use App\Http\Livewire\Appointmentform;
use App\Models\doctor;
use App\Models\employee;
use App\Models\requestedAppointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AppointmentBookingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function booking_a_public_appointment_saves_the_correct_doctor_id()
    {
        $employee = employee::create([
            'name' => 'Dr. Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '03001234567',
            'position' => 'doctor',
            'status' => 'active',
        ]);
        $doctor = doctor::create(['employee_id' => $employee->id]);

        Livewire::test(Appointmentform::class)
            ->set('name', 'Test Patient')
            ->set('email', 'patient@example.com')
            ->set('phone', '03211234567')
            ->set('stime', '2026-09-01 10:00:00')
            ->set('address', 'Karachi')
            ->set('doctor_id', $doctor->id)
            ->set('message', 'Consultation request')
            ->call('store_requested_appointment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('requested_appointments', [
            'name' => 'Test Patient',
            'doctor_id' => $doctor->id,
        ]);

        $this->assertSame(1, requestedAppointment::count());
    }
}
