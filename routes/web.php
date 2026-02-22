<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\PenggunaController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\StatistikController;
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

    // ----- Admin: Dasbor & Manajemen Pengguna -----
    Route::prefix('admin')->group(function (): void {

        Route::get('/dasbor', [AdminDashboardController::class, 'index'])
            ->name('admin.dashboard');

        Route::get('/pengguna', [PenggunaController::class, 'index'])
            ->name('admin.pengguna.index');

        Route::get('/pengguna/buat', [PenggunaController::class, 'buat'])
            ->name('admin.pengguna.buat');

        Route::post('/pengguna', [PenggunaController::class, 'simpan'])
            ->name('admin.pengguna.simpan');

        Route::get('/pengguna/{pengguna}/edit', [PenggunaController::class, 'edit'])
            ->name('admin.pengguna.edit');

        Route::put('/pengguna/{pengguna}', [PenggunaController::class, 'perbarui'])
            ->name('admin.pengguna.perbarui');

        Route::patch('/pengguna/{pengguna}/status', [PenggunaController::class, 'toggleStatus'])
            ->name('admin.pengguna.toggle-status');

        Route::patch('/pengguna/{pengguna}/reset-sandi', [PenggunaController::class, 'resetKataSandi'])
            ->name('admin.pengguna.reset-sandi');
    });

    Route::get('/direktur/dasbor', fn () => view('dashboard'))
        ->name('direktur.dashboard');

    // ----- Laporan Insiden -----
    // Riwayat laporan dan formulir pembuatan laporan baru.
    Route::get('/laporan', [LaporanController::class, 'index'])
        ->name('laporan.index');

    Route::get('/laporan/buat', [LaporanController::class, 'buat'])
        ->name('laporan.buat');

    Route::post('/laporan', [LaporanController::class, 'simpan'])
        ->name('laporan.simpan');

    Route::patch('/laporan/{laporan}/tandai-dibaca', [LaporanController::class, 'tandaiDibaca'])
        ->name('laporan.tandai-dibaca');

    Route::patch('/laporan/{laporan}/tindak-lanjut', [LaporanController::class, 'tindakLanjut'])
        ->name('laporan.tindak-lanjut');

    Route::get('/laporan/{laporan}', [LaporanController::class, 'tampil'])
        ->name('laporan.tampil');

    // ----- Profil, Pengaturan, & Notifikasi -----
    Route::get('/profil', [ProfilController::class, 'index'])
        ->name('profil.index');

    Route::get('/pengaturan', [PengaturanController::class, 'index'])
        ->name('pengaturan.index');

    Route::get('/notifikasi', [NotifikasiController::class, 'index'])
        ->name('notifikasi.index');

    // ----- Statistik & Analisis -----
    // Akses: Kepala Ruangan, Komite, Direktur (otorisasi dihandle controller).
    Route::get('/statistik', [StatistikController::class, 'index'])
        ->name('statistik.index');
});
