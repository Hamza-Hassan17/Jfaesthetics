@php
    $lowStockCount = \App\Models\medicine::whereColumn('quantity', '<=', 'low_stock_threshold')->count();
    $adminSettings = \App\Models\Settings::pluck('value', 'key')->toArray();
@endphp
<!doctype html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ env('APP_NAME') }} Admin</title>
    <link href="{{ config('app.url') }}assets/vendor/fontawesome/css/fontawesome.min.css" rel="stylesheet">
    <link href="{{ config('app.url') }}assets/vendor/fontawesome/css/solid.min.css" rel="stylesheet">
    <link href="{{ config('app.url') }}assets/vendor/fontawesome/css/brands.min.css" rel="stylesheet">
    <link href="{{ config('app.url') }}assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ config('app.url') }}assets/css/master.css" rel="stylesheet">
    <link href="{{  config('app.url') }}assets/vendor/chartsjs/Chart.min.css" rel="stylesheet">
    <script src="{{ config('app.url') }}assets/vendor/chartsjs/Chart.min.js"></script>
    <link href="{{ config('app.url') }}assets/vendor/flagiconcss/css/flag-icon.min.css" rel="stylesheet">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">



    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        #sidebar ul li a:hover,
        #sidebar ul li.active > a {
            color: #0a3535;
            background: rgba(20, 128, 128, 0.1);
            font-weight: 700;
            border-right: 3px solid #148080;
        }

        #sidebar ul li a:hover i,
        #sidebar ul li.active > a i {
            color: #148080;
        }

        #sidebar {
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
        }

        #sidebar ul.components {
            overflow-y: auto;
        }

        .jf-support-card {
            margin-top: auto;
            flex-shrink: 0;
            padding: 16px;
        }

        .jf-support-card .inner {
            background: linear-gradient(135deg, rgba(20, 128, 128, 0.1), rgba(201, 162, 39, 0.08));
            border: 1px solid rgba(20, 128, 128, 0.15);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }

        .jf-support-card i.fa-life-ring {
            font-size: 22px;
            color: #148080;
            margin-bottom: 8px;
        }

        .jf-support-card h6 {
            font-weight: 700;
            font-size: 13.5px;
            color: #0a3535;
            margin-bottom: 2px;
        }

        .jf-support-card p {
            font-size: 12px;
            color: #64777a;
            margin-bottom: 10px;
        }

        .jf-support-card .btn-jf-support {
            background: #148080;
            color: #fff;
            font-size: 12.5px;
            font-weight: 700;
            padding: 6px 16px;
            border-radius: 20px;
            display: inline-block;
        }

        .jf-support-card .btn-jf-support:hover {
            background: #0d5c5c;
            color: #fff;
        }

        .jf-sidebar-version {
            text-align: center;
            font-size: 11px;
            color: #97a5a5;
            padding: 8px 16px 16px;
            flex-shrink: 0;
        }

        .jf-navbar-search {
            position: relative;
            width: 260px;
        }

        .jf-navbar-search input {
            width: 100%;
            height: 38px;
            border-radius: 20px;
            border: 1px solid #e6ecec;
            background: #f6f9f9;
            padding: 0 16px 0 38px;
            font-size: 13px;
        }

        .jf-navbar-search input:focus {
            outline: none;
            border-color: #148080;
            background: #fff;
        }

        .jf-navbar-search i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #97a5a5;
            font-size: 13px;
        }

        .jf-navbar-icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1px solid #e6ecec;
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #4a5a5a;
            position: relative;
        }

        .jf-navbar-icon-btn:hover {
            background: #f6f9f9;
            color: #148080;
        }

        .jf-navbar-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #e05353;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            min-width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 3px;
        }

        .jf-navbar-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(20, 128, 128, 0.12);
            color: #148080;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .jf-navbar-user-text {
            line-height: 1.2;
            text-align: left;
        }

        .jf-navbar-user-text .name {
            font-weight: 700;
            font-size: 13.5px;
            color: #0a3535;
            display: block;
        }

        .jf-navbar-user-text .role {
            font-size: 11.5px;
            color: #97a5a5;
            display: block;
        }
    </style>
    @livewireStyles
</head>

