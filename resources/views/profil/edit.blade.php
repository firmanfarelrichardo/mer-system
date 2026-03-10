{{--
|--------------------------------------------------------------------------
| Edit Profil (profil/edit.blade.php)
|--------------------------------------------------------------------------
| Formulir pembaruan profil mandiri (self-service).
|
| Field yang dapat diperbarui pengguna sendiri:
|   - Nomor HP
|   - Jabatan
|   - Tanggal bergabung unit
|
| Field terlindungi (hanya Admin yang dapat mengubah):
|   - Nama lengkap, Nomor induk, Email, Unit kerja, Peran
|
| Tersedia untuk semua peran yang terautentikasi.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Edit Profil — Sistem MER')

@section('konten')

    {{-- ================================================================
         HEADER + BREADCRUMB
         ================================================================ --}}
    <div class="mb-6">
        <nav class="mb-2 text-xs text-slate-400">
            <a href="{{ route('profil.index') }}" class="hover:text-brand">Profil Saya</a>
            <span class="mx-1">›</span>
            <span class="text-slate-600">Edit</span>
        </nav>
        <h1 class="text-2xl font-bold text-slate-800">Edit Profil</h1>
        <p class="mt-1 text-sm text-slate-400">Perbarui informasi kontak dan pekerjaan Anda.</p>
    </div>

    <div class="mx-auto max-w-3xl space-y-6">

        {{-- ================================================================
             FORMULIR UTAMA
             ================================================================ --}}
        <form method="POST" action="{{ route('profil.perbarui') }}">
            @csrf
            @method('PUT')

            {{-- ===== INFORMASI PRIBADI ===== --}}
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold text-slate-700">Informasi Pribadi</h2>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                    {{-- Nama Lengkap --}}
                    <div>
                        <label for="nama_lengkap" class="mb-1 block text-sm font-medium text-slate-700">
                            Nama Lengkap <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap"
                               value="{{ old('nama_lengkap', $pengguna->nama_lengkap) }}"
                               placeholder="Nama lengkap Anda"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm placeholder-slate-400
                                      focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30
                                      @error('nama_lengkap') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                        @error('nama_lengkap')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Nomor HP --}}
                    <div>
                        <label for="nomor_hp" class="mb-1 block text-sm font-medium text-slate-700">
                            Nomor Telepon / HP
                        </label>
                        <input type="text" id="nomor_hp" name="nomor_hp"
                               value="{{ old('nomor_hp', $pengguna->nomor_hp) }}"
                               placeholder="Contoh: 081234567890"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm placeholder-slate-400
                                      focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30
                                      @error('nomor_hp') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                        @error('nomor_hp')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-slate-700">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email" id="email" name="email"
                               value="{{ old('email', $pengguna->email) }}"
                               placeholder="contoh@email.com"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm placeholder-slate-400
                                      focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30
                                      @error('email') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                        @error('email')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Username --}}
                    <div>
                        <label for="username" class="mb-1 block text-sm font-medium text-slate-700">
                            Username
                        </label>
                        <input type="text" id="username" name="username"
                               value="{{ old('username', $pengguna->username) }}"
                               placeholder="Contoh: budi.santoso"
                               autocomplete="username"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-mono placeholder-slate-400
                                      focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30
                                      @error('username') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                        <p class="mt-1 text-xs text-slate-400">Huruf, angka, titik, atau garis bawah. Maks. 50 karakter. Dapat digunakan untuk login.</p>
                        @error('username')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- ===== INFORMASI PEKERJAAN ===== --}}
            <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-1 text-sm font-semibold text-slate-700">Informasi Pekerjaan</h2>
                <p class="mb-4 text-xs text-slate-400">
                    Unit kerja dikelola oleh administrator.
                </p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                    {{-- Unit Kerja (read-only) --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Unit / Departemen
                        </label>
                        <div class="flex w-full items-center rounded-lg border border-slate-200 bg-slate-50
                                    px-3 py-2.5 text-sm text-slate-500">
                            {{ $pengguna->unitKerja?->nama_unit ?? '—' }}
                        </div>
                        <p class="mt-1 text-xs text-slate-400">Hanya dapat diubah oleh Admin.</p>
                    </div>

                    {{-- Jabatan --}}
                    <div>
                        <label for="jabatan" class="mb-1 block text-sm font-medium text-slate-700">
                            Jabatan
                        </label>
                        <input type="text" id="jabatan" name="jabatan"
                               value="{{ old('jabatan', $pengguna->jabatan) }}"
                               placeholder="Contoh: Perawat Pelaksana"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm placeholder-slate-400
                                      focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30
                                      @error('jabatan') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                        @error('jabatan')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Tanggal Bergabung Unit --}}
                    <div>
                        <label for="tanggal_bergabung_unit" class="mb-1 block text-sm font-medium text-slate-700">
                            Tanggal Bergabung Unit
                        </label>
                        <input type="date" id="tanggal_bergabung_unit" name="tanggal_bergabung_unit"
                               value="{{ old('tanggal_bergabung_unit', $pengguna->tanggal_bergabung_unit?->format('Y-m-d')) }}"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm placeholder-slate-400
                                      focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand/30
                                      @error('tanggal_bergabung_unit') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                        @error('tanggal_bergabung_unit')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- ===== INFORMASI AKUN (nomor induk read-only) ===== --}}
            <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-1 text-sm font-semibold text-slate-700">Informasi Akun</h2>
                <p class="mb-4 text-xs text-slate-400">NIP hanya dapat diubah oleh administrator.</p>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">NIP (Nomor Induk)</label>
                    <div class="flex w-full items-center rounded-lg border border-slate-200 bg-slate-50
                                px-3 py-2.5 font-mono text-sm text-slate-500">
                        {{ $pengguna->nomor_induk }}
                    </div>
                    <p class="mt-1 text-xs text-slate-400">Hanya dapat diubah oleh Admin.</p>
                </div>
            </div>

            {{-- ===== AKSI ===== --}}
            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('profil.index') }}"
                   class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium
                          text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 transition-colors">
                    Batal
                </a>
                <button type="submit"
                        class="rounded-lg bg-brand px-5 py-2 text-sm font-medium text-white
                               hover:bg-brand/90 focus:outline-none focus:ring-2 focus:ring-brand/30 transition-colors">
                    Simpan Perubahan
                </button>
            </div>

        </form>

    </div>

@endsection
