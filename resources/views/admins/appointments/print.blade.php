<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Doctor Visit - {{ $appointment->patient->name ?? 'Patient' }}</title>
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
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 78px;
            font-weight: 700;
            color: rgba(30, 42, 110, 0.07);
            white-space: nowrap;
            z-index: 0;
            pointer-events: none;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
        .page > *:not(.watermark) { position: relative; z-index: 1; }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            padding: 14px 22px;
            background: #f6f3ec;
        }
        .card-header .brand {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .card-header .logo-mark {
            flex-shrink: 0;
            width: 55px;
            height: 55px;
        }
        .card-header .brand h1 {
            margin: 0 0 4px;
            font-size: 26px;
            font-weight: 800;
        }
        .card-header .brand h1 .medi { color: #14213d; }
        .card-header .brand h1 .life { color: #14213d; }
        .card-header .brand h1 .clinics { color: #1e73be; }
        .card-header .brand p { margin: 1px 0; font-size: 12px; color: #444; line-height: 1.4; }
        .band-title {
            background: #1e2a6e;
            color: #fff;
            text-align: center;
            font-size: 40px;
            font-weight: 800;
            letter-spacing: .04em;
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
            color: #1e2a6e;
            margin-bottom: 4px;
        }
        .notes-area {
            border: 1px solid #cfd8d8;
            border-radius: 6px;
            min-height: 230mm;
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
        <div class="watermark">MEDI LIFE CLINICS</div>

        <div class="card-header">
            {{-- TODO: swap for the real MediLife Clinics logo file once provided --}}
            <svg class="logo-mark" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
                <circle cx="20" cy="16" r="7" fill="#1e2a6e"/>
                <circle cx="44" cy="16" r="7" fill="#1e73be"/>
                <path d="M8 40c2-10 10-14 24-14s22 4 24 14" stroke="#1e2a6e" stroke-width="4" fill="none" stroke-linecap="round"/>
                <path d="M32 26v22M22 37h20" stroke="#1e73be" stroke-width="4" stroke-linecap="round"/>
            </svg>
            <div class="brand">
                <h1><span class="medi">MEDI</span><span class="life">LIFE</span> <span class="clinics">CLINICS</span></h1>
                <p>63-C/2, 24th Commercial Street, Touheed Commercial Area, Phase V, DHA, Karachi.</p>
                <p>Cell: 0321-2331421</p>
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
                    <div class="field-cell"><span class="lbl">Status</span><span class="val">{{ ucfirst(str_replace('_', ' ', $appointment->status ?? 'booked')) }}</span></div>
                </div>
                <div class="field-row">
                    <div class="field-cell"><span class="lbl">Doctor</span><span class="val">{{ $appointment->doctor->employ->name ?? 'Dr. Fabreen Naz' }}</span></div>
                    <div class="field-cell"><span class="lbl">Age</span><span class="val">{{ $appointment->age ?: ($appointment->patient->age ?? '') }}</span></div>
                </div>
                <div class="field-row">
                    <div class="field-cell"><span class="lbl">Location</span><span class="val">{{ $appointment->location ?: '' }}</span></div>
                    <div class="field-cell"><span class="lbl">Phone</span><span class="val">{{ $appointment->phone ?: ($appointment->patient->phone ?? '') }}</span></div>
                </div>
                <div class="field-row">
                    <div class="field-cell"><span class="lbl">Purpose of Visit</span><span class="val">{{ $appointment->description ?: '' }}</span></div>
                    <div class="field-cell"><span class="lbl">Consultation Fee</span><span class="val">Rs. {{ number_format($appointment->consultation_fee ?? 1500) }}</span></div>
                </div>
            </div>

            <div class="notes-label">Notes / Rx</div>
            <div class="notes-area">{{ $appointment->prescription }}</div>
        </div>

        <div class="footer-note">
            Ph: 021-35294822, 35294833 &nbsp;&nbsp;Email: drfabreennaz@hotmail.com
        </div>
    </div>
</body>
</html>
