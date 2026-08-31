<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { size: A4; margin: 15mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #222;
            margin: 0;
            background: #eee;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 10mm auto;
            background: #fff;
            padding: 15mm;
            box-shadow: 0 0 8px rgba(0,0,0,.2);
            position: relative;
            overflow: hidden;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            white-space: nowrap;
            font-size: 78px;
            font-weight: 700;
            color: #d8d8d8;
            z-index: 0;
            pointer-events: none;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
        .page > *:not(.watermark) { position: relative; z-index: 1; }
        .footer-note {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 10.5px;
            color: #777;
            text-align: center;
            font-style: italic;
        }
        .invoice-header { position: relative; min-height: 60px; margin-bottom: 20px; }
        .invoice-logo { position: absolute; left: 0; top: 50%; transform: translateY(-50%); height: 55px; width: auto; }
        .brand-center { text-align: center; }
        .brand-center h1 { margin: 0 0 4px; font-size: 24px; color: #333; }
        .brand-center p { margin: 2px 0; font-size: 12px; color: #555; }
        .brand-center .address-line { max-width: 320px; margin: 2px auto; }
        .info-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; }
        .info-row > div { flex: 1; }
        .patient-info p { margin: 2px 0; font-size: 13px; }
        .invoice-meta { text-align: right; }
        .invoice-meta h2 { margin: 0 0 6px; font-weight: 400; color: #333; }
        .invoice-meta p { margin: 2px 0; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f4f4f4; }
        .section-title { margin-top: 25px; font-size: 15px; font-weight: 600; color: #333; }
        .totals-payment { display: flex; justify-content: space-between; margin-top: -10px; gap: 20px; }
        .payment-history { flex: 1; }
        .summary { width: 260px; }
        .summary table td { border: none; padding: 4px 0; font-size: 13px; }
        .summary table td.label { color: #444; }
        .summary table td.value { text-align: right; font-weight: 600; }
        .summary table tr.grand td { border-top: 1px solid #999; pa-5ing-top: 8px; font-size: 14px; }
        .notes { padding-top: 16px; font-size: 12px; color: #444; }
        .notes ul { margin: 6px 0 0; padding-left: 18px; }
        .notes li { margin-bottom: 4px; }
        .print-bar { text-align: center; margin: 10px 0; }
        @media print {
            body { background: #fff; }
            .page { box-shadow: none; margin: 0; width: auto; min-height: auto; }
            .print-bar { display: none; }
        }
    </style>
</head>
<body>
    <div class="print-bar">
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>
    <div class="page">
        <div class="watermark">JF Aesthetics</div>

        <div class="invoice-header">
            <img class="invoice-logo" src="{{ config('app.url') }}images/logo.png" alt="{{ $settings['title'] ?? config('app.name') }} logo">

            <div class="brand-center">
                <h1>{{ $settings['title'] ?? config('app.name') }}</h1>
                <p>UAN: {{ $settings['business_phone'] ?? '' }}</p>
                <p class="address-line">Address: {{ $settings['address'] ?? '' }}</p>
            </div>
        </div>

        <div class="info-row">
            <div class="patient-info">
                <p><strong>Name:</strong> {{ $invoice->patient->name ?? 'N/A' }}</p>
                <p><strong>MR#:</strong> H-{{ str_pad($invoice->patient_id, 2, '0', STR_PAD_LEFT) }}-{{ str_pad($invoice->patient_id, 6, '0', STR_PAD_LEFT) }}</p>
                <p><strong>Phone:</strong> {{ $invoice->patient->phone ?? 'N/A' }}</p>
                <p><strong>Doctor:</strong> {{ $invoice->doctor->employ->name ?? 'N/A' }}</p>
            </div>
            <div class="invoice-meta">
                <h2>Invoice</h2>
                <p><strong>Invoice ID:</strong> {{ $invoice->invoice_number }}</p>
                <p><strong>Created On:</strong> {{ $invoice->created_at->format('M d Y h:i A') }}</p>
                <p><strong>Printed By:</strong> {{ $invoice->printed_by ?? 'N/A' }}</p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Service/Product</th>
                    <th>Quantity</th>
                    <th>Session</th>
                    <th>Service Charges</th>
                    <th>Sub Total</th>
                    <th>Discount</th>
                    <th>After Discount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->service }}</td>
                        <td>{{ $item->service_id ? '-' : $item->quantity }}</td>
                        <td>{{ $item->medicine_id ? '-' : ($item->session_number ?? '-') }}</td>
                        <td>{{ number_format($item->service_charges, 0) }}</td>
                        <td>{{ number_format($item->sub_total, 0) }}</td>
                        <td>
                            {{ number_format($item->discount, 0) }}
                            @if ($item->discount_type === 'percent')
                                ({{ rtrim(rtrim(number_format($item->discount_value, 2), '0'), '.') }}%)
                            @endif
                        </td>
                        <td>{{ number_format($item->after_discount, 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals-payment">
            <div class="payment-history">
                <div class="section-title">Payment History</div>
                <table>
                    <thead>
                        <tr><th>Date</th><th>Amount</th><th>Payment Mode</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($invoice->payments as $payment)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($payment->paid_on)->format('d M Y') }}</td>
                                <td>{{ number_format($payment->amount, 0) }}</td>
                                <td>{{ $payment->payment_mode }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">No payments recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="summary">
                <table>
                    <tr><td class="label">Grand Total:</td><td class="value">PKR {{ number_format($invoice->grand_total, 0) }}</td></tr>
                    <tr><td class="label">Sub Total:</td><td class="value">PKR {{ number_format($invoice->sub_total, 0) }}</td></tr>
                    <tr><td class="label">Total:</td><td class="value">PKR {{ number_format($invoice->grand_total, 0) }}</td></tr>
                    <tr><td class="label">Paid:</td><td class="value">PKR {{ number_format($invoice->paid_total, 0) }}/-</td></tr>
                    <tr class="grand"><td class="label">Un Paid:</td><td class="value">PKR {{ number_format($invoice->unpaid_total, 0) }}</td></tr>
                    @if ($invoice->overpaid_total > 0)
                        <tr><td class="label">Overpaid:</td><td class="value">PKR {{ number_format($invoice->overpaid_total, 0) }}</td></tr>
                    @endif
                </table>
            </div>
        </div>

        <div class="notes">
            <strong>Notes:</strong>
            <ul>
                @if ($invoice->notes)
                    <li>{{ $invoice->notes }}</li>
                @endif
                <li>All payments are non refundable.</li>
                <li>Please retain this invoice for tracking remaining sessions in a package.</li>
                <li>For any billing queries, please contact the clinic within 7 days of this invoice date.</li>
            </ul>
        </div>

        <div class="footer-note">
            This is a computer-generated invoice, there is no sign and stamp required, and it is not valid for use in any court of law or government office.
        </div>
    </div>
</body>
</html>
