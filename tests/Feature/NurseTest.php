<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Nurses;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class NurseTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_nurse_can_be_created_without_error()
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Nurses::class)
            ->set('name', 'Amina Sheikh')
            ->set('email', 'amina@example.com')
            ->set('phone', '03001112233')
            ->set('gender', 'Female')
            ->set('address', 'Karachi')
            ->set('qualification', 'BSN')
            ->set('position', 'Staff Nurse')
            ->set('registered', 1)
            ->set('photo', UploadedFile::fake()->image('nurse.jpg'))
            ->call('add_nurse')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('nurses', [
            'name' => 'Amina Sheikh',
            'position' => 'Staff Nurse',
        ]);
    }

    /** @test */
    public function the_nurses_admin_route_is_reachable()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('nurses'))->assertOk();
    }
}
