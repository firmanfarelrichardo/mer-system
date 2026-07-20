@extends('layouts.guest')

@section('title', 'Masuk - Sistem Pelaporan Kesalahan Pengobatan')

@section('content')
    {{--
        Formulir Masuk
        - Token CSRF (@csrf) wajib ada di setiap form POST (middleware Laravel).
        - Gambar captcha dapat diklik untuk memperbarui kode tanpa muat ulang halaman.
    --}}
    <form method="POST" action="{{ route('login') }}" novalidate class="space-y-5">
        @csrf

        {{-- NIP / Username --}}
        <div class="space-y-1.5">
            <label for="nomor_induk"
                   class="block text-[13px] font-semibold text-slate-600">
                NIP / Username
            </label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                    </svg>
                </span>
                <input
                    type="text"
                    id="nomor_induk"
                    name="nomor_induk"
                    value="{{ old('nomor_induk') }}"
                    autocomplete="username"
                    autofocus
                    required
                    placeholder="Masukkan NIP atau username"
                    class="w-full rounded-xl border py-2.5 pl-10 pr-4 text-sm text-slate-800 placeholder-slate-400 outline-none transition
                           focus:ring-2 focus:ring-primary-600/30
                           {{ $errors->has('nomor_induk')
                               ? 'border-red-400 bg-red-50 focus:border-red-400'
                               : 'border-slate-200 bg-slate-50 focus:border-primary-500 focus:bg-white' }}"
                >
            </div>
            @error('nomor_induk')
                <p class="flex items-center gap-1.5 text-[12px] text-red-600" role="alert">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                    </svg>
                    {{ $message }}
                </p>
            @enderror
        </div>

        {{-- Kata Sandi --}}
        <div class="space-y-1.5">
            <label for="kata_sandi"
                   class="block text-[13px] font-semibold text-slate-600">
                Kata Sandi
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
                    id="kata_sandi"
                    name="kata_sandi"
                    autocomplete="current-password"
                    required
                    placeholder="Masukkan kata sandi"
                    class="w-full rounded-xl border py-2.5 pl-10 pr-10 text-sm text-slate-800 placeholder-slate-400 outline-none transition
                           focus:ring-2 focus:ring-primary-600/30
                           {{ $errors->has('kata_sandi')
                               ? 'border-red-400 bg-red-50 focus:border-red-400'
                               : 'border-slate-200 bg-slate-50 focus:border-primary-500 focus:bg-white' }}"
                >
                {{-- Toggle tampilkan / sembunyikan sandi --}}
                <button type="button"
                        onclick="const f=document.getElementById('kata_sandi');f.type=f.type==='password'?'text':'password';this.querySelector('svg').classList.toggle('text-primary-600')"
                        class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600"
                        aria-label="Tampilkan/sembunyikan kata sandi">
                    <svg class="h-4 w-4 transition-colors" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                    </svg>
                </button>
            </div>
            @error('kata_sandi')
                <p class="flex items-center gap-1.5 text-[12px] text-red-600" role="alert">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                    </svg>
                    {{ $message }}
                </p>
            @enderror
        </div>

        {{--
            Captcha (mews/captcha) - Disembunyikan saat CAPTCHA_DISABLE=true.
            Klik gambar untuk memperbarui kode tanpa muat ulang halaman.

            Catatan implementasi:
            • config('captcha.disable') dibaca dari config/captcha.php → env('CAPTCHA_DISABLE').
              Menggunakan config() bukan env() langsung agar kompatibel dengan config:cache produksi.
            • Blok ini dibungkus dalam elemen isolasi (min-w-0 + block) agar tidak
              mengganggu flex/grid induknya saat dirender bersama field lain.
        --}}
        @unless(config('captcha.disable'))
        {{-- ── CAPTCHA FIELD ─────────────────────────────────────────────────── --}}
        <div class="min-w-0 space-y-1.5">
            {{-- Label + countdown realtime di sebelah kanan --}}
            <div class="flex items-center justify-between">
                <label for="captcha"
                       class="block text-[13px] font-semibold text-slate-600">
                    Kode Keamanan
                </label>
                <span id="captcha-countdown"
                      class="text-[11px] font-medium tabular-nums text-slate-400"
                      aria-live="polite"
                      aria-atomic="true">
                </span>
            </div>

            {{--
                Side-by-side: gambar kiri | input kanan.
                Panel gambar menggunakan lebar relatif (60%) agar mengikuti lebar
                kartu - gambar di-stretch tepat mengisi panel sehingga tidak terpotong.
            --}}
            <div class="flex overflow-hidden rounded-xl border
                        {{ $errors->has('captcha') ? 'border-red-400' : 'border-slate-200' }}">

                {{-- Kiri: gambar captcha + overlay kedaluwarsa --}}
                <div class="relative flex w-[60%] shrink-0 items-center justify-center border-r p-2
                            {{ $errors->has('captcha') ? 'border-red-400' : 'border-slate-200' }} bg-white">
                    <img
                        id="gambar-captcha"
                        src="{{ captcha_src('default') }}"
                        alt="Kode keamanan captcha"
                        title="Klik untuk memperbarui kode"
                        class="block h-[84px] w-full cursor-pointer object-contain object-center"
                    >
                    {{-- Overlay muncul ketika captcha sudah kedaluwarsa --}}
                    <div id="captcha-overlay"
                         style="display: none"
                         class="absolute inset-0 cursor-pointer flex-col items-center justify-center gap-1.5 bg-white/90 text-center">
                        <svg class="h-5 w-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                        </svg>
                        <span class="text-[11px] font-semibold leading-tight text-slate-600">Klik untuk<br>memperbarui</span>
                    </div>
                </div>

                {{-- Kanan: input kode --}}
                <div class="flex flex-1 items-center {{ $errors->has('captcha') ? 'bg-red-50' : 'bg-slate-50' }}">
                    <input
                        type="text"
                        id="captcha"
                        name="captcha"
                        autocomplete="off"
                        placeholder="Ketik kode…"
                        class="w-full bg-transparent px-3 py-3 text-sm text-slate-800
                               placeholder-slate-400 outline-none text-center tracking-widest font-semibold"
                    >
                </div>
            </div>

            @error('captcha')
                <p class="flex items-center gap-1.5 text-[12px] text-red-600" role="alert">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                    </svg>
                    {{ $message }}
                </p>
            @enderror
        </div>
        {{-- ── /CAPTCHA FIELD ────────────────────────────────────────────────── --}}

        @push('scripts')
        <script>
        (function () {
            'use strict';

            var DURASI_DETIK = {{ (int) config('captcha.default.expire', 60) }};
            var elHitung     = document.getElementById('captcha-countdown');
            var elOverlay    = document.getElementById('captcha-overlay');
            var elGambar     = document.getElementById('gambar-captcha');
            var elInput      = document.getElementById('captcha');
            var waktuHabis, interval;

            /** Perbarui teks & warna countdown setiap detik. */
            function perbarui() {
                var sisa = Math.max(0, Math.round((waktuHabis - Date.now()) / 1000));
                var mnt  = Math.floor(sisa / 60);
                var dtk  = sisa % 60;

                elHitung.textContent = mnt + ':' + (dtk < 10 ? '0' : '') + dtk;

                if (sisa <= 10) {
                    elHitung.className = 'text-[11px] font-semibold tabular-nums text-red-500';
                } else if (sisa <= 20) {
                    elHitung.className = 'text-[11px] font-semibold tabular-nums text-amber-500';
                } else {
                    elHitung.className = 'text-[11px] font-medium tabular-nums text-slate-400';
                }

                if (sisa === 0) {
                    clearInterval(interval);
                    perbaruiCaptcha();
                }
            }

            /** Mulai hitung mundur dari DURASI_DETIK. */
            function mulaiHitung() {
                clearInterval(interval);
                waktuHabis = Date.now() + DURASI_DETIK * 1000;
                perbarui();
                interval = setInterval(perbarui, 1000);
            }

            /** Muat ulang gambar captcha dan reset hitung mundur. */
            function perbaruiCaptcha() {
                elGambar.src            = '{{ url('captcha/default') }}?' + Date.now();
                elOverlay.style.display = 'none';
                elInput.disabled        = false;
                elInput.value           = '';
                mulaiHitung();
            }

            elGambar.addEventListener('click',   perbaruiCaptcha);
            elOverlay.addEventListener('click',  perbaruiCaptcha);
            window.addEventListener('beforeunload', function () { clearInterval(interval); });

            mulaiHitung();
        }());
        </script>
        @endpush

        @endunless

        {{-- Tombol Masuk --}}
        <button
            type="submit"
            class="group relative mt-1 flex w-full items-center justify-center gap-2 overflow-hidden rounded-xl
                   bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-primary-900/30
                   transition hover:bg-primary-500 active:scale-[.98] active:bg-primary-700
                   focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
            </svg>
            Login
        </button>

        {{-- Bantuan Akses / Lupa Password --}}
        <div class="mt-3 text-center">
            <p class="text-[12px] text-slate-400">
                Lupa kata sandi?
                <a href="{{ route('auth.bantuan-akses') }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="font-semibold text-primary-600 hover:text-primary-500 hover:underline">
                    Hubungi Admin
                </a>
            </p>
        </div>

    </form>
@endsection
