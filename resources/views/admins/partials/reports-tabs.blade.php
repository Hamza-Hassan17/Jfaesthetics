<style>
    .jfr-report-tabs { border-bottom: 2px solid #e9eef0; margin-bottom: 22px; }
    .jfr-report-tabs a {
        display: inline-block;
        padding: 10px 20px;
        color: #7a8a8a;
        font-weight: 700;
        font-size: 14px;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
    }
    .jfr-report-tabs a:hover { color: #148080; text-decoration: none; }
    .jfr-report-tabs a.active { color: #148080; border-color: #148080; }
</style>
<div class="jfr-report-tabs">
    <a href="{{ route('admin_reports') }}" class="{{ request()->routeIs('admin_reports') ? 'active' : '' }}">Consultancy Report</a>
    <a href="{{ route('admin_reports_appointments') }}" class="{{ request()->routeIs('admin_reports_appointments') ? 'active' : '' }}">Appointment Report</a>
</div>
