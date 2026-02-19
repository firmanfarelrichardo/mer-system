{{--
|--------------------------------------------------------------------------
| Tambah Pengguna Baru (admin/pengguna/buat.blade.php)
|--------------------------------------------------------------------------
| Formulir pembuatan akun pengguna baru. Field sesuai validasi di
| SimpanPenggunaRequest: nama_lengkap, nomor_induk, email, nomor_hp,
| alamat, unit_id, kata_sandi + konfirmasi, peran_ids[], is_aktif.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Tambah Pengguna — Sistem MER')

@section('konten')

    {{-- HEADER + BREADCRUMB --}}
    <div class="mb-6">
        <nav class="mb-2 text-xs text-slate-400">
            <a href="{{ route('admin.pengguna.index') }}" class="hover:text-brand">Manajemen Pengguna</a>
            <span class="mx-1">›</span>
            <span class="text-slate-600">Tambah Baru</span>
        </nav>
        <h1 class="text-2xl font-bold text-slate-800">Tambah Pengguna Baru</h1>
        <p class="mt-1 text-sm text-slate-400">
            Isi data di bawah untuk menambahkan akun baru ke sistem.
        </p>
    </div>

    {{-- ================================================================
         FORMULIR
         ================================================================ --}}
    <form method="POST" action="{{ route('admin.pengguna.simpan') }}"
          class="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf

        {{-- ===== INFORMASI DASAR ===== --}}
        <fieldset class="mb-6">
            <legend class="mb-4 text-sm font-semibold text-slate-700">Informasi Dasar</legend>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                {{-- Nama Lengkap --}}
                <div class="sm:col-span-2">
                    <label for="nama_lengkap" class="mb-1 block text-sm font-medium text-slate-700">
                        Nama Lengkap <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap"
                           value="{{ old('nama_lengkap') }}"
                           placeholder="Masukkan nama lengkap"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm placeholder-slate-400
                                  focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30
                                  @error('nama_lengkap') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                    @error('nama_lengkap')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Nomor Induk --}}
                <div>
                    <label for="nomor_induk" class="mb-1 block text-sm font-medium text-slate-700">
                        Nomor Induk <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="nomor_induk" name="nomor_induk"
                           value="{{ old('nomor_induk') }}"
                           placeholder="Contoh: 198501012023"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm placeholder-slate-400
                                  focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30
                                  @error('nomor_induk') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                    @error('nomor_induk')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-slate-700">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" id="email" name="email"
                           value="{{ old('email') }}"
                           placeholder="email@contoh.com"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm placeholder-slate-400
                                  focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30
                                  @error('email') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                    @error('email')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Nomor HP --}}
                <div>
                    <label for="nomor_hp" class="mb-1 block text-sm font-medium text-slate-700">
                        Nomor HP <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="nomor_hp" name="nomor_hp"
                           value="{{ old('nomor_hp') }}"
                           placeholder="08xxxxxxxxxx"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm placeholder-slate-400
                                  focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30
                                  @error('nomor_hp') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                    @error('nomor_hp')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Alamat --}}
                <div>
                    <label for="alamat" class="mb-1 block text-sm font-medium text-slate-700">Alamat</label>
                    <input type="text" id="alamat" name="alamat"
                           value="{{ old('alamat') }}"
                           placeholder="Alamat lengkap (opsional)"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm placeholder-slate-400
                                  focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                    @error('alamat')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </fieldset>

        <hr class="mb-6 border-slate-100">

        {{-- ===== UNIT KERJA & PERAN ===== --}}
        <fieldset class="mb-6">
            <legend class="mb-4 text-sm font-semibold text-slate-700">Unit Kerja & Peran</legend>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                {{-- Unit Kerja --}}
                <div>
                    <label for="unit_id" class="mb-1 block text-sm font-medium text-slate-700">Unit Kerja</label>
                    <select id="unit_id" name="unit_id"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700
                                   focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                        <option value="">— Pilih Unit Kerja —</option>
                        @foreach ($daftarUnit as $unit)
                            <option value="{{ $unit->id }}" @selected(old('unit_id') == $unit->id)>
                                {{ $unit->nama_unit }}
                            </option>
                        @endforeach
                    </select>
                    @error('unit_id')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Peran (multi-checkbox) --}}
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Peran <span class="text-red-500">*</span>
                    </label>
                    <div class="rounded-lg border border-slate-200 px-3 py-2.5 @error('peran_ids') border-red-300 @enderror">
                        @foreach ($daftarPeran as $peran)
                            <label class="flex items-center gap-2 py-1 text-sm text-slate-700">
                                <input type="checkbox" name="peran_ids[]" value="{{ $peran->id }}"
                                       @checked(is_array(old('peran_ids')) && in_array($peran->id, old('peran_ids')))
                                       class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand/50">
                                {{ $peran->nama_peran }}
                            </label>
                        @endforeach
                    </div>
                    @error('peran_ids')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </fieldset>

        <hr class="mb-6 border-slate-100">

        {{-- ===== KATA SANDI ===== --}}
        <fieldset class="mb-6">
            <legend class="mb-4 text-sm font-semibold text-slate-700">Kata Sandi</legend>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                {{-- Kata Sandi --}}
                <div>
                    <label for="kata_sandi" class="mb-1 block text-sm font-medium text-slate-700">
                        Kata Sandi <span class="text-red-500">*</span>
                    </label>
                    <input type="password" id="kata_sandi" name="kata_sandi"
                           placeholder="Minimal 8 karakter"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm placeholder-slate-400
                                  focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30
                                  @error('kata_sandi') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                    @error('kata_sandi')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Konfirmasi Kata Sandi --}}
                <div>
                    <label for="kata_sandi_confirmation" class="mb-1 block text-sm font-medium text-slate-700">
                        Konfirmasi Kata Sandi <span class="text-red-500">*</span>
                    </label>
                    <input type="password" id="kata_sandi_confirmation" name="kata_sandi_confirmation"
                           placeholder="Ulangi kata sandi"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm placeholder-slate-400
                                  focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30">
                </div>
            </div>
        </fieldset>

        <hr class="mb-6 border-slate-100">

        {{-- ===== STATUS AKTIF ===== --}}
        <fieldset class="mb-8">
            <label class="flex items-center gap-3 text-sm text-slate-700">
                <input type="hidden" name="is_aktif" value="0">
                <input type="checkbox" name="is_aktif" value="1"
                       @checked(old('is_aktif', 1))
                       class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand/50">
                <span class="font-medium">Aktifkan akun langsung setelah dibuat</span>
            </label>
        </fieldset>

        {{-- ===== TOMBOL AKSI ===== --}}
        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
            <a href="{{ route('admin.pengguna.index') }}"
               class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-600
                      transition-colors hover:bg-slate-50">
                Batal
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white
                           shadow-sm transition-colors hover:bg-brand/90 focus:outline-none focus:ring-2 focus:ring-brand/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Simpan Pengguna
            </button>
        </div>
    </form>

@endsection
