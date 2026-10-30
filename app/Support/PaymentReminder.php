<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Settings;
use Illuminate\Support\Str;

/**
 * Click-to-WhatsApp payment reminders (free: staff press Send in
 * WhatsApp themselves). Builds a wa.me link with the patient's number
 * and a ready message for an invoice that still has a balance.
 */
class PaymentReminder
{
    /** Pakistani numbers to international form: 0300-1234567 / 3001234567 / +92 300... -> 923001234567. */
    public static function whatsappNumber(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (Str::startsWith($digits, '0092')) {
            $digits = substr($digits, 2);
        } elseif (Str::startsWith($digits, '03') && strlen($digits) === 11) {
            $digits = '92' . substr($digits, 1);
        } elseif (Str::startsWith($digits, '3') && strlen($digits) === 10) {
            $digits = '92' . $digits;
        }

        return preg_match('/^92\d{10}$/', $digits) ? $digits : null;
    }

    public static function message(Invoice $invoice): string
    {
        $clinic = Settings::where('key', 'title')->value('value') ?: config('app.name');
        $phone = Settings::where('key', 'business_phone')->value('value');
        $money = fn ($amount) => 'PKR ' . number_format($amount);

        return "Dear {$invoice->patient->name},\n\n"
            . "This is a kind reminder from {$clinic} about your invoice #{$invoice->invoice_number} "
            . "dated {$invoice->created_at->format('d M Y')}.\n\n"
            . "Total: {$money($invoice->grand_total)}\n"
            . "Paid: {$money($invoice->paid_total)}\n"
            . "Balance due: {$money($invoice->unpaid_total)}\n\n"
            . "Please clear the remaining balance at your earliest convenience."
            . ($phone ? " For any questions, call us at {$phone}." : '')
            . "\n\nThank you,\n{$clinic}";
    }

    /** Null when nothing is due or the patient has no usable mobile number. */
    public static function url(Invoice $invoice): ?string
    {
        if ($invoice->unpaid_total <= 0 || !$invoice->patient) {
            return null;
        }

        $number = self::whatsappNumber($invoice->patient->phone);

        return $number ? 'https://wa.me/' . $number . '?text=' . rawurlencode(self::message($invoice)) : null;
    }
}
