{{--
|--------------------------------------------------------------------------
| Sidebar Navigasi (sidebar.blade.php)
|--------------------------------------------------------------------------
| Sidebar navigasi utama yang TERPUSAT dan data-driven.
|
| Variabel disuntikkan via View Composer di AppServiceProvider::boot():
|   $pengguna        — Auth::user()
|   $punyaPeran      — closure: fn(array) => bool, respects $peranAktif
|   $isPeneliti      — bool, true jika pengguna adalah auditor/peneliti
|   $peranAktif      — string|null, peran simulasi aktif (null = mode penuh)
|   $menuUtama       — menu dashboard (semua peran)
|   $menuPelaporan   — menu laporan (Nakes / Kepala Ruangan / Komite)
|   $menuNotifikasi  — menu notifikasi (semua peran)
|   $menuAdmin       — menu administrasi (Admin)
|   $menuDirektur    — menu eksekutif (Direktur)
|   $menuBawah       — profil & pengaturan
|
| Untuk menambah menu baru → edit AppServiceProvider, bukan file ini.
|--------------------------------------------------------------------------
--}}
<aside class="flex h-full w-64 shrink-0 flex-col bg-brand text-white">

    {{-- ============================================================
         HEADER: Logo + Nama Sistem + Close button (mobile)
         ============================================================ --}}
    <div class="flex items-center justify-between border-b border-white/10 px-5 py-5">
        <div class="flex items-center gap-3">
            <img
                src="{{ asset('images/icon-mer_system.jpg') }}"
                alt="Logo Sistem MER"
                class="h-13 w-auto shrink-0 rounded-lg"
            >
            <div>
                <h2 class="text-md font-bold leading-tight tracking-wide">MERS</h2>
                <p class="text-[11px] text-white/60">Sistem Pelaporan Kesalahan Pengobatan</p>
            </div>
        </div>
        {{-- Tombol tutup sidebar — hanya tampil di mobile --}}
        <button type="button"
                class="rounded-lg p-1 text-white/60 hover:bg-white/10 hover:text-white lg:hidden"
                onclick="toggleSidebar()"
                aria-label="Tutup menu">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- ============================================================
         NAVIGASI UTAMA
         ============================================================ --}}
    <nav class="mt-4 flex-1 space-y-1 overflow-y-auto px-3" aria-label="Menu utama">

        {{-- Peneliti dalam "mode penuh" (tidak sedang menyimulasikan peran tertentu):
             semua seksi menu ditampilkan tanpa kecuali.
             Saat peneliti menyimulasikan peran, $isPenelitiPenuh = false
             dan $punyaPeran() memfilter menu berdasarkan $peranAktif. --}}
        @php $isPenelitiPenuh = $isPeneliti && $peranAktif === null; @endphp

        {{-- === Menu Umum (semua peran) === --}}
        @foreach ($menuUtama as $item)
            @include('layouts.partials.sidebar-item', $item)
        @endforeach

        {{-- === Notifikasi (tepat di bawah Dashboard — bukan Admin, kecuali Peneliti) === --}}
        @if ($isPenelitiPenuh || ! $punyaPeran(['Admin']))
            @foreach ($menuNotifikasi as $item)
                @include('layouts.partials.sidebar-item', $item)
            @endforeach
        @endif

        {{-- === Menu Pelaporan (Nakes / Kepala Ruangan / Komite / Peneliti) === --}}
        @if ($isPenelitiPenuh || $punyaPeran(['Nakes', 'Kepala Ruangan', 'Komite']))
            <div class="my-3 border-t border-white/10"></div>
            <p class="mb-1 px-3 text-[10px] font-semibold uppercase tracking-widest text-white/40">
                Pelaporan
            </p>
            @foreach ($menuPelaporan as $item)
                @if ($isPenelitiPenuh || empty($item['peran']) || $punyaPeran($item['peran']))
                    @include('layouts.partials.sidebar-item', $item)
                @endif
            @endforeach
        @endif

        {{-- === Menu Admin (Admin / Peneliti) === --}}
        @if ($isPenelitiPenuh || $punyaPeran(['Admin']))
            <div class="my-3 border-t border-white/10"></div>
            <p class="mb-1 px-3 text-[10px] font-semibold uppercase tracking-widest text-white/40">
                Administrasi
            </p>
            @foreach ($menuAdmin as $item)
                @include('layouts.partials.sidebar-item', $item)
            @endforeach

            {{-- === Sub-grup: Manajemen Formulir === --}}
            <div class="my-3 border-t border-white/10"></div>
            <p class="mb-1 px-3 text-[10px] font-semibold uppercase tracking-widest text-white/40">
                Manajemen Formulir
            </p>
            @foreach ($menuFormulir as $item)
                @include('layouts.partials.sidebar-item', $item)
            @endforeach
        @endif

        {{-- === Menu Direktur (Laporan + Statistik, read-only / Peneliti) === --}}
        @if ($isPenelitiPenuh || $punyaPeran(['Direktur']))
            <div class="my-3 border-t border-white/10"></div>
            <p class="mb-1 px-3 text-[10px] font-semibold uppercase tracking-widest text-white/40">
                Laporan
            </p>
            @foreach ($menuDirektur as $item)
                @include('layouts.partials.sidebar-item', $item)
            @endforeach
        @endif
    </nav>

    {{-- ============================================================
         NAVIGASI BAWAH: Profil, Pengaturan, Keluar
         ============================================================ --}}
    <div class="space-y-1 border-t border-white/10 px-3 pb-4 pt-3">
        @foreach ($menuBawah as $item)
            @include('layouts.partials.sidebar-item', $item)
        @endforeach

        {{-- Tombol Keluar --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm
                           font-medium text-white/70 transition-colors hover:bg-red-500/20
                           hover:text-red-300">
                <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                </svg>
                Keluar
            </button>
        </form>
    </div>
</aside>
