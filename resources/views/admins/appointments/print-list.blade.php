<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Appointment Report</title>
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
            font-size: 78px;
            font-weight: 700;
            color: rgba(30, 42, 110, 0.08);
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
        .brand-center h1 { margin: 0 0 4px; font-size: 22px; color: #14213d; }
        .brand-center p { margin: 2px 0; font-size: 12px; color: #555; }
        .report-meta { text-align: center; margin-bottom: 15px; }
        .report-meta h2 { margin: 0 0 6px; font-weight: 400; color: #333; font-size: 17px; }
        .summary-row { display: flex; gap: 12px; margin-bottom: 15px; }
        .summary-row > div { flex: 1; border: 1px solid #ddd; border-radius: 6px; padding: 8px 12px; text-align: center; }
        .summary-row .label { font-size: 11px; color: #777; }
        .summary-row .value { font-size: 15px; font-weight: 700; color: #1e2a6e; }
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
        <div class="watermark">MEDI LIFE CLINICS</div>

        <div class="brand-center">
            <h1>MEDILIFE <span style="color:#1e73be;">CLINICS</span></h1>
            <p>63-C/2, 24th Commercial Street, Touheed Commercial Area, Phase V, DHA, Karachi.</p>
            <p>Cell: 0321-2331421</p>
        </div>

        <div class="report-meta">
            <h2>Appointment Report — Dr. Fabreen Naz</h2>
            @if (!empty($filters['from']) || !empty($filters['to']))
                <p>{{ $filters['from'] ?? 'Start' }} &mdash; {{ $filters['to'] ?? 'Today' }}</p>
            @endif
        </div>

        <div class="summary-row">
            <div>
                <div class="label">Total Appointments</div>
                <div class="value">{{ $appointments->count() }}</div>
            </div>
            <div>
                <div class="label">Unique Patients</div>
                <div class="value">{{ $appointments->pluck('patient_id')->unique()->count() }}</div>
            </div>
            <div>
                <div class="label">Completed</div>
                <div class="value">{{ $appointments->where('status', 'completed')->count() }}</div>
            </div>
            <div>
                <div class="label">Cancelled / No Show</div>
                <div class="value">{{ $appointments->whereIn('status', ['cancelled', 'no_show'])->count() }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Case No.</th>
                    <th>Patient</th>
                    <th>Appointment Date</th>
                    <th>Status</th>
                    <th>Purpose of Visit</th>
                    <th>Consultation Fee</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($appointments as $appointment)
                    <tr>
                        <td>{{ $appointment->case_no ?: '-' }}</td>
                        <td>{{ $appointment->patient->name ?? 'N/A' }}</td>
                        <td>{{ $appointment->intime ? $appointment->intime->format('d M Y h:i A') : '-' }}</td>
                        <td>{{ \App\Http\Livewire\Admins\AppointmentReport::STATUS_LABELS[$appointment->status] ?? ucfirst($appointment->status) }}</td>
                        <td>{{ $appointment->description }}</td>
                        <td>Rs. {{ number_format($appointment->consultation_fee, 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No appointments found for this filter.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer-note">
            This is a computer-generated report and is not valid for use in any court of law or government office.
        </div>
    </div>
</body>
</html>
