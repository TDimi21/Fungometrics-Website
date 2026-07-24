<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // Local dev needs the Vite dev-server origin allowed too (script tags
        // + stylesheet are served from 127.0.0.1:5173, not this app's own
        // origin) and 'unsafe-inline' for script-src, since the blade
        // template's inline window.onerror handler otherwise gets blocked
        // right alongside the real scripts.
        $scriptSources = app()->environment('local')
            ? "'self' 'unsafe-eval' 'unsafe-inline' http://127.0.0.1:5173 http://localhost:5173"
            : "'self'";
        $styleSources = app()->environment('local')
            ? "'self' 'unsafe-inline' https://fonts.googleapis.com http://127.0.0.1:5173 http://localhost:5173"
            : "'self' 'unsafe-inline' https://fonts.googleapis.com";
        // Local dev's frontend deliberately talks to the PRODUCTION api (see
        // .env's VITE_API_ENDPOINT comment: "localhost uses real prod data +
        // credentials"), so that origin has to be allowed here too — without
        // it, axios/fetch calls from the locally-served page are silently
        // blocked by CSP before they leave the page, and axios's rejected
        // promise ends up with no `.response` at all (a CSP block isn't an
        // HTTP response), which crashes any .catch() that assumes one.
        $connectSources = app()->environment('local')
            ? "'self' ws: wss: http://localhost:* http://127.0.0.1:* https://app.fmtrx.com"
            : "'self'";

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src {$scriptSources}",
            "style-src {$styleSources}",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "connect-src {$connectSources}",
            "media-src 'self' blob: https:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
