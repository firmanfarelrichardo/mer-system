{{--
|--------------------------------------------------------------------------
| Komponen: Direktur Vitals (dashboard/direktur-vitals)
|--------------------------------------------------------------------------
| Fokus: Executive Dashboard - read-only, high-level.
| Menampilkan status kesehatan sistem, rata-rata waktu respons,
| ringkasan eksekutif, dan tren mingguan (sparkline text).
|
| Props:
|   $data - array ['status_kesehatan', 'high_sev_hari_ini',
|                   'rata_respons_jam', 'ringkasan', 'tren_mingguan']
|--------------------------------------------------------------------------
--}}

@props(['data'])

<div class="space-y-6">

    {{-- ================================================================
         SYSTEM HEALTH STATUS - indikator utama
         ================================================================ --}}
    @php
        $health = match($data['status_kesehatan']) {
            'kritis' => [
                'label'  => 'KRITIS',
                'desc'   => 'Terdapat ' . $data['high_sev_hari_ini'] . ' kasus KTD/Sentinel hari ini. Perlu perhatian segera.',
                'bg'     => 'bg-red-50 border-red-200',
                'icon'   => 'bg-red-100 text-red-600',
                'text'   => 'text-red-800',
                'sub'    => 'text-red-600',
                'dot'    => 'bg-red-500',
            ],
            'waspada' => [
                'label'  => 'WASPADA',
                'desc'   => 'Terdapat ' . $data['high_sev_hari_ini'] . ' kasus KTD/Sentinel hari ini. Pantau perkembangannya.',
                'bg'     => 'bg-amber-50 border-amber-200',
                'icon'   => 'bg-amber-100 text-amber-600',
                'text'   => 'text-amber-800',
                'sub'    => 'text-amber-600',
                'dot'    => 'bg-amber-500',
            ],
            default => [
                'label'  => 'AMAN',
                'desc'   => 'Tidak ada kasus KTD/Sentinel hari ini. Sistem berjalan normal.',
                'bg'     => 'bg-emerald-50 border-emerald-200',
                'icon'   => 'bg-emerald-100 text-emerald-600',
                'text'   => 'text-emerald-800',
                'sub'    => 'text-emerald-600',
                'dot'    => 'bg-emerald-500',
            ],
        };
    @endphp
    <div class="flex items-center gap-4 rounded-xl border {{ $health['bg'] }} p-5 shadow-sm">
        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl {{ $health['icon'] }}">
            <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
            </svg>
        </div>
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-block h-2.5 w-2.5 rounded-full {{ $health['dot'] }} animate-pulse"></span>
                <p class="text-lg font-bold {{ $health['text'] }}">Status Sistem: {{ $health['label'] }}</p>
            </div>
            <p class="mt-0.5 text-sm {{ $health['sub'] }}">{{ $health['desc'] }}</p>
        </div>
    </div>

    {{-- ================================================================
         EXECUTIVE METRICS - 4 kartu ringkas
         ================================================================ --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">

        {{-- Total Laporan --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 text-center shadow-sm">
            <p class="text-2xl font-bold text-slate-800">{{ number_format($data['ringkasan']['total']) }}</p>
            <p class="mt-1 text-xs font-medium text-slate-400">Total Laporan</p>
        </div>

        {{-- Kasus Baru --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 text-center shadow-sm">
            <p class="text-2xl font-bold text-amber-600">{{ $data['ringkasan']['kasus_baru'] }}</p>
            <p class="mt-1 text-xs font-medium text-slate-400">Kasus Baru</p>
        </div>

        {{-- Dalam Proses --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 text-center shadow-sm">
            <p class="text-2xl font-bold text-blue-600">{{ $data['ringkasan']['dalam_proses'] }}</p>
            <p class="mt-1 text-xs font-medium text-slate-400">Dalam Proses</p>
        </div>

        {{-- Selesai --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 text-center shadow-sm">
            <p class="text-2xl font-bold text-emerald-600">{{ $data['ringkasan']['selesai'] }}</p>
            <p class="mt-1 text-xs font-medium text-slate-400">Selesai</p>
        </div>

    </div>

    {{-- ================================================================
         RATA-RATA WAKTU RESPONS
         ================================================================ --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold text-slate-700">Rata-Rata Waktu Respons</p>
            <div class="mt-3 flex items-end gap-2">
                <p class="text-3xl font-extrabold text-slate-800">{{ $data['rata_respons_jam'] }}</p>
                <p class="pb-1 text-sm font-medium text-slate-400">jam</p>
            </div>
            <p class="mt-1 text-xs text-slate-400">
                Dari laporan masuk hingga tindak lanjut pertama.
            </p>
        </div>

        {{-- High Severity Hari Ini --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold text-slate-700">KTD / Sentinel Hari Ini</p>
            <div class="mt-3 flex items-end gap-2">
                <p class="text-3xl font-extrabold {{ $data['high_sev_hari_ini'] > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                    {{ $data['high_sev_hari_ini'] }}
                </p>
                <p class="pb-1 text-sm font-medium text-slate-400">kasus</p>
            </div>
            <p class="mt-1 text-xs text-slate-400">
                Kasus severity tinggi yang dilaporkan hari ini.
            </p>
        </div>

    </div>

    {{-- ================================================================
         TREN MINGGUAN - 7 hari terakhir (visual bar sederhana)
         ================================================================ --}}
    @php
        $maxTren   = $data['tren_mingguan']->max() ?: 1;
        $hariLabel = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    @endphp
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="mb-4 text-sm font-semibold text-slate-700">Laporan Masuk - 7 Hari Terakhir</p>
        <div class="flex items-end justify-between gap-2">
            @for ($i = 6; $i >= 0; $i--)
                @php
                    $tanggal = now()->subDays($i)->toDateString();
                    $jumlah  = $data['tren_mingguan'][$tanggal] ?? 0;
                    $tinggi  = $maxTren > 0 ? max(4, round(($jumlah / $maxTren) * 100)) : 4;
                    $hari    = $hariLabel[now()->subDays($i)->dayOfWeek];
                @endphp
                <div class="flex flex-1 flex-col items-center gap-1">
                    <span class="text-xs font-bold text-slate-700">{{ $jumlah }}</span>
                    <div class="w-full rounded-t-md bg-brand/80 transition-all duration-300"
                         style="height: {{ $tinggi }}px"></div>
                    <span class="text-[10px] text-slate-400">{{ $hari }}</span>
                </div>
            @endfor
        </div>
    </div>

</div>
