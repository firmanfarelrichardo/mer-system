@extends('layouts.app')

@section('judul', 'Pilih Kepala Ruangan — Sistem MER')

@section('konten')

    {{-- HEADER --}}
    <div class="mb-6">
        <a href="{{ route('admin.kepala-ruangan.index') }}"
           class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
            Kembali ke Daftar
        </a>
        <h1 class="text-2xl font-bold text-slate-800">
            {{ $karuSaatIni ? 'Ganti' : 'Pilih' }} Kepala Ruangan
        </h1>
        <p class="mt-1 text-sm text-slate-400">
            Pilih tenaga kesehatan yang akan dipilih sebagai Kepala Ruangan
            di <strong class="text-slate-600">{{ $unit->nama_unit }}</strong>.
        </p>
    </div>

    {{-- INFO UNIT --}}
    <div class="mb-6 flex items-center gap-3 rounded-lg border border-brand/20 bg-brand/5 px-4 py-3">
        <svg class="h-5 w-5 shrink-0 text-brand" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m0 0v2.625"/>
        </svg>
        <div>
            <p class="text-sm font-semibold text-brand">{{ $unit->nama_unit }}</p>
            @if ($unit->kode_unit)
                <p class="text-xs text-brand/70">Kode: {{ $unit->kode_unit }}</p>
            @endif
        </div>
    </div>

    {{-- INFO KARU SAAT INI (jika mengganti) --}}
    @if ($karuSaatIni)
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
            </svg>
            <div>
                <p class="text-sm font-semibold text-amber-800">
                    Unit ini sudah memiliki Kepala Ruangan
                </p>
                <p class="mt-0.5 text-xs text-amber-700">
                    <strong>{{ $karuSaatIni->nama_lengkap }}</strong> ({{ $karuSaatIni->nomor_induk }})
                    akan otomatis dicabut perannya saat Anda menunjuk Karu baru.
                </p>
            </div>
        </div>
    @endif

    {{-- FORM --}}
    <div class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
         x-data="pemilihNakes()"
         x-init="init()">

        <form method="POST" action="{{ route('admin.kepala-ruangan.simpan-penunjukan', $unit->id) }}">
            @csrf

            <div class="mb-6">
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Pilih Nakes <span class="text-red-500">*</span>
                </label>
                <p class="mb-3 text-xs text-slate-400">
                    Hanya menampilkan Nakes aktif dari unit <strong>{{ $unit->nama_unit }}</strong> yang belum menjadi Kepala Ruangan.
                </p>

                @if ($daftarNakes->isEmpty())
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                        Tidak ada Nakes dari unit ini yang tersedia untuk dipilih.
                        Pastikan Nakes sudah terdaftar di unit <strong>{{ $unit->nama_unit }}</strong>.
                    </div>
                @else
                    {{-- Pencarian nama/NIP --}}
                    <div class="relative mb-3">
                        <label for="cariNakes" class="mb-1 block text-xs font-medium text-slate-500">
                            Cari nama atau NIP
                        </label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                                 fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                            </svg>
                            <input id="cariNakes" type="text"
                                   x-model="cari"
                                   placeholder="Ketik nama atau NIP…"
                                   class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm text-slate-700
                                          placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                        </div>
                    </div>

                    {{-- Daftar Nakes --}}
                    <input type="hidden" name="pengguna_id" :value="terpilihId">

                    <div class="max-h-64 overflow-y-auto rounded-lg border border-slate-200"
                         :class="{ 'border-red-300': hasError }">

                        <template x-for="nakes in hasilFilter" :key="nakes.id">
                            <label class="flex cursor-pointer items-center gap-3 border-b border-slate-100 px-4 py-2.5
                                          transition-colors last:border-b-0 hover:bg-slate-50"
                                   :class="{ 'bg-brand/5 ring-1 ring-inset ring-brand/20': terpilihId === nakes.id }">
                                <input type="radio" class="text-brand focus:ring-brand/30"
                                       :value="nakes.id"
                                       x-model.number="terpilihId">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-slate-700" x-text="nakes.nama"></p>
                                    <p class="text-xs text-slate-400" x-text="nakes.nip"></p>
                                </div>
                            </label>
                        </template>

                        <div x-show="hasilFilter.length === 0"
                             class="px-4 py-6 text-center text-sm text-slate-400">
                            Tidak ada Nakes yang cocok dengan filter.
                        </div>
                    </div>

                    <div class="mt-1.5 flex items-center justify-between">
                        <p class="text-xs text-slate-400">
                            Menampilkan <span x-text="hasilFilter.length" class="font-medium"></span>
                            dari {{ $daftarNakes->count() }} Nakes
                        </p>
                        @error('pengguna_id')
                            <p class="text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </div>

            @if ($daftarNakes->isNotEmpty())
                <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-700">
                    <strong>Catatan:</strong>
                    Nakes yang dipilih akan mendapatkan peran tambahan Kepala Ruangan dan unit kerjanya
                    akan diperbarui ke <strong>{{ $unit->nama_unit }}</strong>.
                    Pengguna dapat beralih antara peran Nakes dan Kepala Ruangan melalui menu di navbar.
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit"
                            :disabled="! terpilihId"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white
                                   shadow-sm transition-colors hover:bg-brand/90 focus:outline-none focus:ring-2 focus:ring-brand/50
                                   disabled:cursor-not-allowed disabled:opacity-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                        {{ $karuSaatIni ? 'Ganti Kepala Ruangan' : 'Pilih sebagai Kepala Ruangan' }}
                    </button>
                    <a href="{{ route('admin.kepala-ruangan.index') }}"
                       class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600
                              transition-colors hover:bg-slate-50">
                        Batal
                    </a>
                </div>
            @endif
        </form>
    </div>

    @if ($daftarNakes->isNotEmpty())
    <script>
        function pemilihNakes() {
            return {
                semuaNakes: @json($nakesUntukJs),
                cari: '',
                terpilihId: {{ (int) old('pengguna_id', 0) }},
                hasError: {{ $errors->has('pengguna_id') ? 'true' : 'false' }},

                init() {},

                get hasilFilter() {
                    if (! this.cari.trim()) {
                        return this.semuaNakes;
                    }

                    const kata = this.cari.trim().toLowerCase();

                    return this.semuaNakes.filter(n =>
                        n.nama.toLowerCase().includes(kata) ||
                        n.nip.toLowerCase().includes(kata)
                    );
                },
            };
        }
    </script>
    @endif

@endsection
