<?php

namespace Tests\Feature;

use App\Models\doctor;
use App\Models\employee;
use App\Models\Invoice;
use App\Models\patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_a_patient_by_name()
    {
        $user = User::factory()->create(['is_active' => true]);
        $patient = patient::create(['name' => 'Mehwish Khan', 'phone' => '03001234567']);

        $response = $this->actingAs($user)->get('/admin/search-suggestions?q=Mehwish');

        $response->assertOk();
        $response->assertJsonFragment(['type' => 'Patient', 'label' => 'Mehwish Khan (03001234567)']);
    }

    public function test_it_finds_an_invoice_by_patient_name()
    {
        $user = User::factory()->create(['is_active' => true]);
        $patient = patient::create(['name' => 'Zara Ahmed', 'phone' => '03009876543']);
        $employee = employee::create(['name' => 'Dr Test', 'email' => 'drtest_' . uniqid() . '@test.com', 'phone' => '03000000000', 'position' => 'doctor', 'status' => 'active']);
        $doctor = doctor::create(['employee_id' => $employee->id]);
        $invoice = Invoice::create(['invoice_number' => '2600099', 'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'grand_total' => 1000, 'sub_total' => 1000]);

        $response = $this->actingAs($user)->get('/admin/search-suggestions?q=Zara');

        $response->assertOk();
        $response->assertJsonFragment(['type' => 'Invoice']);
    }

    public function test_it_returns_empty_for_short_queries()
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get('/admin/search-suggestions?q=a');

        $response->assertOk();
        $response->assertExactJson([]);
    }
}
