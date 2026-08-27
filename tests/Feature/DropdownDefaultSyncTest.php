<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Employees;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class DropdownDefaultSyncTest extends TestCase
{
    use RefreshDatabase;

    // The Appointments regression case that used to live here (a dropdown
    // whose first <option> is a real value not syncing until touched) no
    // longer applies: the Appointments form has no Doctor/Nurse dropdowns
    // anymore — the doctor is fixed (MediLife Clinics has one doctor) and
    // Prep Nurse was removed. See AdminAppointmentTest for current coverage.

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
