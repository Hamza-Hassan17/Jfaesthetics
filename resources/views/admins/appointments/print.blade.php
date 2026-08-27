<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Appointment - {{ $appointment->patient->name ?? 'Patient' }}</title>
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
            overflow: hidden;
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 18px;
            background: #f6f3ec;
            border-bottom: 3px solid #148080;
        }
        .card-header img { height: 55px; width: auto; }
        .card-header .brand h1 { margin: 0 0 2px; font-size: 22px; color: #0a3535; }
        .card-header .brand p { margin: 1px 0; font-size: 11.5px; color: #555; }
        .band-title {
            background: #0a3535;
            color: #fff;
            text-align: center;
            font-size: 34px;
            font-weight: 800;
            letter-spacing: .06em;
            padding: 10px 0;
            text-transform: uppercase;
        }
        .card-body { padding: 18px 20px 24px; }
        .field-grid {
            border: 1px solid #cfd8d8;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 18px;
        }
        .field-row { display: flex; border-bottom: 1px solid #cfd8d8; }
        .field-row:last-child { border-bottom: none; }
        .field-cell {
            flex: 1;
            padding: 9px 14px;
            font-size: 13px;
            border-right: 1px solid #cfd8d8;
        }
        .field-cell:last-child { border-right: none; }
        .field-cell .lbl { color: #7a8a8a; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; display: block; margin-bottom: 2px; }
        .field-cell .val { font-size: 14px; color: #222; }
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
            min-height: 260mm;
            padding: 10px 14px;
            font-size: 13.5px;
            line-height: 26px;
            background-image: repeating-linear-gradient(to bottom, transparent, transparent 25px, #e2e8e8 26px);
            white-space: pre-wrap;
        }
        .footer-note {
            text-align: center;
            font-size: 11px;
            color: #777;
            padding: 8px 0 4px;
            border-top: 1px solid #eee;
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
        <div class="card-header">
            <img src="{{ config('app.url') }}images/logo.png" alt="{{ $settings['title'] ?? config('app.name') }} logo">
            <div class="brand">
                <h1>{{ $settings['title'] ?? config('app.name') }}</h1>
                <p>{{ $settings['address'] ?? '' }}</p>
                <p>Cell: {{ $settings['business_phone'] ?? '' }}</p>
            </div>
        </div>

        <div class="band-title">Doctor Visit</div>

        <div class="card-body">
            <div class="field-grid">
                <div class="field-row">
                    <div class="field-cell"><span class="lbl">Patient Name</span><span class="val">{{ $appointment->patient->name ?? 'N/A' }}</span></div>
                    <div class="field-cell"><span class="lbl">Case No.</span><span class="val">{{ $appointment->case_no ?: '' }}</span></div>
                </div>
                <div class="field-row">
                    <div class="field-cell"><span class="lbl">Appointment Date</span><span class="val">{{ $appointment->intime ? $appointment->intime->format('d M Y, h:i A') : '' }}</span></div>
                    <div class="field-cell"><span class="lbl">Age</span><span class="val">{{ $appointment->age ?: ($appointment->patient->age ?? '') }}</span></div>
                </div>
                <div class="field-row">
                    <div class="field-cell"><span class="lbl">Doctor</span><span class="val">{{ $appointment->doctor->employ->name ?? 'N/A' }}</span></div>
                    <div class="field-cell"><span class="lbl">Phone</span><span class="val">{{ $appointment->patient->phone ?? '' }}</span></div>
                </div>
                <div class="field-row">
                    <div class="field-cell"><span class="lbl">Location</span><span class="val">{{ $appointment->location ?: '' }}</span></div>
                    <div class="field-cell"><span class="lbl">Purpose of Visit</span><span class="val">{{ $appointment->description ?: '' }}</span></div>
                </div>
            </div>

            <div class="notes-label">Notes / Rx</div>
            <div class="notes-area">{{ $appointment->prescription }}</div>
        </div>

        <div class="footer-note">
            Ph: {{ $settings['business_phone'] ?? '' }} @if (!empty($settings['business_email'])) &nbsp;|&nbsp; Email: {{ $settings['business_email'] }} @endif
        </div>
    </div>
</body>
</html>
