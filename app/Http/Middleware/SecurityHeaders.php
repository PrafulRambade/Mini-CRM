<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Fresh nonce for every request (also correct under long-running workers).
        app()->instance('csp.nonce', base64_encode(random_bytes(18)));

        // Same policy for a <meta> tag: some hosts (e.g. Hostinger/LiteSpeed "Force HTTPS")
        // overwrite the CSP header, but can't touch the page. frame-ancestors is not allowed
        // in <meta>; X-Frame-Options: DENY covers clickjacking there.
        app()->instance('csp.meta', $this->contentSecurityPolicy($request, forMeta: true));

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Don't advertise the PHP version.
        $response->headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Laravel's local debug error page relies on inline scripts; everything else gets the strict policy.
        if (! (config('app.debug') && $response->getStatusCode() >= 500)) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));
        }

        return $response;
    }

    /**
     * Everything is self-hosted, so only our own origin is allowed. Scripts must
     * also carry this request's nonce, which blocks any injected <script>.
     */
    private function contentSecurityPolicy(Request $request, bool $forMeta = false): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-".app('csp.nonce')."'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        if (! $forMeta) {
            $directives[] = "frame-ancestors 'none'";
        }

        if ($request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
