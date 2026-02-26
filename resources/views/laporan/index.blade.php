{{--
|--------------------------------------------------------------------------
| Riwayat Laporan Insiden (laporan/index.blade.php)
|--------------------------------------------------------------------------
| Halaman daftar riwayat laporan insiden medication errors.
| Menampilkan:
|   1. Header judul + sub-judul
|   2. Empat kartu ringkasan (Total, Kasus Baru, Diproses, Selesai)
|   3. Filter pencarian, status, dan tipe insiden
|   4. Tabel data dengan data real dari database (pagination)
|   5. Aksi: Lihat, Tandai Dibaca, Hapus (berdasarkan peran)
|
| Variabel dari controller:
|   $daftarLaporan — LengthAwarePaginator (Insiden with detailPasien)
|   $statistik     — array [total, kasus_baru, sedang_diproses, selesai]
|   $pengguna      — Pengguna (auth user)
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Riwayat Laporan — Sistem MER')

@section('konten')

    {{-- ================================================================
         HEADER HALAMAN
         ================================================================ --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Riwayat Laporan</h1>
        <p class="mt-1 text-sm text-slate-400">Sistem Pelaporan Insiden Obat</p>
    </div>

    {{-- Flash Messages --}}
    @if (session('sukses'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
            {{ session('sukses') }}
        </div>
    @endif

    {{-- ================================================================
         KARTU RINGKASAN (4 kolom)
         ================================================================ --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">

        {{-- Kartu: Total Laporan --}}
        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:gap-4 sm:p-5">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand sm:h-12 sm:w-12">
                <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 sm:text-sm">Total Laporan</p>
                <p class="text-xl font-bold text-slate-800 sm:text-2xl">{{ $statistik['total'] }}</p>
            </div>
        </div>

        {{-- Kartu: Kasus Baru --}}
        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:gap-4 sm:p-5">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-500 sm:h-12 sm:w-12">
                <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 sm:text-sm">Kasus Baru</p>
                <p class="text-xl font-bold text-slate-800 sm:text-2xl">{{ $statistik['kasus_baru'] }}</p>
            </div>
        </div>

        {{-- Kartu: Sedang Diproses --}}
        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:gap-4 sm:p-5">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-500 sm:h-12 sm:w-12">
                <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 sm:text-sm">Diproses</p>
                <p class="text-xl font-bold text-slate-800 sm:text-2xl">{{ $statistik['sedang_diproses'] }}</p>
            </div>
        </div>

        {{-- Kartu: Selesai --}}
        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:gap-4 sm:p-5">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500 sm:h-12 sm:w-12">
                <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 sm:text-sm">Selesai</p>
                <p class="text-xl font-bold text-slate-800 sm:text-2xl">{{ $statistik['selesai'] }}</p>
            </div>
        </div>
    </div>

    {{-- ================================================================
         FILTER & PENCARIAN
         ================================================================ --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        {{-- Input pencarian (tetap di luar komponen untuk akses cepat) --}}
        <form method="GET" action="{{ route('laporan.index') }}" class="relative flex-1">
            {{-- Pertahankan filter lain yang aktif --}}
            @if (request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            @if (request('tipe'))
                <input type="hidden" name="tipe" value="{{ request('tipe') }}">
            @endif
            @if (request('per_halaman'))
                <input type="hidden" name="per_halaman" value="{{ request('per_halaman') }}">
            @endif

            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                 fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input type="text" name="cari" value="{{ request('cari') }}"
                   placeholder="Cari nama pasien atau ID..."
                   class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-700 shadow-sm
                          placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
        </form>

        <div class="flex items-center gap-2">
            {{-- Reusable Filter Component --}}
            <x-filter-dropdown :action="route('laporan.index')" title="Filter Laporan">

                {{-- Pencarian --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Kata Kunci</label>
                    <input type="text" name="cari" value="{{ request('cari') }}"
                           placeholder="Cari nama pasien atau ID..."
                           class="w-full rounded-lg border border-slate-200 py-2 px-3 text-sm placeholder-slate-400
                                  focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                </div>

                {{-- Status --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Status</label>
                    <select name="status"
                            class="w-full rounded-lg border border-slate-200 py-2 px-3 text-sm text-slate-700
                                   focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                        <option value="">Semua Status</option>
                        <option value="kasus_baru" @selected(request('status') === 'kasus_baru')>Kasus Baru</option>
                        <option value="investigasi" @selected(request('status') === 'investigasi')>Investigasi</option>
                        <option value="tindak_lanjut" @selected(request('status') === 'tindak_lanjut')>Tindak Lanjut</option>
                        <option value="selesai" @selected(request('status') === 'selesai')>Selesai</option>
                    </select>
                </div>

                {{-- Tipe Insiden --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Tipe Insiden</label>
                    <select name="tipe"
                            class="w-full rounded-lg border border-slate-200 py-2 px-3 text-sm text-slate-700
                                   focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                        <option value="">Semua Tipe</option>
                        <option value="KTD" @selected(request('tipe') === 'KTD')>KTD</option>
                        <option value="KNC" @selected(request('tipe') === 'KNC')>KNC</option>
                        <option value="KTC" @selected(request('tipe') === 'KTC')>KTC</option>
                        <option value="KPC" @selected(request('tipe') === 'KPC')>KPC</option>
                        <option value="SENTINEL" @selected(request('tipe') === 'SENTINEL')>Sentinel</option>
                    </select>
                </div>

            </x-filter-dropdown>

            @if (request()->hasAny(['cari', 'status', 'tipe']))
                <a href="{{ route('laporan.index') }}"
                   class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-600
                          shadow-sm transition-colors hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                    Reset
                </a>
            @endif
        </div>
    </div>

    {{-- ================================================================
         TABEL DATA LAPORAN
         ================================================================ --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">

                <thead class="border-b border-slate-200 bg-slate-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold text-slate-500 sm:px-5">ID Insiden</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold text-slate-500 sm:px-5">Pasien</th>
                        @if (! $pengguna->memilikiPeran('Nakes'))
                            <th class="hidden whitespace-nowrap px-4 py-3 font-semibold text-slate-500 md:table-cell sm:px-5">Unit Kerja</th>
                        @endif
                        <th class="hidden whitespace-nowrap px-4 py-3 font-semibold text-slate-500 sm:table-cell sm:px-5">Tgl Kejadian</th>
                        @if ($pengguna->memilikiPeran('Nakes'))
                            <th class="hidden whitespace-nowrap px-4 py-3 font-semibold text-slate-500 lg:table-cell sm:px-5">Tgl Dilaporkan</th>
                        @endif
                        <th class="whitespace-nowrap px-4 py-3 font-semibold text-slate-500 sm:px-5">Jenis</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold text-slate-500 sm:px-5">Status</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold text-slate-500 sm:px-5">Aksi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse ($daftarLaporan as $laporan)
                        <tr class="transition-colors hover:bg-slate-50/60 {{ !$laporan->sudah_dibaca ? 'bg-blue-50/30' : '' }}">

                            {{-- ID Insiden --}}
                            <td class="whitespace-nowrap px-4 py-3.5 sm:px-5">
                                <div class="flex items-center gap-2">
                                    @unless ($laporan->sudah_dibaca)
                                        <span class="h-2 w-2 shrink-0 rounded-full bg-blue-500" title="Belum dibaca"></span>
                                    @endunless
                                    <span class="font-mono text-xs font-semibold text-slate-600">{{ $laporan->nomor_laporan }}</span>
                                </div>
                            </td>

                            {{-- Nama Pasien --}}
                            <td class="whitespace-nowrap px-4 py-3.5 font-medium text-slate-800 sm:px-5">
                                {{ $laporan->detailPasien?->nama_pasien ?? '—' }}
                            </td>

                            {{-- Unit Kerja (hanya untuk peran manajemen) --}}
                            @if (! $pengguna->memilikiPeran('Nakes'))
                                <td class="hidden whitespace-nowrap px-4 py-3.5 text-slate-500 md:table-cell sm:px-5">
                                    {{ $laporan->nama_unit_kerja ?? $laporan->unitKerja?->nama_unit ?? '—' }}
                                </td>
                            @endif

                            {{-- Tanggal kejadian --}}
                            <td class="hidden whitespace-nowrap px-4 py-3.5 sm:table-cell sm:px-5">
                                @if ($laporan->tgl_kejadian)
                                    <span class="block text-xs font-medium text-slate-700">{{ $laporan->tgl_kejadian->format('d M Y') }}</span>
                                    <span class="block text-xs text-slate-400">{{ $laporan->tgl_kejadian->format('H:i') }}</span>
                                @else
                                    <span class="text-slate-500">—</span>
                                @endif
                            </td>

                            {{-- Tgl Dilaporkan — hanya untuk Nakes --}}
                            @if ($pengguna->memilikiPeran('Nakes'))
                                <td class="hidden whitespace-nowrap px-4 py-3.5 lg:table-cell sm:px-5">
                                    @if ($laporan->tgl_lapor)
                                        <span class="block text-xs font-medium text-slate-700">{{ $laporan->tgl_lapor->format('d M Y, H:i:s') }}</span>
                                        <span class="block text-xs text-slate-400">{{ $laporan->tgl_lapor->diffForHumans() }}</span>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            @endif

                            {{-- Jenis insiden (badge berwarna) --}}
                            <td class="whitespace-nowrap px-4 py-3.5 sm:px-5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $laporan->warnaInsiden() }}">
                                    {{ $laporan->labelTipeInsiden() }}
                                </span>
                            </td>

                            {{-- Status (badge berwarna) --}}
                            <td class="whitespace-nowrap px-4 py-3.5 sm:px-5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $laporan->warnaStatus() }}">
                                    {{ $laporan->labelStatus() }}
                                </span>
                            </td>

                            {{-- Kolom Aksi --}}
                            <td class="whitespace-nowrap px-4 py-3.5 sm:px-5">
                                <div class="flex items-center gap-2">

                                    {{-- Tombol Lihat --}}
                                    <a href="{{ route('laporan.tampil', $laporan->id) }}"
                                       class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5
                                              text-xs font-semibold text-slate-600 shadow-sm transition-colors hover:bg-slate-50">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                        </svg>
                                        Lihat
                                    </a>

                                    {{-- Tombol Tandai Dibaca — untuk Kepala Ruangan / Komite / Admin --}}
                                    @if (!$laporan->sudah_dibaca && ($pengguna->memilikiPeran('Kepala Ruangan') || $pengguna->memilikiPeran('Komite') || $pengguna->memilikiPeran('Admin')))
                                        <form method="POST" action="{{ route('laporan.tandai-dibaca', $laporan->id) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    title="Tandai sudah dibaca"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-white px-3 py-1.5
                                                           text-xs font-semibold text-blue-600 shadow-sm transition-colors hover:bg-blue-50">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                                </svg>
                                                Dibaca
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Tombol Hapus — TIDAK tersedia untuk Nakes.
                                         Hanya Kepala Ruangan / Komite / Admin yang bisa hapus,
                                         dan hanya jika status masih kasus_baru. --}}
                                    @if ($laporan->status_saat_ini === 'kasus_baru'
                                         && ($pengguna->memilikiPeran('Kepala Ruangan') || $pengguna->memilikiPeran('Komite') || $pengguna->memilikiPeran('Admin')))
                                        <form method="POST" action="#" class="inline"
                                              onsubmit="return confirm('Apakah Anda yakin ingin menghapus laporan ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-white px-3 py-1.5
                                                           text-xs font-semibold text-red-600 shadow-sm transition-colors hover:bg-red-50">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                                </svg>
                                                Hapus
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $pengguna->memilikiPeran('Nakes') ? 7 : 7 }}" class="px-5 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12H9.75m3 0H9.75m0 0H8.25m1.5 0v3m0-3v-3m3.75 3H9.75M3.375 3h17.25c.621 0 1.125.504 1.125 1.125v15.75c0 .621-.504 1.125-1.125 1.125H3.375A1.125 1.125 0 0 1 2.25 19.875V4.125c0-.621.504-1.125 1.125-1.125Z"/>
                                    </svg>
                                    <p class="text-sm font-medium text-slate-400">Belum ada laporan</p>
                                    <p class="text-xs text-slate-300">Laporan yang dibuat akan muncul di sini</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$daftarLaporan" />
    </div>

@endsection
