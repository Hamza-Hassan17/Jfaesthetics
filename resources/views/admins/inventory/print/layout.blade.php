<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { size: A4; margin: 15mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #222; margin: 0; background: #eee; }
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 10mm auto;
            background: #fff;
            padding: 10mm 15mm 15mm;
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
            font-size: 81px;
            font-weight: 700;
            color: #e4e4e4;
            z-index: 0;
            pointer-events: none;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
        .page > *:not(.watermark) { position: relative; z-index: 1; }
        .doc-header { position: relative; min-height: 60px; margin-bottom: 10px; }
        .doc-logo { position: absolute; left: 0; top: 50%; transform: translateY(-50%); height: 55px; width: auto; }
        .brand-center { text-align: center; }
        .brand-center h1 { margin: 0 0 4px; font-size: 24px; color: #333; }
        .brand-center p { margin: 2px 0; font-size: 12px; color: #555; }
        .brand-center .address-line { max-width: 320px; margin: 2px auto; }
        .info-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; margin-bottom: 8px; }
        .info-row > div { flex: 1; }
        .info-row p { margin: 2px 0; font-size: 13px; }
        .doc-meta { text-align: right; }
        .doc-meta h2 { margin: 0 0 6px; font-weight: 400; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; font-size: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f4f4f4; }
        td.num, th.num { text-align: right; }
        .summary { width: 260px; margin-left: auto; margin-top: 10px; }
        .summary table td { border: none; padding: 4px 0; font-size: 13px; }
        .summary table td.value { text-align: right; font-weight: 600; }
        .summary table tr.grand td { border-top: 1px solid #999; padding-top: 8px; font-size: 14px; }
        .notes { margin-top: 10px; font-size: 12px; color: #444; }
        .signatures { display: flex; justify-content: space-between; margin-top: 40px; font-size: 12px; }
        .signatures div { border-top: 1px solid #999; width: 40%; text-align: center; padding-top: 4px; }
        .footer-note { margin-top: 14px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 10.5px; color: #777; text-align: center; font-style: italic; }
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

        <div class="doc-header">
            <img class="doc-logo" src="{{ config('app.url') }}images/logo.png" alt="{{ $settings['title'] ?? config('app.name') }} logo">
            <div class="brand-center">
                <h1>{{ $settings['title'] ?? config('app.name') }}</h1>
                <p>UAN: {{ $settings['business_phone'] ?? '' }}</p>
                <p class="address-line">Address: {{ $settings['address'] ?? '' }}</p>
            </div>
        </div>

        <div class="info-row">
            <div>@yield('party')</div>
            <div class="doc-meta">@yield('meta')</div>
        </div>

        @yield('body')

        <div class="footer-note">This is a computer-generated document.</div>
    </div>
</body>
</html>
