<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Appiontment;
use App\Http\Livewire\Admins\Employees;
use App\Models\doctor;
use App\Models\employee;
use App\Models\nurse;
use App\Models\patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class DropdownDefaultSyncTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_appointment_actually_saves_the_first_listed_nurse_doctor_and_patient()
    {
        // Regression for: dropdowns whose first <option> is a real value (not a
        // blank placeholder) show that value in the browser by default, but
        // Livewire's model never syncs it unless the user changes the
        // selection — so submitting without touching a dropdown that already
        // "looks right" used to fail validation ("field is required") even
        // though a value was visibly selected.
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
            ->set('description', 'Routine checkup')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('appointments', ['nurse_id' => $nurse->id]);
    }

    /** @test */
    public function adding_an_employee_without_touching_the_status_dropdown_still_saves()
    {
        // Regression: Employees.php declared `public $status;` with no default,
        // while the Status <select> had no blank placeholder and showed
        // "Active" first — so a fresh form always LOOKED like Active was
        // selected, but the property stayed null until the user clicked it,
        // causing "The status field is required" on every first submit.
        $this->actingAs(User::factory()->create());

        Livewire::test(Employees::class)
            ->set('name', 'Amina Sheikh')
            ->set('email', 'amina@example.com')
            ->set('phone', '03001112233')
            ->set('salary', 50000)
            ->set('address', 'Karachi')
            ->set('qualification', 'MBBS')
            ->set('position', 'nurse')
            ->set('image', UploadedFile::fake()->image('e.jpg'))
            ->call('add_employee')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employees', [
            'email' => 'amina@example.com',
            'status' => 'active',
        ]);
    }
}
