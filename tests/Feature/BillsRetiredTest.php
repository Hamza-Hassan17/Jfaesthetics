<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillsRetiredTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function the_old_bills_route_no_longer_exists()
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('patient_bills'));
    }

    /** @test */
    public function invoices_is_the_only_billing_route_and_is_reachable()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin_invoices'))->assertOk();
    }
}
