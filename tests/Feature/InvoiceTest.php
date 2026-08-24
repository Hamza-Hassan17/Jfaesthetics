<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Invoices;
use App\Models\patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): patient
    {
        return patient::create([
            'name' => 'Husna Khan',
            'email' => 'husna@example.com',
            'phone' => '923480259560',
            'address' => 'Karachi',
            'gender' => 'Female',
            'age' => 28,
            'bloodgroup' => 'O+',
        ]);
    }

    /** @test */
    public function generating_an_invoice_computes_grand_total_paid_and_unpaid_correctly()
    {
        $this->actingAs(User::factory()->create());
        $patient = $this->patient();

        Livewire::test(Invoices::class)
            ->call('show_create_form')
            ->set('patient_id', $patient->id)
            ->set('items.0.service', 'Carbon+PRP+Meso 3sessions')
            ->set('items.0.quantity', 1)
            ->set('items.0.service_charges', 40000)
            ->set('items.0.discount_type', 'flat')
            ->set('items.0.discount_value', 0)
            ->set('payments.0.paid_on', '2026-07-10')
            ->set('payments.0.amount', 20000)
            ->set('payments.0.payment_mode', 'Cash')
            ->call('generate_invoice')
            ->assertHasNoErrors();

        $invoice = \App\Models\Invoice::where('patient_id', $patient->id)->latest()->first();

        $this->assertNotNull($invoice);
        $this->assertEquals(40000, $invoice->grand_total);
        $this->assertEquals(20000, $invoice->paid_total);
        $this->assertEquals(20000, $invoice->unpaid_total);
    }

    /** @test */
    public function a_percentage_discount_is_computed_off_the_line_items_subtotal()
    {
        $this->actingAs(User::factory()->create());
        $patient = $this->patient();

        Livewire::test(Invoices::class)
            ->call('show_create_form')
            ->set('patient_id', $patient->id)
            ->set('items.0.service', 'Carbon+PRP+Meso 3sessions')
            ->set('items.0.quantity', 1)
            ->set('items.0.service_charges', 40000)
            ->set('items.0.discount_type', 'percent')
            ->set('items.0.discount_value', 10)
            ->set('payments.0.amount', 0)
            ->call('generate_invoice')
            ->assertHasNoErrors();

        $invoice = \App\Models\Invoice::where('patient_id', $patient->id)->latest()->first();

        $this->assertEquals(4000, $invoice->items->first()->discount);
        $this->assertEquals(36000, $invoice->grand_total);
    }

    /** @test */
    public function a_line_item_can_record_which_session_number_it_covers()
    {
        $this->actingAs(User::factory()->create());
        $patient = $this->patient();

        Livewire::test(Invoices::class)
            ->call('show_create_form')
            ->set('patient_id', $patient->id)
            ->set('items.0.service', 'Carbon+PRP+Meso 3sessions')
            ->set('items.0.quantity', 1)
            ->set('items.0.session', 2)
            ->set('items.0.service_charges', 40000)
            ->call('generate_invoice')
            ->assertHasNoErrors();

        $invoice = \App\Models\Invoice::where('patient_id', $patient->id)->latest()->first();

        $this->assertEquals(2, $invoice->items->first()->session_number);
    }
}
