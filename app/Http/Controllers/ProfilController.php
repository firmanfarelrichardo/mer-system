<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ProfilController — menangani halaman profil pengguna.
 *
 * Menampilkan informasi profil pengguna yang sedang login, termasuk
 * data pribadi, peran, dan unit kerja. Halaman ini tersedia untuk
 * semua peran yang terautentikasi.
 */
class ProfilController extends Controller
{
    /**
     * Tampilkan halaman profil pengguna.
     */
    public function index(Request $permintaan): View
    {
        return view('profil.index');
    }
}
