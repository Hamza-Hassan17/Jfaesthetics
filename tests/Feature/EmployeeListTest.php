<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Employees;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeListTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_newly_added_employee_actually_appears_in_the_list_afterward()
    {
        $this->actingAs(User::factory()->create());

        $component = Livewire::test(Employees::class)
            ->set('name', 'Amina Sheikh')
            ->set('email', 'amina@example.com')
            ->set('phone', '03001112233')
            ->set('salary', 50000)
            ->set('address', 'Karachi')
            ->set('qualification', 'MBBS')
            ->set('position', 'nurse')
            ->set('status', 'active')
            ->set('image', UploadedFile::fake()->image('employee.jpg'))
            ->call('add_employee')
            ->assertHasNoErrors();

        // The regression: add_employee() used to leave the position filter
        // reset to null, which made the index query run `WHERE position = NULL`
        // — always zero rows, regardless of what was just saved.
        $component->assertSee('Amina Sheikh');
    }
}
