{{--
|--------------------------------------------------------------------------
| Komponen: Export Date Range (export-date-range)
|--------------------------------------------------------------------------
| Komponen anonim reusable untuk mencetak laporan rekapitulasi insiden
| berdasarkan rentang tanggal yang dipilih pengguna.
|
| Props:
|   $action — URL tujuan form (route laporan.export.summary)
|   $label  — Teks tombol (default: "Cetak PDF")
|
| Validasi sisi klien (Alpine.js — pola Alpine.data() seperti proyek ini):
|   - tanggal_akhir tidak boleh sebelum tanggal_mulai.
|   - Pesan error ditampilkan secara inline tanpa reload halaman.
|
| Input tambahan:
|   orientation (portrait|landscape) — default portrait
|--------------------------------------------------------------------------
--}}

@props([
    'action',
    'label' => 'Cetak PDF',
])

{{-- Definisi Alpine.data — didaftarkan sekali via @pushOnce agar tidak
     duplikat jika komponen dirender lebih dari satu kali di halaman yang
     sama. Mengikuti pola Alpine.data() yang digunakan seluruh proyek ini. --}}
@pushOnce('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('exportDateRange', () => ({
            tanggalMulai : '',
            tanggalAkhir : '',
            orientasi    : 'portrait',
            errorMsg     : '',

            // Cek validitas — dipanggil dari :disabled secara reaktif
            valid() {
                return this.tanggalMulai !== ''
                    && this.tanggalAkhir !== ''
                    && new Date(this.tanggalAkhir) >= new Date(this.tanggalMulai);
            },

            // Validasi penuh dengan pesan error
            validate() {
                if (!this.tanggalMulai || !this.tanggalAkhir) {
                    this.errorMsg = 'Tanggal mulai dan tanggal akhir wajib diisi.';
                    return false;
                }
                if (new Date(this.tanggalAkhir) < new Date(this.tanggalMulai)) {
                    this.errorMsg = 'Tanggal akhir tidak boleh sebelum tanggal mulai.';
                    return false;
                }
                this.errorMsg = '';
                return true;
            },

            // Submit form hanya jika lolos validasi
            submit(formEl) {
                if (this.validate()) {
                    formEl.submit();
                }
            },
        }));
    });
</script>
@endPushOnce

<div x-data="exportDateRange()" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

    <div class="mb-4 flex items-center gap-2">
        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-100">
            <svg class="h-4 w-4 text-rose-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                 viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
        </div>
        <p class="text-sm font-semibold text-slate-700">Cetak Laporan Rekapitulasi</p>
    </div>

    <form
        method="GET"
        action="{{ $action }}"
        target="_blank"
        @submit.prevent="submit($el)"
        novalidate
    >
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end">

            {{-- Tanggal Mulai --}}
            <div>
                <label for="tanggal_mulai_export"
                       class="mb-1 block text-xs font-medium text-slate-600">
                    Tanggal Mulai
                </label>
                <input
                    type="date"
                    id="tanggal_mulai_export"
                    name="tanggal_mulai"
                    x-model="tanggalMulai"
                    @change="validate()"
                    class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                           text-slate-800 focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                    required
                    aria-required="true"
                >
            </div>

            {{-- Tanggal Akhir --}}
            <div>
                <label for="tanggal_akhir_export"
                       class="mb-1 block text-xs font-medium text-slate-600">
                    Tanggal Akhir
                </label>
                <input
                    type="date"
                    id="tanggal_akhir_export"
                    name="tanggal_akhir"
                    x-model="tanggalAkhir"
                    :min="tanggalMulai"
                    @change="validate()"
                    class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                           text-slate-800 focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                    required
                    aria-required="true"
                >
            </div>

            {{-- Tombol Cetak --}}
            <div>
                <button
                    type="submit"
                    :disabled="!valid()"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg
                           bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm
                           transition hover:bg-rose-700 focus:outline-none focus:ring-2
                           focus:ring-rose-500 focus:ring-offset-1
                           disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247m-9.5 0a48.524 48.524 0 0 0-1.912.247C4.768 7.44 4 8.374 4 9.456v6.294A2.25 2.25 0 0 0 6.25 18h.75m9.5 0h.75" />
                    </svg>
                    {{ $label }}
                </button>
            </div>

        </div>

        {{-- Orientasi Kertas --}}
        <div class="mt-3 flex flex-wrap items-center gap-4">
            <span class="text-xs font-medium text-slate-500">Orientasi Kertas:</span>

            <label class="flex cursor-pointer items-center gap-1.5">
                <input
                    type="radio"
                    name="orientation"
                    value="portrait"
                    x-model="orientasi"
                    class="h-3.5 w-3.5 accent-rose-600"
                >
                <span class="flex items-center gap-1 text-xs text-slate-600">
                    <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 12 16" fill="none"
                         xmlns="http://www.w3.org/2000/svg">
                        <rect x="1" y="0.5" width="10" height="15" rx="1" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    Portrait
                </span>
            </label>

            <label class="flex cursor-pointer items-center gap-1.5">
                <input
                    type="radio"
                    name="orientation"
                    value="landscape"
                    x-model="orientasi"
                    class="h-3.5 w-3.5 accent-rose-600"
                >
                <span class="flex items-center gap-1 text-xs text-slate-600">
                    <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 16 12" fill="none"
                         xmlns="http://www.w3.org/2000/svg">
                        <rect x="0.5" y="1" width="15" height="10" rx="1" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    Landscape
                </span>
            </label>
        </div>

        {{-- Pesan Validasi Klien --}}
        <p
            x-show="errorMsg"
            x-text="errorMsg"
            x-transition
            class="mt-2 text-xs font-medium text-red-600"
            role="alert"
        ></p>

    </form>
</div>
