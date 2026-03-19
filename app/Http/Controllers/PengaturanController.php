<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PengaturanController — menangani halaman pengaturan akun.
 *
 * Menyediakan antarmuka untuk pengguna mengelola pengaturan akunnya,
 * seperti mengubah kata sandi.
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
}
