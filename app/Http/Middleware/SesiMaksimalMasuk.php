<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware SesiMaksimalMasuk
 *
 * Membatasi durasi sesi aktif maksimal 10 menit sejak waktu masuk.
 * Berbeda dengan session lifetime (idle timeout), middleware ini menghitung
 * waktu ABSOLUT sejak pengguna berhasil login — tidak direset oleh aktivitas.
 *
 * Alur:
 *   - Setiap request terautentikasi, cek selisih waktu sekarang − _login_at.
 *   - Jika sudah ≥ DURASI_MAKS_DETIK, paksa logout & redirect ke halaman masuk.
 *
 * Berlaku untuk SEMUA peran (Nakes, Karu, Komite, Direktur, Admin) — RBAC
 * tidak membedakan batas waktu sesi; semua diperlakukan sama demi keamanan.
 */
class SesiMaksimalMasuk
{
    /**
     * Durasi maksimal sesi aktif dalam detik (10 menit).
     */
    private const DURASI_MAKS_DETIK = 10 * 60;

    /**
     * Kunci penyimpanan timestamp login di dalam session.
     */
    public const KUNCI_LOGIN_PADA = '_login_at';

    /**
     * Tangani permintaan masuk.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $loginPada = $request->session()->get(self::KUNCI_LOGIN_PADA);

        // Jika timestamp tidak ada (sesi lama / tidak ada di session), paksa keluar.
        if (! $loginPada) {
            return $this->paksakanKeluar($request);
        }

        $selisihDetik = now()->timestamp - (int) $loginPada;

        if ($selisihDetik >= self::DURASI_MAKS_DETIK) {
            return $this->paksakanKeluar($request);
        }

        return $next($request);
    }

    /**
     * Hancurkan sesi, logout, dan arahkan ke halaman masuk dengan pesan.
     */
    private function paksakanKeluar(Request $request): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('peringatan', 'Sesi Anda telah berakhir setelah 10 menit. Silakan masuk kembali.');
    }

    /**
     * Kembalikan durasi maksimal sesi dalam detik.
     * Digunakan oleh view untuk menghitung countdown di sisi klien.
     */
    public static function durasiMaksDet(): int
    {
        return self::DURASI_MAKS_DETIK;
    }
}
