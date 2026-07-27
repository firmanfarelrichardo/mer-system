<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PengaturanController - menangani halaman pengaturan akun.
 *
 * Menyediakan antarmuka untuk pengguna mengelola pengaturan akunnya,
 * seperti mengubah kata sandi dan preferensi notifikasi.
 * Tersedia untuk semua peran yang terautentikasi.
 */
class PengaturanController extends Controller
{
    /**
     * Tampilkan halaman pengaturan akun.
     */
    public function index(Request $permintaan): View
    {
        return view('pengaturan.index');
    }

    /**
     * Perbarui kata sandi pengguna.
     */
    public function updatePassword(Request $permintaan): \Illuminate\Http\RedirectResponse
    {
        $permintaan->validate([
            'kata_sandi_lama' => ['required'],
            'kata_sandi_baru' => [
                'required',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',      // minimal satu huruf kecil
                'regex:/[A-Z]/',      // minimal satu huruf besar
                'regex:/[0-9]/',      // minimal satu angka
            ],
        ], [
            'kata_sandi_lama.required'  => 'Kata sandi saat ini wajib diisi.',
            'kata_sandi_baru.required'  => 'Kata sandi baru wajib diisi.',
            'kata_sandi_baru.min'       => 'Kata sandi baru minimal 8 karakter.',
            'kata_sandi_baru.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'kata_sandi_baru.regex'     => 'Kata sandi harus mengandung huruf besar, huruf kecil, dan angka.',
        ]);

        $pengguna = $permintaan->user();

        if (! \Illuminate\Support\Facades\Hash::check($permintaan->kata_sandi_lama, $pengguna->kata_sandi)) {
            return back()->withErrors(['kata_sandi_lama' => 'Kata sandi saat ini tidak cocok dengan catatan kami.']);
        }

        $pengguna->update([
            'kata_sandi' => \Illuminate\Support\Facades\Hash::make($permintaan->kata_sandi_baru),
        ]);

        return back()->with('sukses', 'Kata sandi berhasil diperbarui.');
    }
}
