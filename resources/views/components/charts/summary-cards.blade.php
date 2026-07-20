{{--
|--------------------------------------------------------------------------
| Komponen: x-charts.summary-cards
|--------------------------------------------------------------------------
| Menampilkan 4 kartu ringkasan angka utama.
|
| Props:
|   $ringkasanAngka - array dari StatistikController::hitungRingkasanAngka()
|   $bisaLihatSemua - bool, true jika direktur/komite
|
| Akses: Semua peran (Kepala Ruangan, Komite, Direktur)
|--------------------------------------------------------------------------
--}}
@props(['ringkasanAngka', 'bisaLihatSemua' => false])

@php
    $kartu = [
        [
            'label'   => 'Total Insiden',
            'nilai'   => $ringkasanAngka['total'],
            'satuan'  => 'laporan',
            'warna'   => 'brand',
            'bg'      => 'bg-brand/10',
            'text'    => 'text-brand',
            'ikon'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>',
        ],
        [
            'label'   => 'Kasus Baru',
            'nilai'   => $ringkasanAngka['kasus_baru'],
            'satuan'  => 'belum ditangani',
            'warna'   => 'red',
            'bg'      => 'bg-red-100',
            'text'    => 'text-red-600',
            'ikon'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>',
        ],
        [
            'label'   => 'Selesai',
            'nilai'   => $ringkasanAngka['selesai'],
            'satuan'  => 'ditangani',
            'warna'   => 'emerald',
            'bg'      => 'bg-emerald-100',
            'text'    => 'text-emerald-600',
            'ikon'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>',
        ],
        [
            'label'   => 'Bulan Ini',
            'nilai'   => $ringkasanAngka['insiden_bulan_ini'],
            'satuan'  => now()->translatedFormat('F Y'),
            'warna'   => 'amber',
            'bg'      => 'bg-amber-100',
            'text'    => 'text-amber-600',
            'ikon'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"/>',
        ],
    ];
@endphp

<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    @foreach ($kartu as $k)
        <div class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md">
            {{-- Dekorasi latar melingkar --}}
            <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full {{ $k['bg'] }} opacity-60"></div>

            <div class="relative">
                {{-- Ikon --}}
                <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl {{ $k['bg'] }} {{ $k['text'] }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        {!! $k['ikon'] !!}
                    </svg>
                </div>

                {{-- Angka utama --}}
                <p class="text-3xl font-bold text-slate-800">{{ number_format((int) $k['nilai']) }}</p>

                {{-- Label --}}
                <p class="mt-0.5 text-sm font-medium text-slate-600">{{ $k['label'] }}</p>
                <p class="text-xs text-slate-400">{{ $k['satuan'] }}</p>
            </div>
        </div>
    @endforeach
</div>

{{-- Baris kedua: angka turunan --}}
<div class="mt-4 grid grid-cols-2 gap-4">
    <div class="flex items-center gap-3 rounded-xl border border-slate-100 bg-white px-5 py-3 shadow-sm">
        <span class="text-sm text-slate-500">Rata-rata per Bulan</span>
        <span class="ml-auto text-lg font-bold text-slate-700">{{ $ringkasanAngka['rata_rata_per_bulan'] }}</span>
    </div>
    <div class="flex items-center gap-3 rounded-xl border border-slate-100 bg-white px-5 py-3 shadow-sm">
        <span class="text-sm text-slate-500">Tingkat Penyelesaian</span>
        <span class="ml-auto text-lg font-bold text-emerald-600">{{ $ringkasanAngka['tingkat_penyelesaian'] }}%</span>
    </div>
</div>
