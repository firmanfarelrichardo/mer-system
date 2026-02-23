<?php

namespace App\Providers;

use App\Events\InsidenStatusBerubah;
use App\Listeners\KirimNotifikasiInsiden;
use App\Models\Insiden;
use App\Models\KategoriKesalahan;
use App\Models\Peran;
use App\Models\UnitKerja;
use App\Observers\KategoriKesalahanObserver;
use App\Observers\UnitKerjaObserver;
use App\Policies\InsidenPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ----------------------------------------------------------------
        // Timezone & Locale: Asia/Jakarta (WIB), Bahasa Indonesia
        // Memastikan Carbon menggunakan bahasa Indonesia untuk semua output
        // waktu relatif — diffForHumans() → "2 menit yang lalu", dll.
        // Zona waktu ditangani oleh config/app.php (env APP_TIMEZONE).
        // ----------------------------------------------------------------
        Carbon::setLocale('id');

        // ----------------------------------------------------------------
        // Policy: Insiden
        // Mendaftarkan policy secara eksplisit untuk kejelasan.
        // ----------------------------------------------------------------
        Gate::policy(Insiden::class, InsidenPolicy::class);

        // ----------------------------------------------------------------
        // Observer: Audit Logging (decoupled)
        // Mencatat audit log otomatis setiap kali model dimanipulasi.
        // ----------------------------------------------------------------
        UnitKerja::observe(UnitKerjaObserver::class);
        KategoriKesalahan::observe(KategoriKesalahanObserver::class);

        // ----------------------------------------------------------------
        // Event → Listener: Notifikasi Insiden
        // Setiap perubahan status insiden memicu notifikasi ke peran terkait.
        // ----------------------------------------------------------------
        Event::listen(InsidenStatusBerubah::class, KirimNotifikasiInsiden::class);

        // ----------------------------------------------------------------
        // Gate: Peran Manajemen
        // Shorthand untuk mengecek apakah pengguna merupakan manajemen
        // (Karu/Komite/Direktur) — digunakan di sidebar & views.
        // ----------------------------------------------------------------
        Gate::define('manajemen', function ($pengguna) {
            return $pengguna->memilikiPeran(Peran::KEPALA_RUANGAN)
                || $pengguna->memilikiPeran(Peran::KOMITE)
                || $pengguna->memilikiPeran(Peran::DIREKTUR);
        });

        // ----------------------------------------------------------------
        // View Composer: Sidebar Navigation
        // Menyuntikkan data menu ke layouts.sidebar agar tidak bergantung
        // pada @php block scope — lebih andal dan mudah diuji.
        // ----------------------------------------------------------------
        View::composer('layouts.sidebar', function (\Illuminate\View\View $view): void {
            $pengguna = auth()->user();

            // Helper: cek apakah pengguna punya salah satu peran dari daftar.
            $punyaPeran = fn (array $daftar): bool => $pengguna
                ? collect($daftar)->contains(fn (string $p) => $pengguna->memilikiPeran($p))
                : false;

            // Helper: menentukan route dashboard sesuai peran pengguna.
            $routeDashboard = match (true) {
                $pengguna?->memilikiPeran('Direktur')       => 'direktur.dashboard',
                $pengguna?->memilikiPeran('Admin')          => 'admin.dashboard',
                $pengguna?->memilikiPeran('Komite')         => 'komite.dashboard',
                $pengguna?->memilikiPeran('Kepala Ruangan') => 'kepala-ruangan.dashboard',
                $pengguna?->memilikiPeran('Nakes')           => 'nakes.dashboard',
                default                                      => 'dashboard',
            };

            $menuUtama = [
                [
                    'label' => 'Dashboard',
                    'route' => $routeDashboard,
                    'aktif' => ['admin.dashboard', 'nakes.dashboard', 'kepala-ruangan.dashboard', 'komite.dashboard', 'direktur.dashboard'],
                    'peran' => [],
                    'ikon'  => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z',
                ],
            ];

            // ── Menu Pelaporan ────────────────────────────────────────
            // Item dibedakan per peran menggunakan kunci 'peran'.
            // Sidebar melakukan filter per-item di loop masing-masing.
            $menuPelaporan = [
                // Nakes: membuat & melihat laporan milik sendiri.
                [
                    'label' => 'Buat Laporan',
                    'route' => 'laporan.buat',
                    'aktif' => ['laporan.buat'],
                    'peran' => [Peran::NAKES],
                    'ikon'  => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 0v6m3-3H9m1.5-3H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
                ],
                [
                    'label' => 'Riwayat Laporan',
                    'route' => 'laporan.index',
                    'aktif' => ['laporan.index', 'laporan.tampil'],
                    'peran' => [Peran::NAKES],
                    'ikon'  => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15a2.25 2.25 0 0 1 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z',
                ],

                // Kepala Ruangan & Komite: menerima & menindaklanjuti laporan.
                // 'Laporan Masuk' menggunakan rute yang sama (laporan.index)
                // — data di-scope otomatis via scopeUntukPeran() di model.
                [
                    'label' => 'Laporan Masuk',
                    'route' => 'laporan.index',
                    'aktif' => ['laporan.index', 'laporan.tampil'],
                    'peran' => [Peran::KEPALA_RUANGAN, Peran::KOMITE],
                    'ikon'  => 'M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3',
                ],
                [
                    'label'  => 'Statistik',
                    'route'  => null,
                    'aktif'  => [],
                    'peran'  => [Peran::KEPALA_RUANGAN, Peran::KOMITE],
                    'segera' => true,
                    'ikon'   => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
                ],
            ];

            $menuNotifikasi = [
                [
                    'label' => 'Notifikasi',
                    'route' => 'notifikasi.index',
                    'aktif' => ['notifikasi.*'],
                    'peran' => [],
                    'ikon'  => 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
                    'badge' => $pengguna ? $pengguna->unreadNotifications()->count() : 0,
                ],
            ];

            $menuAdmin = [
                [
                    'label' => 'Manajemen Pengguna',
                    'route' => 'admin.pengguna.index',
                    'aktif' => ['admin.pengguna.*'],
                    'peran' => ['Admin'],
                    'ikon'  => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
                ],
                [
                    'label'  => 'Master Unit Kerja',
                    'route'  => 'admin.unit-kerja.index',
                    'aktif'  => ['admin.unit-kerja.*'],
                    'peran'  => ['Admin'],
                    'ikon'   => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m0 0v2.625',
                ],
                [
                    'label'  => 'Master Kategori Insiden',
                    'route'  => 'admin.kategori.index',
                    'aktif'  => ['admin.kategori.*'],
                    'peran'  => ['Admin'],
                    'ikon'   => 'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z M6 6h.008v.008H6V6Z',
                ],
                [
                    'label'  => 'Log Pengguna',
                    'route'  => 'admin.log-aktivitas.pengguna',
                    'aktif'  => ['admin.log-aktivitas.pengguna'],
                    'peran'  => ['Admin'],
                    'ikon'   => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                ],
                [
                    'label'  => 'Log Admin',
                    'route'  => 'admin.log-aktivitas.admin',
                    'aktif'  => ['admin.log-aktivitas.admin'],
                    'peran'  => ['Admin'],
                    'ikon'   => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                ],
            ];

            // ── Menu Direktur ─────────────────────────────────────────
            // Direktur: monitoring read-only — tidak ada feedback/tindak lanjut.
            $menuDirektur = [
                [
                    'label' => 'Semua Laporan',
                    'route' => 'laporan.index',
                    'aktif' => ['laporan.index', 'laporan.tampil'],
                    'peran' => [Peran::DIREKTUR],
                    'ikon'  => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15a2.25 2.25 0 0 1 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z',
                ],
                [
                    'label'  => 'Statistik & Analitik',
                    'route'  => null,
                    'aktif'  => [],
                    'peran'  => [Peran::DIREKTUR],
                    'segera' => true,
                    'ikon'   => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
                ],
            ];

            $menuBawah = [
                [
                    'label' => 'Profil Saya',
                    'route' => 'profil.index',
                    'aktif' => ['profil.*'],
                    'ikon'  => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
                ],
                [
                    'label' => 'Pengaturan',
                    'route' => 'pengaturan.index',
                    'aktif' => ['pengaturan.*'],
                    'ikon'  => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
                ],
            ];

            $view->with(compact(
                'pengguna',
                'punyaPeran',
                'menuUtama',
                'menuPelaporan',
                'menuNotifikasi',
                'menuAdmin',
                'menuDirektur',
                'menuBawah',
            ));
        });
    }
}
