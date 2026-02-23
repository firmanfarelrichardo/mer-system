<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\KategoriController;
use App\Http\Controllers\Admin\LogAktivitasController;
use App\Http\Controllers\Admin\PenggunaController;
use App\Http\Controllers\Admin\UnitKerjaController;
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

    // ----- Hub Dasbor -----
    // Route 'dashboard' wajib ada: digunakan oleh Laravel's RedirectIfAuthenticated
    // (middleware 'guest') sebagai fallback — mencegah infinite redirect loop.
    // Sekaligus sebagai fallback default setelah login berhasil.
    Route::get('/dasbor', function () {
        /** @var \App\Models\Pengguna $pengguna */
        $pengguna = auth()->user();
        $peran    = $pengguna?->daftarPeran() ?? [];

        return match (true) {
            in_array('Direktur',       $peran, true) => redirect()->route('direktur.dashboard'),
            in_array('Admin',          $peran, true) => redirect()->route('admin.dashboard'),
            in_array('Komite',         $peran, true) => redirect()->route('komite.dashboard'),
            in_array('Kepala Ruangan', $peran, true) => redirect()->route('kepala-ruangan.dashboard'),
            in_array('Perawat',        $peran, true) => redirect()->route('perawat.dashboard'),
            default                                  => redirect()->route('laporan.index'),
        };
    })->name('dashboard');

    // ----- Dasbor per peran -----
    // Setiap peran memiliki endpoint dasbor tersendiri.
    // Ganti closure dengan kontroler nyata saat dasbor selesai dibuat.
    Route::get('/perawat/dasbor',        fn () => view('dashboard'))->name('perawat.dashboard');
    Route::get('/kepala-ruangan/dasbor', fn () => view('dashboard'))->name('kepala-ruangan.dashboard');
    Route::get('/komite/dasbor',         fn () => view('dashboard'))->name('komite.dashboard');
    Route::get('/direktur/dasbor',       fn () => view('dashboard'))->name('direktur.dashboard');

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

        // ----- Master Unit Kerja -----
        Route::get('/unit-kerja', [UnitKerjaController::class, 'index'])
            ->name('admin.unit-kerja.index');

        Route::get('/unit-kerja/buat', [UnitKerjaController::class, 'buat'])
            ->name('admin.unit-kerja.buat');

        Route::post('/unit-kerja', [UnitKerjaController::class, 'simpan'])
            ->name('admin.unit-kerja.simpan');

        Route::get('/unit-kerja/{unit_kerja}/edit', [UnitKerjaController::class, 'edit'])
            ->name('admin.unit-kerja.edit');

        Route::put('/unit-kerja/{unit_kerja}', [UnitKerjaController::class, 'perbarui'])
            ->name('admin.unit-kerja.perbarui');

        Route::delete('/unit-kerja/{unit_kerja}', [UnitKerjaController::class, 'hapus'])
            ->name('admin.unit-kerja.hapus');

        // ----- Master Kategori Insiden -----
        Route::get('/kategori', [KategoriController::class, 'index'])
            ->name('admin.kategori.index');

        Route::get('/kategori/buat', [KategoriController::class, 'buat'])
            ->name('admin.kategori.buat');

        Route::post('/kategori', [KategoriController::class, 'simpan'])
            ->name('admin.kategori.simpan');

        Route::get('/kategori/{kategori}/edit', [KategoriController::class, 'edit'])
            ->name('admin.kategori.edit');

        Route::put('/kategori/{kategori}', [KategoriController::class, 'perbarui'])
            ->name('admin.kategori.perbarui');

        Route::delete('/kategori/{kategori}', [KategoriController::class, 'hapus'])
            ->name('admin.kategori.hapus');

        // ----- Log Aktivitas (dipisah: Pengguna & Admin) -----
        Route::get('/log-aktivitas/pengguna', [LogAktivitasController::class, 'indexPengguna'])
            ->name('admin.log-aktivitas.pengguna');

        Route::get('/log-aktivitas/admin', [LogAktivitasController::class, 'indexAdmin'])
            ->name('admin.log-aktivitas.admin');
    });

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
});
