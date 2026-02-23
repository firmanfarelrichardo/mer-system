{{--
|--------------------------------------------------------------------------
| Halaman Notifikasi (notifikasi/index.blade.php)
|--------------------------------------------------------------------------
| Menampilkan daftar notifikasi pengguna (dibaca dan belum dibaca).
| Konten notifikasi menyesuaikan per peran:
|   - Nakes: notif status laporan sendiri
|   - Kepala Ruangan: notif laporan baru di unit
|   - Komite: notif laporan butuh investigasi
|   - Admin: notif pengguna baru, akun nonaktif
|   - Direktur: notif laporan statistik bulanan
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Notifikasi — Sistem MER')

@section('konten')

    @php
        $pengguna = Auth::user();

        // Data dummy notifikasi — nanti akan diganti dari database
        $daftarNotifikasi = [
            [
                'id'          => 1,
                'judul'       => 'Laporan INC-2026-0045 telah diterima',
                'pesan'       => 'Laporan insiden yang Anda buat telah berhasil diterima dan sedang menunggu verifikasi oleh Kepala Ruangan.',
                'waktu'       => '2 menit yang lalu',
                'tipe'        => 'laporan',
                'ikon_warna'  => 'bg-brand/10 text-brand',
                'dibaca'      => false,
            ],
            [
                'id'          => 2,
                'judul'       => 'Status laporan INC-2026-0042 diperbarui',
                'pesan'       => 'Laporan INC-2026-0042 telah diubah statusnya menjadi "Investigasi" oleh Komite Keselamatan Pasien.',
                'waktu'       => '1 jam yang lalu',
                'tipe'        => 'status',
                'ikon_warna'  => 'bg-blue-50 text-blue-500',
                'dibaca'      => false,
            ],
            [
                'id'          => 3,
                'judul'       => 'Tindak lanjut diperlukan untuk INC-2026-0038',
                'pesan'       => 'Komite meminta Anda untuk melengkapi informasi tindakan segera pada laporan INC-2026-0038.',
                'waktu'       => '3 jam yang lalu',
                'tipe'        => 'tindakan',
                'ikon_warna'  => 'bg-amber-50 text-amber-500',
                'dibaca'      => false,
            ],
            [
                'id'          => 4,
                'judul'       => 'Laporan INC-2026-0035 telah selesai',
                'pesan'       => 'Proses investigasi dan tindak lanjut untuk insiden INC-2026-0035 telah selesai dilaksanakan.',
                'waktu'       => '1 hari yang lalu',
                'tipe'        => 'selesai',
                'ikon_warna'  => 'bg-emerald-50 text-emerald-500',
                'dibaca'      => true,
            ],
            [
                'id'          => 5,
                'judul'       => 'Pengingat: Lengkapi laporan Anda',
                'pesan'       => 'Anda memiliki 1 laporan yang belum dilengkapi. Silakan lengkapi sebelum batas waktu pelaporan.',
                'waktu'       => '2 hari yang lalu',
                'tipe'        => 'pengingat',
                'ikon_warna'  => 'bg-violet-50 text-violet-500',
                'dibaca'      => true,
            ],
        ];

        $belumDibaca = collect($daftarNotifikasi)->where('dibaca', false)->count();
    @endphp

    {{-- ================================================================
         HEADER HALAMAN
         ================================================================ --}}
    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Notifikasi</h1>
            <p class="mt-1 text-sm text-slate-400">
                Anda memiliki <span class="font-semibold text-brand">{{ $belumDibaca }}</span> notifikasi belum dibaca.
            </p>
        </div>
        <button type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white
                       px-4 py-2 text-sm font-medium text-slate-600 shadow-sm transition-colors
                       hover:bg-slate-50">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                 viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            Tandai Semua Dibaca
        </button>
    </div>

    {{-- ================================================================
         DAFTAR NOTIFIKASI
         ================================================================ --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="divide-y divide-slate-100">
            @foreach ($daftarNotifikasi as $notif)
                <div @class([
                    'flex gap-4 px-5 py-4 transition-colors hover:bg-slate-50/60',
                    'bg-brand/[0.02]' => ! $notif['dibaca'],
                ])>
                    {{-- Ikon tipe notifikasi --}}
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $notif['ikon_warna'] }}">
                        @switch($notif['tipe'])
                            @case('laporan')
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125
                                             1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25
                                             0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125
                                             1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                @break
                            @case('status')
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993
                                             0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25
                                             0 0 1 13.803-3.7l3.181 3.182" />
                                </svg>
                                @break
                            @case('tindakan')
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73
                                             0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898
                                             0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                </svg>
                                @break
                            @case('selesai')
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                @break
                            @default
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1
                                             18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64
                                             3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714
                                             0a3 3 0 1 1-5.714 0" />
                                </svg>
                        @endswitch
                    </div>

                    {{-- Konten notifikasi --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <p @class([
                                'text-sm',
                                'font-semibold text-slate-800' => ! $notif['dibaca'],
                                'font-medium text-slate-600'   => $notif['dibaca'],
                            ])>
                                {{ $notif['judul'] }}
                            </p>
                            @unless($notif['dibaca'])
                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand"></span>
                            @endunless
                        </div>
                        <p class="mt-0.5 text-sm text-slate-400 line-clamp-2">{{ $notif['pesan'] }}</p>
                        <p class="mt-1 text-xs text-slate-300">{{ $notif['waktu'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Footer -- info paginasi placeholder --}}
        <div class="flex items-center justify-between border-t border-slate-200 px-5 py-3 text-sm text-slate-400">
            <span>Menampilkan 1–5 dari 23 notifikasi</span>
            <div class="flex gap-1">
                <button disabled
                        class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-medium text-slate-300">
                    Sebelumnya
                </button>
                <button class="rounded-lg border border-brand bg-brand px-3 py-1 text-xs font-medium text-white">
                    1
                </button>
                <button class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-medium
                               text-slate-600 hover:bg-slate-50">
                    2
                </button>
                <button class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-medium
                               text-slate-600 hover:bg-slate-50">
                    Berikutnya
                </button>
            </div>
        </div>
    </div>

@endsection
