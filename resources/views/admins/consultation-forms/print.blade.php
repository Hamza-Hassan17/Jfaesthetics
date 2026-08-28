<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Consultation Form - {{ $form->patient->name ?? 'Patient' }}</title>
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
            font-size: 92px;
            font-weight: 700;
            color: rgba(20, 128, 127, 0.07);
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
        .card-header img { height: 55px; width: auto; }
        .card-header .brand {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
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
        .section-label {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #0a3535;
            margin: 10px 0 4px;
        }
        .compact-block {
            border: 1px solid #cfd8d8;
            border-radius: 6px;
            padding: 7px 14px;
            font-size: 12.5px;
            margin-bottom: 10px;
        }
        .sign-row { display: flex; gap: 20px; margin-bottom: 10px; }
        .sign-row .field-cell { border: 1px solid #cfd8d8; border-radius: 6px; }
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
            min-height: 120mm;
            padding: 10px 14px;
            font-size: 13px;
            line-height: 26px;
            background-image: repeating-linear-gradient(to bottom, transparent, transparent 25px, #e2e8e8 26px);
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

        <div class="band-title">Patient Consultation Form</div>

        <div class="card-body">
            <div class="field-grid">
                <div class="field-row">
                    <div class="field-cell"><span class="lbl">Patient Name</span><span class="val">{{ $form->patient->name ?? 'N/A' }}</span></div>
                    <div class="field-cell"><span class="lbl">Phone No.</span><span class="val">{{ $form->phone ?: ($form->patient->phone ?? '') }}</span></div>
                </div>
                <div class="field-row">
                    <div class="field-cell"><span class="lbl">Date</span><span class="val">{{ optional($form->consultation_date)->format('d M Y') }}</span></div>
                    <div class="field-cell"><span class="lbl">Age / Gender</span><span class="val">{{ $form->patient->age ?? '-' }} / {{ $form->patient->gender ?? '-' }}</span></div>
                </div>
                <div class="field-row">
                    <div class="field-cell"><span class="lbl">Consultation For</span><span class="val">{{ $form->consultation_for ?: '-' }}</span></div>
                    <div class="field-cell">
                        <span class="lbl">Medical History</span>
                        <span class="val">{{ $form->medical_history ?: '-' }}@if ($form->medical_history_other) ({{ $form->medical_history_other }})@endif</span>
                    </div>
                </div>
                @if ($form->patient->gender === 'Female' || !empty($form->female_status))
                    <div class="field-row">
                        <div class="field-cell" style="flex: 1 0 100%;">
                            <span class="lbl">For Female Patients</span>
                            <span class="val">
                                @foreach (\App\Http\Livewire\Admins\ConsultationForms::FEMALE_STATUS_OPTIONS as $option)
                                    {!! in_array($option, $form->female_status ?? []) ? '&#9745;' : '&#9744;' !!} {{ $option }}&nbsp;&nbsp;
                                @endforeach
                            </span>
                        </div>
                    </div>
                @endif
            </div>

            <div class="compact-block">
                {!! $form->declaration_confirmed ? '&#9745;' : '&#9744;' !!}
                I confirm that the information provided above is true and complete to the best of my knowledge.
            </div>

            <div class="sign-row">
                <div class="field-cell" style="flex: 1;"><span class="lbl">Patient Signature</span><span class="val">{{ $form->patient_signature_name ?: '' }}</span></div>
                <div class="field-cell" style="flex: 1;"><span class="lbl">Consultant</span><span class="val">{{ $form->consultant->employ->name ?? '' }}</span></div>
            </div>

            <div class="compact-block">
                <span class="lbl" style="text-transform: uppercase; color: #7a8a8a; font-size: 10px;">Recommended Treatment</span><br>
                {{ $form->recommended_treatment ?: '-' }}
            </div>

            <div class="notes-label">Notes</div>
            <div class="notes-area">{{ $form->notes }}</div>
        </div>

        <div class="footer-note">
            This is a computer-generated document, there is no sign and stamp required, and it is not valid for use in any court of law or government office.
        </div>
    </div>
</body>
</html>
