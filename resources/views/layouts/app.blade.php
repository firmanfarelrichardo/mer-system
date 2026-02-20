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
</body>
</html>
