{{--
|--------------------------------------------------------------------------
| Draf Laporan Insiden (laporan/draf.blade.php)
|--------------------------------------------------------------------------
| Halaman daftar draf laporan insiden yang belum dikirim oleh Nakes.
| Menampilkan:
|   1. Header judul + sub-judul
|   2. Filter pencarian
|   3. Tabel data draf dengan tombol "Lanjutkan Laporan"
|
| Variabel dari controller:
|   $daftarDraf — LengthAwarePaginator (Insiden with detailPasien, status='DRAF')
|   $pengguna   — Pengguna (auth user)
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Draf Laporan — Sistem MER')

@section('konten')

    {{-- ================================================================
         HEADER HALAMAN
         ================================================================ --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Draf Laporan</h1>
        <p class="mt-1 text-sm text-slate-400">Laporan yang belum selesai dan belum dikirim</p>
    </div>

    {{-- ================================================================
         PENCARIAN
         ================================================================ --}}
    <div class="mb-4">
        <form method="GET" action="{{ route('laporan.draf') }}" class="relative max-w-md">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                 fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input type="text" name="cari" value="{{ request('cari') }}"
                   placeholder="Cari nama pasien, unit kerja…"
                   class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-700 shadow-sm
                          placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
        </form>
    </div>

    {{-- ================================================================
         TABEL DRAF LAPORAN
         ================================================================ --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">

                <thead class="border-b border-slate-200 bg-slate-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold text-slate-500 sm:px-5">ID Draf</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold text-slate-500 sm:px-5">Pasien</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold text-slate-500 sm:px-5">Unit Kerja</th>
                        <th class="hidden whitespace-nowrap px-4 py-3 font-semibold text-slate-500 sm:table-cell sm:px-5">Tgl Kejadian</th>
                        <th class="hidden whitespace-nowrap px-4 py-3 font-semibold text-slate-500 md:table-cell sm:px-5">Terakhir Diubah</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold text-slate-500 sm:px-5">Status</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold text-slate-500 sm:px-5">Aksi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse ($daftarDraf as $draf)
                        <tr class="transition-colors hover:bg-slate-50/60">

                            {{-- ID Draf --}}
                            <td class="whitespace-nowrap px-4 py-3.5 sm:px-5">
                                <span class="font-mono text-xs font-semibold text-slate-500">{{ $draf->nomor_laporan }}</span>
                            </td>

                            {{-- Nama Pasien --}}
                            <td class="whitespace-nowrap px-4 py-3.5 font-medium text-slate-800 sm:px-5">
                                {{ $draf->detailPasien?->nama_pasien ?? '(Belum diisi)' }}
                            </td>

                            {{-- Unit Kerja --}}
                            <td class="whitespace-nowrap px-4 py-3.5 text-slate-500 sm:px-5">
                                {{ $draf->nama_unit_kerja ?? '—' }}
                            </td>

                            {{-- Tanggal Kejadian --}}
                            <td class="hidden whitespace-nowrap px-4 py-3.5 sm:table-cell sm:px-5">
                                @if ($draf->tgl_kejadian)
                                    <span class="block text-xs font-medium text-slate-700">{{ $draf->tgl_kejadian->format('d M Y, H:i:s') }}</span>
                                    <span class="block text-xs text-slate-400">{{ $draf->tgl_kejadian->diffForHumans() }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            {{-- Terakhir Diubah --}}
                            <td class="hidden whitespace-nowrap px-4 py-3.5 md:table-cell sm:px-5">
                                @if ($draf->updated_at)
                                    <span class="block text-xs font-medium text-slate-700"
                                          title="{{ $draf->updated_at->format('d M Y, H:i:s') }}">
                                        {{ $draf->updated_at->format('d M Y, H:i:s') }}
                                    </span>
                                    <span class="block text-xs text-slate-400">{{ $draf->updated_at->diffForHumans() }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            {{-- Status Badge --}}
                            <td class="whitespace-nowrap px-4 py-3.5 sm:px-5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $draf->warnaStatus() }}">
                                    {{ $draf->labelStatus() }}
                                </span>
                            </td>

                            {{-- Aksi --}}
                            <td class="whitespace-nowrap px-4 py-3.5 sm:px-5">
                                <a href="{{ route('laporan.edit', $draf->id) }}"
                                   class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5
                                          text-xs font-semibold text-white shadow-sm transition-colors hover:bg-brand-hover">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                                    </svg>
                                    Lanjutkan Laporan
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                                    </svg>
                                    <p class="text-sm font-medium text-slate-400">Tidak ada draf laporan</p>
                                    <p class="text-xs text-slate-300">Draf yang disimpan akan muncul di sini</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$daftarDraf" />
    </div>

@endsection
