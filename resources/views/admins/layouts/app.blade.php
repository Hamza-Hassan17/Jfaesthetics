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
    @livewireStyles
</head>

<body class="clinic_version">

    <div class="wrapper">
        <nav id="sidebar" class="mt-4">
            <ul class="mt-5 list-unstyled components text-secondary">
                {{-- @auth --}}
                <li>
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
                <li>
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
                <li>
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
                <li>
                    <a href="{{ route('admin_invoices') }}"><i class="fas fa-file-invoice"></i>Invoices</a>
                </li>
                <li>
                    <a href="{{ route('admin_doctor_performance_report') }}"><i class="fas fa-chart-line"></i>Doctor Performance</a>
                </li>

                <li>
                    <a href="{{ route('medicinesStore') }}"><i class="fas fa-file-alt"></i>Medicines Store</a>
                </li>
                <li>
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
                <li>
                    <a href="{{ route('admin_settings') }}"><i class="fas fa-cog"></i>Settings</a>
                </li>
            </ul>
        </nav>
        <div id="body" class="active d-flex flex-column" style="min-height: 100vh;">
            <nav class="navbar navbar-expand-lg fixed-top navbar-white bg-white" style="position: relative;">
                <button type="button" id="sidebarCollapse" class="btn btn-light"><i
                        class="fas fa-bars"></i><span></span></button>
                <a href="{{ route('admin_dashboard') }}" class="d-flex align-items-center" style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); white-space: nowrap;">
                    <img src="{{ config('app.url') }}images/logo.png" alt="logo" style="height: 40px; width: auto;">
                </a>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="nav navbar-nav ml-auto">
                        <li class="nav-item dropdown">
                            <div class="nav-dropdown">
                                <a href="" class="nav-item nav-link dropdown-toggle text-secondary"
                                    data-toggle="dropdown"><i class="fas fa-link"></i> <span>Quick Access</span> <i
                                        style="font-size: .8em;" class="fas fa-caret-down"></i></a>
                                <div class="dropdown-menu dropdown-menu-right nav-link-menu">
                                    <ul class="nav-list">
                                        <li><a href="" class="dropdown-item"><i class="fas fa-list"></i> Access
                                                Logs</a>
                                        </li>
                                        <div class="dropdown-divider"></div>
                                        <li><a href="" class="dropdown-item"><i class="fas fa-database"></i> Back
                                                ups</a></li>
                                        <div class="dropdown-divider"></div>
                                        <li><a href="" class="dropdown-item"><i
                                                    class="fas fa-cloud-download-alt"></i>
                                                Updates</a></li>
                                        <div class="dropdown-divider"></div>
                                        <li><a href="" class="dropdown-item"><i class="fas fa-user-shield"></i>
                                                Roles</a></li>
                                    </ul>
                                </div>
                            </div>
                        </li>
                        <li class="nav-item dropdown">
                            <div class="nav-dropdown">
                                <a href="" class="nav-item nav-link dropdown-toggle text-secondary"
                                    data-toggle="dropdown"><i class="fas fa-user"></i>
                                    <span>{{ auth()->user()->name ?? '' }}</span> <i style="font-size: .8em;"
                                        class="fas fa-caret-down"></i></a>
                                <div class="dropdown-menu dropdown-menu-right nav-link-menu">
                                    <ul class="nav-list">
                                        <li><a href="" class="dropdown-item"><i
                                                    class="fas fa-address-card"></i>
                                                Profile</a></li>
                                        <li><a href="" class="dropdown-item"><i class="fas fa-envelope"></i>
                                                Messages</a></li>
                                        <li><a href="" class="dropdown-item"><i class="fas fa-cog"></i>
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
        </div>
</body>

</html>
