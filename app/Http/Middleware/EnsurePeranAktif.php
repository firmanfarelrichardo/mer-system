<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware EnsurePeranAktif
 *
 * Memastikan peran aktif pengguna (di sesi) sesuai dengan peran yang
 * dibutuhkan untuk mengakses route tertentu.
 *
 * Berbeda dengan pengecekan `memilikiPeran()` biasa:
 * - `memilikiPeran()` memeriksa apakah user punya peran di DB.
 * - Middleware ini memeriksa apakah SESI AKTIF user sesuai peran yang diminta.
 *
 * Contoh penggunaan di routes:
 *   Route::middleware(['peran.aktif:Kepala Ruangan'])->group(...)
 *   Route::middleware(['peran.aktif:Nakes,Kepala Ruangan'])->group(...)
 *
 * Jika tidak sesuai, redirect ke /dasbor dengan pesan peringatan.
 */
class EnsurePeranAktif
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$peranDiizinkan  Daftar nama peran yang diizinkan (dipisahkan koma di route)
     */
    public function handle(Request $request, Closure $next, string ...$peranDiizinkan): Response
    {
        /** @var \App\Models\Pengguna|null $pengguna */
        $pengguna = auth()->user();

        if (! $pengguna) {
            return redirect()->route('login');
        }

        $peranAktif = $pengguna->peranAktif();

        // Jika peran aktif pengguna ada di daftar yang diizinkan → lanjutkan
        if (in_array($peranAktif, $peranDiizinkan, true)) {
            return $next($request);
        }

        // Khusus peneliti mode penuh (Peneliti) → izinkan akses ke semua route
        // Ini memungkinkan peneliti melihat semua halaman tanpa harus switch.
        if ($pengguna->isPeneliti() && $peranAktif === \App\Models\Peran::PENELITI) {
            return $next($request);
        }

        // Peran tidak sesuai → redirect ke dasbor dengan peringatan
        $labelPeran = implode(' / ', array_map(
            fn (string $p) => \App\Models\Peran::PETA_LABEL_DISPLAY[$p] ?? $p,
            $peranDiizinkan
        ));

        return redirect()
            ->route('dashboard')
            ->with('peringatan', "Halaman ini hanya dapat diakses saat bertindak sebagai {$labelPeran}. Silakan ganti peran aktif Anda.");
    }
}
