@extends('layouts.guest')

@section('title', 'Ubah Kata Sandi - Sistem MER')

@section('content')
    {{-- Peringatan keamanan --}}
    <div class="mb-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700" role="alert">
        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
        </svg>
        <span>
            Demi keamanan, Anda <strong>wajib mengubah kata sandi sementara</strong> Anda sebelum melanjutkan akses ke dalam sistem.
        </span>
    </div>

    <form method="POST" action="{{ route('auth.force-change-password.update') }}" novalidate class="space-y-5">
        @csrf

        {{-- Kata Sandi Baru --}}
        <div class="space-y-1.5">
            <label for="kata_sandi_baru"
                   class="block text-[13px] font-semibold text-slate-600">
                Kata Sandi Baru
            </label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                    </svg>
                </span>
                <input
                    type="password"
                    id="kata_sandi_baru"
                    name="kata_sandi_baru"
                    autocomplete="new-password"
                    required
                    placeholder="Minimal 8 karakter (huruf besar, kecil, angka)"
                    class="w-full rounded-xl border py-2.5 pl-10 pr-10 text-sm text-slate-800 placeholder-slate-400 outline-none transition
                           focus:ring-2 focus:ring-primary-600/30
                           {{ $errors->has('kata_sandi_baru')
                               ? 'border-red-400 bg-red-50 focus:border-red-400'
                               : 'border-slate-200 bg-slate-50 focus:border-primary-500 focus:bg-white' }}"
                >
                <button type="button"
                        onclick="const f=document.getElementById('kata_sandi_baru');f.type=f.type==='password'?'text':'password';this.querySelector('svg').classList.toggle('text-primary-600')"
                        class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600"
                        aria-label="Tampilkan/sembunyikan kata sandi">
                    <svg class="h-4 w-4 transition-colors" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                    </svg>
                </button>
            </div>
            @error('kata_sandi_baru')
                <p class="flex items-center gap-1.5 text-[12px] text-red-600" role="alert">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                    </svg>
                    {{ $message }}
                </p>
            @enderror
        </div>

        {{-- Konfirmasi Kata Sandi Baru --}}
        <div class="space-y-1.5">
            <label for="kata_sandi_baru_confirmation"
                   class="block text-[13px] font-semibold text-slate-600">
                Konfirmasi Kata Sandi Baru
            </label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                    </svg>
                </span>
                <input
                    type="password"
                    id="kata_sandi_baru_confirmation"
                    name="kata_sandi_baru_confirmation"
                    autocomplete="new-password"
                    required
                    placeholder="Ketik ulang kata sandi baru"
                    class="w-full rounded-xl border py-2.5 pl-10 pr-10 text-sm text-slate-800 placeholder-slate-400 outline-none transition
                           focus:ring-2 focus:ring-primary-600/30
                           {{ $errors->has('kata_sandi_baru_confirmation')
                               ? 'border-red-400 bg-red-50 focus:border-red-400'
                               : 'border-slate-200 bg-slate-50 focus:border-primary-500 focus:bg-white' }}"
                >
                <button type="button"
                        onclick="const f=document.getElementById('kata_sandi_baru_confirmation');f.type=f.type==='password'?'text':'password';this.querySelector('svg').classList.toggle('text-primary-600')"
                        class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600"
                        aria-label="Tampilkan/sembunyikan kata sandi">
                    <svg class="h-4 w-4 transition-colors" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Syarat kata sandi --}}
        <div class="rounded-lg bg-slate-50 px-3 py-2.5 text-[11px] text-slate-400">
            <p class="font-semibold text-slate-500 mb-1">Syarat kata sandi:</p>
            <ul class="list-disc list-inside space-y-0.5">
                <li>Minimal 8 karakter</li>
                <li>Mengandung huruf besar (A-Z)</li>
                <li>Mengandung huruf kecil (a-z)</li>
                <li>Mengandung angka (0-9)</li>
            </ul>
        </div>

        {{-- Tombol Simpan --}}
        <button
            type="submit"
            class="group relative mt-1 flex w-full items-center justify-center gap-2 overflow-hidden rounded-xl
                   bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-primary-900/30
                   transition hover:bg-primary-500 active:scale-[.98] active:bg-primary-700
                   focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            Simpan Kata Sandi Baru
        </button>

        {{-- Tombol Keluar --}}
        <div class="mt-2 text-center">
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" class="text-[12px] text-slate-400 hover:text-red-500 hover:underline">
                    Keluar dari sistem
                </button>
            </form>
        </div>
    </form>
@endsection
