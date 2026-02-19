{{--
|--------------------------------------------------------------------------
| Halaman Dashboard Utama (dashboard.blade.php)
|--------------------------------------------------------------------------
| Digunakan oleh semua rute dasbor per-peran (perawat, admin, dll.).
| Menampilkan sapaan, ringkasan cepat, dan akses pintas berdasarkan peran.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Dashboard — Sistem MER')

@section('konten')

    @php
        $pengguna    = Auth::user();
        $namaLengkap = $pengguna->nama_lengkap;
        $peranUtama  = $pengguna->daftarPeran()[0] ?? '—';
        $namaUnit    = $pengguna->unitKerja?->nama_unit ?? null;
        $loginTerakhir = $pengguna->terakhir_login_pada?->diffForHumans() ?? '—';
    @endphp

    {{-- ================================================================
         HEADER SAPAAN
         ================================================================ --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">
            Selamat datang, {{ $namaLengkap }} 👋
        </h1>
        <p class="mt-1 text-sm text-slate-400">
            {{ $peranUtama }}{{ $namaUnit ? " — {$namaUnit}" : '' }}
            &nbsp;·&nbsp;
            Login terakhir: {{ $loginTerakhir }}
        </p>
    </div>

    {{-- ================================================================
         AKSES CEPAT — menu kartu berdasarkan peran
         ================================================================ --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

        {{-- Kartu: Buat Laporan — untuk Perawat, Kepala Ruangan, dan Komite --}}
        @if($pengguna->memilikiPeran('Perawat') || $pengguna->memilikiPeran('Kepala Ruangan') || $pengguna->memilikiPeran('Komite'))
            <a href="{{ route('laporan.buat') }}"
               class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-white
                      p-5 shadow-sm transition-shadow hover:shadow-md">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg
                            bg-brand/10 text-brand group-hover:bg-brand group-hover:text-white
                            transition-colors">
                    {{-- Ikon: dokumen + plus --}}
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125
                                 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25
                                 m3.75 0v6m3-3H9m1.5-3H5.625c-.621 0-1.125.504-1.125 1.125v17.25
                                 c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504
                                 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-slate-800">Buat Laporan</p>
                    <p class="mt-0.5 text-sm text-slate-400">Laporkan insiden baru</p>
                </div>
            </a>

            {{-- Kartu: Riwayat Laporan --}}
            <a href="{{ route('laporan.index') }}"
               class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-white
                      p-5 shadow-sm transition-shadow hover:shadow-md">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg
                            bg-blue-50 text-blue-500 group-hover:bg-blue-500 group-hover:text-white
                            transition-colors">
                    {{-- Ikon: clipboard daftar --}}
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0
                                 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424
                                 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75
                                 .75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8
                                 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15
                                 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973
                                 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25
                                 c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504
                                 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-slate-800">Riwayat Laporan</p>
                    <p class="mt-0.5 text-sm text-slate-400">Lihat semua laporan Anda</p>
                </div>
            </a>
        @endif

        {{-- Kartu: Notifikasi (semua peran) --}}
        <a href="{{ route('notifikasi.index') }}"
           class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-white
                  p-5 shadow-sm transition-shadow hover:shadow-md">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg
                        bg-amber-50 text-amber-500 group-hover:bg-amber-500 group-hover:text-white
                        transition-colors">
                {{-- Ikon: lonceng --}}
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1
                             18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312
                             6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255
                             24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
            </div>
            <div>
                <p class="font-semibold text-slate-800">Notifikasi</p>
                <p class="mt-0.5 text-sm text-slate-400">3 notifikasi belum dibaca</p>
            </div>
        </a>

        {{-- Kartu: Kelola Pengguna — khusus Admin --}}
        @if($pengguna->memilikiPeran('Admin'))
            <a href="#"
               class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-white
                      p-5 shadow-sm transition-shadow hover:shadow-md">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg
                            bg-violet-50 text-violet-500 group-hover:bg-violet-500
                            group-hover:text-white transition-colors">
                    {{-- Ikon: grup pengguna --}}
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0
                                 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003
                                 c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318
                                 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109
                                 a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1
                                 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625
                                 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-slate-800">Kelola Pengguna</p>
                    <p class="mt-0.5 text-sm text-slate-400">Tambah &amp; atur akun</p>
                </div>
            </a>
        @endif

        {{-- Kartu: Laporan Statistik — khusus Direktur --}}
        @if($pengguna->memilikiPeran('Direktur'))
            <a href="#"
               class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-white
                      p-5 shadow-sm transition-shadow hover:shadow-md">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg
                            bg-emerald-50 text-emerald-500 group-hover:bg-emerald-500
                            group-hover:text-white transition-colors">
                    {{-- Ikon: batang grafik --}}
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504
                                 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125
                                 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125
                                 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0
                                 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0
                                 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125
                                 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0
                                 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0
                                 1-1.125-1.125V4.125Z" />
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-slate-800">Laporan Statistik</p>
                    <p class="mt-0.5 text-sm text-slate-400">Ringkasan eksekutif</p>
                </div>
            </a>
        @endif
    </div>

@endsection
