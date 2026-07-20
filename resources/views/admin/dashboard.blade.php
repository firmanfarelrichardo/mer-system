{{--
|--------------------------------------------------------------------------
| Dashboard Admin (admin/dashboard.blade.php)
|--------------------------------------------------------------------------
| Menampilkan ringkasan statistik sistem, pengguna login terbaru,
| dan akses cepat ke fitur administrasi.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Dashboard Admin - Sistem MER')

@section('konten')

    @php
        $pengguna    = Auth::user();
        $namaLengkap = $pengguna->nama_lengkap;
    @endphp

    {{-- ================================================================
         HEADER SAPAAN
         ================================================================ --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">
            Selamat datang, {{ $namaLengkap }} 👋
        </h1>
        <p class="mt-1 text-sm text-slate-400">
            Administrasi - Sistem Pelaporan Kesalahan Pengobatan
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

        {{-- Pengguna Non-aktif --}}
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
         PENGGUNA LOGIN TERAKHIR
         ================================================================ --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-700">Login Terakhir</h2>
            <a href="{{ route('admin.pengguna.index') }}" class="text-xs font-medium text-brand hover:underline">
                Lihat Semua →
            </a>
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
