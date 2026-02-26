{{--
|--------------------------------------------------------------------------
| Layout Tamu / Guest (guest.blade.php)
|--------------------------------------------------------------------------
| Layout untuk halaman publik (login, dsb.) yang TIDAK memerlukan
| autentikasi. Desain terinspirasi dari SIGER Medik / SIMRS:
|   - Background: foto rumah sakit (cover-rmh_sakit.png)
|   - Overlay lengkung putih di bagian bawah
|   - Kartu login mengambang di tengah
|   - Logo icon-mer_system.jpg di atas kartu
|   - Info peneliti di bawah form
|   - Footer global di paling bawah
|
| Warna mengikuti CSS variables dari resources/css/theme.css.
|--------------------------------------------------------------------------
--}}
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistem Pelaporan Insiden Medication Errors')</title>

    {{-- SEO: cegah indexing --}}
    <meta name="robots" content="noindex, nofollow">

    {{-- Favicon: logo Rumah Sakit --}}
    <link rel="icon" type="image/jpeg" href="{{ asset('images/icon-rmh_sakit.jpg') }}">
    <link rel="shortcut icon" type="image/jpeg" href="{{ asset('images/icon-rmh_sakit.jpg') }}">

    {{-- Aset Vite — memuat theme.css (palet warna) + Tailwind --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex min-h-screen flex-col bg-page-bg font-sans text-text-base antialiased">

    {{-- ============================================================
         HERO BACKGROUND — foto rumah sakit + overlay lengkung
         ============================================================ --}}
    <div class="relative flex flex-1 flex-col items-center justify-center">

        {{-- Layer 1: Gambar latar --}}
        <div class="absolute inset-0 z-0">
            <img
                src="{{ asset('images/cover-rmh_sakit.png') }}"
                alt="RSU Mayjend. H.M. Ryacudu"
                class="h-full w-full object-cover"
            >
            {{-- Overlay gelap agar teks/kartu lebih kontras --}}
            <div class="absolute inset-0 bg-gradient-to-b from-black/40 via-black/25 to-black/50"></div>
        </div>

        {{-- Layer 2: Kurva putih di bagian bawah (mirip SIGER Medik) --}}
        <svg class="absolute bottom-0 left-0 z-10 w-full" viewBox="0 0 1440 120" preserveAspectRatio="none"
             style="height: 80px">
            <path d="M0,60 C360,120 1080,0 1440,60 L1440,120 L0,120 Z" fill="var(--token-surface)"/>
        </svg>

        {{-- Layer 3: Konten kartu login --}}
        <div class="relative z-20 flex w-full flex-col items-center px-4 py-10">

            {{-- ========== KARTU LOGIN ========== --}}
            <div class="w-full max-w-[420px] overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5">

                {{-- Header dengan gradien brand — dimulai dari warna utama #3AE3DC --}}
                <div class="bg-gradient-to-br from-primary-400 to-primary-800 px-8 pb-6 pt-8 text-center">
                    <img
                        src="{{ asset('images/icon-mer_system.jpg') }}"
                        alt="Logo Sistem MER"
                        class="mx-auto mb-4 h-24 w-auto rounded-xl shadow-lg ring-2 ring-white/25"
                    >
                    <h1 class="text-[17px] font-bold leading-tight text-white">
                        Sistem Pelaporan Insiden Kesalahan Pengobatan
                    </h1>
                    <p class="mt-1 text-[13px] text-primary-200">
                        Medication Errors Report
                    </p>
                </div>

                {{-- Body formulir --}}
                <div class="px-8 pb-6 pt-6">

                    {{-- Flash messages --}}
                    @if (session('sukses'))
                        <div class="mb-5 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700" role="alert">
                            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>
                            <span>{{ session('sukses') }}</span>
                        </div>
                    @endif
                    @if (session('peringatan'))
                        <div class="mb-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700" role="alert">
                            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                            <span>{{ session('peringatan') }}</span>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm-1-5a1 1 0 1 0 2 0V9a1 1 0 1 0-2 0v4Zm1-7.25a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5Z" clip-rule="evenodd"/></svg>
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    {{-- Konten formulir (di-yield oleh child view) --}}
                    @yield('content')

                </div>

                {{-- ========== DOSEN PENELITI ========== --}}
                <div class="border-t border-slate-100 bg-slate-50 px-8 pt-5 pb-5">
                    <p class="mb-3 text-center text-[10px] font-semibold uppercase tracking-widest text-slate-400">
                        Tim Peneliti
                    </p>
                    <ul class="space-y-2">
                        @foreach ([
                            'Bayu Anggileo Pramesona, S.Kep, Ns, MMR, PhD, FISQua',
                            'Prof. Dr. Dyah Wulan Sumekar R. Wardani, SKM, M.Kes',
                            'apt. Dwi Aulia Ramdini, S.Farm., M.Farm',
                            'Prof. Emeritus Surasak Taneepanichskul, MD',
                        ] as $nama)
                            <li class="text-center">
                                <p class="text-[12px] font-semibold leading-snug text-slate-700">{{ $nama }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- ========== KREDIT PENDANAAN ========== --}}
                <div class="bg-gradient-to-r from-primary-600 to-primary-800 px-8 py-3 flex items-center justify-center">
                    <p class="text-[11px] font-medium tracking-wide text-primary-100">
                        Dibiayai oleh&nbsp;<span class="font-bold text-white">HETI Project Unila</span>
                    </p>
                </div>

            </div>
        </div>
    </div>

    {{-- ============================================================
         FOOTER GLOBAL
         ============================================================ --}}
    @include('layouts.footer', ['footerGelap' => true])

</body>
</html>
