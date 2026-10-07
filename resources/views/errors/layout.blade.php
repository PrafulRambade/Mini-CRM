{{-- Self-contained: no auth, DB or session access, so it renders even when those are failing. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('code') · @yield('title')</title>
    <script>
        (function () { var t = null; try { t = localStorage.getItem('crm-theme'); } catch (e) {}
            if (!t) t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-bs-theme', t); })();
    </script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/app.css">
    <style>
        .error-wrap { min-height: 100vh; display: grid; place-items: center; padding: 2rem 1rem; text-align: center; }
        .error-code {
            font-size: clamp(5rem, 18vw, 9rem); font-weight: 800; line-height: 1; letter-spacing: -.06em;
            background: linear-gradient(135deg, var(--primary), #6d5dfc); -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .error-icon { width: 64px; height: 64px; display: grid; place-items: center; border-radius: 18px; font-size: 1.6rem; margin: 0 auto 1.5rem; }
    </style>
</head>
<body>
<main class="error-wrap">
    <div style="max-width: 460px">
        <div class="error-icon tone-@yield('tone', 'primary')"><i class="bi @yield('icon', 'bi-exclamation-circle')"></i></div>
        <div class="error-code">@yield('code')</div>
        <h1 class="h3 fw-bold mt-3">@yield('title')</h1>
        <p class="text-muted mb-4">@yield('message')</p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="javascript:history.back()" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i> Go back</a>
            <a href="/dashboard" class="btn btn-primary"><i class="bi bi-house-door"></i> Dashboard</a>
        </div>
    </div>
</main>
</body>
</html>
