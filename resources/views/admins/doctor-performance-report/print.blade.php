<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Doctor Performance Report</title>
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
        .footer-note {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 10.5px;
            color: #777;
            text-align: center;
            font-style: italic;
        }
        .brand-center { text-align: center; margin-bottom: 20px; }
        .brand-center h1 { margin: 0 0 4px; font-size: 24px; color: #333; }
        .brand-center p { margin: 2px 0; font-size: 12px; color: #555; }
        .report-meta { text-align: center; margin-bottom: 20px; }
        .report-meta h2 { margin: 0 0 6px; font-weight: 400; color: #333; font-size: 18px; }
        .report-meta p { margin: 2px 0; font-size: 13px; color: #555; }
        .doctor-block { margin-top: 25px; page-break-inside: avoid; }
        .doctor-block h3 { margin: 0 0 6px; font-size: 15px; color: #148080; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        .doctor-block .counts { font-size: 12px; color: #555; margin-bottom: 8px; }
        .doctor-columns { display: flex; gap: 20px; }
        .doctor-columns > div { flex: 1; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f4f4f4; }
        .section-title { font-size: 13px; font-weight: 600; color: #333; margin-bottom: 4px; }
        .empty-note { font-size: 12px; color: #999; font-style: italic; }
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
            <h2>Doctor Performance Report</h2>
            <p>{{ \Carbon\Carbon::parse($from)->format('d M Y') }} &mdash; {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
        </div>

        @forelse ($report as $row)
            <div class="doctor-block">
                <h3>{{ $row['doctor_name'] }}</h3>
                <div class="counts">{{ $row['services_count'] }} services sold &nbsp;|&nbsp; {{ $row['appointments_count'] }} appointments</div>
                <div class="doctor-columns">
                    <div>
                        <div class="section-title">Services Sold</div>
                        @if ($row['service_lines']->isEmpty())
                            <p class="empty-note">No services sold in this period.</p>
                        @else
                            <table>
                                <thead><tr><th>Service</th><th>Patient</th><th>Qty</th></tr></thead>
                                <tbody>
                                    @foreach ($row['service_lines'] as $line)
                                        <tr>
                                            <td>{{ $line['service'] }}</td>
                                            <td>{{ $line['patient_name'] }}</td>
                                            <td>{{ $line['quantity'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                    <div>
                        <div class="section-title">Appointments</div>
                        @if ($row['appointments']->isEmpty())
                            <p class="empty-note">No appointments in this period.</p>
                        @else
                            <table>
                                <thead><tr><th>Patient</th><th>Date</th></tr></thead>
                                <tbody>
                                    @foreach ($row['appointments'] as $appt)
                                        <tr>
                                            <td>{{ $appt['patient_name'] }}</td>
                                            <td>{{ \Carbon\Carbon::parse($appt['date'])->format('d M Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="empty-note">No doctors found.</p>
        @endforelse

        <div class="footer-note">
            This is a computer-generated report and is not valid for use in any court of law or government office.
        </div>
    </div>
</body>
</html>
