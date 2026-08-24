<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoices Report</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #222;
            margin: 0;
            background: #eee;
        }
        .page {
            width: 297mm;
            min-height: 210mm;
            margin: 10mm auto;
            background: #fff;
            padding: 12mm;
            box-shadow: 0 0 8px rgba(0,0,0,.2);
            position: relative;
            overflow: hidden;
        }
        .watermark {
            position: absolute;
            top: 30%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 72px;
            font-weight: 700;
            color: rgba(20, 128, 127, 0.08);
            white-space: nowrap;
            z-index: 0;
            pointer-events: none;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
        .page > *:not(.watermark) { position: relative; z-index: 1; }
        .footer-note {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 10.5px;
            color: #777;
            text-align: center;
            font-style: italic;
        }
        .brand-center { text-align: center; margin-bottom: 15px; }
        .brand-center h1 { margin: 0 0 4px; font-size: 22px; color: #333; }
        .brand-center p { margin: 2px 0; font-size: 12px; color: #555; }
        .report-meta { text-align: center; margin-bottom: 15px; }
        .report-meta h2 { margin: 0 0 6px; font-weight: 400; color: #333; font-size: 17px; }
        .summary-row { display: flex; gap: 12px; margin-bottom: 15px; }
        .summary-row > div { flex: 1; border: 1px solid #ddd; border-radius: 6px; padding: 8px 12px; text-align: center; }
        .summary-row .label { font-size: 11px; color: #777; }
        .summary-row .value { font-size: 15px; font-weight: 700; color: #148080; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
        th { background: #f4f4f4; }
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

        <div class="brand-center">
            <h1>{{ $settings['title'] ?? config('app.name') }}</h1>
            <p>Phone: {{ $settings['business_phone'] ?? '' }}</p>
            <p>Address: {{ $settings['address'] ?? '' }}</p>
        </div>

        <div class="report-meta">
            <h2>Invoices Report</h2>
            @if (!empty($filters['from']) || !empty($filters['to']))
                <p>{{ $filters['from'] ?? 'Start' }} &mdash; {{ $filters['to'] ?? 'Today' }}</p>
            @endif
        </div>

        <div class="summary-row">
            <div>
                <div class="label">Total Invoices</div>
                <div class="value">{{ $invoices->count() }}</div>
            </div>
            <div>
                <div class="label">Total Revenue</div>
                <div class="value">PKR {{ number_format($invoices->sum('grand_total'), 0) }}</div>
            </div>
            <div>
                <div class="label">Total Paid</div>
                <div class="value">PKR {{ number_format($invoices->sum('paid_total'), 0) }}</div>
            </div>
            <div>
                <div class="label">Outstanding</div>
                <div class="value">PKR {{ number_format($invoices->sum('unpaid_total'), 0) }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Services</th>
                    <th>Grand Total</th>
                    <th>Paid</th>
                    <th>Unpaid</th>
                    <th>Created On</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice->invoice_number }}</td>
                        <td>{{ $invoice->patient->name ?? 'N/A' }}</td>
                        <td>{{ $invoice->doctor->employ->name ?? 'N/A' }}</td>
                        <td>{{ $invoice->items->pluck('service')->implode(', ') }}</td>
                        <td>{{ number_format($invoice->grand_total, 0) }}</td>
                        <td>{{ number_format($invoice->paid_total, 0) }}</td>
                        <td>{{ number_format($invoice->unpaid_total, 0) }}</td>
                        <td>{{ $invoice->created_at->format('d M Y') }}</td>
                        <td>{{ $invoice->unpaid_total <= 0 ? 'Paid' : ($invoice->paid_total > 0 ? 'Partial' : 'Unpaid') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9">No invoices found for this filter.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer-note">
            This is a computer-generated report and is not valid for use in any court of law or government office.
        </div>
    </div>
</body>
</html>
