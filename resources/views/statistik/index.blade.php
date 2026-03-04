{{--
|--------------------------------------------------------------------------
| Halaman Statistik & Analisis  (statistik/index.blade.php)
|--------------------------------------------------------------------------
| Dashboard analitik insiden medication errors.
|
| Akses: Kepala Ruangan, Komite, Direktur
|
| RBAC — Visibilitas:
|   - Kepala Ruangan  → data unit kerjanya saja; filter unit tidak ditampilkan.
|   - Komite/Direktur → semua data; seluruh filter & grafik aktif.
|
| Variabel dari StatistikController::index():
|   (bool)   $bisaLihatSemua
|   (array)  $ringkasanAngka   [total, kasus_baru, selesai, ...]
|   (array)  $trenBulanan      [labels, data]
|   (array)  $distribusiStatus [labels, data, colors]
|   (array)  $distribusiTipe   [labels, data, colors]
|   (array)  $distribusiTahapan[labels, data, colors, persen]
|   (array)  $ringkasanPerUnit
|   (Collection) $daftarUnit   — kosong jika karu
|   (bool)   $filterAktif
|   (array)  $filterAktifData
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Statistik & Analisis — Sistem MER')

{{-- Chart.js CDN — dimuat SEKALI dari sini via @pushOnce. --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"
        crossorigin="anonymous"></script>
@endpush

@section('konten')

{{-- ================================================================
     HEADER HALAMAN
     ================================================================ --}}
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Statistik & Analisis</h1>
        <p class="mt-0.5 text-sm text-slate-400">
            {{ $pengguna->tenant?->nama ?? 'Sistem Pelaporan Kesalahan Pengobatan' }}
            @if (!$bisaLihatSemua)
                &nbsp;·&nbsp;Unit: <span class="font-medium text-slate-600">{{ $pengguna->unitKerja?->nama_unit ?? '—' }}</span>
            @endif
        </p>
    </div>
    <div class="flex items-center gap-3">

        {{-- Badge tanggal --}}
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs text-slate-500 shadow-sm">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25"/>
            </svg>
            <span>
                @if ($filterAktif && isset($filterAktifData['start_date']))
                    {{ \Carbon\Carbon::parse($filterAktifData['start_date'])->translatedFormat('d M Y') }}
                    @if (isset($filterAktifData['end_date']))
                        &nbsp;–&nbsp;{{ \Carbon\Carbon::parse($filterAktifData['end_date'])->translatedFormat('d M Y') }}
                    @endif
                @else
                    Data hingga {{ now()->translatedFormat('d F Y') }}
                @endif
            </span>
        </div>

        {{-- Komponen Filter Modal --}}
        <x-filter-dropdown
            :action="route('statistik.index')"
            title="Filter Data"
            :maxWidth="$bisaLihatSemua ? 'max-w-lg' : 'max-w-md'">

            {{-- Tanggal Mulai --}}
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Tanggal Mulai</label>
                <input type="date" name="start_date"
                       value="{{ request('start_date', $filterAktifData['start_date'] ?? '') }}"
                       class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                              focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
            </div>

            {{-- Tanggal Selesai --}}
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Tanggal Selesai</label>
                <input type="date" name="end_date"
                       value="{{ request('end_date', $filterAktifData['end_date'] ?? '') }}"
                       class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                              focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
            </div>

            {{-- Unit Kerja — hanya direktur & komite --}}
            @if ($bisaLihatSemua)
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Unit Kerja</label>
                    <select name="unit_kerja"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700
                                   focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                        <option value="">— Semua Unit —</option>
                        @foreach ($daftarUnit as $unit)
                            <option value="{{ $unit }}"
                                    @selected(request('unit_kerja', $filterAktifData['unit_kerja'] ?? '') === $unit)>
                                {{ $unit }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Tipe Insiden --}}
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Tipe Insiden</label>
                <select name="tipe_insiden"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700
                               focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                    <option value="">— Semua Tipe —</option>
                    @foreach ([
                        'KPC'      => 'KPC – Potensial Cedera',
                        'KNC'      => 'KNC – Nyaris Cedera',
                        'KTC'      => 'KTC – Tidak Cedera',
                        'KTD'      => 'KTD – Tidak Diharapkan',
                        'SENTINEL' => 'Sentinel',
                    ] as $kode => $label)
                        <option value="{{ $kode }}"
                                @selected(request('tipe_insiden', $filterAktifData['tipe_insiden'] ?? '') === $kode)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

        </x-filter-dropdown>

        {{-- Tombol Reset — tampil jika ada filter aktif --}}
        @if ($filterAktif)
            <a href="{{ route('statistik.index') }}"
               class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm
                      font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </a>
        @endif

    </div>
</div>

{{-- ================================================================
     SUMMARY CARDS
     Akses: semua peran
     ================================================================ --}}
<div class="mb-8">
    <x-charts.summary-cards :ringkasanAngka="$ringkasanAngka" :bisaLihatSemua="$bisaLihatSemua" />
</div>

{{-- ================================================================
     BARIS GRAFIK 1: Tren Bulanan + Distribusi Status
     Akses: semua peran
     ================================================================ --}}
<div class="mb-8 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <x-charts.tren-insiden :trenBulanan="$trenBulanan" />
    </div>
    <div>
        <x-charts.status-pie :distribusiStatus="$distribusiStatus" />
    </div>
</div>

{{-- ================================================================
     BARIS GRAFIK 2: Severity Bar + Distribusi per Tahapan
     Akses: semua peran
     ================================================================ --}}
<div class="mb-8 grid gap-6 lg:grid-cols-2">
    <x-charts.severity-bar :distribusiTipe="$distribusiTipe" />

    {{-- Distribusi per Tahapan (progress bar, tanpa Chart.js) --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5">
            <h2 class="text-base font-semibold text-slate-800">Kesalahan Berdasarkan Tahapan</h2>
            <p class="text-xs text-slate-400">Distribusi insiden menurut fase proses obat</p>
        </div>

        @php
            $maxTahap = max(array_merge($distribusiTahapan['data'], [1]));
            $dotClass = ['bg-blue-700', 'bg-blue-600', 'bg-blue-500', 'bg-blue-400'];
            $badgeClass = [
                'bg-blue-700 text-white', 'bg-blue-600 text-white',
                'bg-blue-500 text-white', 'bg-blue-400 text-blue-900',
            ];
            $barClass = ['bg-blue-700', 'bg-blue-600', 'bg-blue-500', 'bg-blue-400'];
        @endphp

        <div class="space-y-4">
            @foreach ($distribusiTahapan['labels'] as $i => $label)
                @php
                    $jumlah  = $distribusiTahapan['data'][$i];
                    $persen  = $distribusiTahapan['persen'][$i];
                    $barW    = $maxTahap > 0 ? ($jumlah / $maxTahap) * 100 : 0;
                @endphp
                <div>
                    <div class="mb-1.5 flex items-center justify-between gap-2">
                        <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-bold {{ $badgeClass[$i % 4] }}">
                            {{ $jumlah }} · {{ $persen }}%
                        </span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full {{ $barClass[$i % 4] }} transition-all duration-500"
                             style="width: {{ $barW }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ================================================================
     TABEL RINGKASAN PER UNIT KERJA
     RBAC: HANYA direktur & komite
     ================================================================ --}}
@if ($bisaLihatSemua)
<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-5 flex items-start justify-between">
        <div>
            <h2 class="text-base font-semibold text-slate-800">Ringkasan per Unit Kerja</h2>
            <p class="text-xs text-slate-400">
                @if ($bisaLihatSemua)
                    Data insiden dikelompokkan per unit (tampil maks. 20 unit)
                @else
                    Data insiden di unit kerja Anda
                @endif
            </p>
        </div>
    </div>

    @if (!empty($ringkasanPerUnit))
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <th class="pb-3 pr-4">Unit Kerja</th>
                        <th class="px-3 pb-3 text-center">Total</th>
                        <th class="px-2 pb-3 text-center">KPC</th>
                        <th class="px-2 pb-3 text-center">KNC</th>
                        <th class="px-2 pb-3 text-center">KTC</th>
                        <th class="px-2 pb-3 text-center">KTD</th>
                        <th class="px-2 pb-3 text-center">SNT</th>
                        <th class="pl-3 pb-3 text-center">Selesai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($ringkasanPerUnit as $unit)
                        <tr class="group transition-colors hover:bg-slate-50/50">
                            <td class="py-3 pr-4 font-medium text-slate-700">{{ $unit['unit'] }}</td>
                            <td class="px-3 py-3 text-center font-semibold text-slate-800">
                                {{ $unit['total'] > 0 ? $unit['total'] : '—' }}
                            </td>
                            <td class="px-2 py-3 text-center">
                                @if ($unit['kpc'] > 0)
                                    <span class="inline-flex min-w-[22px] justify-center rounded-full bg-blue-100 px-1.5 py-0.5 text-xs font-bold text-blue-700">{{ $unit['kpc'] }}</span>
                                @else
                                    <span class="text-slate-300">–</span>
                                @endif
                            </td>
                            <td class="px-2 py-3 text-center">
                                @if ($unit['knc'] > 0)
                                    <span class="inline-flex min-w-[22px] justify-center rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-bold text-amber-700">{{ $unit['knc'] }}</span>
                                @else
                                    <span class="text-slate-300">–</span>
                                @endif
                            </td>
                            <td class="px-2 py-3 text-center">
                                @if ($unit['ktc'] > 0)
                                    <span class="inline-flex min-w-[22px] justify-center rounded-full bg-orange-100 px-1.5 py-0.5 text-xs font-bold text-orange-700">{{ $unit['ktc'] }}</span>
                                @else
                                    <span class="text-slate-300">–</span>
                                @endif
                            </td>
                            <td class="px-2 py-3 text-center">
                                @if ($unit['ktd'] > 0)
                                    <span class="inline-flex min-w-[22px] justify-center rounded-full bg-red-100 px-1.5 py-0.5 text-xs font-bold text-red-700">{{ $unit['ktd'] }}</span>
                                @else
                                    <span class="text-slate-300">–</span>
                                @endif
                            </td>
                            <td class="px-2 py-3 text-center">
                                @if ($unit['sentinel'] > 0)
                                    <span class="inline-flex min-w-[22px] justify-center rounded-full bg-red-200 px-1.5 py-0.5 text-xs font-bold text-red-900">{{ $unit['sentinel'] }}</span>
                                @else
                                    <span class="text-slate-300">–</span>
                                @endif
                            </td>
                            <td class="pl-3 py-3 text-center">
                                @if ($unit['selesai'] > 0)
                                    <span class="inline-flex min-w-[22px] justify-center rounded-full bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-600">{{ $unit['selesai'] }}</span>
                                @else
                                    <span class="text-slate-300">–</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                @php
                    $tAll = array_sum(array_column($ringkasanPerUnit, 'total'));
                    $tKpc = array_sum(array_column($ringkasanPerUnit, 'kpc'));
                    $tKnc = array_sum(array_column($ringkasanPerUnit, 'knc'));
                    $tKtc = array_sum(array_column($ringkasanPerUnit, 'ktc'));
                    $tKtd = array_sum(array_column($ringkasanPerUnit, 'ktd'));
                    $tSnt = array_sum(array_column($ringkasanPerUnit, 'sentinel'));
                    $tSls = array_sum(array_column($ringkasanPerUnit, 'selesai'));
                @endphp
                <tfoot>
                    <tr class="border-t-2 border-slate-200 bg-slate-50/60 font-bold text-slate-800">
                        <td class="py-3 pr-4 text-sm">Total</td>
                        <td class="px-3 py-3 text-center text-sm">{{ $tAll }}</td>
                        <td class="px-2 py-3 text-center"><span class="inline-flex min-w-[22px] justify-center rounded-full bg-blue-500 px-1.5 py-0.5 text-xs font-bold text-white">{{ $tKpc }}</span></td>
                        <td class="px-2 py-3 text-center"><span class="inline-flex min-w-[22px] justify-center rounded-full bg-amber-500 px-1.5 py-0.5 text-xs font-bold text-white">{{ $tKnc }}</span></td>
                        <td class="px-2 py-3 text-center"><span class="inline-flex min-w-[22px] justify-center rounded-full bg-orange-500 px-1.5 py-0.5 text-xs font-bold text-white">{{ $tKtc }}</span></td>
                        <td class="px-2 py-3 text-center"><span class="inline-flex min-w-[22px] justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-xs font-bold text-white">{{ $tKtd }}</span></td>
                        <td class="px-2 py-3 text-center"><span class="inline-flex min-w-[22px] justify-center rounded-full bg-red-900 px-1.5 py-0.5 text-xs font-bold text-white">{{ $tSnt }}</span></td>
                        <td class="pl-3 py-3 text-center"><span class="inline-flex min-w-[22px] justify-center rounded-full bg-slate-500 px-1.5 py-0.5 text-xs font-bold text-white">{{ $tSls }}</span></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @else
        <div class="py-14 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3"/>
            </svg>
            <p class="mt-4 text-sm text-slate-400">Belum ada data insiden</p>
        </div>
    @endif
</div>
@endif

@endsection
