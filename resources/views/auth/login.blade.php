@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')
    <section class="auth-hero">
        <div class="auth-card">
            <div class="auth-card-left">
                <div class="inner">
                    <h2>{{ __('Welcome Back!') }}</h2>
                    <p>{{ __('Sign in to access your dashboard and manage clinic operations.') }}</p>

                    <div class="auth-feature">
                        <span class="auth-feature-icon"><i class="fas fa-shield-alt"></i></span>
                        <div>
                            <h6>{{ __('Secure & Protected') }}</h6>
                            <p>{{ __('Your data is safe with us') }}</p>
                        </div>
                    </div>
                    <div class="auth-feature">
                        <span class="auth-feature-icon"><i class="fas fa-bolt"></i></span>
                        <div>
                            <h6>{{ __('Fast & Reliable') }}</h6>
                            <p>{{ __('Quick access to all features') }}</p>
                        </div>
                    </div>
                    <div class="auth-feature">
                        <span class="auth-feature-icon"><i class="fas fa-user-check"></i></span>
                        <div>
                            <h6>{{ __('User Friendly') }}</h6>
                            <p>{{ __('Designed for a seamless experience') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="auth-card-right">
                <h3>{{ __('Sign In') }}</h3>
                <p class="subtitle">{{ __('Enter your credentials to continue') }}</p>

                <form method="POST" action="{{ route('login') }}" class="auth-form">
                    @csrf

                    <div class="form-group">
                        <label for="email">{{ __('Email Address') }}</label>
                        <input type="email" name="email" id="email"
                            class="form-control @error('email') is-invalid @enderror"
                            placeholder="{{ __('Enter your email address') }}" value="{{ old('email') }}" required
                            autofocus>
                        @error('email')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password">{{ __('Password') }}</label>
                        <div class="password-field">
                            <input type="password" name="password" id="password"
                                class="form-control @error('password') is-invalid @enderror"
                                placeholder="{{ __('Enter your password') }}" required autocomplete="current-password">
                            <button type="button" class="toggle-password"
                                onclick="togglePassword('password', 'togglePasswordIcon')">
                                <i class="fas fa-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                        @error('password')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember"
                                {{ old('remember') ? 'checked' : '' }}>
                            <label class="form-check-label" for="remember">{{ __('Remember me') }}</label>
                        </div>
                        @if (Route::has('password.request'))
                            <a class="forgot-link" href="{{ route('password.request') }}">{{ __('Forgot Password?') }}</a>
                        @endif
                    </div>

                    <button type="submit" class="auth-submit-btn">{{ __('Sign In') }}</button>
                </form>
            </div>
        </div>
    </section>
@endsection
