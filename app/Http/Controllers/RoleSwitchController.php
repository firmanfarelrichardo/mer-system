<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Peran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * RoleSwitchController — Fitur "Lihat Sebagai" eksklusif untuk akun peneliti.
 *
 * Hanya akun yang memenuhi kondisi `isPeneliti()` (NIP sesuai env PENELITI_NIP)
 * yang dapat menggunakan fitur ini. Semua permintaan dari pengguna lain akan
 * ditolak dengan respons 403.
 *
 * Peran aktif disimpan di sesi; tidak ada perubahan ke database.
 *
 * @see \App\Models\Pengguna::isPeneliti()
 * @see \App\Models\Pengguna::peranAktif()
 */
class RoleSwitchController extends Controller
{
    /**
     * Daftar peran yang dapat disimulasikan oleh peneliti.
     * Urutan ini juga menentukan urutan tampil di dropdown navbar.
     */
    private const PERAN_TERSEDIA = [
        Peran::NAKES,
        Peran::KEPALA_RUANGAN,
        Peran::KOMITE,
        Peran::ADMIN,
        Peran::DIREKTUR,
        Peran::PENELITI,  // Opsi "Kembali ke Peneliti" — menghapus sesi
    ];

    /**
     * Ganti peran aktif yang disimulasikan di sesi.
     *
     * POST /ganti-peran
     * Body: peran = (salah satu dari PERAN_TERSEDIA)
     *
     * - Jika `peran` = Peran::PENELITI → sesi dihapus (kembali ke mode penuh).
     * - Selain itu  → sesi diperbarui dengan peran yang dipilih.
     * - Selalu redirect ke /dasbor agar hub routing menentukan halaman yang tepat.
     */
    public function switch(Request $request): RedirectResponse
    {
        /** @var \App\Models\Pengguna $pengguna */
        $pengguna = auth()->user();

        // ── Keamanan: hanya peneliti yang boleh mengakses ─────────────
        abort_unless($pengguna->isPeneliti(), 403, 'Akses ditolak.');

        // ── Validasi input ────────────────────────────────────────────
        $validated = $request->validate([
            'peran' => ['required', 'string', 'in:' . implode(',', self::PERAN_TERSEDIA)],
        ]);

        $peranDipilih = $validated['peran'];

        // ── Perbarui atau hapus sesi ──────────────────────────────────
        // Memilih "Peneliti" berarti kembali ke mode penuh (tanpa simulasi).
        if ($peranDipilih === Peran::PENELITI) {
            $request->session()->forget('active_role');
        } else {
            $request->session()->put('active_role', $peranDipilih);
        }


        return redirect()->route('dashboard');
    }

    /**
     * Kembalikan daftar peran yang tersedia untuk dropdown Blade.
     * Static agar dapat dipanggil langsung dari view tanpa instansiasi.
     *
     * @return list<string>
     */
    public static function peranTersedia(): array
    {
        return self::PERAN_TERSEDIA;
    }
}
