{{--
|--------------------------------------------------------------------------
| Layout Utama Aplikasi (app.blade.php)
|--------------------------------------------------------------------------
| Layout master yang digunakan oleh semua halaman terautentikasi.
| Menggunakan pola wrapper: sidebar tetap di kiri, konten utama di kanan.
| Navbar ditampilkan di atas area konten (bukan di atas sidebar).
|
| Responsive:
|   - Desktop (lg+): sidebar tetap di kiri, konten di kanan
|   - Mobile (<lg):   sidebar tersembunyi, ditampilkan via hamburger
|
| Slot yang tersedia:
|   @section('judul')   — judul tab browser
|   @section('konten')  — konten halaman utama
|--------------------------------------------------------------------------
--}}
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('judul', 'Sistem Pelaporan Insiden Medication Errors')</title>

    {{-- Cegah mesin pencari mengindeks halaman internal --}}
    <meta name="robots" content="noindex, nofollow">

    {{-- Favicon: menggunakan logo Rumah Sakit --}}
    <link rel="icon" type="image/jpeg" href="{{ asset('images/icon-rmh_sakit.jpg') }}">
    <link rel="shortcut icon" type="image/jpeg" href="{{ asset('images/icon-rmh_sakit.jpg') }}">

    {{-- Aset Vite: Tailwind CSS + JS --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{-- Latar belakang halaman: abu-abu sangat terang agar mata nyaman --}}
<body class="h-full bg-slate-50 font-sans text-slate-700 antialiased">

    {{-- Wrapper utama: sidebar + area konten --}}
    <div class="flex h-full min-h-screen">

        {{-- Overlay backdrop (mobile only) --}}
        <div id="sidebar-overlay"
             class="fixed inset-0 z-30 hidden bg-black/50 transition-opacity lg:hidden"
             onclick="toggleSidebar()"></div>

        {{-- Sidebar — komponen terpisah untuk modularitas --}}
        <div id="sidebar-container"
             class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full transition-transform duration-300
                    lg:static lg:z-auto lg:translate-x-0 lg:transition-none">
            @include('layouts.sidebar')
        </div>

        {{-- Area konten utama (navbar + halaman) --}}
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">

            {{-- Navbar atas --}}
            @include('layouts.navbar')

            {{-- Konten halaman yang bisa di-scroll --}}
            <main class="flex-1 overflow-y-auto p-4 sm:p-6">
                @yield('konten')
            </main>

            {{-- Footer global —— konsisten di seluruh halaman --}}
            @include('layouts.footer')
        </div>
    </div>

    {{-- ================================================================
         Auto-Logout Countdown (SesiMaksimalMasuk — 10 menit)
         Menampilkan peringatan 60 detik sebelum sesi habis dan
         otomatis submit form logout saat waktu habis.
         ================================================================ --}}
    @auth
    @php
        $loginPada   = session(\App\Http\Middleware\SesiMaksimalMasuk::KUNCI_LOGIN_PADA, 0);
        $durasiMaks  = \App\Http\Middleware\SesiMaksimalMasuk::durasiMaksDet();
        $sisaDetik   = max(0, ($loginPada + $durasiMaks) - now()->timestamp);
    @endphp

    {{-- Form tersembunyi untuk auto-logout (tidak bergantung fetch/XHR) --}}
    <form id="form-keluar-otomatis" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
    </form>

    {{-- Banner peringatan — tersembunyi sampai sisa ≤ 60 detik --}}
    <div id="sesi-peringatan"
         class="hidden fixed bottom-4 right-4 z-50 flex max-w-sm items-start gap-3
                rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-lg">
        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-500" xmlns="http://www.w3.org/2000/svg"
             fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71
                     c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898
                     0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
        <div>
            <p class="text-sm font-semibold text-amber-800">Sesi hampir berakhir</p>
            <p class="mt-0.5 text-xs text-amber-700">
                Anda akan otomatis keluar dalam
                <span id="sesi-waktu-sisa" class="font-bold"></span>.
            </p>
        </div>
    </div>

    <script>
        (function () {
            var sisaDetik   = {{ $sisaDetik }};
            var peringatan  = document.getElementById('sesi-peringatan');
            var waktuSisa   = document.getElementById('sesi-waktu-sisa');
            var formKeluar  = document.getElementById('form-keluar-otomatis');

            function formatDetik(dtk) {
                var m = Math.floor(dtk / 60);
                var s = dtk % 60;
                return m > 0
                    ? m + ' mnt ' + String(s).padStart(2, '0') + ' dtk'
                    : s + ' detik';
            }

            function tick() {
                if (sisaDetik <= 0) {
                    formKeluar.submit();
                    return;
                }

                if (sisaDetik <= 60) {
                    peringatan.classList.remove('hidden');
                    waktuSisa.textContent = formatDetik(sisaDetik);
                }

                sisaDetik--;
            }

            tick();
            var timer = setInterval(function () {
                tick();
                if (sisaDetik < 0) clearInterval(timer);
            }, 1000);
        })();
    </script>
    @endauth

    {{-- Script toggle sidebar untuk mobile --}}
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar-container');
            const overlay = document.getElementById('sidebar-overlay');
            const isOpen  = !sidebar.classList.contains('-translate-x-full');

            if (isOpen) {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            } else {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
        }
    </script>

    {{-- Script yang di-push oleh halaman/komponen individual (mis. Chart.js) --}}
    @stack('scripts')
</body>
</html>
