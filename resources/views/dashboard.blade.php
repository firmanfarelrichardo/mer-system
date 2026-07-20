{{--
|--------------------------------------------------------------------------
| Dashboard - Morning Briefing / Action Center
|--------------------------------------------------------------------------
| SATU view untuk SEMUA peran. Konten di-render secara kondisional
| menggunakan Blade Components terpisah per peran:
|
|   Nakes          → x-dashboard.nakes-briefing
|   Kepala Ruangan → x-dashboard.karu-alerts
|   Komite         → x-dashboard.komite-radar
|   Direktur       → x-dashboard.direktur-vitals
|
| Data dari DashboardController sudah di-scope per peran & tenant.
|
| Variabel dari controller:
|   $pengguna  - App\Models\Pengguna (auth user)
|   $nakes     - array|null (hanya untuk Nakes)
|   $karu      - array|null (hanya untuk Karu)
|   $komite    - array|null (hanya untuk Komite)
|   $direktur  - array|null (hanya untuk Direktur)
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Dashboard - Sistem MER')

@section('konten')

    @php
        $namaLengkap    = $pengguna->nama_lengkap;
        $peranUtama     = $pengguna->daftarPeran()[0] ?? '-';
        $namaUnit       = $pengguna->unitKerja?->nama_unit ?? null;
        $loginTerakhir  = $pengguna->terakhir_login_pada?->diffForHumans() ?? '-';
    @endphp

    {{-- ================================================================
         HEADER SAPAAN - sama untuk semua peran
         ================================================================ --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">
            Selamat datang, {{ $namaLengkap }} 👋
        </h1>
        <p class="mt-1 text-sm text-slate-400">
            {{ $peranUtama }}{{ $namaUnit ? " - {$namaUnit}" : '' }}
            &nbsp;·&nbsp;
            Login terakhir: {{ $loginTerakhir }}
        </p>
    </div>

    {{-- ================================================================
         KONTEN SPESIFIK PERAN - delegasi ke Blade Components.
         Urutan render sesuai hierarki peran di DashboardController.
         ================================================================ --}}

    @if (isset($direktur))
        <x-dashboard.direktur-vitals :data="$direktur" />

    @elseif (isset($komite))
        <x-dashboard.komite-radar :data="$komite" />

    @elseif (isset($karu))
        <x-dashboard.karu-alerts :data="$karu" :pengguna="$pengguna" />

    @elseif (isset($nakes))
        <x-dashboard.nakes-briefing :data="$nakes" :pengguna="$pengguna" />

    @else
        {{-- Fallback (Admin atau peran tak terdefinisi) - pesan navigasi --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-400">
            Tidak ada ringkasan khusus untuk peran Anda. Gunakan menu sidebar untuk navigasi.
        </div>
    @endif

@endsection
