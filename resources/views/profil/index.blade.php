{{--
|--------------------------------------------------------------------------
| Halaman Profil Saya (profil/index.blade.php)
|--------------------------------------------------------------------------
| Menampilkan informasi profil pengguna yang sedang login:
|   - Data pribadi (nama, NIP, email, telepon)
|   - Peran dan unit kerja
|   - Informasi akun (tanggal registrasi, login terakhir)
|
| Tersedia untuk semua peran yang terautentikasi.
| Menyesuaikan informasi berdasarkan peran pengguna.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Profil Saya - Sistem MER')

@section('konten')

    @php
        // $pengguna dikirim dari ProfilController
        $namaLengkap   = $pengguna->nama_lengkap;
        $daftarPeran   = $pengguna->daftarPeran();
        $peranUtama    = $daftarPeran[0] ?? '-';
        $namaUnit      = $pengguna->unitKerja?->nama_unit ?? '-';
        $loginTerakhir = $pengguna->terakhir_login_pada?->translatedFormat('d F Y, H:i') ?? '-';
        $terdaftarPada = $pengguna->created_at?->translatedFormat('d F Y') ?? '-';
        $jabatan       = $pengguna->jabatan ?? '-';
        $tglBergabung  = $pengguna->tanggal_bergabung_unit?->translatedFormat('d F Y') ?? '-';

        // Inisial untuk avatar besar
        $inisial = collect(explode(' ', $namaLengkap))
            ->map(fn (string $kata) => mb_strtoupper(mb_substr($kata, 0, 1)))
            ->take(2)
            ->implode('');
    @endphp

    {{-- ================================================================
         HEADER HALAMAN
         ================================================================ --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Profil Saya</h1>
            <p class="mt-1 text-sm text-slate-400">Informasi akun dan data pribadi Anda.</p>
        </div>
        <a href="{{ route('profil.edit') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white
                  hover:bg-brand/90 focus:outline-none focus:ring-2 focus:ring-brand/30 transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582
                         16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
            </svg>
            Edit Profil
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- ============================================================
             KARTU PROFIL (sidebar kiri)
             ============================================================ --}}
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col items-center text-center">
                    {{-- Avatar besar --}}
                    <div class="flex h-20 w-20 items-center justify-center rounded-full bg-brand text-2xl font-bold text-white">
                        {{ $inisial }}
                    </div>
                    <h2 class="mt-4 text-lg font-semibold text-slate-800">{{ $namaLengkap }}</h2>
                    @if ($jabatan !== '-')
                        <p class="mt-0.5 text-sm text-slate-400">{{ $jabatan }}</p>
                    @endif

                    {{-- Badge peran --}}
                    <div class="mt-3 flex flex-wrap justify-center gap-1.5">
                        @foreach ($daftarPeran as $peran)
                            <span class="inline-flex items-center rounded-full bg-brand/10 px-2.5 py-0.5
                                         text-xs font-semibold text-brand">
                                {{ $peran }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <div class="mt-6 space-y-3 border-t border-slate-100 pt-4">
                    <div class="flex items-center gap-3 text-sm">
                        <svg class="h-4 w-4 shrink-0 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                             viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75
                                     9.75h.75m-.75 3h.75m-.75 3h.75M3 3h18M3 21h18" />
                        </svg>
                        <span class="text-slate-600">{{ $namaUnit }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <svg class="h-4 w-4 shrink-0 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                             viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25
                                     0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25
                                     2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                        </svg>
                        <span class="font-mono text-slate-600">{{ $pengguna->nomor_induk }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <svg class="h-4 w-4 shrink-0 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                             viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25
                                     0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15
                                     a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07
                                     1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25
                                     2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                        <span class="text-slate-600">{{ $pengguna->email ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             DETAIL INFORMASI (area kanan)
             ============================================================ --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- Informasi Pribadi --}}
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-base font-semibold text-slate-800">Informasi Pribadi</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">Nama Lengkap</label>
                        <p class="text-sm font-medium text-slate-800">{{ $namaLengkap }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">NIP (Nomor Induk)</label>
                        <p class="font-mono text-sm font-medium text-slate-800">{{ $pengguna->nomor_induk }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">Username</label>
                        <p class="font-mono text-sm font-medium text-slate-800">{{ $pengguna->username ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">Email</label>
                        <p class="text-sm font-medium text-slate-800">{{ $pengguna->email ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">Nomor Telepon</label>
                        <p class="text-sm font-medium text-slate-800">{{ $pengguna->nomor_hp ?? '-' }}</p>
                    </div>
                </div>
            </div>

            {{-- Informasi Pekerjaan --}}
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-base font-semibold text-slate-800">Informasi Pekerjaan</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">Unit / Departemen</label>
                        <p class="text-sm font-medium text-slate-800">{{ $namaUnit }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">Jabatan</label>
                        <p class="text-sm font-medium text-slate-800">{{ $jabatan }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">Tanggal Bergabung Unit</label>
                        <p class="text-sm font-medium text-slate-800">{{ $tglBergabung }}</p>
                    </div>
                </div>
            </div>

            {{-- Informasi Akun --}}
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-base font-semibold text-slate-800">Informasi Akun</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">Terdaftar Sejak</label>
                        <p class="text-sm font-medium text-slate-800">{{ $terdaftarPada }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">Login Terakhir</label>
                        <p class="text-sm font-medium text-slate-800">{{ $loginTerakhir }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">Status Akun</label>
                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5
                                     text-xs font-semibold text-emerald-700">
                            Aktif
                        </span>
                    </div>
                </div>
            </div>

            {{-- Statistik khusus peran --}}
            {{-- Ringkasan Aktivitas dihapus --}}

        </div>
    </div>

@endsection
