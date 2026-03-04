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
{{-- x-data="idleTimer()" : Komponen Alpine untuk auto-logout idle (5 menit tanpa aktivitas). --}}
{{-- Event listener dipasang di sini agar menangkap aktivitas dari seluruh halaman.         --}}
<body
    x-data="idleTimer()"
    @mousemove="updateActivity"
    @keydown="updateActivity"
    @scroll.window="updateActivity"
    @click="updateActivity"
    class="h-full bg-slate-50 font-sans text-slate-700 antialiased">

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

    {{-- ================================================================
         Auto-Logout on Idle (Alpine.js — 5 menit tanpa aktivitas)

         ARSITEKTUR:
           - updateActivity() hanya meng-update variabel lastActivity.
             Throttle manual (500 ms) mencegah eksekusi ribuan kali/detik
             akibat mousemove, sehingga 0% lag pada UI.
           - checkIdle() berjalan via setInterval setiap 10 detik.
             Metode ini jauh lebih efisien daripada mengevaluasi kondisi
             pada setiap event aktivitas.
           - Saat idle terdeteksi: POST /logout-idle (+ CSRF token) dikirim
             via Fetch, lalu window.location.href diarahkan ke login.

         ISOLASI: Script ini hanya ada di app.blade.php (layout
         terautentikasi). guest.blade.php tidak terpengaruh.
         ================================================================ --}}
    @auth
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('idleTimer', () => ({
                lastActivity  : Date.now(),
                idleLimit     : 10 * 60 * 1000, // 600.000 ms = 5 menit
                _lastThrottle : 0,
                _intervalId   : null,

                init() {
                    // Pengecekan setiap 10 detik — jauh lebih hemat daripada setiap event.
                    this._intervalId = setInterval(() => this.checkIdle(), 10_000);
                },

                destroy() {
                    // Bersihkan interval saat komponen dihancurkan (SPA navigation, dll.)
                    if (this._intervalId) clearInterval(this._intervalId);
                },

                updateActivity() {
                    // Throttle manual: perbarui lastActivity maks. 1x per 500ms.
                    // Mencegah eksekusi berat akibat event mousemove yang sangat sering.
                    const now = Date.now();
                    if (now - this._lastThrottle < 500) return;
                    this._lastThrottle = now;
                    this.lastActivity  = now;
                },

                checkIdle() {
                    if ((Date.now() - this.lastActivity) < this.idleLimit) return;

                    // Hentikan interval agar checkIdle tidak terpanggil dua kali.
                    clearInterval(this._intervalId);

                    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

                    fetch('{{ route("logout.idle") }}', {
                        method  : 'POST',
                        headers : {
                            'Content-Type' : 'application/json',
                            'Accept'       : 'application/json',
                            'X-CSRF-TOKEN' : csrfToken,
                        },
                    })
                    .catch(() => {
                        // Abaikan error jaringan — logout tetap dilakukan di sisi klien.
                    })
                    .finally(() => {
                        // Paksa redirect ke halaman login tanpa menunggu respons server.
                        window.location.href = '{{ route("login") }}';
                    });
                },
            }));
        });
    </script>
    @endauth

    {{-- Script yang di-push oleh halaman/komponen individual (mis. Chart.js) --}}
    @stack('scripts')
</body>
</html>
