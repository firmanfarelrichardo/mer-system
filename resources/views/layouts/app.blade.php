{{--
|--------------------------------------------------------------------------
| Layout Utama Aplikasi (app.blade.php)
|--------------------------------------------------------------------------
| Layout master yang digunakan oleh semua halaman terautentikasi.
| Menggunakan pola wrapper: sidebar tetap di kiri, konten utama di kanan.
| Navbar ditampilkan di atas area konten (bukan di atas sidebar).
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

    {{-- Aset Vite: Tailwind CSS + JS --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{-- Latar belakang halaman: abu-abu sangat terang agar mata nyaman --}}
<body class="h-full bg-slate-50 font-sans text-slate-700 antialiased">

    {{-- Wrapper utama: sidebar + area konten --}}
    <div class="flex h-full min-h-screen">

        {{-- Sidebar — komponen terpisah untuk modularitas --}}
        @include('layouts.sidebar')

        {{-- Area konten utama (navbar + halaman) --}}
        <div class="flex flex-1 flex-col overflow-hidden">

            {{-- Navbar atas --}}
            @include('layouts.navbar')

            {{-- Konten halaman yang bisa di-scroll --}}
            <main class="flex-1 overflow-y-auto p-6">
                @yield('konten')
            </main>
        </div>
    </div>

</body>
</html>
