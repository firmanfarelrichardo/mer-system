<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware CheckForcePasswordChange
 *
 * Memaksa pengguna yang memiliki flag `wajib_ganti_sandi = true`
 * untuk mengganti kata sandi sementara sebelum mengakses sistem.
 *
 * CRITICAL: Route berikut WAJIB dikecualikan untuk mencegah infinite redirect:
 *   - auth.force-change-password (GET & POST halaman ganti sandi)
 *   - logout (POST keluar dari sistem)
 *   - logout.idle (POST auto-logout idle)
 */
class CheckForcePasswordChange
{
    /**
     * Route yang dikecualikan dari pengecekan paksa ganti sandi.
     * Jika tidak dikecualikan → Too Many Redirects (HTTP 310).
     *
     * @var list<string>
     */
    private const EXCLUDED_ROUTES = [
        'auth.force-change-password',
        'auth.force-change-password.update',
        'logout',
        'logout.idle',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (
            Auth::check()
            && Auth::user()->wajib_ganti_sandi === true
            && ! $this->isExcludedRoute($request)
        ) {
            return redirect()->route('auth.force-change-password');
        }

        return $next($request);
    }

    /**
     * Periksa apakah route saat ini termasuk dalam daftar pengecualian.
     */
    private function isExcludedRoute(Request $request): bool
    {
        $currentRoute = $request->route()?->getName();

        return $currentRoute !== null && in_array($currentRoute, self::EXCLUDED_ROUTES, true);
    }
}
