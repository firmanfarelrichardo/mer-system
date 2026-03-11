<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Peran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * RoleSwitchController — Fitur "Ganti Peran" untuk pengguna multi-role.
 *
 * Mendukung dua skenario:
 * 1. Akun peneliti — dapat simulasi semua peran untuk pengujian.
 * 2. Pengguna dual-role — misal Nakes yang juga menjabat sebagai Karu.
 *
 * Peran aktif disimpan di sesi; tidak ada perubahan ke database.
 *
 * Akses ditolak (403) jika pengguna tidak memiliki hak ganti peran
 * (hanya punya 1 peran dan bukan peneliti).
 *
 * @see \App\Models\Pengguna::bisaGantiPeran()
 * @see \App\Models\Pengguna::peranAktif()
 */
class RoleSwitchController extends Controller
{
    /**
     * Ganti peran aktif yang disimulasikan di sesi.
     *
     * POST /ganti-peran
     * Body: peran = (salah satu dari peran yang dapat dipilih pengguna)
     *
     * - Jika `peran` = Peran::PENELITI (khusus peneliti) → sesi dihapus (kembali ke mode penuh).
     * - Selain itu → sesi diperbarui dengan peran yang dipilih.
     * - Selalu redirect ke /dasbor agar hub routing menentukan halaman yang tepat.
     */
    public function switch(Request $request): RedirectResponse
    {
        /** @var \App\Models\Pengguna $pengguna */
        $pengguna = auth()->user();

        // ── Keamanan: hanya pengguna multi-role yang boleh mengakses ──
        abort_unless($pengguna->bisaGantiPeran(), 403, 'Akses ditolak.');

        // ── Validasi input — peran harus ada di daftar yang diizinkan ──
        $peranTersedia = $pengguna->peranYangDapatDipilih();

        $validated = $request->validate([
            'peran' => ['required', 'string', 'in:' . implode(',', $peranTersedia)],
        ]);

        $peranDipilih = $validated['peran'];

        // ── Perbarui sesi via method model ────────────────────────────
        $pengguna->setPeranAktif($peranDipilih);

        return redirect()->route('dashboard');
    }

    /**
     * Reset peran aktif ke default (peran utama dari database).
     *
     * POST /reset-peran
     *
     * Berguna jika pengguna ingin kembali ke peran default tanpa
     * memilih secara eksplisit dari dropdown.
     */
    public function reset(Request $request): RedirectResponse
    {
        /** @var \App\Models\Pengguna $pengguna */
        $pengguna = auth()->user();

        $pengguna->resetPeranAktif();

        return redirect()->route('dashboard');
    }
}
