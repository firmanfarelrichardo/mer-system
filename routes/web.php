<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FaktorPenyebabController;
use App\Http\Controllers\Admin\IntervensiController;
use App\Http\Controllers\Admin\JenisKesalahanController;
use App\Http\Controllers\Admin\KategoriController;
use App\Http\Controllers\Admin\LogAktivitasController;
use App\Http\Controllers\Admin\PenggunaController;
use App\Http\Controllers\Admin\TipeCederaController;
use App\Http\Controllers\Admin\UnitKerjaController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\LaporanExportController;
use App\Http\Controllers\RoleSwitchController;
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
// Rute Publik (tidak memerlukan autentikasi)
// -----------------------------------------------------------------------
// Bantuan Akses — Dynamic WhatsApp Redirect ke Admin aktif.
// Bebas dari middleware auth agar dapat diakses oleh pengguna yang lupa sandi.
Route::get('bantuan-akses', [AuthController::class, 'bantuanLogin'])
    ->name('auth.bantuan-akses');

// -----------------------------------------------------------------------
// Rute Terautentikasi (harus sudah masuk)
// -----------------------------------------------------------------------
Route::middleware(['auth'])->group(function (): void {

    Route::post('keluar', [AuthController::class, 'keluar'])
        ->name('logout');

    // Logout khusus karena idle (tidak ada aktivitas) — dipanggil via Fetch dari Alpine.js.
    // Dipisahkan dari POST /keluar agar audit log mencatat konteks yang berbeda.
    Route::post('logout-idle', [AuthController::class, 'logoutIdle'])
        ->name('logout.idle');

    // ----- Ganti Sandi Paksa (Force Change Password) -----
    // Route ini WAJIB berada DI LUAR middleware force.password.change
    // agar tidak menyebabkan infinite redirect loop.
    Route::get('ubah-sandi-wajib', [AuthController::class, 'tampilkanFormGantiSandiPaksa'])
        ->name('auth.force-change-password');

    Route::post('ubah-sandi-wajib', [AuthController::class, 'prosesGantiSandiPaksa'])
        ->name('auth.force-change-password.update');

    // -------------------------------------------------------------------
    // Rute yang dilindungi oleh CheckForcePasswordChange middleware.
    // Pengguna dengan flag wajib_ganti_sandi=true akan di-redirect
    // ke halaman ganti sandi paksa sebelum mengakses route di bawah ini.
    // -------------------------------------------------------------------
    Route::middleware(['force.password.change'])->group(function (): void {
    // ----- Ganti Peran (eksklusif peneliti) -----
    // Otorisasi dilakukan di dalam controller via abort_unless($pengguna->isPeneliti()).
    // Tidak memerlukan middleware khusus — guard 'auth' sudah mencakup grup ini.
    Route::post('/ganti-peran', [RoleSwitchController::class, 'switch'])
        ->name('peneliti.ganti-peran');

    // ----- Hub Dasbor -----
    // Route 'dashboard' wajib ada: digunakan oleh Laravel's RedirectIfAuthenticated
    // (middleware 'guest') sebagai fallback — mencegah infinite redirect loop.
    // Admin di-redirect ke admin dashboard sendiri; peran lain ke DashboardController.
    Route::get('/dasbor', function () {
        /** @var \App\Models\Pengguna $pengguna */
        $pengguna = auth()->user();

        // memilikiPeran() session-aware untuk peneliti — redirect otomatis
        // mencerminkan peran yang sedang disimulasikan. Peneliti mode penuh
        // tidak memiliki active_role di sesi, sehingga memilikiPeran() jatuh
        // ke cek DB (Peneliti), semua branch false → default admin.dashboard.
        return match (true) {
            $pengguna->memilikiPeran('Admin')           => redirect()->route('admin.dashboard'),
            $pengguna->memilikiPeran('Direktur')        => redirect()->route('direktur.dashboard'),
            $pengguna->memilikiPeran('Komite')          => redirect()->route('komite.dashboard'),
            $pengguna->memilikiPeran('Kepala Ruangan')  => redirect()->route('kepala-ruangan.dashboard'),
            $pengguna->memilikiPeran('Nakes')           => redirect()->route('nakes.dashboard'),
            $pengguna->isPeneliti()                     => redirect()->route('admin.dashboard'),
            default                                     => redirect()->route('laporan.index'),
        };
    })->name('dashboard');

    // ----- Dasbor per peran (Morning Briefing / Action Center) -----
    Route::get('/nakes/dasbor',          [DashboardController::class, 'index'])->name('nakes.dashboard');
    Route::get('/kepala-ruangan/dasbor', [DashboardController::class, 'index'])->name('kepala-ruangan.dashboard');
    Route::get('/komite/dasbor',         [DashboardController::class, 'index'])->name('komite.dashboard');
    Route::get('/direktur/dasbor',       [DashboardController::class, 'index'])->name('direktur.dashboard');

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

        Route::patch('/pengguna/{pengguna}/unban-login', [PenggunaController::class, 'unbanLogin'])
            ->name('admin.pengguna.unban-login');

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

        // ----- Master Jenis Kesalahan -----
        Route::get('/jenis-kesalahan', [JenisKesalahanController::class, 'index'])
            ->name('admin.jenis-kesalahan.index');

        Route::post('/jenis-kesalahan', [JenisKesalahanController::class, 'simpan'])
            ->name('admin.jenis-kesalahan.simpan');

        Route::put('/jenis-kesalahan/{id}', [JenisKesalahanController::class, 'perbarui'])
            ->name('admin.jenis-kesalahan.perbarui');

        Route::patch('/jenis-kesalahan/{id}/toggle-aktif', [JenisKesalahanController::class, 'toggleAktif'])
            ->name('admin.jenis-kesalahan.toggle-aktif');

        Route::delete('/jenis-kesalahan/{id}', [JenisKesalahanController::class, 'hapus'])
            ->name('admin.jenis-kesalahan.hapus');

        // ----- Master Tipe Cedera -----
        Route::get('/tipe-cedera', [TipeCederaController::class, 'index'])
            ->name('admin.tipe-cedera.index');

        Route::post('/tipe-cedera', [TipeCederaController::class, 'simpan'])
            ->name('admin.tipe-cedera.simpan');

        Route::put('/tipe-cedera/{id}', [TipeCederaController::class, 'perbarui'])
            ->name('admin.tipe-cedera.perbarui');

        Route::patch('/tipe-cedera/{id}/toggle-aktif', [TipeCederaController::class, 'toggleAktif'])
            ->name('admin.tipe-cedera.toggle-aktif');

        Route::delete('/tipe-cedera/{id}', [TipeCederaController::class, 'hapus'])
            ->name('admin.tipe-cedera.hapus');

        // ----- Master Faktor Penyebab -----
        Route::get('/faktor-penyebab', [FaktorPenyebabController::class, 'index'])
            ->name('admin.faktor-penyebab.index');

        Route::post('/faktor-penyebab', [FaktorPenyebabController::class, 'simpan'])
            ->name('admin.faktor-penyebab.simpan');

        Route::put('/faktor-penyebab/{id}', [FaktorPenyebabController::class, 'perbarui'])
            ->name('admin.faktor-penyebab.perbarui');

        Route::patch('/faktor-penyebab/{id}/toggle-aktif', [FaktorPenyebabController::class, 'toggleAktif'])
            ->name('admin.faktor-penyebab.toggle-aktif');

        Route::delete('/faktor-penyebab/{id}', [FaktorPenyebabController::class, 'hapus'])
            ->name('admin.faktor-penyebab.hapus');

        // ----- Master Tindakan Intervensi -----
        Route::get('/intervensi', [IntervensiController::class, 'index'])
            ->name('admin.intervensi.index');

        Route::post('/intervensi', [IntervensiController::class, 'simpan'])
            ->name('admin.intervensi.simpan');

        Route::put('/intervensi/{id}', [IntervensiController::class, 'perbarui'])
            ->name('admin.intervensi.perbarui');

        Route::patch('/intervensi/{id}/toggle-aktif', [IntervensiController::class, 'toggleAktif'])
            ->name('admin.intervensi.toggle-aktif');

        Route::delete('/intervensi/{id}', [IntervensiController::class, 'hapus'])
            ->name('admin.intervensi.hapus');

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

    Route::get('/laporan/draf', [LaporanController::class, 'draf'])
        ->name('laporan.draf');

    // Riwayat laporan milik pengguna sendiri (digunakan Kepala Ruangan sebagai pelapor).
    Route::get('/laporan/saya', [LaporanController::class, 'riwayatSaya'])
        ->name('laporan.riwayat-saya');

    Route::post('/laporan', [LaporanController::class, 'simpan'])
        ->name('laporan.simpan');

    Route::post('/laporan/auto-save', [LaporanController::class, 'autoSave'])
        ->name('laporan.auto-save');

    Route::get('/laporan/{laporan}/edit', [LaporanController::class, 'edit'])
        ->name('laporan.edit');

    Route::patch('/laporan/{laporan}/tandai-dibaca', [LaporanController::class, 'tandaiDibaca'])
        ->name('laporan.tandai-dibaca');

    Route::patch('/laporan/{laporan}/tindak-lanjut', [LaporanController::class, 'tindakLanjut'])
        ->name('laporan.tindak-lanjut');

    Route::get('/laporan/{laporan}/cetak-pdf', [LaporanController::class, 'cetakPdf'])
        ->name('laporan.cetak-pdf');

    Route::get('/laporan/{laporan}', [LaporanController::class, 'tampil'])
        ->name('laporan.tampil');

    // ----- Profil, Pengaturan, & Notifikasi -----
    Route::get('/profil', [ProfilController::class, 'index'])
        ->name('profil.index');

    Route::get('/profil/edit', [ProfilController::class, 'edit'])
        ->name('profil.edit');

    Route::put('/profil', [ProfilController::class, 'perbarui'])
        ->name('profil.perbarui');

    Route::get('/pengaturan', [PengaturanController::class, 'index'])
        ->name('pengaturan.index');

    Route::get('/notifikasi', [NotifikasiController::class, 'index'])
        ->name('notifikasi.index');

    // ----- Statistik & Analisis -----
    // Akses: Kepala Ruangan, Komite, Direktur (otorisasi dihandle controller).
    Route::get('/statistik', [StatistikController::class, 'index'])
        ->name('statistik.index');

    // ----- Ekspor Laporan Rekapitulasi (Komite & Direktur) -----
    Route::get('/laporan/ekspor/rekapitulasi', [LaporanExportController::class, 'exportSummary'])
        ->name('komite.export.summary');

    // Alias untuk akses Direktur — sama route, cukup satu handler.
    Route::get('/laporan/ekspor/rekapitulasi-direktur', [LaporanExportController::class, 'exportSummary'])
        ->name('direktur.export.summary');
    Route::get('/notifikasi/{notifikasi}/baca', [NotifikasiController::class, 'bacaDanArahkan'])
        ->name('notifikasi.baca');

    Route::patch('/notifikasi/{notifikasi}/tandai-dibaca', [NotifikasiController::class, 'tandaiDibaca'])
        ->name('notifikasi.tandai-dibaca');

    Route::post('/notifikasi/tandai-semua-dibaca', [NotifikasiController::class, 'tandaiSemuaDibaca'])
        ->name('notifikasi.tandai-semua-dibaca');

    }); // Akhir middleware force.password.change
});
