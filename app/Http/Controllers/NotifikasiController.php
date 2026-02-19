<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * NotifikasiController — menangani halaman notifikasi pengguna.
 *
 * Menampilkan daftar notifikasi (dibaca dan belum dibaca) untuk
 * pengguna yang sedang login. Konten notifikasi menyesuaikan
 * dengan peran pengguna masing-masing.
 */
class NotifikasiController extends Controller
{
    /**
     * Tampilkan halaman daftar notifikasi.
     */
    public function index(Request $permintaan): View
    {
        return view('notifikasi.index');
    }
}
