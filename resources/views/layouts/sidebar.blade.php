{{--
|--------------------------------------------------------------------------
| Sidebar Navigasi (sidebar.blade.php)
|--------------------------------------------------------------------------
| Sidebar tetap di sisi kiri layar dengan latar belakang warna brand
| (teal primary-800 — hijau-toska gelap, WCAG AA ≥ 4.5:1 vs putih).
| Warna dikontrol via CSS variable --token-brand di resources/css/theme.css.
|
| Menu yang tampil bersifat TERPUSAT: satu file blade ini mengontrol
| seluruh menu untuk semua peran. Gunakan blok @if / memilikiPeran()
| untuk menampilkan/menyembunyikan item menu berdasarkan peran pengguna.
|
| Saat ini berfokus pada peran Perawat (Nakes), namun siap dikembangkan
| untuk peran lain (Kepala Ruangan, Komite, Admin, Direktur).
|--------------------------------------------------------------------------
--}}

@php
    $pengguna = auth()->user();

    // Tentukan halaman aktif berdasarkan nama route saat ini
    $routeAktif = request()->routeIs(...) ? '' : '';
@endphp

<aside class="flex w-64 flex-col bg-brand text-white">

    {{-- Header sidebar: Logo & judul sistem --}}
    <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
        {{-- Logo MER System (gambar asli, tanpa paksa square) --}}
        <img
            src="{{ asset('images/icon-mer_system.jpg') }}"
            alt="Logo Sistem MER"
            class="h-9 w-auto shrink-0 rounded-lg"
        >
        <div>
            <h2 class="text-sm font-bold leading-tight tracking-wide">Sistem MER</h2>
            <p class="text-[11px] text-white/60">Pelaporan Insiden Obat</p>
        </div>
    </div>

    {{-- Navigasi utama — kelompok atas --}}
    <nav class="mt-4 flex-1 space-y-1 px-3" aria-label="Menu utama">

        {{-- ============================================================
             Menu untuk SEMUA peran yang terautentikasi
             ============================================================ --}}

        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
           @class([
               'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
               'bg-white/15 text-white'         => request()->routeIs('dashboard', 'perawat.dashboard', 'admin.dashboard', 'direktur.dashboard', 'komite.dashboard', 'kepala-ruangan.dashboard'),
               'text-white/70 hover:bg-white/10 hover:text-white' => ! request()->routeIs('dashboard', 'perawat.dashboard', 'admin.dashboard', 'direktur.dashboard', 'komite.dashboard', 'kepala-ruangan.dashboard'),
           ])>
            {{-- Ikon: kotak-kotak (dashboard) --}}
            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                 viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25
                         2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25
                         2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0
                         1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1
                         2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18
                         10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1
                         2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18
                         20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
            </svg>
            Dashboard
        </a>

        {{-- ============================================================
             Menu khusus Perawat / Kepala Ruangan / Komite
             (peran yang boleh membuat & melihat laporan insiden)
             ============================================================ --}}
        @if($pengguna->memilikiPeran('Perawat') || $pengguna->memilikiPeran('Kepala Ruangan') || $pengguna->memilikiPeran('Komite'))

            {{-- Buat Laporan --}}
            <a href="{{ route('laporan.buat') }}"
               @class([
                   'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                   'bg-white/15 text-white'         => request()->routeIs('laporan.buat'),
                   'text-white/70 hover:bg-white/10 hover:text-white' => ! request()->routeIs('laporan.buat'),
               ])>
                {{-- Ikon: dokumen + tanda plus --}}
                <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125
                             0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75
                             0v6m3-3H9m1.5-3H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0
                             .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504
                             1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                Buat Laporan
            </a>

            {{-- Riwayat Laporan --}}
            <a href="{{ route('laporan.index') }}"
               @class([
                   'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                   'bg-white/15 text-white'         => request()->routeIs('laporan.index', 'laporan.tampil'),
                   'text-white/70 hover:bg-white/10 hover:text-white' => ! request()->routeIs('laporan.index', 'laporan.tampil'),
               ])>
                {{-- Ikon: daftar clipboard --}}
                <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0
                             2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424
                             0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0
                             .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0
                             0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15a2.25 2.25
                             0 0 1 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095
                             4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621
                             0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125
                             1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504
                             -1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0
                             3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                </svg>
                Riwayat Laporan
            </a>
        @endif

        {{-- Notifikasi (semua peran) --}}
        <a href="{{ route('notifikasi.index') }}"
           @class([
               'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
               'bg-white/15 text-white'         => request()->routeIs('notifikasi.*'),
               'text-white/70 hover:bg-white/10 hover:text-white' => ! request()->routeIs('notifikasi.*'),
           ])>
            {{-- Ikon: lonceng --}}
            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                 viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1
                         18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64
                         3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714
                         0a3 3 0 1 1-5.714 0" />
            </svg>
            Notifikasi
            {{-- Badge jumlah notifikasi belum dibaca --}}
            <span class="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full
                         bg-red-500 px-1.5 text-[11px] font-bold leading-none text-white">
                3
            </span>
        </a>

        {{-- ============================================================
             Menu khusus Admin
             ============================================================ --}}
        @if($pengguna->memilikiPeran('Admin'))
            {{-- Pemisah visual antar kelompok menu --}}
            <div class="my-3 border-t border-white/10"></div>
            <p class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">
                Administrasi
            </p>

            {{-- Kelola Pengguna (placeholder) --}}
            <a href="#"
               class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium
                      text-white/70 transition-colors hover:bg-white/10 hover:text-white">
                <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0
                             4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113
                             -.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331
                             0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1
                             11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1
                             6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1
                             5.25 0Z" />
                </svg>
                Kelola Pengguna
            </a>
        @endif

        {{-- ============================================================
             Menu khusus Direktur
             ============================================================ --}}
        @if($pengguna->memilikiPeran('Direktur'))
            <div class="my-3 border-t border-white/10"></div>
            <p class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">
                Eksekutif
            </p>

            {{-- Laporan Statistik (placeholder) --}}
            <a href="#"
               class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium
                      text-white/70 transition-colors hover:bg-white/10 hover:text-white">
                <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504
                             1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125
                             1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125
                             1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504
                             1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5
                             4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504
                             21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125
                             1.125 0 0 1-1.125-1.125V4.125Z" />
                </svg>
                Laporan Statistik
            </a>
        @endif
    </nav>

    {{-- Navigasi bawah — Profil, Pengaturan, Keluar --}}
    <div class="mt-auto space-y-1 border-t border-white/10 px-3 pb-4 pt-3">

        {{-- Profil Saya --}}
        <a href="{{ route('profil.index') }}"
           @class([
               'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
               'bg-white/15 text-white'         => request()->routeIs('profil.*'),
               'text-white/70 hover:bg-white/10 hover:text-white' => ! request()->routeIs('profil.*'),
           ])>
            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                 viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501
                         20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676
                         0-5.216-.584-7.499-1.632Z" />
            </svg>
            Profil Saya
        </a>

        {{-- Pengaturan --}}
        <a href="{{ route('pengaturan.index') }}"
           @class([
               'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
               'bg-white/15 text-white'         => request()->routeIs('pengaturan.*'),
               'text-white/70 hover:bg-white/10 hover:text-white' => ! request()->routeIs('pengaturan.*'),
           ])>
            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                 viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398
                         1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127
                         .325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1
                         1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293
                         .241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43
                         .991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125
                         0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47
                         6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09
                         .543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c
                         -.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196
                         -.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297
                         -2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613
                         .43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004
                         -.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1
                         1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086
                         .22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            Pengaturan
        </a>

        {{-- Keluar (POST form untuk keamanan sesi) --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm
                           font-medium text-white/70 transition-colors hover:bg-red-500/20
                           hover:text-red-300">
                {{-- Ikon: pintu keluar --}}
                <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25
                             2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15
                             m3 0 3-3m0 0-3-3m3 3H9" />
                </svg>
                Keluar
            </button>
        </form>
    </div>
</aside>
