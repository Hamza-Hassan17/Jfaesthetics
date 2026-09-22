<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Invoices;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceUpdatedAtFilterTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): patient
    {
        return patient::create([
            'name' => 'Updated At Patient',
            'email' => 'updated-at@example.com',
            'phone' => '923000000111',
            'address' => 'Karachi',
            'gender' => 'Female',
            'age' => 30,
            'bloodgroup' => 'O+',
        ]);
    }

    public function test_adding_a_payment_bumps_the_invoices_updated_at()
    {
        $patient = $this->patient();

        $invoice = Invoice::create(['invoice_number' => 'UPD-1', 'patient_id' => $patient->id]);
        $invoice->created_at = now()->subDays(7);
        $invoice->updated_at = now()->subDays(7);
        $invoice->saveQuietly();

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'paid_on' => now()->format('Y-m-d'),
            'amount' => 2000,
            'payment_mode' => 'Cash',
        ]);

        $this->assertTrue($invoice->fresh()->updated_at->isToday());
    }

    public function test_a_payment_entered_today_but_backdated_does_not_bump_updated_at()
    {
        $patient = $this->patient();

        $invoice = Invoice::create(['invoice_number' => 'UPD-BACKDATED', 'patient_id' => $patient->id]);
        $invoice->created_at = now()->subDays(7);
        $invoice->updated_at = now()->subDays(7);
        $invoice->saveQuietly();

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'paid_on' => now()->subDays(7)->format('Y-m-d'),
            'amount' => 2000,
            'payment_mode' => 'Cash',
        ]);

        $this->assertFalse($invoice->fresh()->updated_at->isToday());
    }

    public function test_invoices_page_filters_by_updated_at_range_including_payment_activity()
    {
        $this->actingAs(User::factory()->create());
        $patient = $this->patient();

        $oldInvoice = Invoice::create(['invoice_number' => 'UPD-OLD', 'patient_id' => $patient->id]);
        $oldInvoice->created_at = now()->subDays(10);
        $oldInvoice->updated_at = now()->subDays(10);
        $oldInvoice->saveQuietly();

        $touchedInvoice = Invoice::create(['invoice_number' => 'UPD-TOUCHED', 'patient_id' => $patient->id]);
        $touchedInvoice->created_at = now()->subDays(10);
        $touchedInvoice->updated_at = now()->subDays(10);
        $touchedInvoice->saveQuietly();

        // A payment recorded today on the older invoice should bump its
        // updated_at into today's range, so it must be found by a filter
        // for "yesterday to tomorrow" even though it was created 10 days
        // ago and nothing else about the invoice row changed.
        InvoicePayment::create([
            'invoice_id' => $touchedInvoice->id,
            'paid_on' => now()->format('Y-m-d'),
            'amount' => 5000,
            'payment_mode' => 'Cash',
        ]);

        Livewire::test(Invoices::class)
            ->set('filter_from', now()->subDay()->format('Y-m-d'))
            ->set('filter_to', now()->addDay()->format('Y-m-d'))
            ->call('$refresh')
            ->assertSee('UPD-TOUCHED')
            ->assertDontSee('UPD-OLD');
    }

    public function test_reset_filters_clears_search_date_range_and_status()
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Invoices::class)
            ->set('search', 'ABC')
            ->set('filter_from', '2026-01-01')
            ->set('filter_to', '2026-01-31')
            ->set('filter_status', 'paid')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('filter_from', '')
            ->assertSet('filter_to', '')
            ->assertSet('filter_status', '');
    }

    public function test_invoices_page_filters_by_payment_status()
    {
        $this->actingAs(User::factory()->create());
        $patient = $this->patient();

        $paidInvoice = Invoice::create(['invoice_number' => 'STATUS-PAID', 'patient_id' => $patient->id]);
        \App\Models\InvoiceItem::create([
            'invoice_id' => $paidInvoice->id,
            'service' => 'Facial',
            'quantity' => 1,
            'service_charges' => 5000,
            'discount_type' => 'flat',
            'discount_value' => 0,
            'sub_total' => 5000,
            'discount' => 0,
            'after_discount' => 5000,
        ]);
        InvoicePayment::create([
            'invoice_id' => $paidInvoice->id,
            'paid_on' => now()->format('Y-m-d'),
            'amount' => 5000,
            'payment_mode' => 'Cash',
        ]);

        $partialInvoice = Invoice::create(['invoice_number' => 'STATUS-PARTIAL', 'patient_id' => $patient->id]);
        \App\Models\InvoiceItem::create([
            'invoice_id' => $partialInvoice->id,
            'service' => 'Facial',
            'quantity' => 1,
            'service_charges' => 5000,
            'discount_type' => 'flat',
            'discount_value' => 0,
            'sub_total' => 5000,
            'discount' => 0,
            'after_discount' => 5000,
        ]);
        InvoicePayment::create([
            'invoice_id' => $partialInvoice->id,
            'paid_on' => now()->format('Y-m-d'),
            'amount' => 2000,
            'payment_mode' => 'Cash',
        ]);

        Livewire::test(Invoices::class)
            ->set('filter_status', 'paid')
            ->call('$refresh')
            ->assertSee('STATUS-PAID')
            ->assertDontSee('STATUS-PARTIAL');

        Livewire::test(Invoices::class)
            ->set('filter_status', 'partial')
            ->call('$refresh')
            ->assertSee('STATUS-PARTIAL')
            ->assertDontSee('STATUS-PAID');
    }
}
