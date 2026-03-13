<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders — Defense-in-depth layer di level aplikasi.
 *
 * Nginx sudah mengirim security headers, tapi middleware ini menjadi
 * "jaring pengaman kedua" untuk response yang langsung dari PHP-FPM
 * (misalnya: error page, redirect, JSON API) yang mungkin bypass
 * konfigurasi `add_header` Nginx pada location tertentu.
 *
 * Header yang di-set:
 * - X-Content-Type-Options : nosniff (anti MIME-sniffing)
 * - X-Frame-Options        : DENY (anti clickjacking)
 * - X-XSS-Protection       : 1; mode=block (legacy browser)
 * - Referrer-Policy         : strict-origin-when-cross-origin
 * - Permissions-Policy      : mati semua sensor (camera, mic, geo)
 *
 * CSP dan HSTS sengaja TIDAK di-set di sini karena:
 * - CSP: satu source of truth di Nginx, hindari konflik policy.
 * - HSTS: harus dari Nginx agar berlaku sebelum request sampai PHP.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');

        // Hapus header yang membocorkan informasi stack teknologi.
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('server');

        return $response;
    }
}