<body class="clinic_version">

    <div class="wrapper">
        <nav id="sidebar" class="mt-4">
            <ul class="mt-5 list-unstyled components text-secondary">
                {{-- @auth --}}
                <li class="{{ request()->routeIs('admin_dashboard') ? 'active' : '' }}">
                    <a href="{{ route('admin_dashboard') }}"><i class="fas fa-home"></i> Dashboard</a>
                </li>
                {{-- <li>
                    <a href="{{ route('admin_docters') }}"><i class="fas fa-file-alt"></i>Docters</a>
                </li> --}}
                {{-- Hidden per request: Operation report --}}
                {{-- <li>
                    <a href="{{ route('admin_operations_report') }}"><i class="fas fa-file-alt"></i>Operation
                        report</a>
                </li> --}}
                {{-- Hidden per request: Birth Report --}}
                {{-- <li>
                    <a href="{{ route('admin_birth_report') }}"><i class="fas fa-file-alt"></i>Birth Report</a>
                </li> --}}
                <li class="{{ request()->routeIs('admin_patients') ? 'active' : '' }}">
                    <a href="{{ route('admin_patients') }}"><i class="fas fa-file-alt"></i>Patients</a>
                </li>
                {{-- Hidden per request: Nurses --}}
                {{-- <li>
                    <a href="{{ route('nurses') }}"><i class="fas fa-file-alt"></i>Nurses</a>
                </li> --}}
                {{-- Hidden per request: Appointments --}}
                {{-- <li>
                    <a href="{{ route('appointment') }}"><i class="fas fa-file-alt"></i>Appointments</a>
                </li> --}}
                <li class="{{ request()->routeIs('employees') ? 'active' : '' }}">
                    <a href="{{ route('employees') }}"><i class="fas fa-file-alt"></i>Employees</a>
                </li>
                {{-- Hidden per request: Department --}}
                {{-- <li>
                    <a href="{{ route('departments') }}"><i class="fas fa-file-alt"></i>Department</a>
                </li> --}}
                {{-- Hidden per request: Rooms --}}
                {{-- <li>
                    <a href="{{ route('Rooms') }}"><i class="fas fa-file-alt"></i>Rooms</a>
                </li> --}}
                {{-- Hidden per request: Beds --}}
                {{-- <li>
                    <a href="{{ route('patients_beds') }}"><i class="fas fa-file-alt"></i>Beds</a>
                </li> --}}
                <li class="{{ request()->routeIs('admin_doctor_performance_report') ? 'active' : '' }}">
                    <a href="{{ route('admin_doctor_performance_report') }}"><i class="fas fa-chart-line"></i>Doctor Performance</a>
                </li>

                <li class="{{ request()->routeIs('medicinesStore') ? 'active' : '' }}">
                    <a href="{{ route('medicinesStore') }}"><i class="fas fa-file-alt"></i>Medicines Store</a>
                </li>
                <li class="{{ request()->routeIs('admin_services') ? 'active' : '' }}">
                    <a href="{{ route('admin_services') }}"><i class="fas fa-list"></i>Services</a>
                </li>
                {{-- Hidden per request: HOD's --}}
                {{-- <li>
                    <a href="{{ route('hods') }}"><i class="fas fa-file-alt"></i>HOD's</a>
                </li> --}}
                {{-- Hidden per request: Blocks --}}
                {{-- <li>
                    <a href="{{ route('blocks') }}"><i class="fas fa-file-alt"></i>Blocks</a>
                </li> --}}
                {{-- Hidden per request: Appointment Requests --}}
                {{-- <li>
                    <a href="{{ route('requestedAppointment') }}"><i class="fas fa-file-alt"></i>Appointment
                        Requests</a>
                </li> --}}
                {{-- Hidden per request: Subscribers --}}
                {{-- <li>
                    <a href="{{ route('subscibers') }}"><i class="fas fa-file-alt"></i>Subscribers</a>
                </li> --}}
                {{-- Hidden per request: Contacted Messages --}}
                {{-- <li>
                    <a href="{{ route('contactedus') }}"><i class="fas fa-file-alt"></i>Contacted Messages</a>
                </li> --}}
                <li class="{{ request()->routeIs('admin_invoices*') ? 'active' : '' }}">
                    <a href="{{ route('admin_invoices') }}"><i class="fas fa-file-invoice-dollar"></i>Reports</a>
                </li>
                <li class="{{ request()->routeIs('admin_settings') ? 'active' : '' }}">
                    <a href="{{ route('admin_settings') }}"><i class="fas fa-cog"></i>Settings</a>
                </li>
            </ul>

            <div class="jf-support-card">
                <div class="inner">
                    <i class="fas fa-life-ring"></i>
                    <h6>Need Help?</h6>
                    <p>Contact Support</p>
                    <a href="mailto:{{ $adminSettings['business_email'] ?? 'info@jfaesthetics.com' }}" class="btn-jf-support">Get Support <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <div class="jf-sidebar-version">
                Version 1.0.0<br>
                &copy; {{ date('Y') }} {{ $adminSettings['title'] ?? env('APP_NAME') }}
            </div>
        </nav>
        <div id="body" class="active d-flex flex-column" style="min-height: 100vh;">
            <nav class="navbar navbar-expand-lg fixed-top navbar-white bg-white" style="position: relative;">
                <button type="button" id="sidebarCollapse" class="btn btn-light"><i
                        class="fas fa-bars"></i><span></span></button>
                <a href="{{ route('admin_dashboard') }}" class="d-flex align-items-center" style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); white-space: nowrap;">
                    <img src="{{ config('app.url') }}images/logo.png" alt="logo" style="height: 40px; width: auto;">
                </a>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="nav navbar-nav ml-auto align-items-center" style="gap: 12px;">
                        <li class="nav-item d-none d-lg-block">
                            <div class="jf-navbar-search">
                                <i class="fas fa-search"></i>
                                <input type="text" id="jfNavbarQuickSearch" placeholder="Search anything..." autocomplete="off">
                            </div>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('medicinesStore') }}" class="jf-navbar-icon-btn" title="Low stock alerts">
                                <i class="fas fa-bell"></i>
                                @if ($lowStockCount > 0)
                                    <span class="jf-navbar-badge">{{ $lowStockCount }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item d-none d-md-block">
                            <button type="button" id="jfFullscreenToggle" class="jf-navbar-icon-btn" title="Toggle fullscreen" style="border: 1px solid #e6ecec;">
                                <i class="fas fa-expand"></i>
                            </button>
                        </li>
                        <li class="nav-item dropdown">
                            <div class="nav-dropdown">
                                <a href="" class="nav-item nav-link dropdown-toggle text-secondary d-flex align-items-center"
                                    data-toggle="dropdown" style="gap: 8px;">
                                    <span class="jf-navbar-avatar"><i class="fas fa-user"></i></span>
                                    <span class="jf-navbar-user-text d-none d-md-block">
                                        <span class="name">{{ auth()->user()->name ?? 'Admin' }}</span>
                                        <span class="role">Administrator</span>
                                    </span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right nav-link-menu">
                                    <ul class="nav-list">
                                        <li><a href="{{ route('admin_settings') }}" class="dropdown-item"><i
                                                    class="fas fa-address-card"></i>
                                                Profile</a></li>
                                        <li><a href="{{ route('admin_settings') }}" class="dropdown-item"><i class="fas fa-cog"></i>
                                                Settings</a>
                                        </li>
                                        <div class="dropdown-divider"></div>
                                        <li>
                                            <form method="POST" action="{{ route('logout') }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item logout-button">
                                                    <i class="fas fa-sign-out-alt"></i> Logout
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </nav>
            <div class="content" style="flex: 1 0 auto;">
                <div class="container">
                    <br><br><br>

                    {{ $slot }}

                    @yield('admin_content')
                </div>
            </div>

            <footer class="text-center text-muted py-3" style="font-size: 13px; border-top: 1px solid #e6ecf5; background: #fff; flex-shrink: 0;">
                Designed and Developed by Supersoft Technologies
            </footer>

            @livewireScripts

            <script src="{{ config('app.url') }}js/livewire-turbolinks.js"></script>
            <script src="{{ config('app.url') }}js/alpine.js"></script>
            <script src="{{ config('app.url') }}assets/vendor/jquery/jquery.min.js"></script>
            <script src="{{ config('app.url') }}assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
            <script src="{{ config('app.url') }}assets/js/script.js"></script>
            <script>
                document.getElementById('jfFullscreenToggle')?.addEventListener('click', function () {
                    if (!document.fullscreenElement) {
                        document.documentElement.requestFullscreen?.();
                    } else {
                        document.exitFullscreen?.();
                    }
                });

                (function () {
                    var quickNavRoutes = {
                        'dashboard': '{{ route('admin_dashboard') }}',
                        'patients': '{{ route('admin_patients') }}',
                        'employees': '{{ route('employees') }}',
                        'doctor performance': '{{ route('admin_doctor_performance_report') }}',
                        'medicines': '{{ route('medicinesStore') }}',
                        'medicine store': '{{ route('medicinesStore') }}',
                        'services': '{{ route('admin_services') }}',
                        'invoices': '{{ route('admin_invoices') }}',
                        'reports': '{{ route('admin_invoices') }}',
                        'settings': '{{ route('admin_settings') }}',
                    };
                    var input = document.getElementById('jfNavbarQuickSearch');
                    input?.addEventListener('keydown', function (e) {
                        if (e.key !== 'Enter') return;
                        var term = this.value.trim().toLowerCase();
                        if (!term) return;
                        var matchKey = Object.keys(quickNavRoutes).find(function (key) {
                            return key.includes(term) || term.includes(key);
                        });
                        if (matchKey) {
                            window.location.href = quickNavRoutes[matchKey];
                        }
                    });
                })();
            </script>
        </div>
</body>

</html>
