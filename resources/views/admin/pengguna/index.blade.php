{{--
|--------------------------------------------------------------------------
| Daftar Pengguna (admin/pengguna/index.blade.php)
|--------------------------------------------------------------------------
| Tabel daftar pengguna dengan fitur pencarian, filter (peran, unit,
| status), dan aksi (edit, toggle status, reset kata sandi).
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Manajemen Pengguna — Sistem MER')

@section('konten')

    {{-- ================================================================
         HEADER + TOMBOL TAMBAH
         ================================================================ --}}
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Manajemen Pengguna</h1>
            <p class="mt-1 text-sm text-slate-400">
                Kelola daftar akun pengguna sistem pelaporan kesalahan pengobatan.
            </p>
        </div>
        <a href="{{ route('admin.pengguna.buat') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white
                  shadow-sm transition-colors hover:bg-brand/90 focus:outline-none focus:ring-2 focus:ring-brand/50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Tambah Pengguna
        </a>
    </div>

    {{-- ================================================================
         FILTER & PENCARIAN
         ================================================================ --}}
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        {{-- Input pencarian cepat --}}
        <form method="GET" action="{{ route('admin.pengguna.index') }}" class="relative flex-1">
            @if (!empty($filter['peran_id']))
                <input type="hidden" name="peran_id" value="{{ $filter['peran_id'] }}">
            @endif
            @if (!empty($filter['unit_id']))
                <input type="hidden" name="unit_id" value="{{ $filter['unit_id'] }}">
            @endif
            @if (isset($filter['status']) && $filter['status'] !== '')
                <input type="hidden" name="status" value="{{ $filter['status'] }}">
            @endif
            @if (request('per_halaman'))
                <input type="hidden" name="per_halaman" value="{{ request('per_halaman') }}">
            @endif

            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
            </span>
            <input type="text" name="cari" value="{{ $filter['cari'] ?? '' }}"
                   placeholder="Nama, NIP, email, atau no. HP…"
                   class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-700 shadow-sm
                          placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
        </form>

        <div class="flex items-center gap-2">
            {{-- Reusable Filter Component --}}
            <x-filter-dropdown :action="route('admin.pengguna.index')" title="Filter Pengguna">

                {{-- Pencarian --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Kata Kunci</label>
                    <input type="text" name="cari" value="{{ $filter['cari'] ?? '' }}"
                           placeholder="Nama, NIP, email, atau no. HP…"
                           class="w-full rounded-lg border border-slate-200 py-2 px-3 text-sm placeholder-slate-400
                                  focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                </div>

                {{-- Filter Peran --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Peran</label>
                    <select name="peran_id"
                            class="w-full rounded-lg border border-slate-200 py-2 px-3 text-sm text-slate-700
                                   focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                        <option value="">Semua Peran</option>
                        @foreach ($daftarPeran as $peran)
                            <option value="{{ $peran->id }}" @selected(($filter['peran_id'] ?? '') == $peran->id)>
                                {{ $peran->nama_display }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Unit Kerja --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Unit Kerja</label>
                    <select name="unit_id"
                            class="w-full rounded-lg border border-slate-200 py-2 px-3 text-sm text-slate-700
                                   focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                        <option value="">Semua Unit</option>
                        @foreach ($daftarUnit as $unit)
                            <option value="{{ $unit->id }}" @selected(($filter['unit_id'] ?? '') == $unit->id)>
                                {{ $unit->nama_unit }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Status --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Status</label>
                    <select name="status"
                            class="w-full rounded-lg border border-slate-200 py-2 px-3 text-sm text-slate-700
                                   focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                        <option value="">Semua Status</option>
                        <option value="1" @selected(($filter['status'] ?? '') === 1)>Aktif</option>
                        <option value="0" @selected(isset($filter['status']) && $filter['status'] === 0)>Nonaktif</option>
                    </select>
                </div>

            </x-filter-dropdown>

            @if (!empty($filter['cari']) || !empty($filter['peran_id']) || !empty($filter['unit_id']) || isset($filter['status']) && $filter['status'] !== '')
                <a href="{{ route('admin.pengguna.index') }}"
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
         TABEL DAFTAR PENGGUNA
         ================================================================ --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">

        @if ($daftarPengguna->isEmpty())
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                </svg>
                <p class="mt-3 text-sm text-slate-500">Tidak ada pengguna ditemukan.</p>
                <a href="{{ route('admin.pengguna.buat') }}"
                   class="mt-3 inline-block text-sm font-medium text-brand hover:underline">
                    + Tambah pengguna baru
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="px-5 py-3 font-semibold text-slate-500">Nama</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">NIP</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Email</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Peran</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Unit Kerja</th>
                            <th class="px-5 py-3 text-center font-semibold text-slate-500">Status</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Login Terakhir</th>
                            <th class="px-5 py-3 text-center font-semibold text-slate-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($daftarPengguna as $akun)
                            <tr class="transition-colors hover:bg-slate-50/50">
                                {{-- Nama + No. HP --}}
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800">{{ $akun->nama_lengkap }}</p>
                                    @if ($akun->nomor_hp)
                                        <p class="text-xs text-slate-400">{{ $akun->nomor_hp }}</p>
                                    @endif
                                </td>

                                {{-- NIP --}}
                                <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $akun->nomor_induk }}</td>

                                {{-- Email --}}
                                <td class="px-5 py-3 text-slate-600">{{ $akun->email }}</td>

                                {{-- Peran ---}}
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($akun->peran as $p)
                                            <span class="inline-block rounded-full bg-brand/10 px-2.5 py-0.5 text-xs font-medium text-brand">
                                                {{ $p->nama_display }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>

                                {{-- Unit Kerja --}}
                                <td class="px-5 py-3 text-slate-600">
                                    {{ $akun->unitKerja?->nama_unit ?? '—' }}
                                </td>

                                {{-- Status --}}
                                <td class="px-5 py-3 text-center">
                                    @if ($akun->is_aktif)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                                            Nonaktif
                                        </span>
                                    @endif
                                </td>

                                {{-- Login Terakhir --}}
                                <td class="px-5 py-3 text-xs text-slate-400">
                                    @if ($akun->terakhir_login_pada)
                                        <span class="block">{{ $akun->terakhir_login_pada->format('d M Y') }}</span>
                                        <span class="text-slate-500">{{ $akun->terakhir_login_pada->format('H:i:s') }}</span>
                                    @else
                                        <span class="text-slate-500">—</span>
                                    @endif
                                </td>

                                {{-- Aksi --}}
                                <td class="px-5 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">

                                        {{-- Edit --}}
                                        <a href="{{ route('admin.pengguna.edit', $akun->id) }}"
                                           title="Edit Pengguna"
                                           class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-brand">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                                            </svg>
                                        </a>

                                        {{-- Toggle Status --}}
                                        <form method="POST" action="{{ route('admin.pengguna.toggle-status', $akun->id) }}"
                                              data-confirm="{{ $akun->is_aktif ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $akun->nama_lengkap }}?"
                                              data-confirm-label="{{ $akun->is_aktif ? 'Nonaktifkan' : 'Aktifkan' }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    title="{{ $akun->is_aktif ? 'Nonaktifkan' : 'Aktifkan' }} Akun"
                                                    class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100
                                                           {{ $akun->is_aktif ? 'hover:text-red-600' : 'hover:text-green-600' }}">
                                                @if ($akun->is_aktif)
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/>
                                                    </svg>
                                                @else
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                                    </svg>
                                                @endif
                                            </button>
                                        </form>

                                        {{-- Reset Kata Sandi --}}
                                        <form method="POST" action="{{ route('admin.pengguna.reset-sandi', $akun->id) }}"
                                              data-confirm="Reset kata sandi {{ $akun->nama_lengkap }} ke nilai default?"
                                              data-confirm-label="Ya, Reset">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    title="Reset Kata Sandi"
                                                    class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-amber-600">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$daftarPengguna" />
        @endif
    </div>

@endsection
