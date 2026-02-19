{{--
|--------------------------------------------------------------------------
| Formulir Buat Laporan Insiden (laporan/buat.blade.php)
|--------------------------------------------------------------------------
| Halaman formulir multi-bagian untuk membuat laporan insiden baru.
| Bagian formulir:
|   1. Informasi Pelapor (otomatis dari sesi)
|   2. Data Pasien
|   3. Detail Kejadian / Insiden
|   4. Klasifikasi Insiden (Jenis: KTD/KNC/KTC/KPC)
|   5. Tindakan Segera yang Dilakukan
|
| Menggunakan layout utama (@extends layouts.app) agar sidebar
| dan navbar otomatis tampil.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Buat Laporan — Sistem MER')

@section('konten')

    @php
        $pengguna  = Auth::user();
        $namaUnit  = $pengguna->unitKerja?->nama_unit ?? '—';
    @endphp

    {{-- ================================================================
         HEADER HALAMAN
         ================================================================ --}}
    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Buat Laporan Insiden</h1>
            <p class="mt-1 text-sm text-slate-400">Isi formulir di bawah untuk melaporkan insiden medication error.</p>
        </div>
        <a href="{{ route('laporan.index') }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white
                  px-4 py-2 text-sm font-medium text-slate-600 shadow-sm transition-colors
                  hover:bg-slate-50">
            {{-- Ikon: panah kembali --}}
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                 viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            Kembali
        </a>
    </div>

    {{-- ================================================================
         FORMULIR LAPORAN INSIDEN
         ================================================================ --}}
    <form method="POST" action="#" class="space-y-6">
        @csrf

        {{-- ============================================================
             BAGIAN 1: INFORMASI PELAPOR  (terisi otomatis)
             ============================================================ --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand text-xs font-bold text-white">1</span>
                Informasi Pelapor
            </h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Nama Pelapor</label>
                    <input type="text" value="{{ $pengguna->nama_lengkap }}" disabled
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-600">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Nomor Induk</label>
                    <input type="text" value="{{ $pengguna->nomor_induk }}" disabled
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-600">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Unit Kerja</label>
                    <input type="text" value="{{ $namaUnit }}" disabled
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-600">
                </div>
            </div>
        </div>

        {{-- ============================================================
             BAGIAN 2: DATA PASIEN
             ============================================================ --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand text-xs font-bold text-white">2</span>
                Data Pasien
            </h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                <div>
                    <label for="nomor_rekam_medis" class="mb-1 block text-xs font-medium text-slate-500">
                        No. Rekam Medis <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nomor_rekam_medis" id="nomor_rekam_medis" required
                           placeholder="Contoh: RM-000123"
                           value="{{ old('nomor_rekam_medis') }}"
                           class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                  placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                    @error('nomor_rekam_medis')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="ruangan_pasien" class="mb-1 block text-xs font-medium text-slate-500">
                        Ruangan / Bangsal <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="ruangan_pasien" id="ruangan_pasien" required
                           placeholder="Contoh: ICU, Rawat Inap A1"
                           value="{{ old('ruangan_pasien') }}"
                           class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                  placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                    @error('ruangan_pasien')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="umur_pasien" class="mb-1 block text-xs font-medium text-slate-500">
                        Umur Pasien
                    </label>
                    <div class="flex gap-2">
                        <input type="number" name="umur_pasien" id="umur_pasien" min="0" max="150"
                               placeholder="Umur"
                               value="{{ old('umur_pasien') }}"
                               class="w-24 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                      placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                        <select name="satuan_umur"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                       focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                            <option value="tahun" {{ old('satuan_umur') === 'tahun' ? 'selected' : '' }}>Tahun</option>
                            <option value="bulan" {{ old('satuan_umur') === 'bulan' ? 'selected' : '' }}>Bulan</option>
                            <option value="hari" {{ old('satuan_umur') === 'hari' ? 'selected' : '' }}>Hari</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             BAGIAN 3: DETAIL KEJADIAN / INSIDEN
             ============================================================ --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand text-xs font-bold text-white">3</span>
                Detail Kejadian
            </h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                <div class="sm:col-span-2">
                    <label for="lokasi_kejadian" class="mb-1 block text-xs font-medium text-slate-500">
                        Lokasi Kejadian <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="lokasi_kejadian" id="lokasi_kejadian" required
                           placeholder="Contoh: Ruang ICU Bed 3, Apotek Rawat Jalan"
                           value="{{ old('lokasi_kejadian') }}"
                           class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                  placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                    @error('lokasi_kejadian')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="kronologi_kejadian" class="mb-1 block text-xs font-medium text-slate-500">
                        Kronologi Kejadian <span class="text-red-500">*</span>
                    </label>
                    <textarea name="kronologi_kejadian" id="kronologi_kejadian" rows="4" required
                              placeholder="Jelaskan kronologi kejadian secara rinci. Sertakan informasi obat yang terlibat (nama, dosis, rute pemberian), apa yang terjadi, dan bagaimana insiden terdeteksi."
                              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                     placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">{{ old('kronologi_kejadian') }}</textarea>
                    @error('kronologi_kejadian')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- ============================================================
             BAGIAN 4: KLASIFIKASI INSIDEN
             ============================================================ --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand text-xs font-bold text-white">4</span>
                Klasifikasi Insiden
            </h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="jenis_insiden" class="mb-1 block text-xs font-medium text-slate-500">
                        Jenis Insiden <span class="text-red-500">*</span>
                    </label>
                    <select name="jenis_insiden" id="jenis_insiden" required
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                   focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                        <option value="">— Pilih Jenis Insiden —</option>
                        <option value="KTD" {{ old('jenis_insiden') === 'KTD' ? 'selected' : '' }}>KTD — Kejadian Tidak Diharapkan</option>
                        <option value="KNC" {{ old('jenis_insiden') === 'KNC' ? 'selected' : '' }}>KNC — Kejadian Nyaris Cedera</option>
                        <option value="KTC" {{ old('jenis_insiden') === 'KTC' ? 'selected' : '' }}>KTC — Kejadian Tidak Cedera</option>
                        <option value="KPC" {{ old('jenis_insiden') === 'KPC' ? 'selected' : '' }}>KPC — Kondisi Potensial Cedera</option>
                    </select>
                    @error('jenis_insiden')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="kategori_kesalahan" class="mb-1 block text-xs font-medium text-slate-500">
                        Kategori Kesalahan <span class="text-red-500">*</span>
                    </label>
                    <select name="kategori_kesalahan" id="kategori_kesalahan" required
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                   focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                        <option value="">— Pilih Kategori —</option>
                        <option value="salah_obat" {{ old('kategori_kesalahan') === 'salah_obat' ? 'selected' : '' }}>Salah Obat</option>
                        <option value="salah_dosis" {{ old('kategori_kesalahan') === 'salah_dosis' ? 'selected' : '' }}>Salah Dosis</option>
                        <option value="salah_pasien" {{ old('kategori_kesalahan') === 'salah_pasien' ? 'selected' : '' }}>Salah Pasien</option>
                        <option value="salah_rute" {{ old('kategori_kesalahan') === 'salah_rute' ? 'selected' : '' }}>Salah Rute Pemberian</option>
                        <option value="salah_waktu" {{ old('kategori_kesalahan') === 'salah_waktu' ? 'selected' : '' }}>Salah Waktu Pemberian</option>
                        <option value="obat_kadaluarsa" {{ old('kategori_kesalahan') === 'obat_kadaluarsa' ? 'selected' : '' }}>Obat Kadaluarsa</option>
                        <option value="duplikasi_terapi" {{ old('kategori_kesalahan') === 'duplikasi_terapi' ? 'selected' : '' }}>Duplikasi Terapi</option>
                        <option value="lainnya" {{ old('kategori_kesalahan') === 'lainnya' ? 'selected' : '' }}>Lainnya</option>
                    </select>
                    @error('kategori_kesalahan')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
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
                <div>
                    <label for="dampak_insiden" class="mb-1 block text-xs font-medium text-slate-500">
                        Dampak pada Pasien <span class="text-red-500">*</span>
                    </label>
                    <select name="dampak_insiden" id="dampak_insiden" required
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                   focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                        <option value="">— Pilih Dampak —</option>
                        <option value="tidak_cedera" {{ old('dampak_insiden') === 'tidak_cedera' ? 'selected' : '' }}>Tidak Ada Cedera</option>
                        <option value="cedera_ringan" {{ old('dampak_insiden') === 'cedera_ringan' ? 'selected' : '' }}>Cedera Ringan</option>
                        <option value="cedera_sedang" {{ old('dampak_insiden') === 'cedera_sedang' ? 'selected' : '' }}>Cedera Sedang</option>
                        <option value="cedera_berat" {{ old('dampak_insiden') === 'cedera_berat' ? 'selected' : '' }}>Cedera Berat</option>
                        <option value="meninggal" {{ old('dampak_insiden') === 'meninggal' ? 'selected' : '' }}>Meninggal</option>
                    </select>
                    @error('dampak_insiden')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- ============================================================
             BAGIAN 5: TINDAKAN SEGERA YANG DILAKUKAN
             ============================================================ --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-800">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand text-xs font-bold text-white">5</span>
                Tindakan Segera
            </h2>
            <div>
                <label for="tindakan_segera" class="mb-1 block text-xs font-medium text-slate-500">
                    Tindakan Segera yang Dilakukan
                </label>
                <textarea name="tindakan_segera" id="tindakan_segera" rows="3"
                          placeholder="Jelaskan tindakan segera yang sudah dilakukan setelah insiden terdeteksi (jika ada)."
                          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                 placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">{{ old('tindakan_segera') }}</textarea>
            </div>
        </div>

        {{-- ============================================================
             TOMBOL AKSI
             ============================================================ --}}
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('laporan.index') }}"
               class="rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium
                      text-slate-600 shadow-sm transition-colors hover:bg-slate-50">
                Batal
            </a>
            <button type="submit"
                    class="rounded-lg bg-brand px-5 py-2.5 text-sm font-medium text-white shadow-sm
                           transition-colors hover:bg-brand-dark focus:outline-none focus:ring-2
                           focus:ring-brand/50 focus:ring-offset-2">
                Kirim Laporan
            </button>
        </div>
    </form>

@endsection
