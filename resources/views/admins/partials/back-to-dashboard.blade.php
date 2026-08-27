@php
    $landingRoute = auth()->user()->landingRouteName();
    $landingLabels = [
        'admin_dashboard' => 'Back to Dashboard',
        'appointment' => 'Back to Appointments',
        'admin_patients' => 'Back to Patients',
        'admin_invoices' => 'Back to Invoices',
        'admin_consultation_forms' => 'Back to Consultation Forms',
        'admin_reports' => 'Back to Reports',
    ];
@endphp
@if (!request()->routeIs($landingRoute))
    <a href="{{ route($landingRoute) }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> {{ $landingLabels[$landingRoute] ?? 'Back to Dashboard' }}</a>
@endif
