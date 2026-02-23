{{--
|--------------------------------------------------------------------------
| Halaman Notifikasi (notifikasi/index.blade.php)
|--------------------------------------------------------------------------
| Menampilkan daftar notifikasi pengguna (dibaca dan belum dibaca).
|
| Variabel dari controller:
|   $daftarNotifikasi — LengthAwarePaginator<NotifikasiData>
|   $belumDibaca      — int (jumlah notifikasi belum dibaca)
|
| Konten notifikasi menyesuaikan per peran:
|   - Nakes: notif status laporan sendiri
|   - Kepala Ruangan: notif laporan baru di unit
|   - Komite: notif laporan butuh investigasi
|   - Direktur: notif sentinel event
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Notifikasi — Sistem MER')

@section('konten')

    {{-- ================================================================
         HEADER HALAMAN
         ================================================================ --}}
    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Notifikasi</h1>
            <p class="mt-1 text-sm text-slate-400">
                @if ($belumDibaca > 0)
                    Anda memiliki <span class="font-semibold text-brand">{{ $belumDibaca }}</span> notifikasi belum dibaca.
                @else
                    Semua notifikasi telah dibaca.
                @endif
            </p>
        </div>

        @if ($belumDibaca > 0)
            <form method="POST" action="{{ route('notifikasi.tandai-semua-dibaca') }}">
                @csrf
                <button type="submit"
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
            </form>
        @endif
    </div>

    {{-- Flash messages --}}
    @if (session('sukses'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('sukses') }}
        </div>
    @endif

    @if (session('galat'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('galat') }}
        </div>
    @endif

    {{-- ================================================================
         DAFTAR NOTIFIKASI
         ================================================================ --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($daftarNotifikasi->isEmpty())
            {{-- Empty state --}}
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100">
                    <svg class="h-8 w-8 text-slate-300" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1
                                 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64
                                 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714
                                 0a3 3 0 1 1-5.714 0" />
                    </svg>
                </div>
                <p class="mt-4 text-sm font-medium text-slate-500">Belum ada notifikasi</p>
                <p class="mt-1 text-xs text-slate-400">Notifikasi akan muncul saat ada aktivitas terkait laporan insiden.</p>
            </div>
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($daftarNotifikasi as $notif)
                    <a href="{{ route('notifikasi.baca', $notif->id) }}"
                       @class([
                           'flex gap-4 px-5 py-4 transition-colors hover:bg-slate-50/60',
                           'bg-brand/[0.02]' => ! $notif->dibaca,
                       ])>
                        {{-- Ikon tipe notifikasi --}}
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $notif->ikonWarna }}">
                            @switch($notif->tipe)
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
                                    'font-semibold text-slate-800' => ! $notif->dibaca,
                                    'font-medium text-slate-600'   => $notif->dibaca,
                                ])>
                                    {{ $notif->judul }}
                                </p>
                                @unless($notif->dibaca)
                                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand"></span>
                                @endunless
                            </div>
                            <p class="mt-0.5 text-sm text-slate-400 line-clamp-2">{{ $notif->pesan }}</p>
                            <p class="mt-1 text-xs text-slate-300" title="{{ $notif->waktuLengkap }}">
                                {{ $notif->waktuRelatif }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Paginasi --}}
            @if ($daftarNotifikasi->hasPages())
                <div class="border-t border-slate-200 px-5 py-3">
                    {{ $daftarNotifikasi->links() }}
                </div>
            @endif
        @endif
    </div>

@endsection
