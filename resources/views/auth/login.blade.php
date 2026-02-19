@extends('layouts.guest')

@section('title', 'Masuk — Sistem Pelaporan Insiden')

@section('content')
    {{--
        Formulir Masuk
        - Token CSRF (@csrf) wajib ada di setiap form POST (middleware Laravel).
        - autocomplete="off" pada kata sandi mencegah browser menyimpan kredensial.
        - Gambar captcha dapat diklik untuk memperbarui kode tanpa muat ulang halaman.
    --}}
    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        {{-- Nomor Induk --}}
        <div class="form-group">
            <label for="nomor_induk">Nomor Induk</label>
            <input
                type="text"
                id="nomor_induk"
                name="nomor_induk"
                value="{{ old('nomor_induk') }}"
                autocomplete="username"
                autofocus
                required
                placeholder="Contoh: PRW-001"
                class="{{ $errors->has('nomor_induk') ? 'is-invalid' : '' }}"
            >
            @error('nomor_induk')
                <div class="invalid-feedback" role="alert">{{ $message }}</div>
            @enderror
        </div>

        {{-- Kata Sandi --}}
        <div class="form-group">
            <label for="kata_sandi">Kata Sandi</label>
            <input
                type="password"
                id="kata_sandi"
                name="kata_sandi"
                autocomplete="current-password"
                required
                placeholder="••••••••"
                class="{{ $errors->has('kata_sandi') ? 'is-invalid' : '' }}"
            >
            @error('kata_sandi')
                <div class="invalid-feedback" role="alert">{{ $message }}</div>
            @enderror
        </div>

        {{--
            Captcha (mews/captcha)
            Ditampilkan di setiap percobaan masuk — bukan hanya setelah gagal.
            Pendekatan ini mencegah serangan credential-stuffing otomatis sejak awal.
            Disembunyikan saat CAPTCHA_DISABLE=true (lingkungan lokal/CI).
        --}}
        @unless(config('captcha.disable'))
        <div class="form-group">
            <label for="captcha">Kode Keamanan (Captcha)</label>
            <div class="captcha-wrap">
                {{--
                    captcha_src() menghasilkan URL gambar captcha dari mews/captcha.
                    Klik gambar untuk memperbarui kode tanpa muat ulang halaman.
                --}}
                <img
                    id="gambar-captcha"
                    src="{{ captcha_src('default') }}"
                    alt="Kode keamanan captcha"
                    title="Klik untuk memperbarui kode"
                    onclick="this.src='{{ url('captcha/default') }}?'+Date.now()"
                    style="cursor:pointer;"
                >
                <input
                    type="text"
                    id="captcha"
                    name="captcha"
                    autocomplete="off"
                    required
                    placeholder="Ketik kode di atas"
                    class="{{ $errors->has('captcha') ? 'is-invalid' : '' }}"
                >
            </div>
            @error('captcha')
                <div class="invalid-feedback" role="alert">{{ $message }}</div>
            @enderror
        </div>
        @endunless

        <button type="submit" class="btn-primary">Masuk</button>
    </form>
@endsection
