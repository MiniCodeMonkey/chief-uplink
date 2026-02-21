<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        if ($response->headers->has('Content-Type')
            && ! str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            return $response;
        }

        $nonce = Vite::cspNonce();

        $csp = $this->buildCspPolicy($nonce, $request);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }

    private function buildCspPolicy(string $nonce, Request $request): string
    {
        $reverbHost = env('REVERB_HOST', 'localhost');
        $reverbPort = env('REVERB_PORT', 8080);
        $reverbScheme = env('REVERB_SCHEME', 'https') === 'https' ? 'wss' : 'ws';

        $wsUrl = "{$reverbScheme}://{$reverbHost}:{$reverbPort}";

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'nonce-{$nonce}' https://fonts.bunny.net",
            "font-src 'self' https://fonts.bunny.net",
            "img-src 'self' data: https://avatars.githubusercontent.com",
            "connect-src 'self' {$wsUrl}",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self' https://github.com",
            "object-src 'none'",
        ];

        if (app()->environment('local')) {
            $viteUrl = 'http://localhost:5173';
            $directives[1] = "script-src 'self' 'nonce-{$nonce}' {$viteUrl}";
            $directives[2] = "style-src 'self' 'unsafe-inline' https://fonts.bunny.net";
            $directives[3] = "font-src 'self' https://fonts.bunny.net {$viteUrl}";
            $directives[5] = "connect-src 'self' ws://localhost:{$reverbPort} wss://localhost:{$reverbPort} ws://localhost:5173 http://localhost:5173";
        }

        return implode('; ', $directives);
    }
}
