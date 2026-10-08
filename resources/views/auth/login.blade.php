<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @section('title', 'Sign in')
    @include('partials.head')
</head>
<body>
<div class="auth-wrap">
    <button type="button" class="icon-btn auth-theme-toggle" data-action="toggle-theme" aria-label="Toggle dark mode">
        <i class="bi bi-moon-stars" data-theme-icon></i>
    </button>

    <main class="auth-main">
        <div class="auth-card">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-3">
                <span class="brand-logo"><i class="bi bi-graph-up-arrow"></i></span>
                <span class="fw-bold fs-5">{{ config('app.name') }}</span>
            </div>

            <div class="auth-panel">
                <h1 class="h5 fw-bold mb-1 text-center">Welcome back</h1>
                <p class="text-muted small mb-3 text-center">Sign in to your account to continue.</p>

                <form method="POST" action="{{ route('login.store') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <div class="input-icon">
                            <i class="bi bi-envelope"></i>
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror"
                                   placeholder="you@company.com" required autofocus autocomplete="username" maxlength="255">
                        </div>
                        @error('email')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-icon position-relative">
                            <i class="bi bi-lock"></i>
                            <input type="password" id="password" name="password"
                                   class="form-control pe-5 @error('password') is-invalid @enderror"
                                   placeholder="••••••••" required autocomplete="current-password">
                            <button type="button" class="btn btn-ghost btn-icon position-absolute top-50 end-0 translate-middle-y me-1"
                                    aria-label="Show password" data-no-loading data-action="toggle-password" data-target="password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        @error('password')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                        <label class="form-check-label" for="remember">Keep me signed in</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Sign in <i class="bi bi-arrow-right"></i></button>
                </form>

                @if (app()->environment('local') || config('app.demo_logins'))
                    <div class="demo-creds">
                        <span class="text-muted"><i class="bi bi-info-circle"></i> Demo:</span>
                        @foreach (['admin@crm.test' => 'Admin', 'sales1@crm.test' => 'Sales 1', 'sales2@crm.test' => 'Sales 2'] as $email => $label)
                            <button type="button" class="btn btn-sm btn-light-soft" data-no-loading
                                    data-action="fill-demo" data-email="{{ $email }}" data-password="{{ config('app.demo_password') }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </main>
</div>

@include('partials.flash')
@include('partials.scripts')
</body>
</html>
