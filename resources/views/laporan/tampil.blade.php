{{--
|--------------------------------------------------------------------------
| Detail Laporan Insiden (laporan/tampil.blade.php)
|--------------------------------------------------------------------------
| Halaman detail (read-only) untuk melihat satu laporan insiden.
| Menampilkan semua informasi: pelapor, pasien, kronologi, klasifikasi,
| tindakan segera, dan status terkini.
|
| Data saat ini menggunakan dummy statis — nanti akan diganti dengan
| data dari model PelaporanInsiden via controller.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Detail Laporan — Sistem MER')

@section('konten')

    @php
        // Data dummy — nanti akan diganti $laporan dari controller
        $laporan = [
            'id_insiden'         => request()->segment(2, 'INC-2026-0045'),
            'status'             => 'Kasus Baru',
            'status_warna'       => 'bg-amber-100 text-amber-700',
            'jenis_insiden'      => 'KTD',
            'jenis_warna'        => 'bg-red-100 text-red-700',
            'tanggal_lapor'      => '18 Februari 2026, 14:32 WIB',
            'pelapor_nama'       => Auth::user()->nama_lengkap,
            'pelapor_nip'        => Auth::user()->nomor_induk,
            'pelapor_unit'       => Auth::user()->unitKerja?->nama_unit ?? '—',
            'nama_pasien'        => 'Tn. Ahmad Hidayat',
            'no_rekam_medis'     => 'RM-001234',
            'ruangan'            => 'ICU Bed 3',
            'umur_pasien'        => '58 Tahun',
            'tanggal_kejadian'   => '18 Februari 2026',
            'waktu_kejadian'     => '08:30 WIB',
            'lokasi_kejadian'    => 'Ruang ICU Bed 3',
            'kategori_kesalahan' => 'Salah Dosis',
            'nama_obat'          => 'Heparin 25.000 IU/5ml',
            'dampak_insiden'     => 'Cedera Ringan',
            'kronologi'          => 'Pasien seharusnya menerima Heparin 5.000 IU subkutan, namun diberikan 10.000 IU secara intravena. Kesalahan terdeteksi saat verifikasi obat oleh perawat shift berikutnya pada pukul 14:00 WIB. Pasien mengalami perpanjangan waktu perdarahan yang terpantau pada hasil lab APTT.',
            'tindakan_segera'    => 'Pemberian Heparin dihentikan segera. Dilakukan pemeriksaan APTT ulang. Dokter jaga dihubungi dan memberikan instruksi observasi ketat. Pasien stabil setelah 6 jam observasi.',
        ];
    @endphp

    {{-- ================================================================
         HEADER HALAMAN
         ================================================================ --}}
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-800">{{ $laporan['id_insiden'] }}</h1>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $laporan['status_warna'] }}">
                    {{ $laporan['status'] }}
                </span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $laporan['jenis_warna'] }}">
                    {{ $laporan['jenis_insiden'] }}
                </span>
            </div>
            <p class="mt-1 text-sm text-slate-400">Dilaporkan pada {{ $laporan['tanggal_lapor'] }}</p>
        </div>
        <a href="{{ route('laporan.index') }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white
                  px-4 py-2 text-sm font-medium text-slate-600 shadow-sm transition-colors
                  hover:bg-slate-50">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                 viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            Kembali
        </a>
    </div>

    <div class="space-y-6">

        {{-- ============================================================
             INFORMASI PELAPOR
             ============================================================ --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                <svg class="h-5 w-5 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501
                             20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676
                             0-5.216-.584-7.499-1.632Z" />
                </svg>
                Informasi Pelapor
            </h2>
            <div class="grid grid-cols-1 gap-y-3 sm:grid-cols-3 sm:gap-x-6">
                <div>
                    <p class="text-xs font-medium text-slate-400">Nama Pelapor</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $laporan['pelapor_nama'] }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-400">Nomor Induk</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $laporan['pelapor_nip'] }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-400">Unit Kerja</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $laporan['pelapor_unit'] }}</p>
                </div>
            </div>
        </div>

        {{-- ============================================================
             DATA PASIEN
             ============================================================ --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                <svg class="h-5 w-5 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0
                             2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25
                             2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75
                             0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789
                             6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                </svg>
                Data Pasien
            </h2>
            <div class="grid grid-cols-1 gap-y-3 sm:grid-cols-2 lg:grid-cols-4 sm:gap-x-6">
                <div>
                    <p class="text-xs font-medium text-slate-400">Nama Pasien</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $laporan['nama_pasien'] }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-400">No. Rekam Medis</p>
                    <p class="mt-0.5 font-mono text-sm font-medium text-slate-800">{{ $laporan['no_rekam_medis'] }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-400">Ruangan</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $laporan['ruangan'] }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-400">Umur</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $laporan['umur_pasien'] }}</p>
                </div>
            </div>
        </div>

        {{-- ============================================================
             DETAIL KEJADIAN
             ============================================================ --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                <svg class="h-5 w-5 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
                Detail Kejadian
            </h2>
            <div class="grid grid-cols-1 gap-y-3 sm:grid-cols-3 sm:gap-x-6">
                <div>
                    <p class="text-xs font-medium text-slate-400">Tanggal Kejadian</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $laporan['tanggal_kejadian'] }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-400">Waktu Kejadian</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $laporan['waktu_kejadian'] }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-400">Lokasi Kejadian</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $laporan['lokasi_kejadian'] }}</p>
                </div>
            </div>
            <div class="mt-4">
                <p class="text-xs font-medium text-slate-400">Kronologi Kejadian</p>
                <p class="mt-1 text-sm leading-relaxed text-slate-700">{{ $laporan['kronologi'] }}</p>
            </div>
        </div>

        {{-- ============================================================
             KLASIFIKASI INSIDEN
             ============================================================ --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                <svg class="h-5 w-5 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659
                             1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0
                             5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0
                             0 0 9.568 3Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                </svg>
                Klasifikasi Insiden
            </h2>
            <div class="grid grid-cols-1 gap-y-3 sm:grid-cols-2 lg:grid-cols-4 sm:gap-x-6">
                <div>
                    <p class="text-xs font-medium text-slate-400">Jenis Insiden</p>
                    <div class="mt-1">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $laporan['jenis_warna'] }}">
                            {{ $laporan['jenis_insiden'] }}
                        </span>
                    </div>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-400">Kategori Kesalahan</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $laporan['kategori_kesalahan'] }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-400">Nama Obat</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $laporan['nama_obat'] }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-400">Dampak pada Pasien</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $laporan['dampak_insiden'] }}</p>
                </div>
            </div>
        </div>

        {{-- ============================================================
             TINDAKAN SEGERA
             ============================================================ --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                <svg class="h-5 w-5 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42
                             15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655
                             5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164
                             1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004
                             3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.049
                             .58.025 1.193-.14 1.743" />
                </svg>
                Tindakan Segera yang Dilakukan
            </h2>
            <p class="text-sm leading-relaxed text-slate-700">{{ $laporan['tindakan_segera'] }}</p>
        </div>

    </div>

@endsection
