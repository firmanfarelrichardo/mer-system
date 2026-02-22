{{--
|--------------------------------------------------------------------------
| Master Unit Kerja (admin/unit-kerja/index.blade.php)
|--------------------------------------------------------------------------
| Tabel + modal inline untuk tambah / edit unit kerja.
| Alpine.js mengelola state modal (mode buat/edit, field values).
| Jika validasi gagal (server-side), modal auto-terbuka kembali via old().
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Master Unit Kerja — Sistem MER')

@php
    $modalAwalBuka = $errors->any();
    $modeAwal      = old('_modal_mode', 'buat');
    $editIdAwal    = old('_modal_edit_id');
@endphp

@section('konten')

    {{-- ================================================================
         WRAPPER ALPINE — mengelola state modal
         ================================================================ --}}
    {{-- x-data pakai single-quote agar @json() (output double-quote) aman di dalam atribut --}}
    <div x-data='{
        modalBuka: {{ $modalAwalBuka ? "true" : "false" }},
        mode:      @json($modeAwal),
        editId:    @json($editIdAwal),
        fields: {
            kode_unit:  @json(old("kode_unit", "")),
            nama_unit:  @json(old("nama_unit", "")),
            keterangan: @json(old("keterangan", "")),
        },
        bukaModalBuat() {
            this.mode   = "buat";
            this.editId = null;
            this.fields = { kode_unit: "", nama_unit: "", keterangan: "" };
            this.modalBuka = true;
        },
        bukaModalEdit(d) {
            this.mode   = "edit";
            this.editId = d.id;
            this.fields = { kode_unit: d.kode_unit, nama_unit: d.nama_unit, keterangan: d.keterangan ?? "" };
            this.modalBuka = true;
        },
        get judul() {
            return this.mode === "buat" ? "Tambah Unit Kerja" : "Edit Unit Kerja";
        },
        get formAction() {
            return this.mode === "buat"
                ? "{{ route("admin.unit-kerja.simpan") }}"
                : "{{ url("admin/unit-kerja") }}/" + this.editId;
        },
    }'>

    {{-- ================================================================
         HEADER + TOMBOL TAMBAH
         ================================================================ --}}
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Master Unit Kerja</h1>
            <p class="mt-1 text-sm text-slate-400">
                Kelola daftar unit kerja / departemen rumah sakit.
            </p>
        </div>
        <button @click="bukaModalBuat()"
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white
                       shadow-sm transition-colors hover:bg-brand/90 focus:outline-none focus:ring-2 focus:ring-brand/50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Tambah Unit Kerja
        </button>
    </div>

    {{-- ================================================================
         NOTIFIKASI SUKSES
         ================================================================ --}}
    @if (session('sukses'))
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
             x-data="{ tampil: true }" x-show="tampil" x-transition>
            <svg class="h-5 w-5 shrink-0 text-green-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <span>{{ session('sukses') }}</span>
            <button @click="tampil = false" class="ml-auto text-green-400 hover:text-green-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    {{-- ================================================================
         FILTER & PENCARIAN
         ================================================================ --}}
    <form method="GET" action="{{ route('admin.unit-kerja.index') }}"
          class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">

            {{-- Pencarian --}}
            <div class="flex-1">
                <label for="cari" class="mb-1 block text-xs font-medium text-slate-500">Cari</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </span>
                    <input type="text" id="cari" name="cari" value="{{ $filter['cari'] ?? '' }}"
                           placeholder="Cari kode atau nama unit kerja…"
                           class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm placeholder-slate-400
                                  focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                </div>
            </div>

            {{-- Tombol Filter --}}
            <div class="flex items-center gap-2">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-4 py-2 text-xs font-semibold text-white
                               transition-colors hover:bg-brand/90">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/>
                    </svg>
                    Cari
                </button>
                <a href="{{ route('admin.unit-kerja.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-4 py-2 text-xs font-medium text-slate-600
                          transition-colors hover:bg-slate-50">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                    Reset
                </a>
            </div>
        </div>
    </form>

    {{-- ================================================================
         TABEL DAFTAR UNIT KERJA
         ================================================================ --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">

        @if ($daftarUnit->isEmpty())
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m0 0v2.625"/>
                </svg>
                <p class="mt-3 text-sm text-slate-500">Belum ada unit kerja terdaftar.</p>
                <button @click="bukaModalBuat()" type="button"
                        class="mt-3 inline-block text-sm font-medium text-brand hover:underline">
                    + Tambah unit kerja baru
                </button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="px-5 py-3 font-semibold text-slate-500">Kode Unit</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Nama Unit</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Keterangan</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Dibuat</th>
                            <th class="px-5 py-3 text-center font-semibold text-slate-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($daftarUnit as $unit)
                            @php
                                $unitJson = json_encode([
                                    'id'         => $unit->id,
                                    'kode_unit'  => $unit->kode_unit,
                                    'nama_unit'  => $unit->nama_unit,
                                    'keterangan' => $unit->keterangan ?? '',
                                ]);
                            @endphp
                            <tr class="transition-colors hover:bg-slate-50/50">
                                <td class="px-5 py-3 font-mono text-xs font-medium text-slate-700">{{ $unit->kode_unit }}</td>
                                <td class="px-5 py-3 font-medium text-slate-800">{{ $unit->nama_unit }}</td>
                                <td class="px-5 py-3 text-slate-500">{{ $unit->keterangan ?? '—' }}</td>
                                <td class="px-5 py-3 text-xs text-slate-400">{{ $unit->created_at?->format('d M Y') }}</td>
                                <td class="px-5 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        {{-- Edit: data-unit berisi JSON unit, dibaca Alpine via JSON.parse --}}
                                        <button
                                            type="button"
                                            title="Edit Unit Kerja"
                                            data-unit='{{ $unitJson }}'
                                            @click="bukaModalEdit(JSON.parse($el.dataset.unit))"
                                            class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-brand">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                                            </svg>
                                        </button>

                                        {{-- Hapus --}}
                                        <form method="POST" action="{{ route('admin.unit-kerja.hapus', $unit->id) }}"
                                              onsubmit="return confirm('Hapus unit kerja {{ $unit->nama_unit }}? Data yang terkait tidak akan terpengaruh.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    title="Hapus Unit Kerja"
                                                    class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-red-600">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
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

            {{-- Pagination --}}
            @if ($daftarUnit->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $daftarUnit->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- ================================================================
         MODAL — Tambah / Edit Unit Kerja
         ================================================================ --}}
    <div
        x-show="modalBuka"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="display: none;"
    >
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-slate-900/50" @click="modalBuka = false"></div>

        {{-- Dialog --}}
        <div
            x-show="modalBuka"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative z-10 w-full max-w-md rounded-xl bg-white shadow-xl"
            @click.stop
        >
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-800" x-text="judul"></h2>
                <button @click="modalBuka = false" type="button"
                        class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Form --}}
            <form method="POST" x-bind:action="formAction">
                @csrf
                {{-- Method spoofing: PUT saat edit, kosong (diabaikan) saat buat --}}
                <input type="hidden" name="_method" x-bind:value="mode === 'edit' ? 'PUT' : ''">
                {{-- State untuk auto-reopen modal jika validasi gagal --}}
                <input type="hidden" name="_modal_mode"    x-bind:value="mode">
                <input type="hidden" name="_modal_edit_id" x-bind:value="editId ?? ''">

                <div class="space-y-4 px-6 py-5">

                    {{-- Kode Unit --}}
                    <div>
                        <label for="m_kode_unit" class="mb-1 block text-sm font-medium text-slate-700">
                            Kode Unit <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="m_kode_unit" name="kode_unit"
                               x-model="fields.kode_unit"
                               placeholder="Contoh: IGD, ICU, RAWAT-INAP"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm placeholder-slate-400
                                      focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30
                                      @error('kode_unit') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                        @error('kode_unit')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Nama Unit --}}
                    <div>
                        <label for="m_nama_unit" class="mb-1 block text-sm font-medium text-slate-700">
                            Nama Unit <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="m_nama_unit" name="nama_unit"
                               x-model="fields.nama_unit"
                               placeholder="Contoh: Instalasi Gawat Darurat"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm placeholder-slate-400
                                      focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30
                                      @error('nama_unit') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                        @error('nama_unit')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Keterangan --}}
                    <div>
                        <label for="m_keterangan" class="mb-1 block text-sm font-medium text-slate-700">Keterangan</label>
                        <textarea id="m_keterangan" name="keterangan" rows="3"
                                  x-model="fields.keterangan"
                                  placeholder="Deskripsi singkat (opsional)"
                                  class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm placeholder-slate-400
                                         focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30
                                         @error('keterangan') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"></textarea>
                        @error('keterangan')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 px-6 py-4">
                    <button type="button" @click="modalBuka = false"
                            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600
                                   transition-colors hover:bg-slate-50">
                        Batal
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white
                                   shadow-sm transition-colors hover:bg-brand/90 focus:outline-none focus:ring-2 focus:ring-brand/50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span x-text="mode === 'buat' ? 'Simpan' : 'Perbarui'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    </div>{{-- end x-data wrapper --}}

@endsection
