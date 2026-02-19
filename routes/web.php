<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\ProfilController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute Web — Sistem Pelaporan Insiden Medication Errors
|--------------------------------------------------------------------------
*/

// -----------------------------------------------------------------------
// Pengalihan root
// Mengunjungi / selalu mengarahkan tamu ke formulir masuk dan pengguna
// yang sudah masuk ke halaman yang mereka tuju sebelumnya (atau dasbor).
// -----------------------------------------------------------------------
Route::redirect('/', '/masuk')->name('beranda');

// -----------------------------------------------------------------------
// Rute Tamu (hanya pengguna yang belum masuk)
// -----------------------------------------------------------------------
Route::middleware('guest')->group(function (): void {

    Route::get('masuk', [AuthController::class, 'tampilkanFormulirMasuk'])
        ->name('login');

    // Maks. 5 percobaan per 10 menit ditangani oleh LoginRequest::pastikanBelumDibatasi().
    Route::post('masuk', [AuthController::class, 'masuk']);
});

// -----------------------------------------------------------------------
// Rute Terautentikasi (harus sudah masuk)
// -----------------------------------------------------------------------
Route::middleware('auth')->group(function (): void {

    Route::post('keluar', [AuthController::class, 'keluar'])
        ->name('logout');

    // Dasbor umum — akan diganti ke kontroler terpisah nantinya.
    Route::get('/dasbor', fn () => view('dashboard'))
        ->name('dashboard');

    // ----- Placeholder dasbor per peran -----
    // Rute ini akan diarahkan ke kontroler nyata setelah dasbor selesai dibuat.

    Route::get('/perawat/dasbor', fn () => view('dashboard'))
        ->name('perawat.dashboard');

    Route::get('/kepala-ruangan/dasbor', fn () => view('dashboard'))
        ->name('kepala-ruangan.dashboard');

    Route::get('/komite/dasbor', fn () => view('dashboard'))
        ->name('komite.dashboard');

    Route::get('/admin/dasbor', fn () => view('dashboard'))
        ->name('admin.dashboard');

    Route::get('/direktur/dasbor', fn () => view('dashboard'))
        ->name('direktur.dashboard');

    // ----- Laporan Insiden -----
    // Riwayat laporan dan formulir pembuatan laporan baru.
    Route::get('/laporan', [LaporanController::class, 'index'])
        ->name('laporan.index');

    Route::get('/laporan/buat', [LaporanController::class, 'buat'])
        ->name('laporan.buat');

    Route::get('/laporan/{laporan}', [LaporanController::class, 'tampil'])
        ->name('laporan.tampil');

    // ----- Profil, Pengaturan, & Notifikasi -----
    Route::get('/profil', [ProfilController::class, 'index'])
        ->name('profil.index');

    Route::get('/pengaturan', [PengaturanController::class, 'index'])
        ->name('pengaturan.index');

    Route::get('/notifikasi', [NotifikasiController::class, 'index'])
        ->name('notifikasi.index');
});
