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
}
