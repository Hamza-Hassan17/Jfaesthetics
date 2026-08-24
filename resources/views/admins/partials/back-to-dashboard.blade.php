@if (!request()->routeIs('admin_dashboard'))
    <a href="{{ route('admin_dashboard') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
@endif
