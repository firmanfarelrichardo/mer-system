<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\NotifikasiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * NotifikasiController — menangani halaman & aksi notifikasi pengguna.
 *
 * Mengikuti pola layered architecture:
 *   Controller → Service → Repository → Model/DB
 *
 * Fitur:
 *   1. Index           — daftar notifikasi (paginasi)
 *   2. Baca & Arahkan  — tandai dibaca + redirect ke URL tujuan
 *   3. Tandai Dibaca   — tandai satu notifikasi dibaca (PATCH)
 *   4. Tandai Semua    — tandai semua notifikasi dibaca (POST)
 */
class NotifikasiController extends Controller
{
    public function __construct(
        private readonly NotifikasiService $service,
    ) {}

    /**
     * Tampilkan halaman daftar notifikasi.
     */
    public function index(Request $permintaan): View
    {
        $pengguna          = Auth::user();
        $daftarNotifikasi  = $this->service->daftarNotifikasi($pengguna, 15);
        $belumDibaca       = $this->service->hitungBelumDibaca($pengguna);

        return view('notifikasi.index', compact('daftarNotifikasi', 'belumDibaca'));
    }

    /**
     * Tandai notifikasi sebagai dibaca lalu redirect ke URL tujuan.
     * Digunakan saat pengguna meng-klik item notifikasi.
     */
    public function bacaDanArahkan(string $notifikasi): RedirectResponse
    {
        $pengguna = Auth::user();
        $url      = $this->service->bacaDanArahkan($pengguna, $notifikasi);

        if (! $url) {
            return redirect()->route('notifikasi.index')
                ->with('galat', 'Notifikasi tidak ditemukan.');
        }

        return redirect($url);
    }

    /**
     * Tandai satu notifikasi sebagai dibaca (PATCH — tanpa redirect).
     */
    public function tandaiDibaca(string $notifikasi): RedirectResponse
    {
        $pengguna = Auth::user();
        $this->service->tandaiDibaca($pengguna, $notifikasi);

        return back()->with('sukses', 'Notifikasi ditandai sudah dibaca.');
    }

    /**
     * Tandai semua notifikasi sebagai dibaca.
     */
    public function tandaiSemuaDibaca(): RedirectResponse
    {
        $pengguna = Auth::user();
        $jumlah   = $this->service->tandaiSemuaDibaca($pengguna);

        return back()->with('sukses', "{$jumlah} notifikasi ditandai sudah dibaca.");
    }
}
