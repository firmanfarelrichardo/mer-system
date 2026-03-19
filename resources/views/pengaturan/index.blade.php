{{--
|--------------------------------------------------------------------------
| Halaman Pengaturan (pengaturan/index.blade.php)
|--------------------------------------------------------------------------
| Halaman pengaturan akun pengguna:
|   - Ubah Kata Sandi
|
| Tersedia untuk semua peran yang terautentikasi.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Pengaturan — Sistem MER')

@section('konten')

    @php
        $pengguna = Auth::user();
    @endphp

    {{-- ================================================================
         HEADER HALAMAN
         ================================================================ --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Pengaturan</h1>
        <p class="mt-1 text-sm text-slate-400">Kelola preferensi akun dan keamanan Anda.</p>
    </div>

    <div class="space-y-6">

        {{-- ============================================================
             UBAH KATA SANDI
             ============================================================ --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-1 text-base font-semibold text-slate-800">Ubah Kata Sandi</h2>
            <p class="mb-5 text-sm text-slate-400">Pastikan Anda menggunakan kata sandi yang kuat dan unik.</p>

            <form method="POST" action="#" class="max-w-md space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="kata_sandi_lama" class="mb-1 block text-xs font-medium text-slate-500">
                        Kata Sandi Saat Ini <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="kata_sandi_lama" id="kata_sandi_lama" required
                           autocomplete="current-password"
                           class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                  placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                    @error('kata_sandi_lama')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="kata_sandi_baru" class="mb-1 block text-xs font-medium text-slate-500">
                        Kata Sandi Baru <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="kata_sandi_baru" id="kata_sandi_baru" required
                           autocomplete="new-password"
                           class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                  placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                    <p class="mt-1 text-xs text-slate-400">Minimal 8 karakter, kombinasi huruf besar, kecil, angka, dan simbol.</p>
                    @error('kata_sandi_baru')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="kata_sandi_baru_confirmation" class="mb-1 block text-xs font-medium text-slate-500">
                        Konfirmasi Kata Sandi Baru <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="kata_sandi_baru_confirmation" id="kata_sandi_baru_confirmation" required
                           autocomplete="new-password"
                           class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700
                                  placeholder:text-slate-300 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="rounded-lg bg-brand px-5 py-2.5 text-sm font-medium text-white shadow-sm
                                   transition-colors hover:bg-brand-dark focus:outline-none focus:ring-2
                                   focus:ring-brand/50 focus:ring-offset-2">
                        Simpan Kata Sandi
                    </button>
                </div>
            </form>
        </div>

        {{-- ============================================================
             SESI AKTIF
             ============================================================ --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-1 text-base font-semibold text-slate-800">Sesi Aktif</h2>
            <p class="mb-5 text-sm text-slate-400">Perangkat yang saat ini login ke akun Anda.</p>

            <div class="space-y-3">
                {{-- Sesi saat ini --}}
                <div class="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <div class="flex items-center gap-3">
                        <svg class="h-5 w-5 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                             viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3
                                     3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25
                                     2.25h-13.5A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25
                                     0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25
                                     2.25 0 0 1-2.25 2.25h-13.5A2.25 2.25 0 0 1 3 12V5.25" />
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-slate-700">Perangkat ini</p>
                            <p class="text-xs text-slate-400">{{ request()->ip() }} · {{ request()->header('User-Agent') ? Str::limit(request()->header('User-Agent'), 60) : '—' }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5
                                 text-xs font-semibold text-emerald-700">
                        Aktif
                    </span>
                </div>
            </div>
        </div>

    </div>

@endsection
