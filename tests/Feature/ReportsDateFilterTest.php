<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Reports;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): patient
    {
        return patient::create([
            'name' => 'Report Filter Patient',
            'email' => 'report-filter@example.com',
            'phone' => '923000000000',
            'address' => 'Karachi',
            'gender' => 'Female',
            'age' => 30,
            'bloodgroup' => 'O+',
        ]);
    }

    /**
     * An invoice created a week ago that only just had a payment recorded
     * today must still show up when the report is filtered to "today" -
     * date filtering reflects activity, not just the invoice's original
     * creation date.
     */
    public function test_an_old_invoice_with_a_payment_recorded_today_appears_in_a_report_filtered_to_today()
    {
        $patient = $this->patient();

        $invoice = Invoice::create([
            'invoice_number' => 'RPT-1',
            'patient_id' => $patient->id,
        ]);
        $invoice->created_at = now()->subDays(7);
        $invoice->updated_at = now()->subDays(7);
        $invoice->saveQuietly();

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'paid_on' => now()->format('Y-m-d'),
            'amount' => 2000,
            'payment_mode' => 'Cash',
        ]);

        $results = Reports::queryFilteredInvoices([
            'from' => now()->subDay()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ]);

        $this->assertTrue($results->pluck('id')->contains($invoice->id));
    }

    /**
     * An invoice with no activity in the window (created outside it, no
     * payments in it) must NOT show up - the fix should not turn the date
     * filter into a no-op.
     */
    public function test_an_untouched_old_invoice_does_not_appear_in_a_report_filtered_to_today()
    {
        $patient = $this->patient();

        $invoice = Invoice::create([
            'invoice_number' => 'RPT-2',
            'patient_id' => $patient->id,
        ]);
        $invoice->created_at = now()->subDays(7);
        $invoice->updated_at = now()->subDays(7);
        $invoice->saveQuietly();

        $results = Reports::queryFilteredInvoices([
            'from' => now()->subDay()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ]);

        $this->assertFalse($results->pluck('id')->contains($invoice->id));
    }

    /**
     * Reproduces the exact reported scenario: two older invoices, each
     * getting a payment recorded today. The date-filtered "Total Revenue"
     * must equal the sum of just those two payments (500 + 50000 =
     * 50500), not each invoice's full lifetime paid/grand total.
     */
    public function test_total_revenue_sums_only_payments_recorded_within_the_filtered_range()
    {
        $patient = $this->patient();

        $invoiceA = Invoice::create(['invoice_number' => 'RPT-A', 'patient_id' => $patient->id]);
        $invoiceA->created_at = now()->subDays(7);
        $invoiceA->updated_at = now()->subDays(7);
        $invoiceA->saveQuietly();

        // An older payment on invoice A, outside the filtered window -
        // must NOT be counted.
        $oldPayment = InvoicePayment::create([
            'invoice_id' => $invoiceA->id,
            'paid_on' => now()->subDays(7)->format('Y-m-d'),
            'amount' => 9999,
            'payment_mode' => 'Cash',
        ]);
        $oldPayment->created_at = now()->subDays(7);
        $oldPayment->updated_at = now()->subDays(7);
        $oldPayment->saveQuietly();

        // Today's payment on invoice A - must be counted.
        InvoicePayment::create([
            'invoice_id' => $invoiceA->id,
            'paid_on' => now()->format('Y-m-d'),
            'amount' => 500,
            'payment_mode' => 'Cash',
        ]);

        $invoiceB = Invoice::create(['invoice_number' => 'RPT-B', 'patient_id' => $patient->id]);
        $invoiceB->created_at = now()->subDays(7);
        $invoiceB->updated_at = now()->subDays(7);
        $invoiceB->saveQuietly();

        InvoicePayment::create([
            'invoice_id' => $invoiceB->id,
            'paid_on' => now()->format('Y-m-d'),
            'amount' => 50000,
            'payment_mode' => 'Cash',
        ]);

        $from = now()->subDay()->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $filtered = Reports::queryFilteredInvoices(['from' => $from, 'to' => $to]);
        $revenue = Reports::sumRevenueInRange($filtered, $from, $to);

        $this->assertEquals(50500, $revenue);
    }

    /**
     * Regression for the exact bug reported after the first fix: a payment
     * whose DB row was inserted today (created_at = today) but whose real
     * paid_on date is old must NOT count as today's revenue, and must not
     * make the invoice appear in a report filtered to today either -
     * filtering has to go by paid_on (the real payment date), not
     * created_at (when the row happened to be typed in).
     */
    public function test_a_payment_entered_today_but_paid_on_an_old_date_is_not_counted_as_todays_revenue()
    {
        $patient = $this->patient();

        $invoice = Invoice::create(['invoice_number' => 'RPT-BACKDATED', 'patient_id' => $patient->id]);
        $invoice->created_at = now()->subDays(7);
        $invoice->updated_at = now()->subDays(7);
        $invoice->saveQuietly();

        // Row inserted right now (created_at defaults to now), but records
        // a payment that was actually made a week ago.
        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'paid_on' => now()->subDays(7)->format('Y-m-d'),
            'amount' => 62320.97,
            'payment_mode' => 'Cash',
        ]);

        $from = now()->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $filtered = Reports::queryFilteredInvoices(['from' => $from, 'to' => $to]);

        $this->assertFalse($filtered->pluck('id')->contains($invoice->id));
        $this->assertEquals(0, Reports::sumRevenueInRange($filtered, $from, $to));
    }
}
