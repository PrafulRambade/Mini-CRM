<meta charset="utf-8">
@if (app()->bound('csp.meta'))
<meta http-equiv="Content-Security-Policy" content="{{ app('csp.meta') }}">
@endif
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%232a78d6'/%3E%3Cpath d='M9 21l4.5-6 3.5 4 6-8' stroke='white' stroke-width='2.6' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
<script @nonce>
    // Apply theme + sidebar state before first paint (no flash).
    (function () {
        var d = document.documentElement, t = null, m = null;
        try { t = localStorage.getItem('crm-theme'); m = localStorage.getItem('crm-sidebar-mini'); } catch (e) {}
        if (!t) t = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        d.setAttribute('data-bs-theme', t);
        if (m === '1') d.classList.add('sidebar-mini');
    })();
</script>
{{-- All front-end libraries are self-hosted: no third-party CDN can alter them. --}}
<link rel="preload" href="{{ asset('vendor/inter/inter-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
