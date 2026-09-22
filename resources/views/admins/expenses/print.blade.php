<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Expense Voucher #{{ $expense->id }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
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
            box-shadow: 0 0 8px rgba(0,0,0,.2);
            position: relative;
            overflow: hidden;
        }
        .watermark {
            position: absolute;
            top: 58%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            white-space: nowrap;
            font-size: 81px;
            font-weight: 700;
            color: #d8d8d8;
            z-index: 999;
            pointer-events: none;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
        .page > *:not(.watermark) { position: relative; z-index: 1; }
        .card-header { position: relative; min-height: 60px; padding: 14px 20px; background: #f6f3ec; }
        .card-header img { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); height: 55px; width: auto; }
        .card-header .brand { text-align: center; }
        .card-header .brand h1 { margin: 0 0 4px; font-size: 24px; color: #0a3535; }
        .card-header .brand p { margin: 1px 0; font-size: 12px; color: #555; }
        .band-title {
            background: #0a3535;
            color: #fff;
            text-align: center;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: .04em;
            padding: 9px 0;
            text-transform: uppercase;
        }
        .card-body { padding: 16px 20px 22px; }
        .field-grid {
            border: 1px solid #cfd8d8;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 14px;
        }
        .field-row { display: flex; border-bottom: 1px solid #cfd8d8; }
        .field-row:last-child { border-bottom: none; }
        .field-cell {
            flex: 1;
            padding: 7px 14px;
            font-size: 12.5px;
            border-right: 1px solid #cfd8d8;
        }
        .field-cell:last-child { border-right: none; }
        .field-cell .lbl { color: #7a8a8a; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; display: block; margin-bottom: 1px; }
        .field-cell .val { font-size: 13px; color: #222; }
        .amount-block {
            border: 1px solid #cfd8d8;
            border-radius: 6px;
            padding: 14px 18px;
            margin-bottom: 14px;
            text-align: right;
        }
        .amount-block .lbl { color: #7a8a8a; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; display: block; }
        .amount-block .val { font-size: 26px; font-weight: 800; color: #0a3535; }
        .notes-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #0a3535;
            margin-bottom: 4px;
        }
        .notes-area {
            border: 1px solid #cfd8d8;
            border-radius: 6px;
            min-height: 40mm;
            padding: 10px 14px;
            font-size: 13px;
            line-height: 22px;
            white-space: pre-wrap;
        }
        .footer-note {
            text-align: center;
            font-size: 10.5px;
            color: #777;
            padding: 8px 0 4px;
            border-top: 1px solid #eee;
            font-style: italic;
        }
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

        <div class="card-header">
            <img src="{{ config('app.url') }}images/logo.png" alt="{{ $settings['title'] ?? config('app.name') }} logo">
            <div class="brand">
                <h1>{{ $settings['title'] ?? config('app.name') }}</h1>
                <p>{{ $settings['address'] ?? '' }}</p>
                <p>Phone: {{ $settings['business_phone'] ?? '' }}</p>
            </div>
        </div>

        <div class="band-title">Expense Voucher</div>

        <div class="card-body">
            <div class="field-grid">
                <div class="field-row">
                    <div class="field-cell"><span class="lbl">Voucher #</span><span class="val">{{ $expense->id }}</span></div>
                    <div class="field-cell"><span class="lbl">Date</span><span class="val">{{ optional($expense->expense_date)->format('d M Y') }}</span></div>
                </div>
                <div class="field-row">
                    <div class="field-cell"><span class="lbl">Category</span><span class="val">{{ $expense->category }}</span></div>
                    <div class="field-cell"><span class="lbl">Payment Mode</span><span class="val">{{ $expense->payment_mode }}</span></div>
                </div>
                <div class="field-row">
                    <div class="field-cell"><span class="lbl">Recorded By</span><span class="val">{{ $expense->created_by ?: 'N/A' }}</span></div>
                    <div class="field-cell"><span class="lbl">Recorded On</span><span class="val">{{ $expense->created_at->format('d M Y') }}</span></div>
                </div>
            </div>

            <div class="amount-block">
                <span class="lbl">Amount</span>
                <span class="val">PKR {{ number_format($expense->amount, 2) }}</span>
            </div>

            <div class="notes-label">Description</div>
            <div class="notes-area">{{ $expense->description ?: '-' }}</div>
        </div>

        <div class="footer-note">
            This is a computer-generated document, there is no sign and stamp required, and it is not valid for use in any court of law or government office.
        </div>
    </div>
</body>
</html>
