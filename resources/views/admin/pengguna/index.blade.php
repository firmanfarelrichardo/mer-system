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
                            <th class="px-5 py-3 font-semibold text-slate-500">Username</th>
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

                                {{-- Username --}}
                                <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ $akun->username ?? '—' }}</td>

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

                                        {{-- Reset Kata Sandi (Emergency Reset via AJAX) --}}
                                        <button type="button"
                                                title="Reset Kata Sandi Darurat"
                                                data-reset-sandi-url="{{ route('admin.pengguna.reset-sandi', $akun->id) }}"
                                                data-nama-pengguna="{{ $akun->nama_lengkap }}"
                                                data-nomor-hp="{{ $akun->nomor_hp }}"
                                                onclick="konfirmasiResetSandi(this)"
                                                class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-amber-600">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z"/>
                                            </svg>
                                        </button>
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

{{-- ================================================================
     Modal Hasil Reset Sandi — ditampilkan setelah reset berhasil.
     Menampilkan sandi acak + tombol kirim via WhatsApp.
     ================================================================ --}}
<div id="modal-hasil-reset"
     x-data="hasilResetModal()"
     @hasil-reset.window="open($event.detail)"
     @keydown.escape.window="tutup()"
     x-show="isOpen"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-[9997] flex items-center justify-center p-4"
     style="display: none;"
     role="dialog"
     aria-modal="true"
     aria-labelledby="hasil-reset-title">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="tutup()"></div>

    {{-- Panel --}}
    <div class="relative w-full max-w-md"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-2">

        <div class="overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200/60">

            {{-- Strip hijau atas --}}
            <div class="h-1.5 w-full bg-emerald-500"></div>

            <div class="p-6">

                {{-- Ikon sukses --}}
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100">
                    <svg class="h-7 w-7 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>

                <h3 id="hasil-reset-title" class="mb-1 text-center text-base font-bold text-slate-800">
                    Sandi Darurat Berhasil Digenerate
                </h3>
                <p class="mb-5 text-center text-sm text-slate-500">
                    Berikan sandi sementara ini kepada <span class="font-semibold text-slate-700" x-text="nama"></span>.
                </p>

                {{-- Kotak sandi --}}
                <div class="mb-5 flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <span class="font-mono text-xl font-bold tracking-widest text-emerald-700" x-text="sandi"></span>
                    <button type="button"
                            @click="salin()"
                            title="Salin sandi"
                            class="flex-shrink-0 rounded-lg border border-emerald-200 bg-white p-1.5 text-emerald-600 transition-colors hover:bg-emerald-100">
                        <svg x-show="!disalin" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"/>
                        </svg>
                        <svg x-show="disalin" class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </button>
                </div>

                <p class="mb-5 rounded-lg bg-amber-50 px-3 py-2.5 text-center text-xs text-amber-700">
                    <svg class="mr-1 inline h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                    </svg>
                    Sandi ini hanya ditampilkan <strong>sekali</strong>. Pengguna wajib menggantinya saat login berikutnya.
                </p>

                {{-- Tombol --}}
                <div class="flex gap-3">
                    <button type="button"
                            @click="tutup()"
                            class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600
                                   transition-colors hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300">
                        Tutup
                    </button>

                    <button type="button"
                            x-show="nomorWa"
                            @click="kirimWa()"
                            class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 py-2.5
                                   text-sm font-semibold text-white transition-all hover:bg-[#20bd5a]
                                   focus:outline-none focus:ring-2 focus:ring-[#25D366]/50">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/>
                        </svg>
                        Kirim via WhatsApp
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('hasilResetModal', () => ({
            isOpen:   false,
            nama:     '',
            sandi:    '',
            nomorWa:  null,
            disalin:  false,

            open({ nama, sandi, nomorWa }) {
                this.nama    = nama;
                this.sandi   = sandi;
                this.nomorWa = nomorWa;
                this.disalin = false;
                this.isOpen  = true;
            },

            tutup() {
                this.isOpen = false;
            },

            salin() {
                navigator.clipboard.writeText(this.sandi).then(() => {
                    this.disalin = true;
                    setTimeout(() => { this.disalin = false; }, 2000);
                });
            },

            kirimWa() {
                const teks = encodeURIComponent(
                    `Halo ${this.nama}, berikut kata sandi sementara akun MER System Anda:\n\n` +
                    `Kata Sandi: ${this.sandi}\n\n` +
                    `Silakan login dan segera ganti kata sandi Anda demi keamanan akun.`
                );
                window.open(`https://wa.me/${this.nomorWa}?text=${teks}`, '_blank');
            },
        }));
    });

    /**
     * Picu confirm-modal sistem, lalu eksekusi reset via AJAX.
     * Hasilnya ditampilkan di hasil-reset modal (bergaya, bukan native browser).
     */
    function konfirmasiResetSandi(el) {
        const url  = el.dataset.resetSandiUrl;
        const nama = el.dataset.namaPengguna;

        window.dispatchEvent(new CustomEvent('confirm-modal', {
            detail: {
                message:      `Reset kata sandi ${nama} secara darurat? Sandi baru digenerate secara acak dan pengguna wajib menggantinya saat login berikutnya.`,
                confirmLabel: 'Ya, Reset Sandi',
                destructive:  false,
                onConfirm() {
                    fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept':       'application/json',
                        },
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.sukses) {
                            window.dispatchEvent(new CustomEvent('hasil-reset', {
                                detail: {
                                    nama:    data.nama,
                                    sandi:   data.sandi_acak,
                                    nomorWa: data.nomor_wa,
                                },
                            }));
                        } else {
                            window.dispatchEvent(new CustomEvent('toast', {
                                detail: { type: 'error', message: data.pesan || 'Gagal mereset kata sandi.' },
                            }));
                        }
                    })
                    .catch(() => {
                        window.dispatchEvent(new CustomEvent('toast', {
                            detail: { type: 'error', message: 'Terjadi kesalahan jaringan. Silakan coba lagi.' },
                        }));
                    });
                },
            },
        }));
    }
</script>
@endpush
