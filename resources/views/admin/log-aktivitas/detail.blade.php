{{--
|--------------------------------------------------------------------------
| Detail Aktivitas Pengguna (admin/log-aktivitas/detail.blade.php)
|--------------------------------------------------------------------------
| Riwayat aktivitas per pengguna — menampilkan semua aksi yang dilakukan
| oleh satu pengguna tertentu.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Riwayat Aktivitas — Sistem MER')

@section('konten')

    {{-- HEADER + BREADCRUMB --}}
    <div class="mb-6">
        <nav class="mb-2 text-xs text-slate-400">
            <a href="{{ route('admin.log-aktivitas.index') }}" class="hover:text-brand">Log Aktivitas</a>
            <span class="mx-1">›</span>
            <span class="text-slate-600">Detail Pengguna</span>
        </nav>
        <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-brand/10 text-brand">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">
                    {{ $dataPengguna->nama_lengkap }}
                    @if ($dataPengguna->trashed())
                        <span class="ml-2 inline-block rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-600">
                            Akun Dihapus
                        </span>
                    @endif
                </h1>
                <p class="text-sm text-slate-400">
                    {{ $dataPengguna->nomor_induk }} · {{ $dataPengguna->email }}
                </p>
            </div>
        </div>
    </div>

    {{-- ================================================================
         TABEL RIWAYAT
         ================================================================ --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">

        @if ($daftarLog->isEmpty())
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                <p class="mt-3 text-sm text-slate-500">Pengguna ini belum memiliki riwayat aktivitas.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="px-5 py-3 font-semibold text-slate-500">Waktu</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Aksi</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Tabel</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">ID Data</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($daftarLog as $log)
                            <tr class="transition-colors hover:bg-slate-50/50">
                                {{-- Waktu --}}
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <p class="text-xs font-medium text-slate-700">{{ $log->created_at->format('d M Y') }}</p>
                                    <p class="text-xs text-slate-400">{{ $log->created_at->format('H:i:s') }}</p>
                                </td>

                                {{-- Aksi --}}
                                <td class="px-5 py-3">
                                    @php
                                        $warnaBadge = match($log->aksi) {
                                            'CREATE'         => 'bg-green-100 text-green-700',
                                            'UPDATE'         => 'bg-blue-100 text-blue-700',
                                            'DELETE'         => 'bg-red-100 text-red-700',
                                            'LOGIN'          => 'bg-slate-100 text-slate-600',
                                            'ACTIVATE'       => 'bg-emerald-100 text-emerald-700',
                                            'DEACTIVATE'     => 'bg-orange-100 text-orange-700',
                                            'RESET_PASSWORD' => 'bg-amber-100 text-amber-700',
                                            default          => 'bg-slate-100 text-slate-600',
                                        };
                                    @endphp
                                    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $warnaBadge }}">
                                        {{ $log->aksi }}
                                    </span>
                                </td>

                                {{-- Tabel --}}
                                <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $log->nama_tabel }}</td>

                                {{-- ID Data --}}
                                <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ $log->id_data ?? '—' }}</td>

                                {{-- Detail --}}
                                <td class="px-5 py-3">
                                    @if ($log->data_lama || $log->data_baru)
                                        <div x-data="{ buka: false }">
                                            <button @click="buka = !buka" type="button"
                                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-brand
                                                           transition-colors hover:bg-brand/10">
                                                <svg class="h-3.5 w-3.5 transition-transform" :class="buka && 'rotate-90'"
                                                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                                                </svg>
                                                Lihat
                                            </button>
                                            <div x-show="buka" x-collapse class="mt-2 max-w-md space-y-2">
                                                @if ($log->data_lama)
                                                    <div>
                                                        <p class="text-[10px] font-semibold uppercase text-red-400">Data Lama</p>
                                                        <pre class="mt-0.5 max-h-32 overflow-auto rounded bg-red-50 p-2 text-[11px] text-red-700">{{ json_encode($log->data_lama, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    </div>
                                                @endif
                                                @if ($log->data_baru)
                                                    <div>
                                                        <p class="text-[10px] font-semibold uppercase text-green-500">Data Baru</p>
                                                        <pre class="mt-0.5 max-h-32 overflow-auto rounded bg-green-50 p-2 text-[11px] text-green-700">{{ json_encode($log->data_baru, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($daftarLog->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $daftarLog->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- Tombol Kembali --}}
    <div class="mt-4">
        <a href="{{ route('admin.log-aktivitas.index') }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition-colors hover:text-brand">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
            Kembali ke Log Aktivitas
        </a>
    </div>

@endsection
