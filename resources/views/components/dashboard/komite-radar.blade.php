{{--
|--------------------------------------------------------------------------
| Komponen: Komite Radar (dashboard/komite-radar)
|--------------------------------------------------------------------------
| Fokus: Radar & Bottleneck Detection — seluruh RS.
| Menampilkan alert severity tinggi, unit bottleneck, rasio resolusi harian,
| dan total kasus belum selesai.
|
| Props:
|   $data — array ['alert_severitas', 'bottleneck', 'masuk_hari_ini',
|                   'selesai_hari_ini', 'belum_selesai']
|--------------------------------------------------------------------------
--}}

@props(['data'])

<div class="space-y-6">

    {{-- ================================================================
         RADAR METRICS — 3 kartu atas
         ================================================================ --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

        {{-- Belum Selesai (seluruh RS) --}}
        <div class="flex flex-col items-center justify-center rounded-xl border-2 border-amber-300 bg-amber-50 p-5 shadow-sm">
            <p class="text-4xl font-extrabold text-amber-700">{{ $data['belum_selesai'] }}</p>
            <p class="mt-1 text-xs font-semibold text-amber-600">Kasus Aktif Seluruh RS</p>
        </div>

        {{-- Masuk Hari Ini --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 text-center shadow-sm">
            <p class="text-2xl font-bold text-slate-800">{{ $data['masuk_hari_ini'] }}</p>
            <p class="mt-1 text-xs font-medium text-slate-400">Masuk Hari Ini</p>
        </div>

        {{-- Rasio Resolusi Hari Ini --}}
        @php
            $rasio = $data['masuk_hari_ini'] > 0
                ? round(($data['selesai_hari_ini'] / $data['masuk_hari_ini']) * 100)
                : ($data['selesai_hari_ini'] > 0 ? 100 : 0);
            $warnaRasio = $rasio >= 70 ? 'text-emerald-600' : ($rasio >= 40 ? 'text-amber-600' : 'text-red-600');
        @endphp
        <div class="rounded-xl border border-slate-200 bg-white p-5 text-center shadow-sm">
            <p class="text-2xl font-bold {{ $warnaRasio }}">{{ $rasio }}%</p>
            <p class="mt-1 text-xs font-medium text-slate-400">Resolusi Hari Ini</p>
        </div>

    </div>

    {{-- ================================================================
         ALERT SEVERITY TINGGI — KTD & Sentinel yang belum selesai
         ================================================================ --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center gap-2">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100">
                <svg class="h-4 w-4 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <p class="text-sm font-semibold text-slate-700">Alert Severity Tinggi</p>
        </div>

        @forelse ($data['alert_severitas'] as $insiden)
            <div class="flex items-center justify-between gap-3 {{ !$loop->last ? 'mb-3 border-b border-slate-100 pb-3' : '' }}">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold
                            {{ $insiden->tipe_insiden === 'SENTINEL' ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700' }}">
                            {{ $insiden->tipe_insiden }}
                        </span>
                        <span class="text-sm font-medium text-slate-700">{{ $insiden->nomor_laporan }}</span>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-400">
                        {{ $insiden->nama_unit_kerja ?? '—' }}
                        &nbsp;·&nbsp;
                        {{ $insiden->tgl_lapor?->diffForHumans() ?? '—' }}
                    </p>
                </div>
                <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                    {{ match($insiden->status_saat_ini) {
                        'kasus_baru' => 'bg-amber-100 text-amber-700',
                        'investigasi' => 'bg-blue-100 text-blue-700',
                        'tindak_lanjut' => 'bg-violet-100 text-violet-700',
                        default => 'bg-slate-100 text-slate-600',
                    } }}">
                    {{ $insiden->labelStatus() }}
                </span>
            </div>
        @empty
            <div class="flex flex-col items-center py-4">
                <svg class="h-10 w-10 text-emerald-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
                <p class="mt-2 text-sm text-emerald-600 font-medium">Tidak ada alert severity tinggi saat ini.</p>
            </div>
        @endforelse
    </div>

    {{-- ================================================================
         UNIT PENUMPUKAN — unit dengan kasus_baru terbanyak
         ================================================================ --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center gap-2">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100">
                <svg class="h-4 w-4 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <p class="text-sm font-semibold text-slate-700">Unit dengan Laporan Paling Lama Menunggu</p>
        </div>

        @forelse ($data['bottleneck'] as $unit)
            @php
                $hariBerselang = $unit->tertua ? \Illuminate\Support\Carbon::parse($unit->tertua)->diffInDays(now()) : 0;
            @endphp
            <div class="flex items-center justify-between gap-3 {{ !$loop->last ? 'mb-3 border-b border-slate-100 pb-3' : '' }}">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-700">{{ $unit->nama_unit_kerja ?? '—' }}</p>
                    <p class="mt-0.5 text-xs text-slate-400">
                        Kasus tertua: {{ $hariBerselang }} hari lalu
                    </p>
                </div>
                <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-700">
                    {{ $unit->total }}
                    <span class="font-normal">menunggu</span>
                </span>
            </div>
        @empty
            <p class="text-sm text-slate-400">Semua unit sudah merespons. Tidak ada laporan yang tertunda.</p>
        @endforelse
    </div>

</div>
