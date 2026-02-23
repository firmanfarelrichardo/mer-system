{{--
|--------------------------------------------------------------------------
| Log Aktivitas (admin/log-aktivitas/index.blade.php)
|--------------------------------------------------------------------------
| View reusable — digunakan oleh 2 route:
|   • admin.log-aktivitas.pengguna  → $tipePeran = 'pengguna'
|   • admin.log-aktivitas.admin     → $tipePeran = 'admin'
|
| Variabel dari Controller:
|   $daftarLog  — LengthAwarePaginator (eager: pengguna.peran withTrashed)
|   $filter     — ['dari_tanggal', 'sampai_tanggal', 'cari']
|   $tipePeran  — 'admin' | 'pengguna'
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@php
    $isAdmin   = $tipePeran === 'admin';
    $judul     = $isAdmin ? 'Log Aktivitas Admin' : 'Log Aktivitas Pengguna';
    $subjudul  = $isAdmin
        ? 'Rekam jejak aktivitas IT Admin di sistem.'
        : 'Rekam jejak aktivitas seluruh pengguna non-admin.';
    $routeName = $isAdmin ? 'admin.log-aktivitas.admin' : 'admin.log-aktivitas.pengguna';
    $hariIni   = now()->format('Y-m-d');

    // IP visibility: hanya role yang ada di config/audit.php yang boleh lihat IP penuh
    $roleAdmin    = auth()->user()?->peran->pluck('nama_peran')->toArray() ?? [];
    $bolehLihatIp = count(array_intersect($roleAdmin, config('audit.roles_boleh_lihat_ip', []))) > 0;
@endphp

@section('judul', $judul . ' — Sistem MER')

@section('konten')

    {{-- ================================================================
         HEADER + FILTER
         ================================================================ --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">{{ $judul }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $subjudul }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- Quick Filter: Hari Ini --}}
            <a href="{{ route($routeName, ['dari_tanggal' => $hariIni, 'sampai_tanggal' => $hariIni]) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-brand/30 bg-brand/5 px-4 py-2.5 text-xs font-semibold text-brand
                      transition-colors hover:bg-brand/10">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                </svg>
                Hari Ini
            </a>

            {{-- Reusable Filter Component --}}
            <x-filter-dropdown :action="route($routeName)">
                {{-- Pencarian --}}
                <div>
                    <label for="cari" class="mb-1 block text-xs font-medium text-slate-500">Cari</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                            </svg>
                        </span>
                        <input type="text" id="cari" name="cari" value="{{ $filter['cari'] ?? '' }}"
                               placeholder="Cari aktivitas atau nama pengguna…"
                               class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm placeholder-slate-400
                                      focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                    </div>
                </div>

                {{-- Tanggal --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="dari_tanggal" class="mb-1 block text-xs font-medium text-slate-500">Dari Tanggal</label>
                        <input type="date" id="dari_tanggal" name="dari_tanggal"
                               value="{{ $filter['dari_tanggal'] ?? '' }}"
                               class="w-full rounded-lg border border-slate-200 py-2 px-3 text-sm text-slate-700
                                      focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                    </div>
                    <div>
                        <label for="sampai_tanggal" class="mb-1 block text-xs font-medium text-slate-500">Sampai Tanggal</label>
                        <input type="date" id="sampai_tanggal" name="sampai_tanggal"
                               value="{{ $filter['sampai_tanggal'] ?? '' }}"
                               class="w-full rounded-lg border border-slate-200 py-2 px-3 text-sm text-slate-700
                                      focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                    </div>
                </div>
            </x-filter-dropdown>
        </div>
    </div>

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
                            <th class="px-5 py-3 font-semibold text-slate-500">Aktivitas</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Alamat IP</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($daftarLog as $log)
                            <tr class="transition-colors hover:bg-slate-50/50">

                                {{-- Waktu --}}
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <p class="text-xs font-medium text-slate-700">
                                        {{ $log->created_at->format('d/m/Y') }}
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        {{ $log->created_at->format('H:i:s') }}
                                    </p>
                                </td>

                                {{-- Pengguna + Peran --}}
                                <td class="px-5 py-3">
                                    @if ($log->pengguna)
                                        <p class="text-sm font-medium text-slate-800">
                                            {{ $log->pengguna->nama_lengkap }}
                                        </p>
                                        {{-- Badge peran --}}
                                        <div class="mt-0.5 flex flex-wrap gap-1">
                                            @forelse ($log->pengguna->peran as $peran)
                                                <span class="inline-block rounded-full bg-brand/10 px-2 py-0.5 text-[10px] font-semibold text-brand">
                                                    {{ $peran->nama_display }}
                                                </span>
                                            @empty
                                                <span class="text-[10px] italic text-slate-400">Tanpa peran</span>
                                            @endforelse
                                        </div>
                                        @if ($log->pengguna->trashed())
                                            <span class="mt-0.5 inline-block rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-600">
                                                Akun Dihapus
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-sm italic text-slate-400">Sistem</span>
                                    @endif
                                </td>

                                {{-- Aktivitas (human-readable) --}}
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
                                    <span class="inline-block rounded-full px-2.5 py-0.5 text-[10px] font-semibold {{ $warnaBadge }}">
                                        {{ $log->aksi }}
                                    </span>
                                    <p class="mt-1 text-xs text-slate-600">
                                        {{ $log->deskripsi_lengkap }}
                                    </p>
                                </td>

                                {{-- Alamat IP (dengan masking jika tidak punya akses) --}}
                                <td class="px-5 py-3 whitespace-nowrap">
                                    @if ($log->alamat_ip)
                                        @if ($bolehLihatIp)
                                            <span class="font-mono text-xs text-slate-500">
                                                {{ $log->alamat_ip }}
                                            </span>
                                        @else
                                            {{-- Masking: tampilkan 2 oktet pertama saja → "125.160.x.x" --}}
                                            @php
                                                $bagianIp = explode('.', $log->alamat_ip);
                                                $ipSamar  = count($bagianIp) >= 2
                                                    ? $bagianIp[0] . '.' . $bagianIp[1] . '.x.x'
                                                    : '—';
                                            @endphp
                                            <span class="font-mono text-xs text-slate-400" title="IP disamarkan">
                                                {{ $ipSamar }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-xs text-slate-300">—</span>
                                    @endif
                                </td>

                                {{-- Detail (collapsible: user_agent + data_lama/baru) --}}
                                <td class="px-5 py-3">
                                    @if ($log->user_agent || $log->data_lama || $log->data_baru)
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
                                                @if ($log->user_agent)
                                                    <div>
                                                        <p class="text-[10px] font-semibold uppercase text-slate-400">Browser / Device</p>
                                                        <p class="mt-0.5 break-all rounded bg-slate-50 p-2 text-[11px] leading-relaxed text-slate-600">
                                                            {{ $log->user_agent }}
                                                        </p>
                                                    </div>
                                                @endif
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
