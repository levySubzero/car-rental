<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers and the CSP (M1.14, audited in M12.3). Inline scripts need the
 * per-request nonce (`Vite::cspNonce()`); Vite's own tags get it automatically.
 */
class SecurityHeaders
{
    public function __construct(private readonly Vite $vite) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Before the view renders, so the layout's inline script and Vite's tags carry it.
        $nonce = $this->vite->useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce, $request->isSecure()));
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce, bool $secure): string
    {
        // Turnstile, and Paystack's inline card popup (M11B.7).
        $script = ["'self'", 'https://challenges.cloudflare.com', 'https://js.paystack.co'];
        $style = ["'self'", "'unsafe-inline'", 'https://fonts.bunny.net'];
        $font = ["'self'", 'data:', 'https://fonts.bunny.net'];
        $img = ["'self'", 'data:', 'blob:', 'https:'];
        $connect = ["'self'", 'https://*.sentry.io', 'https://api.paystack.co'];

        // The Vite dev server injects inline scripts and serves every asset from another origin.
        // A nonce would switch 'unsafe-inline' off, so it is only sent outside dev.
        if (! $this->vite->isRunningHot()) {
            $script[] = "'nonce-{$nonce}'";
        } else {
            $origin = trim((string) file_get_contents($this->vite->hotFile()));
            $script = [...$script, "'unsafe-inline'", $origin];
            $style = [...$style, $origin];
            $font = [...$font, $origin];
            $img = [...$img, $origin];
            $connect = [...$connect, $origin, preg_replace('/^http/', 'ws', $origin)];
        }

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => $script,
            'style-src' => $style,
            'font-src' => $font,
            'img-src' => $img,
            'media-src' => ["'self'", 'blob:', 'https:'],
            'connect-src' => $connect,
            'frame-src' => ['https://challenges.cloudflare.com', 'https://www.google.com', 'https://checkout.paystack.com'],
            'frame-ancestors' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        // Over plain HTTP (a container on localhost) it would send every asset to https.
        if (app()->isProduction() && $secure) {
            $directives['upgrade-insecure-requests'] = [];
        }

        if ($report = config('baines.csp_report_uri')) {
            $directives['report-uri'] = [$report];
        }

        return collect($directives)
            ->map(fn (array $sources, string $directive) => trim($directive.' '.implode(' ', $sources)))
            ->implode('; ');
    }
}
