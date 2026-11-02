<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\User;
use App\Support\PaymentReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_pakistani_numbers_are_normalised_for_whatsapp()
    {
        foreach (['0300-1234567', '03001234567', '3001234567', '+92 300 1234567', '0092 300 1234567'] as $phone) {
            $this->assertSame('923001234567', PaymentReminder::whatsappNumber($phone), $phone);
        }
        foreach ([null, '', '021-35153555', '12345'] as $phone) {
            $this->assertNull(PaymentReminder::whatsappNumber($phone));
        }
    }

    public function test_the_message_opens_in_an_editable_box()
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $patient = patient::create(['name' => 'Sohail', 'phone' => '03178414882', 'address' => '-', 'gender' => 'Male', 'age' => 30, 'bloodgroup' => 'O+', 'email' => 's@x.com']);
        $invoice = Invoice::create(['invoice_number' => 'INV-1', 'patient_id' => $patient->id]);
        $invoice->items()->create(['service' => 'PFM Crown', 'quantity' => 1, 'service_charges' => 20000, 'sub_total' => 20000, 'discount_value' => 0, 'discount_type' => 'flat', 'discount' => 0, 'after_discount' => 20000]);

        \Livewire\Livewire::actingAs(User::factory()->create(['email' => 'super@clinic.pk']))
            ->test(\App\Http\Livewire\Admins\Invoices::class)
            ->call('open_reminder', $invoice->id)
            ->assertSet('reminder_number', '923178414882')
            ->assertSee('Open in WhatsApp')
            ->assertSee('Balance due: PKR 20,000')
            ->call('close_reminder')
            ->assertDontSee('Open in WhatsApp');
    }

    public function test_a_reminder_sends_the_edited_text_from_the_connected_number()
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        config(['app.whatsapp' => ['url' => 'http://wa.test', 'token' => str_repeat('t', 40)]]);
        \Illuminate\Support\Facades\Http::fake(['wa.test/status' => ['state' => 'connected', 'number' => '923465320546', 'qr' => null], 'wa.test/send' => ['ok' => true, 'id' => 'X']]);

        $patient = patient::create(['name' => 'Sohail', 'phone' => '03178414882', 'address' => '-', 'gender' => 'Male', 'age' => 30, 'bloodgroup' => 'O+', 'email' => 's@x.com']);
        $invoice = Invoice::create(['invoice_number' => 'INV-1', 'patient_id' => $patient->id]);
        $invoice->items()->create(['service' => 'PFM Crown', 'quantity' => 1, 'service_charges' => 20000, 'sub_total' => 20000, 'discount_value' => 0, 'discount_type' => 'flat', 'discount' => 0, 'after_discount' => 20000]);

        \Livewire\Livewire::actingAs(User::factory()->create(['email' => 'super@clinic.pk']))
            ->test(\App\Http\Livewire\Admins\Invoices::class)
            ->call('open_reminder', $invoice->id)
            ->assertSee('Send now')
            ->set('reminder_text', 'Edited text')
            ->set('reminder_number', '920000000000') // a tampered number must be ignored
            ->call('send_reminder')
            ->assertSet('reminder_invoice_id', null);

        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request->url() === 'http://wa.test/send'
            && $request['to'] === '923178414882' && $request['text'] === 'Edited text');
    }

    public function test_a_link_only_for_invoices_with_a_balance()
    {
        $patient = patient::create(['name' => 'Sohail', 'phone' => '03178414882', 'address' => '-', 'gender' => 'Male', 'age' => 30, 'bloodgroup' => 'O+', 'email' => 's@x.com']);
        $invoice = Invoice::create(['invoice_number' => 'INV-1', 'patient_id' => $patient->id]);
        $invoice->items()->create(['service' => 'PFM Crown', 'quantity' => 1, 'service_charges' => 20000, 'sub_total' => 20000, 'discount_value' => 0, 'discount_type' => 'flat', 'discount' => 0, 'after_discount' => 20000]);
        $invoice->payments()->create(['amount' => 5000, 'paid_on' => now(), 'payment_mode' => 'Cash']);
        $invoice = $invoice->fresh();

        $url = PaymentReminder::url($invoice);
        $this->assertStringStartsWith('https://wa.me/923178414882?text=', $url);
        $text = rawurldecode(explode('?text=', $url)[1]);
        $this->assertStringContainsString('Dear Sohail', $text);
        $this->assertStringContainsString('Balance due: PKR 15,000', $text);

        $invoice->payments()->create(['amount' => 15000, 'paid_on' => now(), 'payment_mode' => 'Cash']);
        $this->assertNull(PaymentReminder::url($invoice->fresh()));
    }
}
