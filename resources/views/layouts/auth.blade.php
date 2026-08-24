<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@php
    $settings = \App\Models\Settings::pluck('value', 'key')->toArray();
@endphp

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $settings['title'] ?? config('app.name') }} - @yield('title', 'Sign In')</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito:400,600,700,800" rel="stylesheet">

    @if (!empty($settings['icon']))
        <link rel="shortcut icon" href="{{ config('app.url') . 'storage/' . $settings['icon'] }}" type="image/x-icon">
    @endif

    <link href="{{ config('app.url') }}assets/vendor/fontawesome/css/fontawesome.min.css" rel="stylesheet">
    <link href="{{ config('app.url') }}assets/vendor/fontawesome/css/solid.min.css" rel="stylesheet">
    <link href="{{ config('app.url') }}assets/vendor/fontawesome/css/brands.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ config('app.url') }}css/bootstrap.min.css">

    @livewireStyles

    <style>
        :root {
            --jf-teal-dark: #0a3535;
            --jf-teal: #148080;
            --jf-teal-light: #1ea6a3;
            --jf-gold: #c9a227;
            --jf-gold-light: #e3c568;
            --jf-ink: #1f2d2d;
            --jf-muted: #64777a;
        }

        html, body {
            height: 100%;
        }

        body.auth-body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--jf-ink);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .auth-body main {
            flex: 1 0 auto;
        }

        /* ---------- Topbar ---------- */
        .auth-topbar {
            background: #fff;
            border-bottom: 1px solid #eef1f1;
            padding: 14px 0;
            box-shadow: 0 2px 10px rgba(10, 53, 53, 0.04);
        }

        .auth-topbar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .auth-topbar .logo img {
            height: 46px;
            width: auto;
        }

        .auth-topbar .contact-strip {
            display: flex;
            align-items: center;
            gap: 28px;
        }

        .auth-topbar .contact-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .auth-topbar .contact-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            border-radius: 50%;
            background: rgba(20, 128, 128, 0.1);
            color: var(--jf-teal);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        .auth-topbar .contact-text a {
            display: block;
            color: var(--jf-ink);
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            line-height: 1.3;
        }

        .auth-topbar .contact-text span {
            display: block;
            color: var(--jf-muted);
            font-size: 12px;
        }

        /* ---------- Hero / sign-in card ---------- */
        .auth-hero {
            position: relative;
            padding: 70px 15px;
            background: linear-gradient(135deg, rgba(8, 42, 42, 0.93), rgba(15, 74, 74, 0.88)),
                url('{{ config('app.url') }}images/slider-bg.png') center/cover no-repeat;
        }

        .auth-card {
            max-width: 960px;
            margin: 0 auto;
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(4, 26, 26, 0.35);
            display: flex;
            flex-wrap: wrap;
        }

        .auth-card-left {
            flex: 1 1 42%;
            min-width: 300px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(160deg, #0a3535 0%, #145c5c 60%, #1a6b6b 100%);
            color: #fff;
            padding: 48px 42px;
        }

        .auth-card-left::before {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.14);
            top: -90px;
            right: -90px;
        }

        .auth-card-left::after {
            content: "";
            position: absolute;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 1px solid rgba(201, 162, 39, 0.25);
            bottom: -60px;
            left: -60px;
        }

        .auth-card-left .inner {
            position: relative;
            z-index: 1;
        }

        .auth-card-left h2 {
            font-weight: 800;
            font-size: 28px;
            margin-bottom: 12px;
        }

        .auth-card-left > .inner > p {
            color: rgba(255, 255, 255, 0.78);
            font-size: 14.5px;
            margin-bottom: 34px;
        }

        .auth-feature {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 22px;
        }

        .auth-feature:last-child {
            margin-bottom: 0;
        }

        .auth-feature-icon {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.14);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--jf-gold-light);
            font-size: 15px;
        }

        .auth-feature h6 {
            color: #fff;
            font-weight: 700;
            font-size: 14.5px;
            margin-bottom: 2px;
        }

        .auth-feature p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
            margin-bottom: 0;
        }

        .auth-card-right {
            flex: 1 1 58%;
            min-width: 320px;
            padding: 48px 46px;
        }

        .auth-card-right h3 {
            font-weight: 800;
            color: var(--jf-teal-dark);
            margin-bottom: 6px;
        }

        .auth-card-right > p.subtitle {
            color: var(--jf-muted);
            font-size: 14px;
            margin-bottom: 28px;
        }

        .auth-form .form-group {
            margin-bottom: 20px;
        }

        .auth-form label {
            font-weight: 700;
            font-size: 13.5px;
            color: var(--jf-ink);
            margin-bottom: 6px;
        }

        .auth-form .form-control {
            height: 48px;
            border-radius: 10px;
            border: 1px solid #e1e7e7;
            padding: 0 16px;
            font-size: 14px;
        }

        .auth-form .form-control:focus {
            border-color: var(--jf-teal);
            box-shadow: 0 0 0 3px rgba(20, 128, 128, 0.12);
        }

        .auth-form .password-field {
            position: relative;
        }

        .auth-form .password-field .form-control {
            padding-right: 44px;
        }

        .auth-form .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: 0;
            color: var(--jf-muted);
            cursor: pointer;
            padding: 4px;
        }

        .auth-form .form-check-label {
            font-size: 13.5px;
            color: var(--jf-ink);
        }

        .auth-form .forgot-link {
            color: var(--jf-teal);
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
        }

        .auth-form .forgot-link:hover {
            color: var(--jf-gold);
            text-decoration: underline;
        }

        .auth-submit-btn {
            width: 100%;
            height: 50px;
            border: 0;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--jf-teal-light), var(--jf-teal-dark));
            color: #fff;
            font-weight: 800;
            font-size: 15px;
            letter-spacing: .3px;
            box-shadow: 0 12px 24px rgba(20, 128, 128, 0.28);
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .auth-submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 28px rgba(20, 128, 128, 0.35);
            color: #fff;
        }

        .auth-form .invalid-feedback {
            display: block;
        }

        /* ---------- Footer ---------- */
        .auth-footer {
            background: linear-gradient(180deg, #0c3232, #071e1e);
            color: rgba(255, 255, 255, 0.85);
            padding: 46px 0 20px;
        }

        .auth-footer h3 {
            color: #fff;
            font-weight: 800;
            font-size: 16px;
            letter-spacing: .4px;
            margin-bottom: 18px;
        }

        .auth-footer p {
            font-size: 13.5px;
            color: rgba(255, 255, 255, 0.72);
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .auth-footer p i {
            color: var(--jf-gold-light);
            margin-top: 3px;
        }

        .auth-footer .subcriber-info p {
            display: block;
        }

        .auth-footer .newsletter-form {
            position: relative;
            margin-top: 6px;
        }

        .auth-footer .newsletter-form .form-control {
            height: 48px;
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
            padding: 0 54px 0 18px;
            font-size: 13.5px;
        }

        .auth-footer .newsletter-form .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .auth-footer .newsletter-form .form-control:focus {
            outline: none;
            border-color: var(--jf-gold);
        }

        .auth-footer .newsletter-form .mc-submit {
            position: absolute;
            right: 5px;
            top: 5px;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 0;
            background: linear-gradient(135deg, var(--jf-gold-light), var(--jf-gold));
            color: #0a3535;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-footer .mailchimp-alerts {
            font-size: 12.5px;
            margin-top: 8px;
        }

        .auth-copyright {
            background: #051616;
            color: rgba(255, 255, 255, 0.55);
            text-align: center;
            font-size: 12.5px;
            padding: 14px 0;
        }

        @media (max-width: 991px) {
            .auth-topbar .contact-strip {
                display: none;
            }
        }

        @media (max-width: 575px) {
            .auth-card-left, .auth-card-right {
                padding: 34px 26px;
            }
        }
    </style>

    @yield('head')
</head>

<body class="auth-body">
    <header class="auth-topbar">
        <div class="container">
            <a class="logo" href="{{ url('/') }}">
                <img src="{{ config('app.url') }}images/logo.png" alt="{{ $settings['title'] ?? config('app.name') }} logo">
            </a>
            <div class="contact-strip">
                <div class="contact-item">
                    <span class="contact-icon"><i class="fas fa-phone-alt"></i></span>
                    <span class="contact-text">
                        <a href="tel:{{ $settings['business_phone'] ?? '123 123 123' }}">{{ $settings['business_phone'] ?? '123 123 123' }}</a>
                        <span>Call Us</span>
                    </span>
                </div>
                <div class="contact-item">
                    <span class="contact-icon"><i class="fas fa-envelope"></i></span>
                    <span class="contact-text">
                        <a href="mailto:{{ $settings['business_email'] ?? 'info@jfaesthetics.com' }}">{{ $settings['business_email'] ?? 'info@jfaesthetics.com' }}</a>
                        <span>Email Us</span>
                    </span>
                </div>
                <div class="contact-item">
                    <span class="contact-icon"><i class="fas fa-clock"></i></span>
                    <span class="contact-text">
                        <a href="#">Mon - Sat: {{ $settings['working_horse'] ?? '10:00 AM - 6:00 PM' }}</a>
                        <span>Working Hours</span>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="auth-footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6 mb-4 mb-md-0">
                    <h3>CONTACT US</h3>
                    <p><i class="fas fa-map-marker-alt"></i> {{ $settings['address'] ?? 'Defence Phase 5, Near Medicare Clinic, Karachi' }}</p>
                    <p><i class="fas fa-envelope"></i> {{ $settings['business_email'] ?? 'info@jfaesthetics.com' }}</p>
                    <p><i class="fas fa-phone-alt"></i> {{ $settings['business_phone'] ?? '123 123 123' }}</p>
                </div>
                @livewire('subscribe')
            </div>
        </div>
    </footer>
    <div class="auth-copyright">
        <div class="container">
            &copy; {{ date('Y') }} {{ $settings['title'] ?? config('app.name') }}. All rights reserved. Designed and Developed by
            <a href="https://supersofttechnology.com/" target="_blank" rel="noopener" style="color: rgba(255,255,255,.75);">Supersoft Technologies</a>
        </div>
    </div>

    @livewireScripts
    <script src="{{ config('app.url') }}assets/vendor/jquery/jquery.min.js"></script>
    <script src="{{ config('app.url') }}assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(fieldId, iconId) {
            const input = document.getElementById(fieldId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
    @yield('scripts')
</body>

</html>
