<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Consultation Form - {{ $form->patient->name ?? 'Patient' }}</title>
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
        .invoice-logo { height: 55px; width: auto; margin-bottom: 10px; }
        .brand-center { text-align: center; margin-bottom: 10px; }
        .brand-center h1 { margin: 0 0 4px; font-size: 22px; color: #333; }
        .brand-center p { margin: 2px 0; font-size: 12px; color: #555; }
        .form-title {
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: .06em;
            margin: 4px 0 22px;
        }
        .field-line { font-size: 13px; margin: 10px 0; }
        .field-line .lbl { font-weight: 400; }
        .field-line .fill {
            display: inline-block;
            border-bottom: 1px solid #333;
            min-width: 160px;
            padding: 0 4px 2px;
        }
        .inline-fields { display: flex; flex-wrap: wrap; gap: 6px 34px; margin: 10px 0; }
        .inline-fields .field-line { margin: 0; }
        .section-label { font-weight: 700; font-size: 13.5px; margin-top: 20px; }
        .checklist-row { display: flex; flex-wrap: wrap; gap: 6px 26px; font-size: 13px; margin-top: 8px; }
        .checklist-row > span:before { content: "\2610  "; }
        .checklist-row > span.checked:before { content: "\2611  "; font-weight: 700; }
        .checklist-row > span.checked { font-weight: 600; }
        .block-fill {
            font-size: 13px;
            margin-top: 8px;
            min-height: 18px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
        }
        .declaration-text { font-size: 12.5px; margin-top: 8px; color: #333; }
        .sign-row { display: flex; justify-content: space-between; margin-top: 40px; gap: 30px; }
        .sign-row .field-line { flex: 1; margin: 0; }
        .sign-row .fill { display: block; margin-top: 22px; min-width: 0; }
        .footer-note {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 10.5px;
            color: #777;
            text-align: center;
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

        <img class="invoice-logo" src="{{ config('app.url') }}images/logo.png" alt="{{ $settings['title'] ?? config('app.name') }} logo">

        <div class="brand-center">
            <h1>{{ $settings['title'] ?? config('app.name') }}</h1>
            <p>Phone: {{ $settings['business_phone'] ?? '' }}</p>
            <p>Address: {{ $settings['address'] ?? '' }}</p>
        </div>

        <div class="form-title">Patient Consultation Form</div>

        <div class="field-line">
            <span class="lbl">Date:</span>
            <span class="fill">{{ optional($form->consultation_date)->format('d/m/Y') }}</span>
        </div>

        <div class="section-label">Patient Details</div>
        <div class="inline-fields">
            <div class="field-line"><span class="lbl">Name:</span> <span class="fill">{{ $form->patient->name ?? 'N/A' }}</span></div>
            <div class="field-line"><span class="lbl">Age:</span> <span class="fill" style="min-width: 60px;">{{ $form->patient->age ?? 'N/A' }}</span></div>
            <div class="field-line"><span class="lbl">Gender:</span> <span class="fill" style="min-width: 100px;">{{ $form->patient->gender ?? 'N/A' }}</span></div>
        </div>
        <div class="field-line"><span class="lbl">Phone:</span> <span class="fill">{{ $form->patient->phone ?? 'N/A' }}</span></div>

        <div class="section-label">Consultation For</div>
        <div class="checklist-row">
            @foreach (\App\Http\Livewire\Admins\ConsultationForms::CONSULTATION_FOR_OPTIONS as $option)
                <span class="{{ in_array($option, $form->consultation_for ?? []) ? 'checked' : '' }}">{{ $option }}</span>
            @endforeach
        </div>

        <div class="section-label">Medical History</div>
        <div class="checklist-row">
            @foreach (\App\Http\Livewire\Admins\ConsultationForms::MEDICAL_HISTORY_OPTIONS as $option)
                @if ($option !== 'Other')
                    <span class="{{ in_array($option, $form->medical_history ?? []) ? 'checked' : '' }}">{{ $option }}</span>
                @endif
            @endforeach
            <span class="{{ in_array('Other', $form->medical_history ?? []) ? 'checked' : '' }}">Other: <span class="fill" style="min-width: 140px;">{{ $form->medical_history_other ?: '' }}</span></span>
        </div>

        @if ($form->patient->gender === 'Female' || !empty($form->female_status))
            <div class="section-label">For Female Patients</div>
            <div class="checklist-row">
                @foreach (\App\Http\Livewire\Admins\ConsultationForms::FEMALE_STATUS_OPTIONS as $option)
                    <span class="{{ in_array($option, $form->female_status ?? []) ? 'checked' : '' }}">{{ $option }}</span>
                @endforeach
            </div>
        @endif

        <div class="section-label">Declaration</div>
        <div class="declaration-text">
            I confirm that the information provided above is true and complete to the best of my knowledge.
            @if ($form->declaration_confirmed) (Confirmed) @endif
        </div>

        <div class="sign-row">
            <div class="field-line">
                <span class="lbl">Patient Signature:</span>
                <span class="fill">{{ $form->patient_signature_name ?: '' }}</span>
            </div>
            <div class="field-line">
                <span class="lbl">Consultant:</span>
                <span class="fill">{{ $form->consultant->employ->name ?? '' }}</span>
            </div>
        </div>

        <div class="field-line" style="margin-top: 20px;">
            <span class="lbl">Recommended Treatment:</span>
            <span class="fill" style="min-width: 300px;">{{ $form->recommended_treatment ?: '' }}</span>
        </div>

        <div class="section-label">Notes</div>
        <div class="block-fill">{{ $form->notes ?: '' }}</div>

        <div class="footer-note">
            This is a computer-generated document, there is no sign and stamp required, and it is not valid for use in any court of law or government office.
        </div>
    </div>
</body>
</html>
