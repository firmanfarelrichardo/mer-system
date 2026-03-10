{{--
|--------------------------------------------------------------------------
| Komponen: Nakes Briefing (dashboard/nakes-briefing)
|--------------------------------------------------------------------------
| Fokus: Reassurance & Edukasi.
| Menampilkan rekap laporan pribadi bulan ini dan kutipan patient safety.
|
| Props:
|   $data      — array ['total_bulan_ini', 'selesai_bulan_ini', 'dalam_proses']
|   $pengguna  — App\Models\Pengguna
|--------------------------------------------------------------------------
--}}

@props(['data', 'pengguna'])

<div class="space-y-6">

    {{-- ================================================================
         REKAP LAPORAN PRIBADI — Tracker visual ringkas
         ================================================================ --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

        {{-- Total Laporan Bulan Ini --}}
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand/10">
                <svg class="h-6 w-6 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $data['total_bulan_ini'] }}</p>
                <p class="text-xs font-medium text-slate-400">Laporan bulan ini</p>
            </div>
        </div>

        {{-- Dalam Proses --}}
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50">
                <svg class="h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $data['dalam_proses'] }}</p>
                <p class="text-xs font-medium text-slate-400">Sedang diproses</p>
            </div>
        </div>

        {{-- Selesai --}}
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50">
                <svg class="h-6 w-6 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $data['selesai_bulan_ini'] }}</p>
                <p class="text-xs font-medium text-slate-400">Telah selesai</p>
            </div>
        </div>

    </div>

    {{-- ================================================================
         PROGRESS BAR VISUAL — persentase penyelesaian bulan ini
         ================================================================ --}}
    @php
        $persen = $data['total_bulan_ini'] > 0
            ? round(($data['selesai_bulan_ini'] / $data['total_bulan_ini']) * 100)
            : 0;
    @endphp
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-2 flex items-center justify-between">
            <p class="text-sm font-semibold text-slate-700">Progres Penyelesaian Bulan Ini</p>
            <span class="text-sm font-bold text-brand">{{ $persen }}%</span>
        </div>
        <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-brand transition-all duration-500 ease-out"
                 style="width: {{ $persen }}%"></div>
        </div>
        <p class="mt-2 text-xs text-slate-400">
            {{ $data['selesai_bulan_ini'] }} dari {{ $data['total_bulan_ini'] }} laporan yang Anda buat sudah dituntaskan.
        </p>
    </div>

    {{-- ================================================================
         KUTIPAN KESELAMATAN PASIEN — edukasi & motivasi harian
         ================================================================ --}}
    @php
        // Kutipan diputar berdasarkan hari dalam bulan agar variasi harian.
        $kutipan = [
            'Melaporkan insiden bukan tentang menyalahkan, tetapi tentang belajar dan mencegah kejadian serupa. — WHO Patient Safety',
            'Setiap laporan insiden yang Anda kirim membantu membangun budaya keselamatan pasien di rumah sakit ini.',
            'Kesalahan adalah manusiawi, tetapi menyembunyikannya adalah pilihan. Terima kasih sudah berani melapor.',
            'Satu laporan kecil hari ini bisa mencegah satu insiden besar di masa depan.',
            'Pelaporan insiden adalah bentuk kepedulian tertinggi seorang tenaga medis/tenaga kesehatan terhadap keselamatan pasien.',
            'Budaya keselamatan dimulai dari transparansi. Anda adalah bagian penting dari perubahan ini.',
            'Setiap laporan yang Anda buat adalah investasi untuk sistem kesehatan yang lebih aman.',
        ];
        $kutipanHariIni = $kutipan[now()->day % count($kutipan)];
    @endphp
    <div class="rounded-xl border border-brand/20 bg-brand/5 p-5">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand/10">
                <svg class="h-5 w-5 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-brand">Kutipan Keselamatan Pasien</p>
                <p class="mt-1.5 text-sm italic leading-relaxed text-slate-600">"{{ $kutipanHariIni }}"</p>
            </div>
        </div>
    </div>

</div>
