{{--
|--------------------------------------------------------------------------
| Log Aktivitas (admin/log-aktivitas/index.blade.php)
|--------------------------------------------------------------------------
| Dashboard audit trail — menampilkan semua log aktivitas dengan filter
| berdasarkan aksi, pengguna, dan rentang tanggal.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Log Aktivitas — Sistem MER')

@section('konten')

    {{-- ================================================================
         HEADER
         ================================================================ --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Log Aktivitas</h1>
        <p class="mt-1 text-sm text-slate-400">
            Audit trail — rekam jejak seluruh aktivitas pengguna di sistem.
        </p>
    </div>

    {{-- ================================================================
         FILTER
         ================================================================ --}}
    <form method="GET" action="{{ route('admin.log-aktivitas.index') }}"
          class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">

            {{-- Pencarian --}}
            <div class="lg:col-span-2">
                <label for="cari" class="mb-1 block text-xs font-medium text-slate-500">Cari</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </span>
                    <input type="text" id="cari" name="cari" value="{{ $filter['cari'] ?? '' }}"
                           placeholder="Cari tabel atau nama pengguna…"
                           class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm placeholder-slate-400
                                  focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                </div>
            </div>

            {{-- Filter Aksi --}}
            <div>
                <label for="aksi" class="mb-1 block text-xs font-medium text-slate-500">Aksi</label>
                <select id="aksi" name="aksi"
                        class="w-full rounded-lg border border-slate-200 py-2 px-3 text-sm text-slate-700
                               focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                    <option value="">Semua Aksi</option>
                    @foreach ($daftarAksi as $aksi)
                        <option value="{{ $aksi }}" @selected(($filter['aksi'] ?? '') === $aksi)>
                            {{ $aksi }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filter Pengguna --}}
            <div>
                <label for="pengguna_id" class="mb-1 block text-xs font-medium text-slate-500">Pengguna</label>
                <select id="pengguna_id" name="pengguna_id"
                        class="w-full rounded-lg border border-slate-200 py-2 px-3 text-sm text-slate-700
                               focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                    <option value="">Semua Pengguna</option>
                    @foreach ($daftarPengguna as $pgn)
                        <option value="{{ $pgn->id }}" @selected(($filter['pengguna_id'] ?? '') == $pgn->id)>
                            {{ $pgn->nama_lengkap }} ({{ $pgn->nomor_induk }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Dari Tanggal --}}
            <div>
                <label for="dari_tanggal" class="mb-1 block text-xs font-medium text-slate-500">Dari Tanggal</label>
                <input type="date" id="dari_tanggal" name="dari_tanggal"
                       value="{{ $filter['dari_tanggal'] ?? '' }}"
                       class="w-full rounded-lg border border-slate-200 py-2 px-3 text-sm text-slate-700
                              focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
            </div>

            {{-- Sampai Tanggal --}}
            <div>
                <label for="sampai_tanggal" class="mb-1 block text-xs font-medium text-slate-500">Sampai Tanggal</label>
                <input type="date" id="sampai_tanggal" name="sampai_tanggal"
                       value="{{ $filter['sampai_tanggal'] ?? '' }}"
                       class="w-full rounded-lg border border-slate-200 py-2 px-3 text-sm text-slate-700
                              focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
            </div>
        </div>

        {{-- Tombol Filter --}}
        <div class="mt-3 flex items-center gap-2">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-4 py-2 text-xs font-semibold text-white
                           transition-colors hover:bg-brand/90">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/>
                </svg>
                Terapkan Filter
            </button>
            <a href="{{ route('admin.log-aktivitas.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-4 py-2 text-xs font-medium text-slate-600
                      transition-colors hover:bg-slate-50">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
                Reset
            </a>
        </div>
    </form>

    {{-- ================================================================
         TABEL LOG AKTIVITAS
         ================================================================ --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">

        @if ($daftarLog->isEmpty())
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                <p class="mt-3 text-sm text-slate-500">Belum ada log aktivitas tercatat.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="px-5 py-3 font-semibold text-slate-500">Waktu</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Pengguna</th>
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

                                {{-- Pengguna --}}
                                <td class="px-5 py-3">
                                    @if ($log->pengguna)
                                        <a href="{{ route('admin.log-aktivitas.detail', $log->pengguna->id) }}"
                                           class="font-medium text-brand hover:underline">
                                            {{ $log->pengguna->nama_lengkap }}
                                        </a>
                                        @if ($log->pengguna->trashed())
                                            <span class="ml-1 inline-block rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-600">
                                                Dihapus
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-slate-400 italic">Sistem</span>
                                    @endif
                                </td>

                                {{-- Aksi (badge berwarna) --}}
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

                                {{-- Detail (collapsible) --}}
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

@endsection
