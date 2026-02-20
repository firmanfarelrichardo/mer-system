<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * PerintahLoginRequest — Validasi formulir masuk pengguna.
 *
 * Langkah keamanan yang diterapkan:
 *  1. Validasi input (format nomor induk, panjang kata sandi).
 *  2. Verifikasi captcha via mews/captcha (aturan `captcha`).
 *  3. Pembatasan percobaan (throttle) — maks. 5 kali per 10 menit
 *     per kombinasi nomor-induk + IP untuk mencegah serangan brute-force.
 */
class LoginRequest extends FormRequest
{
    /* ------------------------------------------------------------------
     | Otorisasi
     | -----------------------------------------------------------------
     | Formulir login dapat diakses tamu — selalu izinkan.
     | ----------------------------------------------------------------*/

    public function authorize(): bool
    {
        return true;
    }

    /* ------------------------------------------------------------------
     | Aturan Validasi
     | -----------------------------------------------------------------
     | • `nomor_induk` — wajib diisi, string, maks. 100 karakter.
     | • `kata_sandi`  — wajib diisi, string, min. 8 karakter.
     | • `captcha`     — divalidasi oleh aturan bawaan mews/captcha
     |   yang mencocokkan nilai dengan kunci sesi di server.
     |   Dilewati saat CAPTCHA_DISABLE=true (lingkungan lokal/CI).
     | ----------------------------------------------------------------*/

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Aturan dasar yang selalu wajib ada.
        $aturan = [
            'nomor_induk' => ['required', 'string', 'max:100'],
            'kata_sandi'  => ['required', 'string', 'min:8'],
        ];

        // Tambahkan aturan captcha HANYA bila CAPTCHA_DISABLE=false (captcha aktif).
        //
        // Menggunakan config() — bukan env() — agar kompatibel dengan config:cache
        // produksi (env() mengembalikan null setelah cache di-build).
        //
        // Kunci 'captcha' sengaja dihilangkan dari array saat tidak aktif,
        // bukan diisi [] (kosong), agar Laravel tidak mendaftarkan field ini
        // dalam pipeline validasi sama sekali.
        if (! config('captcha.disable', false)) {
            $aturan['captcha'] = ['required', 'captcha'];
        }

        return $aturan;
    }

    /**
     * Nama atribut yang lebih ramah untuk pesan kesalahan.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nomor_induk' => 'nomor induk',
            'kata_sandi'  => 'kata sandi',
            'captcha'     => 'kode captcha',
        ];
    }

    /**
     * Pesan kesalahan kustom.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nomor_induk.required' => 'Nomor induk wajib diisi.',
            'nomor_induk.max'      => 'Nomor induk maksimal 100 karakter.',
            'kata_sandi.required'  => 'Kata sandi wajib diisi.',
            'kata_sandi.min'       => 'Kata sandi minimal 8 karakter.',
            'captcha.required'     => 'Kode captcha wajib diisi.',
            'captcha.captcha'      => 'Kode captcha tidak sesuai. Silakan klik gambar untuk memperbarui.',
        ];
    }

    /* ------------------------------------------------------------------
     | Pembatasan Percobaan (Rate Limiting)
     | -----------------------------------------------------------------
     | Memastikan maks. 5 percobaan login per 10 menit per kombinasi
     | nomor-induk + IP. Jika melebihi batas:
     |   - Peristiwa Lockout ditembakkan (dapat dilistenkan).
     |   - ValidationException dilempar beserta sisa waktu tunggu.
     | ----------------------------------------------------------------*/

    /**
     * Pastikan permintaan login tidak sedang dibatasi.
     *
     * @throws ValidationException
     */
    public function pastikanBelumDibatasi(): void
    {
        if (! RateLimiter::tooManyAttempts($this->kunciThrottle(), maxAttempts: 5)) {
            return;
        }

        // Tembakkan peristiwa Lockout agar dapat diproses listener (misal: notifikasi).
        event(new Lockout($this));

        $detik = RateLimiter::availableIn($this->kunciThrottle());
        $menit = (int) ceil($detik / 60);

        throw ValidationException::withMessages([
            'nomor_induk' => __('Terlalu banyak percobaan masuk. Silakan coba lagi dalam :menit menit.', [
                'menit' => $menit,
            ]),
        ]);
    }

    /**
     * Tambahkan satu hitungan gagal pada throttle percobaan ini.
     * Jendela waktu 10 menit (600 detik) — setiap percobaan gagal
     * memperpanjang durasi pemblokiran dari hitungan paling awal.
     */
    public function tambahHitungGagal(): void
    {
        RateLimiter::hit($this->kunciThrottle(), decaySeconds: 600);
    }

    /**
     * Hapus catatan throttle setelah login berhasil.
     */
    public function hapusThrottle(): void
    {
        RateLimiter::clear($this->kunciThrottle());
    }

    /**
     * Buat kunci throttle unik dari nomor induk + alamat IP klien.
     * Menggunakan Str::transliterate agar karakter non-ASCII aman disimpan di cache.
     */
    public function kunciThrottle(): string
    {
        return Str::transliterate(
            Str::lower($this->string('nomor_induk')) . '|' . $this->ip()
        );
    }
}
