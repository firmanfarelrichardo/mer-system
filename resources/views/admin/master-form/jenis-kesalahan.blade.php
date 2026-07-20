{{--
|--------------------------------------------------------------------------
| Master Jenis Kesalahan (admin/master-form/jenis-kesalahan.blade.php)
|--------------------------------------------------------------------------
| Tabel daftar jenis kesalahan obat dengan pencarian, toggle aktif, dan CRUD.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Master Jenis Kesalahan - Sistem MER')

@php
    $modalAwalBuka = $errors->any();
    $modeAwal      = old('_modal_mode', 'buat');
    $editIdAwal    = old('_modal_edit_id');
@endphp

@section('konten')

<div
    x-data='{
        modalBuka : {{ $modalAwalBuka ? "true" : "false" }},
        mode      : @json($modeAwal),
        editId    : @json($editIdAwal),
        fields    : {
            nama : @json(old("nama", "")),
        },

        bukaModalBuat() {
            this.mode   = "buat";
            this.editId = null;
            this.fields = { nama: "" };
            this.modalBuka = true;
        },

        bukaModalEdit(d) {
            this.mode   = "edit";
            this.editId = d.id;
            this.fields = { nama: d.nama };
            this.modalBuka = true;
        },

        get judul() {
            return this.mode === "buat" ? "Tambah Jenis Kesalahan" : "Edit Jenis Kesalahan";
        },

        get formAction() {
            return this.mode === "buat"
                ? "{{ route("admin.jenis-kesalahan.simpan") }}"
                : "{{ url("admin/jenis-kesalahan") }}/" + this.editId;
        },
    }'
>

    {{-- ================================================================
         HEADER + TOMBOL TAMBAH
         ================================================================ --}}
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Master Jenis Kesalahan</h1>
            <p class="mt-1 text-sm text-slate-400">
                Kelola daftar jenis kesalahan obat untuk formulir pelaporan kesalahan pengobatan.
            </p>
        </div>
        <button @click="bukaModalBuat()"
                class="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white
                       shadow-sm transition-colors hover:bg-brand/90 focus:outline-none focus:ring-2 focus:ring-brand/50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Tambah Jenis Kesalahan
        </button>
    </div>

    {{-- ================================================================
         FILTER & PENCARIAN
         ================================================================ --}}
    <form method="GET" action="{{ route('admin.jenis-kesalahan.index') }}"
          class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        @if (request('per_halaman'))
            <input type="hidden" name="per_halaman" value="{{ request('per_halaman') }}">
        @endif
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
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
                           placeholder="Cari nama jenis kesalahan…"
                           class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm placeholder-slate-400
                                  focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                </div>
            </div>

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
                <a href="{{ route('admin.jenis-kesalahan.index') }}"
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
         TABEL DAFTAR JENIS KESALAHAN
         ================================================================ --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">

        @if ($daftarData->isEmpty())
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                </svg>
                <p class="mt-3 text-sm text-slate-500">Belum ada jenis kesalahan terdaftar.</p>
                <button @click="bukaModalBuat()"
                        class="mt-3 inline-block text-sm font-medium text-brand hover:underline">
                    + Tambah jenis kesalahan baru
                </button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="px-5 py-3 font-semibold text-slate-500 w-12">#</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Nama</th>
                            <th class="px-5 py-3 font-semibold text-slate-500 text-center w-28">Status</th>
                            <th class="px-5 py-3 font-semibold text-slate-500">Dibuat</th>
                            <th class="px-5 py-3 text-center font-semibold text-slate-500 w-32">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($daftarData as $item)
                            @php
                                $itemJson = json_encode([
                                    'id'   => $item->id,
                                    'nama' => $item->nama,
                                ]);
                            @endphp
                            <tr class="transition-colors hover:bg-slate-50/50">
                                <td class="px-5 py-3 text-xs text-slate-400">{{ $loop->iteration + ($daftarData->currentPage() - 1) * $daftarData->perPage() }}</td>
                                <td class="px-5 py-3 font-medium text-slate-800">{{ $item->nama }}</td>
                                <td class="px-5 py-3 text-center">
                                    <form method="POST" action="{{ route('admin.jenis-kesalahan.toggle-aktif', $item->id) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" title="{{ $item->is_aktif ? 'Nonaktifkan' : 'Aktifkan' }}"
                                                class="group relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent
                                                       transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-brand/50 focus:ring-offset-2
                                                       {{ $item->is_aktif ? 'bg-green-500' : 'bg-slate-300' }}">
                                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0
                                                         transition duration-200 ease-in-out
                                                         {{ $item->is_aktif ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                        </button>
                                    </form>
                                </td>
                                <td class="px-5 py-3 text-xs text-slate-800">
                                    <span class="block">{{ $item->created_at?->format('d M Y') }}</span>
                                    <span class="text-slate-500">{{ $item->created_at?->format('H:i:s') }}</span>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button type="button"
                                                title="Edit"
                                                data-item='{{ $itemJson }}'
                                                @click="bukaModalEdit(JSON.parse($el.dataset.item))"
                                                class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-brand">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                                            </svg>
                                        </button>

                                        <form method="POST" action="{{ route('admin.jenis-kesalahan.hapus', $item->id) }}"
                                              data-confirm="Hapus jenis kesalahan {{ addslashes($item->nama) }}? Aksi ini tidak dapat dibatalkan."
                                              data-confirm-destructive="true"
                                              data-confirm-label="Hapus">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    title="Hapus"
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

            <x-pagination :paginator="$daftarData" />
        @endif
    </div>

    {{-- ================================================================
         MODAL TAMBAH / EDIT
         ================================================================ --}}
    <div x-show="modalBuka"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="display: none;">

        <div class="absolute inset-0 bg-slate-900/50" @click="modalBuka = false"></div>

        <div x-show="modalBuka"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative z-10 w-full max-w-sm rounded-2xl bg-white shadow-xl">

            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-800" x-text="judul"></h2>
                <button @click="modalBuka = false"
                        class="rounded-lg p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form method="POST" x-bind:action="formAction" class="px-6 py-5 space-y-4">
                @csrf
                <input type="hidden" name="_method" :value="mode === 'edit' ? 'PUT' : ''">
                <input type="hidden" name="_modal_mode"    :value="mode">
                <input type="hidden" name="_modal_edit_id" :value="editId">

                <div>
                    <label for="modal_nama"
                           class="mb-1.5 block text-sm font-medium text-slate-700">
                        Nama Jenis Kesalahan <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="modal_nama"
                           name="nama"
                           x-model="fields.nama"
                           placeholder="Contoh: Salah Pasien"
                           autocomplete="off"
                           class="w-full rounded-lg border px-3 py-2 text-sm placeholder-slate-400
                                  focus:outline-none focus:ring-1
                                  @error('nama') border-red-400 focus:border-red-400 focus:ring-red-300
                                  @else border-slate-200 focus:border-brand focus:ring-brand/30 @enderror">
                    @error('nama')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-3 pt-1">
                    <button type="button"
                            @click="modalBuka = false"
                            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600
                                   transition-colors hover:bg-slate-50">
                        Batal
                    </button>
                    <button type="submit"
                            class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white
                                   transition-colors hover:bg-brand/90 focus:outline-none focus:ring-2 focus:ring-brand/50"
                            x-text="mode === 'buat' ? 'Simpan' : 'Perbarui'">
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>{{-- end x-data --}}

@endsection
