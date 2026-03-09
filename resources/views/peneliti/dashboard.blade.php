{{--
|--------------------------------------------------------------------------
| Dashboard Peneliti (peneliti/dashboard.blade.php)
|--------------------------------------------------------------------------
| Dashboard khusus untuk akun Dosen Peneliti dalam mode penuh (tanpa
| simulasi peran). Menampilkan statistik pengguna, login terbaru,
| dan laporan insiden terbaru yang masuk ke sistem.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Dashboard Peneliti — Sistem MER')

@section('konten')

    @php
        $pengguna    = Auth::user();
        $namaLengkap = $pengguna->nama_lengkap;
    @endphp

    {{-- ================================================================
         BANNER PENELITI — mengarahkan untuk menggunakan dropdown
         "Lihat Sebagai" di navbar.
         ================================================================ --}}
    <div class="mb-6 flex gap-4 rounded-xl border border-indigo-200 bg-indigo-50 p-5">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5
                         c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49
                         16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
        </div>
        <div>
            <p class="text-sm font-semibold text-indigo-800">Mode Peneliti Aktif</p>
            <p class="mt-1 text-sm text-indigo-700">
                Anda sedang mengakses sistem sebagai <span class="font-semibold">Dosen Peneliti</span>.
                Gunakan tombol <span class="inline-flex items-center gap-1 rounded-md border border-indigo-300
                bg-white px-1.5 py-0.5 text-xs font-semibold text-indigo-700">
                    <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                         stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5
                                 c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49
                                 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    Lihat Sebagai
                </span>
                di sudut kanan atas untuk menjelajahi sistem dari sudut pandang setiap peran pengguna.
            </p>
        </div>
    </div>

    {{-- ================================================================
         HEADER SAPAAN
         ================================================================ --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">
            Selamat datang, {{ $namaLengkap }} 👋
        </h1>
        <p class="mt-1 text-sm text-slate-400">
            Dosen Peneliti - Sistem Pelaporan Kesalahan Pengobatan
        </p>
    </div>

    {{-- ================================================================
         KARTU STATISTIK PENGGUNA
         ================================================================ --}}
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

        {{-- Total Pengguna --}}
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $statistik['total'] }}</p>
                <p class="text-sm text-slate-400">Total Pengguna</p>
            </div>
        </div>

        {{-- Pengguna Aktif --}}
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-green-100 text-green-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $statistik['aktif'] }}</p>
                <p class="text-sm text-slate-400">Pengguna Aktif</p>
            </div>
        </div>

        {{-- Pengguna Nonaktif --}}
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $statistik['nonaktif'] }}</p>
                <p class="text-sm text-slate-400">Pengguna Nonaktif</p>
            </div>
        </div>
    </div>

    {{-- ================================================================
         LAPORAN TERBARU — insiden yang baru disubmit ke sistem
         ================================================================ --}}
    <div class="mb-8 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-700">Laporan Terbaru</h2>
        </div>

        @if ($laporanTerbaru->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-slate-400">
                Belum ada laporan insiden yang masuk.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="px-5 py-3 font-semibold text-slate-500">No. Laporan</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Tipe</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Status</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Unit Kerja</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Pelapor</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Tanggal Lapor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($laporanTerbaru as $laporan)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-5 py-3">
                                    <a href="{{ route('laporan.tampil', $laporan) }}"
                                       class="font-medium text-brand hover:underline">
                                        {{ $laporan->nomor_laporan }}
                                    </a>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $laporan->warnaInsiden() }}">
                                        {{ $laporan->labelTipeInsiden() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $laporan->warnaStatus() }}">
                                        {{ $laporan->labelStatus() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-slate-500">
                                    {{ $laporan->nama_unit_kerja ?? '—' }}
                                </td>
                                <td class="px-5 py-3">
                                    <p class="text-slate-700">{{ $laporan->pelapor?->nama_lengkap ?? '—' }}</p>
                                </td>
                                <td class="px-5 py-3 text-slate-500">
                                    {{ $laporan->tgl_lapor?->format('d M Y, H:i') ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ================================================================
         PENGGUNA LOGIN TERAKHIR
         ================================================================ --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-700">Login Terakhir</h2>
        </div>

        @if ($loginTerbaru->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-slate-400">
                Belum ada data login.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="px-5 py-3 font-semibold text-slate-500">Nama</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Peran</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Waktu Login</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($loginTerbaru as $user)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800">{{ $user->nama_lengkap }}</p>
                                    <p class="text-xs text-slate-400">{{ $user->nomor_induk }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    @foreach ($user->peran as $p)
                                        <span class="inline-block rounded-full bg-brand/10 px-2.5 py-0.5 text-xs font-medium text-brand">
                                            {{ $p->nama_display }}
                                        </span>
                                    @endforeach
                                </td>
                                <td class="px-5 py-3 text-slate-500">
                                    {{ $user->terakhir_login_pada->format('d M Y, H:i') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

@endsection
