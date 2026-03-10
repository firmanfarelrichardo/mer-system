<?php

namespace App\Providers;

use App\Models\FaktorPenyebab;
use App\Models\Insiden;
use App\Models\JenisKesalahan;
use App\Models\KategoriKesalahan;
use App\Models\Peran;
use App\Models\TindakanIntervensi;
use App\Models\TipeCedera;
use App\Models\UnitKerja;
use App\Observers\FaktorPenyebabObserver;
use App\Observers\InsidenObserver;
use App\Observers\IntervensiObserver;
use App\Observers\JenisKesalahanObserver;
use App\Observers\KategoriKesalahanObserver;
use App\Observers\TipeCederaObserver;
use App\Observers\UnitKerjaObserver;
use App\Policies\InsidenPolicy;
use App\Repositories\InsidenRepository;
use Carbon\Carbon;
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
        Insiden::observe(InsidenObserver::class);
        TipeCedera::observe(TipeCederaObserver::class);
        FaktorPenyebab::observe(FaktorPenyebabObserver::class);
        TindakanIntervensi::observe(IntervensiObserver::class);
        JenisKesalahan::observe(JenisKesalahanObserver::class);

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
        // Gate::before — Bypass Universal untuk Akun Peneliti
        // ----------------------------------------------------------------
        // Memberikan seluruh izin Gate kepada pengguna yang nomor_induk-nya
        // cocok dengan NIP peneliti yang dikonfigurasi di .env.
        //
        // PRINSIP KEAMANAN:
        //   - Nilai diambil dari env(), BUKAN di-hardcode, sehingga akses
        //     dapat dicabut cukup dengan mengosongkan PENELITI_NIP di .env
        //     tanpa perlu deploy ulang kode.
        //   - Guard berlapis: env kosong/null → bypass TIDAK aktif.
        //   - Tidak ada peran khusus di database; mudah dihapus bersih.
        //
        // CARA MENONAKTIFKAN:
        //   Kosongkan atau hapus baris PENELITI_NIP dari file .env:
        //     PENELITI_NIP=
        //   Untuk menonaktifkan permanen, hapus/comment seluruh blok ini.
        // ----------------------------------------------------------------
        $nipPeneliti = config('app.peneliti_nip');

        if (! empty($nipPeneliti)) {
            Gate::before(function ($pengguna, string $ability) use ($nipPeneliti): ?bool {
                // Kembalikan true (bukan false) agar Gate::after & policy
                // lain tidak dieksekusi — langsung diberi akses penuh.
                if ($pengguna->nomor_induk === $nipPeneliti) {
                    return true;
                }

                // Null berarti "teruskan ke pengecekan Gate/Policy berikutnya".
                // Jangan kembalikan false agar pengguna lain tidak terblokir.
                return null;
            });
        }

        // ----------------------------------------------------------------
        // View Composer: isPeneliti (global)
        // Flag boolean untuk mengidentifikasi akun peneliti/auditor.
        // Tersedia di SEMUA view — digunakan oleh sidebar (menu bypass),
        // tampil.blade.php (bypass anonimitas), dll.
        // Menggunakan metode model isPeneliti() sebagai satu-satunya
        // sumber kebenaran — tidak duplikasi logika pengecekan NIP.
        // ----------------------------------------------------------------
        View::composer('*', function (\Illuminate\View\View $view): void {
            $pengguna = auth()->user();

            $view->with('isPeneliti', $pengguna?->isPeneliti() ?? false);
        });

        // ----------------------------------------------------------------
        // View Composer: Sidebar Navigation
        // Menyuntikkan data menu ke layouts.sidebar agar tidak bergantung
        // pada @php block scope — lebih andal dan mudah diuji.
        // ----------------------------------------------------------------
        View::composer('layouts.sidebar', function (\Illuminate\View\View $view): void {
            $pengguna = auth()->user();

            // ── Identitas & peran aktif ────────────────────────────────────
            $isPeneliti = $pengguna?->isPeneliti() ?? false;
            $peranAktif = $isPeneliti ? session('active_role') : null;

            // ── Helper: apakah pengguna "punya" salah satu peran daftar? ──
            // memilikiPeran() sudah session-aware untuk peneliti (cek Pengguna::memilikiPeran).
            // Peneliti mode penuh ($peranAktif === null) → semua seksi ditampilkan.
            $punyaPeran = fn (array $daftar): bool => ($isPeneliti && $peranAktif === null)
                || ($pengguna
                    ? collect($daftar)->contains(fn (string $p) => $pengguna->memilikiPeran($p))
                    : false);

            // ── Helper: route dashboard sesuai peran aktif ────────────────
            // memilikiPeran() sudah session-aware, jadi match ini otomatis
            // mencerminkan peran yang sedang disimulasikan.
            $routeDashboard = match (true) {
                $pengguna?->memilikiPeran('Direktur')       => 'direktur.dashboard',
                $pengguna?->memilikiPeran('Admin')          => 'admin.dashboard',
                $pengguna?->memilikiPeran('Komite')         => 'komite.dashboard',
                $pengguna?->memilikiPeran('Kepala Ruangan') => 'kepala-ruangan.dashboard',
                $pengguna?->memilikiPeran('Nakes')          => 'nakes.dashboard',
                $isPeneliti                                 => 'peneliti.dashboard', // mode penuh
                default                                     => 'dashboard',
            };

            $menuUtama = [
                [
                    'label' => 'Dashboard',
                    'route' => $routeDashboard,
                    'aktif' => ['admin.dashboard', 'nakes.dashboard', 'kepala-ruangan.dashboard', 'komite.dashboard', 'direktur.dashboard', 'peneliti.dashboard'],
                    'peran' => [],
                    'ikon'  => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z',
                ],
            ];

            // ── Menu Pelaporan ────────────────────────────────────────
            // Item dibedakan per peran menggunakan kunci 'peran'.
            // Sidebar melakukan filter per-item di loop masing-masing.
            $menuPelaporan = [
                // Admin: monitoring seluruh laporan tenant.
                [
                    'label' => 'Semua Laporan',
                    'route' => 'laporan.index',
                    'aktif' => ['laporan.index', 'laporan.tampil'],
                    'peran' => [Peran::ADMIN],
                    'ikon'  => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15a2.25 2.25 0 0 1 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z',
                ],

                // Nakes & Kepala Ruangan: dapat membuat laporan baru.
                [
                    'label' => 'Buat Laporan',
                    'route' => 'laporan.buat',
                    'aktif' => ['laporan.buat'],
                    'peran' => [Peran::NAKES, Peran::KEPALA_RUANGAN],
                    'ikon'  => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 0v6m3-3H9m1.5-3H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
                ],

                // Kepala Ruangan: riwayat laporan yang ia buat sendiri (sebagai pelapor).
                [
                    'label' => 'Riwayat Laporan',
                    'route' => 'laporan.riwayat-saya',
                    'aktif' => ['laporan.riwayat-saya'],
                    'peran' => [Peran::KEPALA_RUANGAN],
                    'ikon'  => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15a2.25 2.25 0 0 1 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z',
                ],
                [
                    'label' => 'Draf Laporan',
                    'route' => 'laporan.draf',
                    'aktif' => ['laporan.draf', 'laporan.edit'],
                    'peran' => [Peran::NAKES],
                    'ikon'  => 'M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10',
                    'badge' => ($pengguna && $pengguna->memilikiPeran(Peran::NAKES))
                        ? app(InsidenRepository::class)->countDraft($pengguna->id, $pengguna->tenant_id)
                        : 0,
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
                    'label' => 'Statistik',
                    'route' => 'statistik.index',
                    'aktif' => ['statistik.index'],
                    'peran' => [Peran::KEPALA_RUANGAN, Peran::KOMITE],
                    'ikon'  => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
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

            // ── Menu Manajemen Formulir (Admin) ──────────────────────
            // Sub-menu untuk mengelola isi dropdown/checkbox di formulir Nakes.
            $menuFormulir = [
                [
                    'label'  => 'Jenis Kesalahan',
                    'route'  => 'admin.jenis-kesalahan.index',
                    'aktif'  => ['admin.jenis-kesalahan.*'],
                    'peran'  => ['Admin'],
                    'ikon'   => 'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z M6 6h.008v.008H6V6Z',
                ],
                [
                    'label'  => 'Tipe Cedera',
                    'route'  => 'admin.tipe-cedera.index',
                    'aktif'  => ['admin.tipe-cedera.*'],
                    'peran'  => ['Admin'],
                    'ikon'   => 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z',
                ],
                [
                    'label'  => 'Faktor Penyebab',
                    'route'  => 'admin.faktor-penyebab.index',
                    'aktif'  => ['admin.faktor-penyebab.*'],
                    'peran'  => ['Admin'],
                    'ikon'   => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
                ],
                [
                    'label'  => 'Intervensi Pasien',
                    'route'  => 'admin.intervensi.index',
                    'aktif'  => ['admin.intervensi.*'],
                    'peran'  => ['Admin'],
                    'ikon'   => 'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085',
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
                    'label' => 'Statistik & Analitik',
                    'route' => 'statistik.index',
                    'aktif' => ['statistik.index'],
                    'peran' => [Peran::DIREKTUR],
                    'ikon'  => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
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
                'isPeneliti',
                'peranAktif',
                'menuUtama',
                'menuPelaporan',
                'menuNotifikasi',
                'menuAdmin',
                'menuFormulir',
                'menuDirektur',
                'menuBawah',
            ));
        });
    }
}
