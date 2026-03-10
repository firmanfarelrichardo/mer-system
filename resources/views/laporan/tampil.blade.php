{{--
|--------------------------------------------------------------------------
| Detail Laporan Insiden (laporan/tampil.blade.php)
|--------------------------------------------------------------------------
| Halaman detail satu laporan insiden — menggunakan data REAL dari
| model Insiden beserta relasi detailPasien, pelapor, unitKerja,
| dan tindakLanjut.
|
| Variabel dari controller:
|   $insiden   — App\Models\Insiden (eager-loaded relations)
|   $pengguna  — App\Models\Pengguna (auth user)
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', $insiden->nomor_laporan . ' — Detail Laporan')

@section('konten')

    @php
        $isAdmin    = $pengguna->memilikiPeran(\App\Models\Peran::ADMIN);
        $isKomite   = $pengguna->memilikiPeran(\App\Models\Peran::KOMITE);
        $isDirektur = $pengguna->memilikiPeran(\App\Models\Peran::DIREKTUR);
    @endphp

    {{-- ================================================================
         HEADER HALAMAN
         ================================================================ --}}
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-800">{{ $insiden->nomor_laporan }}</h1>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $insiden->warnaStatus() }}">
                    {{ $insiden->labelStatus() }}
                </span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $insiden->warnaInsiden() }}">
                    {{ $insiden->labelTipeInsiden() }}
                </span>
            </div>
            <p class="mt-1 text-sm text-slate-400">
                Dilaporkan pada {{ $insiden->tgl_lapor?->translatedFormat('d F Y, H:i') ?? '—' }} WIB
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if (! $insiden->isDraf())
                <a href="{{ route('laporan.cetak-pdf', $insiden->id) }}" target="_blank"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-brand bg-brand
                          px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors
                          hover:bg-brand-hover">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1
                                 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096
                                 L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662
                                 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21
                                 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913
                                 -.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015
                                 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0
                                 -10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621
                                 0-1.125.504-1.125 1.125v3.659M18.25 7.28H5.75" />
                    </svg>
                    Cetak PDF
                </a>
            @endif
            <a href="{{ route('laporan.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white
                      px-4 py-2 text-sm font-medium text-slate-600 shadow-sm transition-colors
                      hover:bg-slate-50">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Kembali
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-6">

        {{-- ============================================================
             INFORMASI PELAPOR
             ============================================================ --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                <svg class="h-5 w-5 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501
                             20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676
                             0-5.216-.584-7.499-1.632Z" />
                </svg>
                Informasi Pelapor
            </h2>
            @if ($insiden->is_anonim && ! $isAdmin)
                <p class="text-sm italic text-slate-400">Pelapor memilih untuk melapor secara anonim.</p>
            @else
                <div class="grid grid-cols-1 gap-y-3 sm:grid-cols-3 sm:gap-x-6">
                    <div>
                        <p class="text-xs font-medium text-slate-400">Nama Pelapor</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800">
                            {{ blank($insiden->nama_pelapor) ? ($insiden->pelapor?->nama_lengkap ?? '—') : $insiden->nama_pelapor }}
                            @if ($insiden->is_anonim && $isAdmin)
                                <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                                    Disembunyikan: Anonim
                                </span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-400">Nomor Induk</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $insiden->pelapor?->nomor_induk ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-400">Unit Kerja</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $insiden->nama_unit_kerja ?? $insiden->unitKerja?->nama_unit ?? '—' }}</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- ============================================================
             DATA PASIEN
             ============================================================ --}}
        @if ($insiden->detailPasien)
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                    <svg class="h-5 w-5 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0
                                 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25
                                 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75
                                 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789
                                 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                    </svg>
                    Data Pasien
                </h2>
                <div class="grid grid-cols-1 gap-y-3 sm:grid-cols-2 lg:grid-cols-3 sm:gap-x-6">
                    <div>
                        <p class="text-xs font-medium text-slate-400">Nama Pasien</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $insiden->detailPasien->nama_pasien }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-400">No. Rekam Medis</p>
                        <p class="mt-0.5 font-mono text-sm font-medium text-slate-800">{{ $insiden->detailPasien->nomor_rekam_medis ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-400">Obat Terkait</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $insiden->detailPasien->obat_terkait ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-400">Dosis Obat</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $insiden->detailPasien->dosis_obat ?? '—' }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- ============================================================
             DETAIL KEJADIAN
             ============================================================ --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                <svg class="h-5 w-5 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
                Detail Kejadian
            </h2>
            <div class="grid grid-cols-1 gap-y-3 sm:grid-cols-3 sm:gap-x-6">
                <div>
                    <p class="text-xs font-medium text-slate-400">Tanggal Kejadian</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $insiden->tgl_kejadian?->translatedFormat('d F Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-400">Waktu Kejadian</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $insiden->tgl_kejadian?->format('H:i') ?? '—' }} WIB</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-400">Fase Kesalahan</p>
                    <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $insiden->fase_kesalahan ?? '—' }}</p>
                </div>
            </div>
            @if ($insiden->detailPasien?->kronologi)
                <div class="mt-4">
                    <p class="text-xs font-medium text-slate-400">Kronologi Kejadian</p>
                    <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $insiden->detailPasien->kronologi }}</p>
                </div>
            @endif
        </div>

        {{-- ============================================================
             KLASIFIKASI INSIDEN
             ============================================================ --}}
        @if ($insiden->detailPasien)
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                    <svg class="h-5 w-5 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659
                                 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0
                                 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0
                                 0 0 9.568 3Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                    </svg>
                    Klasifikasi Insiden
                </h2>
                <div class="grid grid-cols-1 gap-y-4 sm:grid-cols-2 lg:grid-cols-3 sm:gap-x-6">
                    <div>
                        <p class="text-xs font-medium text-slate-400">Tipe Insiden</p>
                        <div class="mt-1">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $insiden->warnaInsiden() }}">
                                {{ $insiden->labelTipeInsiden() }}
                            </span>
                        </div>
                    </div>
                    @if ($insiden->detailPasien->jenis_kesalahan)
                        <div>
                            <p class="text-xs font-medium text-slate-400">Jenis Kesalahan</p>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach ((array) $insiden->detailPasien->jenis_kesalahan as $jk)
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ Str::headline(str_replace('_', ' ', $jk)) }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($insiden->detailPasien->cedera)
                        <div>
                            <p class="text-xs font-medium text-slate-400">Cedera</p>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach ((array) $insiden->detailPasien->cedera as $c)
                                    <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">{{ Str::headline(str_replace('_', ' ', $c)) }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($insiden->detailPasien->faktor_penyebab)
                        <div>
                            <p class="text-xs font-medium text-slate-400">Faktor Penyebab</p>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach ((array) $insiden->detailPasien->faktor_penyebab as $fp)
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{{ Str::headline(str_replace('_', ' ', $fp)) }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($insiden->detailPasien->intervensi_pasien)
                        <div>
                            <p class="text-xs font-medium text-slate-400">Intervensi Pasien</p>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach ((array) $insiden->detailPasien->intervensi_pasien as $ip)
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-600">{{ Str::headline(str_replace('_', ' ', $ip)) }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- ============================================================
             RIWAYAT TINDAK LANJUT (timeline)
             ============================================================ --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                <svg class="h-5 w-5 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                Riwayat Tindak Lanjut
            </h2>

            <div class="relative ml-3 border-l-2 border-slate-200 pl-6">
                {{-- Entri tindak lanjut (Karu / Komite), diurutkan terbaru di atas --}}
                @foreach ($insiden->tindakLanjut->sortByDesc('created_at') as $tl)
                    <div class="relative mb-6 last:mb-0">
                        <div class="absolute -left-[1.9rem] top-1 h-3 w-3 rounded-full border-2 border-white {{ $tl->warnaStatus() ? 'bg-brand' : 'bg-slate-300' }}"></div>
                        <div class="rounded-lg border border-slate-100 bg-slate-50/60 p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $tl->warnaStatus() }}">
                                    {{ $tl->labelStatus() }}
                                </span>
                                <span class="text-xs text-slate-400">
                                    oleh <span class="font-medium text-slate-600">{{ $tl->pengguna?->labelPeranDanUnit() ?? '—' }}</span>
                                    &middot; {{ $tl->created_at?->translatedFormat('d M Y, H:i') }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm leading-relaxed text-slate-700">{{ $tl->catatan }}</p>
                        </div>
                    </div>
                @endforeach

                {{-- Entri paling bawah: unggah laporan oleh Nakes (selalu tampil) --}}
                <div class="relative mb-0">
                    <div class="absolute -left-[1.9rem] top-1 h-3 w-3 rounded-full border-2 border-white bg-slate-300"></div>
                    <div class="rounded-lg border border-slate-100 bg-slate-50/60 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-600">
                                Unggah Laporan
                            </span>
                            <span class="text-xs text-slate-400">
                                oleh <span class="font-medium text-slate-600">
                                    @if ($insiden->is_anonim && $isAdmin)
                                        {{ $insiden->pelapor?->labelPelapor() ?? '—' }}
                                        <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-semibold text-amber-700">Anonim</span>
                                    @else
                                        {{ $insiden->is_anonim ? 'Anonim' : ($insiden->pelapor?->labelPelapor() ?? '—') }}
                                    @endif
                                </span>
                                &middot; {{ $insiden->created_at?->translatedFormat('d M Y, H:i') }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm leading-relaxed text-slate-700">Laporan insiden diunggah ke sistem.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             FORMULIR TINDAK LANJUT (Karu & Komite only)
             ============================================================ --}}
        @can('tindakLanjut', $insiden)
            <div class="rounded-xl border-2 border-dashed border-brand/30 bg-brand/5 p-6">
                <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                    <svg class="h-5 w-5 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582
                                 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1
                                 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25
                                 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25
                                 0 0 1 5.25 6H10" />
                    </svg>
                    Feedback Laporan 
                </h2>

                <form method="POST" action="{{ route('laporan.tindak-lanjut', $insiden->id) }}"
                      data-confirm="Apakah Anda yakin ingin menyimpan tindak lanjut ini?">
                    @csrf
                    @method('PATCH')

                    {{-- Dropdown Status Baru --}}
                    <div class="mb-4">
                        <label for="status_baru" class="block text-sm font-medium text-slate-700">
                            Ubah Status <span class="text-red-500">*</span>
                        </label>
                        {{--
                            Dropdown menampilkan semua status yang tersedia.
                            Karu dan Komite memiliki alur status INDEPENDEN:
                            pilihan tidak dibatasi oleh status yang dipilih peran lain.
                        --}}
                        <select name="status_baru" id="status_baru"
                                class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-4 py-2.5
                                       text-sm text-slate-700 shadow-sm focus:border-brand focus:outline-none
                                       focus:ring-2 focus:ring-brand/20 sm:w-64"
                                required>
                            <option value="">— Pilih status —</option>
                            <option value="investigasi" {{ old('status_baru') === 'investigasi' ? 'selected' : '' }}>Investigasi</option>
                            <option value="tindak_lanjut" {{ old('status_baru') === 'tindak_lanjut' ? 'selected' : '' }}>Tindak Lanjut</option>
                            <option value="selesai" {{ old('status_baru') === 'selesai' ? 'selected' : '' }}>Selesai</option>
                        </select>
                    </div>

                    {{-- Catatan --}}
                    <div class="mb-4">
                        <label for="catatan" class="block text-sm font-medium text-slate-700">
                            Catatan <span class="text-red-500">*</span>
                        </label>
                        <textarea name="catatan" id="catatan" rows="4"
                                  class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-4 py-2.5
                                         text-sm text-slate-700 shadow-sm placeholder:text-slate-300
                                         focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                                  placeholder="Tuliskan catatan yang telah atau akan dilakukan..."
                                  required
                                  minlength="10"
                                  maxlength="2000">{{ old('catatan') }}</textarea>
                        <p class="mt-1 text-xs text-slate-400">Minimal 10 karakter, maksimal 2000 karakter.</p>
                    </div>

                    {{-- Submit --}}
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand px-5 py-2.5 text-sm
                                   font-medium text-white shadow-sm transition-colors hover:bg-brand-hover">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                             viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                        </svg>
                        Simpan
                    </button>
                </form>
            </div>
        @endcan

        {{-- ============================================================
             ESKALASI KE DIREKTUR (Komite Only)
             ============================================================ --}}
        @if ($isKomite)
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                    <svg class="h-5 w-5 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504
                                 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125
                                 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125
                                 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0
                                 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5
                                 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21
                                 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125
                                 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                    Meminta Feedback Direktur
                </h2>

                @if ($insiden->is_eskalasi_direktur)
                    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        <strong>Status:</strong> Laporan ini sudah diteruskan ke Direktur untuk memohon feedback.
                    </div>
                @endif

                <form method="POST" action="{{ route('laporan.eskalasi', $insiden->id) }}"
                      data-confirm="{{ $insiden->is_eskalasi_direktur ? 'Apakah Anda yakin ingin membatalkan eskalasi ke Direktur?' : 'Apakah Anda yakin ingin mengekskalasikan laporan ini ke Direktur?' }}">
                    @csrf
                    @method('PATCH')

                    @if ($insiden->is_eskalasi_direktur)
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white
                                       px-5 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition-colors
                                       hover:bg-slate-50">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                 viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                            Batalkan Permintaan
                        </button>
                    @else
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-amber-500 px-5 py-2.5
                                       text-sm font-medium text-white shadow-sm transition-colors hover:bg-amber-600">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                 viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504
                                         1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125
                                         1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125
                                         1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0
                                         .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5
                                         4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21
                                         4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125
                                         0 0 1-1.125-1.125V4.125Z" />
                            </svg>
                            Eskalasikan ke Direktur (Butuh Kebijakan Eksekutif)
                        </button>
                    @endif
                </form>
            </div>
        @endif

        {{-- ============================================================
             FORMULIR ARAHAN DIREKTUR (Direktur Only — jika dieskalasikan)
             ============================================================ --}}
        @if ($isDirektur && $insiden->is_eskalasi_direktur && is_null($insiden->solusi_direktur))
            <div class="rounded-xl border-2 border-dashed border-amber-400/50 bg-amber-50/30 p-6">
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    <div class="flex items-start gap-2">
                        <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-amber-500" xmlns="http://www.w3.org/2000/svg"
                             fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73
                                     0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898
                                     0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        <span>Laporan ini dieskalasikan oleh Komite untuk memohon arahan/kebijakan Direktur.</span>
                    </div>
                </div>

                <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                    <svg class="h-5 w-5 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582
                                 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1
                                 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25
                                 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25
                                 0 0 1 5.25 6H10" />
                    </svg>
                    Berikan Arahan / Solusi Eksekutif
                </h2>

                <form method="POST" action="{{ route('laporan.solusi-direktur', $insiden->id) }}"
                      data-confirm="Apakah Anda yakin ingin menyimpan arahan eksekutif ini?">
                    @csrf
                    @method('PATCH')

                    <div class="mb-4">
                        <label for="solusi_direktur" class="block text-sm font-medium text-slate-700">
                            Arahan / Kebijakan Direktur <span class="text-red-500">*</span>
                        </label>
                        <textarea name="solusi_direktur" id="solusi_direktur" rows="4"
                                  class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-4 py-2.5
                                         text-sm text-slate-700 shadow-sm placeholder:text-slate-300
                                         focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/20"
                                  placeholder="Tuliskan arahan, kebijakan, atau solusi eksekutif untuk laporan ini..."
                                  required
                                  minlength="10"
                                  maxlength="5000">{{ old('solusi_direktur') }}</textarea>
                        <p class="mt-1 text-xs text-slate-400">Minimal 10 karakter, maksimal 5000 karakter.</p>
                    </div>

                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-amber-500 px-5 py-2.5 text-sm
                                   font-medium text-white shadow-sm transition-colors hover:bg-amber-600">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                             viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                        </svg>
                        Simpan Arahan Direktur
                    </button>
                </form>
            </div>
        @endif

        {{-- ============================================================
             INSTRUKSI EKSEKUTIF / ARAHAN DIREKTUR (Read-Only — All Roles)
             ============================================================ --}}
        @if ($insiden->solusi_direktur)
            <div class="rounded-xl border-2 border-amber-300 bg-gradient-to-br from-amber-50 via-white to-amber-50/50 p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                    <svg class="h-5 w-5 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0
                                 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563
                                 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562
                                 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562
                                 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563
                                 0 0 0 .475-.345L11.48 3.5Z" />
                    </svg>
                    Feedback
                </h2>
                <div class="rounded-lg border border-amber-200 bg-white p-4">
                    <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $insiden->solusi_direktur }}</p>
                </div>
                <p class="mt-3 text-xs text-slate-400">
                    Disampaikan pada {{ $insiden->waktu_solusi_direktur?->translatedFormat('d F Y, H:i') ?? '—' }} WIB
                </p>
            </div>
        @endif

    </div>

@endsection
