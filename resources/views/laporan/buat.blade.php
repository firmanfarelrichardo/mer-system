{{--
|--------------------------------------------------------------------------
| Formulir Buat Laporan Insiden — Multi-Step Wizard
|--------------------------------------------------------------------------
| Halaman formulir 4 tahap untuk membuat laporan insiden medication error:
|   1. Data Demografis   — data pasien, lokasi, waktu, jenis insiden
|   2. Detail Insiden     — klasifikasi kesalahan, cedera, faktor, intervensi
|   3. Kronologi Kejadian — narasi kronologi, disclaimer non-hukum
|   4. Konfirmasi         — ringkasan & persetujuan sebelum kirim
|
| Navigasi antar tahap menggunakan vanilla JavaScript (tanpa library).
| Data form dipertahankan di DOM — tidak ada request HTTP antar langkah.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Buat Laporan — Sistem MER')

@section('konten')

    @php
        $pengguna = Auth::user();
        $namaUnit = $pengguna->unitKerja?->nama_unit ?? '—';

        // Opsi jenis insiden sesuai standar keselamatan pasien RS.
        $jenisInsiden = [
            'KPC'      => ['label' => 'Kondisi Potensial Cedera (KPC)',   'deskripsi' => 'Situasi yang berpotensi menimbulkan cedera, tetapi belum terjadi insiden.'],
            'KNC'      => ['label' => 'Kejadian Nyaris Cedera (KNC)',     'deskripsi' => 'Insiden yang belum sampai terpapar ke pasien karena terhentikan atau disadari sebelum tindakan.'],
            'KTC'      => ['label' => 'Kejadian Tidak Cedera (KTC)',      'deskripsi' => 'Insiden sudah terpapar ke pasien, tetapi tidak menimbulkan cedera.'],
            'KTD'      => ['label' => 'Kejadian Tidak Diharapkan (KTD)',  'deskripsi' => 'Insiden yang mengakibatkan cedera pada pasien akibat tindakan medis, bukan penyakit dasarnya.'],
            'SENTINEL' => ['label' => 'Kejadian Sentinel',                'deskripsi' => 'KTD yang mengakibatkan kematian, cedera permanen, atau cedera berat sementara.'],
        ];

        // Fase kesalahan obat (medication error phase).
        $faseKesalahan = [
            'prescribing'    => 'Tahap Peresepan (Prescribing Error)',
            'transcribing'   => 'Tahap Penerjemahan Resep (Transcribing Error)',
            'dispensing'     => 'Tahap Menyiapkan/Peracikan Obat (Dispensing Error)',
            'administration' => 'Tahap Penyerahan Obat kepada Pasien (Administration Error)',
        ];

        // ---------- Master data checkbox dikirim dari Controller ----------
        // $masterJenisKesalahan, $masterTipeCedera, $masterFaktorPenyebab, $masterIntervensi
        // masing-masing berisi Collection of Eloquent model (kolom: id, nama, is_aktif).
    @endphp

    {{-- ================================================================
         HEADER HALAMAN
         ================================================================ --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Formulir Pelaporan Kesalahan Pengobatan</h1>
        <p class="mt-1 text-sm text-slate-400">Sistem Pelaporan Kesalahan Pengobatan</p>
    </div>

    {{-- ================================================================
         STEP INDICATOR
         ================================================================ --}}
    <div id="step-indicator" class="mb-8">
        <div class="mx-auto flex w-full max-w-3xl items-start justify-center px-2 sm:px-0">
            @foreach (['Data Demografis', 'Detail Insiden', 'Kronologi', 'Konfirmasi'] as $i => $label)
                <div class="flex w-16 shrink-0 flex-col items-center sm:w-24">
                    <div id="step-circle-{{ $i + 1 }}"
                         class="flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-bold transition-all duration-300
                                {{ $i === 0
                                    ? 'border-brand bg-brand text-white'
                                    : 'border-slate-300 bg-white text-slate-400' }}">
                        <span class="step-number">{{ $i + 1 }}</span>
                        <svg class="step-check hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                    </div>
                    <span id="step-label-{{ $i + 1 }}"
                          class="mt-2 text-center text-[10px] font-medium leading-tight transition-colors duration-300 sm:text-xs
                                 {{ $i === 0 ? 'text-brand' : 'text-slate-400' }}">
                        {{ $label }}
                    </span>
                </div>
                
                @if ($i < 3)
                    <div id="step-line-{{ $i + 1 }}"
                         class="mx-1 mt-5 h-0.5 flex-auto rounded-full bg-slate-200 transition-colors duration-300 sm:mx-2 sm:max-w-[6rem]"></div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- ================================================================
         FORMULIR UTAMA
         ================================================================ --}}
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4">
            <p class="mb-2 text-sm font-semibold text-red-700">Terdapat kesalahan pada formulir:</p>
            <ul class="list-inside list-disc space-y-1 text-sm text-red-600">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="form-laporan" method="POST" action="{{ route('laporan.simpan') }}" novalidate
          x-data="autoSaveForm()" @input.debounce.2000ms="simpanBackground" @change.debounce.2000ms="simpanBackground">
        @csrf
        {{-- Hidden field: ID draf untuk auto-save (diisi otomatis oleh Alpine.js) --}}
        <input type="hidden" name="insiden_id" :value="insidenId">

        {{-- ============================================================
             TAHAP 1: DATA DEMOGRAFIS
             ============================================================ --}}
        <div id="step-1" class="step-panel">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-1 text-lg font-bold text-slate-800">Bagian A — Data Demografis</h2>
                <p class="mb-6 text-sm text-slate-400">Lengkapi data dasar pasien dan kejadian</p>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    {{-- Nama Pasien --}}
                    <div>
                        <label for="nama_pasien" class="mb-1 block text-xs font-medium text-slate-500">
                            Nama Pasien <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nama_pasien" id="nama_pasien" required
                               placeholder="Masukkan nama lengkap pasien"
                               value="{{ old('nama_pasien') }}"
                               class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                      placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                        @error('nama_pasien')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- No. Rekam Medis --}}
                    <div>
                        <label for="nomor_rekam_medis" class="mb-1 block text-xs font-medium text-slate-500">
                            No. Rekam Medis <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nomor_rekam_medis" id="nomor_rekam_medis" required
                               placeholder="Contoh: RM-20260001"
                               value="{{ old('nomor_rekam_medis') }}"
                               class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                      placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                        @error('nomor_rekam_medis')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Unit Kerja --}}
                    <div>
                        <label for="unit_kerja" class="mb-1 block text-xs font-medium text-slate-500">
                            Unit Kerja <span class="text-red-500">*</span>
                        </label>
                        @php
                            $daftarUnitKerja = [
                                'Poli Anak', 'Poli Kulit Kelamin', 'Poli Saraf', 'Poli Penyakit Dalam',
                                'Poli Gigi', 'Poli Mata', 'Poli THT', 'Poli Kebidanan', 'Poli Bedah',
                                'Poli Paru', 'Poli Tumbuh Kembang Anak', 'Poli Orthopedi', 'Poli Anestesi',
                                'Poli Jiwa', 'Ruang Saraf', 'Ruang Anak', 'Ruang Kebidanan',
                                'Instalasi Bedah Sentral (IBS)', 'Ruang Anestesi', 'Ruang Bedah',
                                'Ruang Penyakit Dalam', 'Ruang VIP', 'Instalasi Farmasi', 'ICU', 'IGD',
                                'Ruang Neonatus', 'Ruang Paru', 'Ruang PONEK', 'Ruang HD', 'Ruang VK',
                                'Ruang Isolasi B',
                            ];
                        @endphp
                        <select name="unit_kerja" id="unit_kerja" required
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                       focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                            <option value="">Pilih unit kerja</option>
                            @foreach ($daftarUnitKerja as $unit)
                                <option value="{{ $unit }}" {{ old('unit_kerja') === $unit ? 'selected' : '' }}>
                                    {{ $unit }}
                                </option>
                            @endforeach
                        </select>
                        @error('unit_kerja')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Tanggal Kejadian --}}
                    <div>
                        <label for="tanggal_kejadian" class="mb-1 block text-xs font-medium text-slate-500">
                            Tanggal Kejadian <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="tanggal_kejadian" id="tanggal_kejadian" required
                               value="{{ old('tanggal_kejadian') }}"
                               class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                      focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                        @error('tanggal_kejadian')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Waktu Kejadian --}}
                    <div>
                        <label for="waktu_kejadian" class="mb-1 block text-xs font-medium text-slate-500">
                            Waktu Kejadian <span class="text-red-500">*</span>
                        </label>
                        <input type="time" name="waktu_kejadian" id="waktu_kejadian" required
                               value="{{ old('waktu_kejadian') }}"
                               class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                      focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                        @error('waktu_kejadian')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Jenis Insiden --}}
                <div class="mt-6">
                    <label class="mb-3 block text-xs font-medium text-slate-500">
                        Jenis Insiden <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($jenisInsiden as $kode => $info)
                            <label class="group relative flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-4 transition-all
                                          hover:border-brand/40 hover:bg-brand/5 has-[:checked]:border-brand has-[:checked]:bg-brand/5 has-[:checked]:ring-1 has-[:checked]:ring-brand/30">
                                <input type="radio" name="jenis_insiden" value="{{ $kode }}"
                                       {{ old('jenis_insiden') === $kode ? 'checked' : '' }}
                                       class="mt-0.5 h-4 w-4 border-slate-300 text-brand focus:ring-brand/30">
                                <div>
                                    <span class="text-sm font-semibold text-slate-700">{{ $info['label'] }}</span>
                                    <p class="mt-0.5 text-xs leading-relaxed text-slate-400">{{ $info['deskripsi'] }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('jenis_insiden')
                        <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Identitas Pelapor (Opsional) --}}
                <div class="mt-6 border-t border-slate-100 pt-6">
                    <h3 class="text-sm font-semibold text-slate-600">Identitas Pelapor <span class="font-normal text-slate-400">(Opsional)</span></h3>
                    <p class="mb-4 text-xs text-slate-400">
                        Jika Anda ingin identitas Anda diketahui, silakan isi informasi berikut. Jika tidak, Anda dapat mengosongkannya untuk pelaporan anonim.
                    </p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="nama_pelapor" class="mb-1 block text-xs font-medium text-slate-500">Nama Pelapor</label>
                            <input type="text" name="nama_pelapor" id="nama_pelapor"
                                   placeholder="Nama lengkap (opsional)"
                                   value="{{ old('nama_pelapor') }}"
                                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                          placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                        </div>
                        <div>
                            <label for="kontak_pelapor" class="mb-1 block text-xs font-medium text-slate-500">Kontak Pelapor</label>
                            <input type="text" name="kontak_pelapor" id="kontak_pelapor"
                                   placeholder="Email atau No. HP (opsional)"
                                   value="{{ old('kontak_pelapor') }}"
                                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                          placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             TAHAP 2: DETAIL INSIDEN
             ============================================================ --}}
        <div id="step-2" class="step-panel hidden">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-1 text-lg font-bold text-slate-800">Bagian B — Karakteristik Insiden</h2>
                <p class="mb-6 text-sm text-slate-400">Detail Tahapan, jenis kesalahan, cedera, faktor penyebab, dan intervensi pasien</p>

                {{-- 1. Fase Kesalahan Obat (dipindahkan ke posisi pertama) --}}
                <fieldset class="mb-6">
                    <legend class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand text-[11px] font-bold text-white">1</span>
                        Tahap Kesalahan Pengobatan <span class="text-red-500">*</span>
                    </legend>
                    <p class="mb-3 text-xs text-slate-400">Pilih tahapan kesalahan pengobatan</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($faseKesalahan as $kode => $label)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 p-4 transition-all
                                          hover:border-brand/40 hover:bg-brand/5 has-[:checked]:border-brand has-[:checked]:bg-brand/5 has-[:checked]:ring-1 has-[:checked]:ring-brand/30">
                                <input type="radio" name="fase_kesalahan" value="{{ $kode }}"
                                       {{ old('fase_kesalahan') === $kode ? 'checked' : '' }}
                                       class="h-4 w-4 border-slate-300 text-brand focus:ring-brand/30">
                                <span class="text-sm font-medium text-slate-600">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('fase_kesalahan')
                        <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </fieldset>

                {{-- 2. Jenis Kesalahan --}}
                <fieldset class="mb-6">
                    <legend class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand text-[11px] font-bold text-white">2</span>
                        Details — Jenis Kesalahan <span class="text-red-500">*</span>
                    </legend>
                    <p class="mb-3 text-xs text-slate-400">Pilih semua jenis kesalahan yang terjadi</p>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($masterJenisKesalahan as $item)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2.5 text-sm transition-all
                                          hover:border-brand/40 hover:bg-brand/5 has-[:checked]:border-brand has-[:checked]:bg-brand/5">
                                <input type="checkbox" name="jenis_kesalahan[]" value="{{ $item->nama }}"
                                       {{ is_array(old('jenis_kesalahan')) && in_array($item->nama, old('jenis_kesalahan')) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand/30">
                                <span class="text-slate-600">{{ $item->nama }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-2">
                        <input type="text" name="jenis_kesalahan_lainnya" placeholder="Lainnya, sebutkan..."
                               value="{{ old('jenis_kesalahan_lainnya') }}"
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700
                                      placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                    </div>
                    @error('jenis_kesalahan')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </fieldset>

                {{-- 3. Cedera yang Terjadi --}}
                <fieldset class="mb-6">
                    <legend class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand text-[11px] font-bold text-white">3</span>
                        Injuries — Cedera yang Terjadi/Efek Kesalahan Pengobatan <span class="text-red-500">*</span>
                    </legend>
                    <p class="mb-3 text-xs text-slate-400">Pilih semua dampak cedera yang dialami pasien</p>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($masterTipeCedera as $item)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2.5 text-sm transition-all
                                          hover:border-brand/40 hover:bg-brand/5 has-[:checked]:border-brand has-[:checked]:bg-brand/5
                                          {{ $item->nama === 'Meninggal' ? 'has-[:checked]:border-red-400 has-[:checked]:bg-red-50' : '' }}">
                                <input type="checkbox" name="cedera[]" value="{{ $item->nama }}"
                                       {{ is_array(old('cedera')) && in_array($item->nama, old('cedera')) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand/30">
                                <span class="{{ $item->nama === 'Meninggal' ? 'font-medium text-red-600' : 'text-slate-600' }}">
                                    {{ $item->nama }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-2">
                        <input type="text" name="cedera_lainnya" placeholder="Lainnya, sebutkan..."
                               value="{{ old('cedera_lainnya') }}"
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700
                                      placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                    </div>
                    @error('cedera')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </fieldset>

                {{-- 4. Faktor Penyebab --}}
                <fieldset class="mb-6">
                    <legend class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand text-[11px] font-bold text-white">4</span>
                        Contributing Factors — Faktor Penyebab <span class="text-red-500">*</span>
                    </legend>
                    <p class="mb-3 text-xs text-slate-400">Pilih faktor-faktor yang berkontribusi terhadap insiden</p>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($masterFaktorPenyebab as $item)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2.5 text-sm transition-all
                                          hover:border-brand/40 hover:bg-brand/5 has-[:checked]:border-brand has-[:checked]:bg-brand/5">
                                <input type="checkbox" name="faktor_penyebab[]" value="{{ $item->nama }}"
                                       {{ is_array(old('faktor_penyebab')) && in_array($item->nama, old('faktor_penyebab')) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand/30">
                                <span class="text-slate-600">{{ $item->nama }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-2">
                        <input type="text" name="faktor_penyebab_lainnya" placeholder="Lainnya, sebutkan..."
                               value="{{ old('faktor_penyebab_lainnya') }}"
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700
                                      placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                    </div>
                    @error('faktor_penyebab')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </fieldset>

                {{-- 5. Intervensi Pasien --}}
                <fieldset class="mb-6">
                    <legend class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand text-[11px] font-bold text-white">5</span>
                        Patient Interventions — Intervensi Pasien <span class="text-red-500">*</span>
                    </legend>
                    <p class="mb-3 text-xs text-slate-400">Pilih tindakan yang dilakukan terhadap pasien</p>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($masterIntervensi as $item)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2.5 text-sm transition-all
                                          hover:border-brand/40 hover:bg-brand/5 has-[:checked]:border-brand has-[:checked]:bg-brand/5">
                                <input type="checkbox" name="intervensi_pasien[]" value="{{ $item->nama }}"
                                       {{ is_array(old('intervensi_pasien')) && in_array($item->nama, old('intervensi_pasien')) ? 'checked' : '' }}
                                       class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand/30">
                                <span class="text-slate-600">{{ $item->nama }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-2">
                        <input type="text" name="intervensi_pasien_lainnya" placeholder="Lainnya, sebutkan..."
                               value="{{ old('intervensi_pasien_lainnya') }}"
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700
                                      placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                    </div>
                    @error('intervensi_pasien')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </fieldset>

                {{-- 6. Nama Obat Terlibat --}}
                <div>
                    <label for="nama_obat" class="mb-1 block text-xs font-medium text-slate-500">
                        Nama Obat yang Terlibat <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nama_obat" id="nama_obat" required
                           placeholder="Contoh: Amoxicillin 500mg"
                           value="{{ old('nama_obat') }}"
                           class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                  placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                    @error('nama_obat')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- 7. Dosis Obat --}}
                <div>
                    <label for="dosis_obat" class="mb-1 block text-xs font-medium text-slate-500">
                        Dosis Obat
                    </label>
                    <input type="text" name="dosis_obat" id="dosis_obat"
                           placeholder="Contoh: 500mg, 2x sehari"
                           value="{{ old('dosis_obat') }}"
                           class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                  placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                    @error('dosis_obat')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- ============================================================
             TAHAP 3: KRONOLOGI KEJADIAN
             ============================================================ --}}
        <div id="step-3" class="step-panel hidden">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-1 text-lg font-bold text-slate-800">Bagian C — Kronologi Kejadian</h2>
                <p class="mb-6 text-sm text-slate-400">Ceritakan kronologi kejadian secara lengkap</p>

                <div class="mb-6">
                    <label for="kronologi_kejadian" class="mb-1 block text-xs font-medium text-slate-500">
                        Kronologi Kejadian <span class="text-red-500">*</span>
                    </label>
                    <textarea name="kronologi_kejadian" id="kronologi_kejadian" rows="6" required maxlength="2000"
                              placeholder="Jelaskan secara detail kronologi kejadian insiden, termasuk waktu, situasi, pihak yang terlibat, dan tindakan yang sudah dilakukan..."
                              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                     placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                              oninput="document.getElementById('char-count').textContent=this.value.length">{{ old('kronologi_kejadian') }}</textarea>
                    <p class="mt-1 text-right text-xs text-slate-400">
                        <span id="char-count">{{ Str::length(old('kronologi_kejadian', '')) }}</span> / 2000 karakter
                    </p>
                    @error('kronologi_kejadian')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Kebijakan Non-Hukum --}}
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.168 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <h4 class="text-sm font-semibold text-amber-800">Kebijakan Non-Hukum</h4>
                            <p class="mt-1 text-xs leading-relaxed text-amber-700">
                                Pelaporan insiden ini bersifat rahasia dan non-hukum (non-punitive). Laporan ini ditujukan semata-mata untuk
                                pembelajaran dan perbaikan sistem, bukan untuk menyalahkan individu. Identitas pelapor dilindungi sesuai
                                kebijakan keselamatan pasien rumah sakit.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" name="pernyataan_kronologi" id="pernyataan_kronologi" value="1"
                               {{ old('pernyataan_kronologi') ? 'checked' : '' }}
                               class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand/30">
                        <span class="text-sm leading-relaxed text-slate-600">
                            Saya menyatakan bahwa informasi yang saya berikan adalah benar dan akurat sesuai dengan kejadian
                            yang terjadi. Saya memahami bahwa laporan ini bersifat rahasia dan non-hukum.
                        </span>
                    </label>
                </div>
            </div>
        </div>

        {{-- ============================================================
             TAHAP 4: KONFIRMASI
             ============================================================ --}}
        <div id="step-4" class="step-panel hidden">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-1 text-lg font-bold text-slate-800">Ringkasan Laporan</h2>
                <p class="mb-6 text-sm text-slate-400">Periksa kembali informasi laporan sebelum dikirim</p>

                {{-- Ringkasan: Data Demografis --}}
                <div class="mb-6">
                    <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand text-[11px] font-bold text-white">1</span>
                        Data Demografis
                    </h3>
                    <div class="rounded-lg border border-slate-100 bg-slate-50 p-4">
                        <dl class="grid grid-cols-1 gap-y-2 text-sm sm:grid-cols-[auto_1fr] sm:gap-x-6">
                            <dt class="text-slate-400">Nama Pasien:</dt>
                            <dd id="ringkasan-nama_pasien" class="font-medium text-slate-700">—</dd>
                            <dt class="text-slate-400">No. Rekam Medis:</dt>
                            <dd id="ringkasan-nomor_rekam_medis" class="font-medium text-slate-700">—</dd>
                            <dt class="text-slate-400">Unit Kerja:</dt>
                            <dd id="ringkasan-unit_kerja" class="font-medium text-slate-700">—</dd>
                            <dt class="text-slate-400">Waktu Kejadian:</dt>
                            <dd id="ringkasan-waktu_kejadian" class="font-medium text-slate-700">—</dd>
                            <dt class="text-slate-400">Jenis Insiden:</dt>
                            <dd id="ringkasan-jenis_insiden" class="font-medium text-slate-700">—</dd>
                        </dl>
                    </div>
                </div>

                {{-- Ringkasan: Detail Insiden --}}
                <div class="mb-6">
                    <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand text-[11px] font-bold text-white">2</span>
                        Detail Insiden
                    </h3>
                    <div class="rounded-lg border border-slate-100 bg-slate-50 p-4">
                        <dl class="grid grid-cols-1 gap-y-2 text-sm">
                            <dt class="text-slate-400">Jenis Kesalahan:</dt>
                            <dd id="ringkasan-jenis_kesalahan" class="font-medium text-slate-700">—</dd>
                            <dt class="mt-1 text-slate-400">Cedera yang Terjadi:</dt>
                            <dd id="ringkasan-cedera" class="font-medium text-slate-700">—</dd>
                            <dt class="mt-1 text-slate-400">Faktor Penyebab:</dt>
                            <dd id="ringkasan-faktor_penyebab" class="font-medium text-slate-700">—</dd>
                            <dt class="mt-1 text-slate-400">Intervensi Pasien:</dt>
                            <dd id="ringkasan-intervensi_pasien" class="font-medium text-slate-700">—</dd>
                            <dt class="mt-1 text-slate-400">Fase Kesalahan:</dt>
                            <dd id="ringkasan-fase_kesalahan" class="font-medium text-slate-700">—</dd>
                            <dt class="mt-1 text-slate-400">Obat Terlibat:</dt>
                            <dd id="ringkasan-nama_obat" class="font-medium text-slate-700">—</dd>
                            <dt class="mt-1 text-slate-400">Dosis Obat:</dt>
                            <dd id="ringkasan-dosis_obat" class="font-medium text-slate-700">—</dd>
                        </dl>
                    </div>
                </div>

                {{-- Ringkasan: Kronologi --}}
                <div class="mb-6">
                    <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand text-[11px] font-bold text-white">3</span>
                        Kronologi Kejadian
                    </h3>
                    <div class="rounded-lg border border-slate-100 bg-slate-50 p-4">
                        <p id="ringkasan-kronologi_kejadian" class="whitespace-pre-line text-sm text-slate-700">—</p>
                    </div>
                </div>

                {{-- Info Pelaporan --}}
                <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <h4 id="ringkasan-status-anonim" class="text-sm font-semibold text-blue-800">Pelaporan Anonim</h4>
                            <p id="ringkasan-info-anonim" class="mt-0.5 text-xs text-blue-700">
                                Laporan ini bersifat anonim. Identitas pelapor tidak akan tercantum dalam sistem.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Konfirmasi Pengiriman --}}
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.168 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <h4 class="text-sm font-semibold text-amber-800">Konfirmasi Pengiriman</h4>
                            <p class="mt-0.5 text-xs text-amber-700">
                                Dengan mengirim laporan ini, Anda menyatakan bahwa informasi yang diberikan adalah benar dan akurat.
                                Laporan ini akan segera ditinjau oleh Kepala Ruang.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" id="konfirmasi_kirim" name="konfirmasi_kirim" value="1"
                               class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand/30">
                        <span class="text-sm leading-relaxed text-slate-600">
                            Saya telah memeriksa dan mengonfirmasi semua informasi di atas sudah benar
                        </span>
                    </label>
                </div>
            </div>
        </div>

        {{-- ============================================================
             TOMBOL NAVIGASI
             ============================================================ --}}
        <div class="mt-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button type="button" id="btn-kembali"
                        class="hidden items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-5 py-2.5
                               text-sm font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50"
                        onclick="ubahTahap(-1)">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                    </svg>
                    Kembali
                </button>

                {{-- Indikator Status Auto-Save --}}
                <div class="flex items-center gap-1.5 text-xs transition-all duration-300">
                    {{-- Saving --}}
                    <template x-if="statusAutoSave === 'saving'">
                        <span class="inline-flex items-center gap-1 text-amber-600">
                            <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Menyimpan...
                        </span>
                    </template>
                    {{-- Saved --}}
                    <template x-if="statusAutoSave === 'saved'">
                        <span class="inline-flex items-center gap-1 text-emerald-600">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                            Draf tersimpan <span x-text="waktuTerakhir"></span>
                        </span>
                    </template>
                    {{-- Error --}}
                    <template x-if="statusAutoSave === 'error'">
                        <span class="inline-flex items-center gap-1 text-red-500">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                            </svg>
                            Gagal menyimpan
                        </span>
                    </template>
                </div>
            </div>

            <div class="ml-auto flex items-center gap-3">
                <button type="submit" name="action" value="simpan_draf" id="btn-draf"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-brand bg-white px-5 py-2.5
                               text-sm font-medium text-brand shadow-sm transition-colors hover:bg-brand/5">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z"/>
                    </svg>
                    Simpan Draf
                </button>

                <button type="button" id="btn-selanjutnya"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-5 py-2.5 text-sm font-medium
                               text-white shadow-sm transition-colors hover:bg-brand-hover focus:outline-none focus:ring-2
                               focus:ring-brand/50 focus:ring-offset-2"
                        onclick="ubahTahap(1)">
                    Selanjutnya
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>

                <button type="submit" name="action" value="kirim_laporan" id="btn-kirim"
                        class="hidden items-center gap-1.5 rounded-lg bg-brand px-5 py-2.5 text-sm font-medium
                               text-white shadow-sm transition-colors hover:bg-brand-hover focus:outline-none focus:ring-2
                               focus:ring-brand/50 focus:ring-offset-2">
                    Kirim Laporan
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                </button>
            </div>
        </div>
    </form>

    {{-- ================================================================
         JAVASCRIPT — Navigasi Multi-Step Wizard
         ================================================================ --}}
    {{-- Auto-save Alpine.js component --}}
    @php $autoSaveInsidenId = null; @endphp
    @include('laporan._auto-save')

    <script>
        (() => {
            'use strict';

            /** Tahap aktif saat ini (1-based). */
            let tahapAktif = 1;
            const TOTAL_TAHAP = 4;

            /** Label referensi untuk ringkasan. */
            const labelJenisInsiden  = @json(collect($jenisInsiden)->mapWithKeys(fn($v, $k) => [$k => $v['label']]));
            const labelFaseKesalahan = @json($faseKesalahan);

            /**
             * Mengubah tahap aktif wizard.
             * @param {number} arah — +1 (maju) atau -1 (mundur)
             */
            window.ubahTahap = function(arah) {
                const tahapBaru = tahapAktif + arah;
                if (tahapBaru < 1 || tahapBaru > TOTAL_TAHAP) return;

                // Isi ringkasan saat masuk ke step konfirmasi.
                if (tahapBaru === TOTAL_TAHAP) isiRingkasan();

                // Sembunyikan panel lama, tampilkan panel baru.
                document.getElementById('step-' + tahapAktif).classList.add('hidden');
                document.getElementById('step-' + tahapBaru).classList.remove('hidden');

                perbaruiIndikator(tahapBaru);
                tahapAktif = tahapBaru;

                // Atur visibilitas tombol.
                document.getElementById('btn-kembali').style.display     = tahapAktif > 1 ? 'inline-flex' : 'none';
                document.getElementById('btn-selanjutnya').style.display  = tahapAktif < TOTAL_TAHAP ? 'inline-flex' : 'none';
                document.getElementById('btn-kirim').style.display        = tahapAktif === TOTAL_TAHAP ? 'inline-flex' : 'none';

                window.scrollTo({ top: 0, behavior: 'smooth' });
            };

            /** Perbarui tampilan step indicator. */
            function perbaruiIndikator(tahapBaru) {
                for (let i = 1; i <= TOTAL_TAHAP; i++) {
                    const lingkaran = document.getElementById('step-circle-' + i);
                    const label     = document.getElementById('step-label-'  + i);
                    const nomor     = lingkaran.querySelector('.step-number');
                    const centang   = lingkaran.querySelector('.step-check');

                    const selesai = i < tahapBaru;
                    const aktif   = i === tahapBaru;

                    // Lingkaran.
                    lingkaran.className = 'flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-bold transition-all duration-300 '
                        + (selesai || aktif
                            ? 'border-brand bg-brand text-white'
                            : 'border-slate-300 bg-white text-slate-400');

                    // Label.
                    label.className = 'mt-2 text-center text-[10px] font-medium leading-tight transition-colors duration-300 sm:text-xs '
                        + (selesai || aktif ? 'text-brand' : 'text-slate-400');

                    // Nomor vs centang.
                    nomor.classList.toggle('hidden', selesai);
                    centang.classList.toggle('hidden', !selesai);

                    // Garis penghubung.
                    if (i < TOTAL_TAHAP) {
                        const line = document.getElementById('step-line-' + i);
                        if (line) {
                            line.className = 'mx-1 mt-5 h-0.5 flex-auto rounded-full transition-colors duration-300 sm:mx-2 sm:max-w-[6rem] ' 
                                + (i < tahapBaru ? 'bg-brand' : 'bg-slate-200');
                        }
                    }
                }
            }

            /** Isi seluruh ringkasan (step 4) dari data form. */
            function isiRingkasan() {
                const form = document.getElementById('form-laporan');

                const nilaiInput = (nama) => form.querySelector('[name="' + nama + '"]')?.value?.trim() || '—';

                const teksDropdown = (id) => {
                    const el = form.querySelector('#' + id);
                    return el?.selectedOptions?.[0]?.text?.trim() || '—';
                };

                const kumpulkanCheckbox = (nama) => {
                    const checked = form.querySelectorAll('[name="' + nama + '"]:checked');
                    if (!checked.length) return '—';
                    return Array.from(checked).map(cb => {
                        const span = cb.closest('label')?.querySelector('span');
                        return span ? span.textContent.trim() : cb.value;
                    }).join(', ');
                };

                const nilaiRadio = (nama) => form.querySelector('[name="' + nama + '"]:checked')?.value || null;

                // Data Demografis.
                setText('ringkasan-nama_pasien',       nilaiInput('nama_pasien'));
                setText('ringkasan-nomor_rekam_medis',  nilaiInput('nomor_rekam_medis'));
                setText('ringkasan-unit_kerja',        teksDropdown('unit_kerja'));

                const tgl = nilaiInput('tanggal_kejadian');
                const wkt = nilaiInput('waktu_kejadian');
                const bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
                if (tgl !== '—') {
                    const [y, m, d] = tgl.split('-');
                    setText('ringkasan-waktu_kejadian', d + ' ' + bulan[parseInt(m) - 1] + ' ' + y + (wkt !== '—' ? ' - ' + wkt : ''));
                } else {
                    setText('ringkasan-waktu_kejadian', '—');
                }

                const jenisVal = nilaiRadio('jenis_insiden');
                setText('ringkasan-jenis_insiden', jenisVal ? (labelJenisInsiden[jenisVal] || jenisVal) : '—');

                // Detail Insiden.
                setText('ringkasan-jenis_kesalahan',   kumpulkanCheckbox('jenis_kesalahan[]'));
                setText('ringkasan-cedera',            kumpulkanCheckbox('cedera[]'));
                setText('ringkasan-faktor_penyebab',   kumpulkanCheckbox('faktor_penyebab[]'));
                setText('ringkasan-intervensi_pasien',  kumpulkanCheckbox('intervensi_pasien[]'));
                setText('ringkasan-nama_obat',          nilaiInput('nama_obat'));
                setText('ringkasan-dosis_obat',         nilaiInput('dosis_obat'));

                const faseVal = nilaiRadio('fase_kesalahan');
                setText('ringkasan-fase_kesalahan', faseVal ? (labelFaseKesalahan[faseVal] || faseVal) : '—');

                // Kronologi.
                setText('ringkasan-kronologi_kejadian', nilaiInput('kronologi_kejadian'));

                // Status anonim.
                const namaPelapor = nilaiInput('nama_pelapor');
                if (namaPelapor !== '—' && namaPelapor !== '') {
                    setText('ringkasan-status-anonim', 'Pelaporan Teridentifikasi');
                    setText('ringkasan-info-anonim',   'Laporan ini dilaporkan oleh: ' + namaPelapor);
                } else {
                    setText('ringkasan-status-anonim', 'Pelaporan Anonim');
                    setText('ringkasan-info-anonim',   'Laporan ini bersifat anonim. Identitas pelapor tidak akan tercantum dalam sistem.');
                }
            }

            /** Helper pendek untuk set textContent. */
            function setText(id, teks) {
                const el = document.getElementById(id);
                if (el) el.textContent = teks;
            }
        })();
    </script>
@endsection