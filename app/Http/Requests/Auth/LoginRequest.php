<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * PerintahLoginRequest - Validasi formulir masuk pengguna.
 *
 * Langkah keamanan yang diterapkan:
 *  1. Validasi input (format nomor induk, panjang kata sandi).
 *  2. Verifikasi captcha via mews/captcha (aturan `captcha`).
 *  3. Pembatasan bertingkat:
 *     - 3 gagal berturut-turut -> ban sementara 5 menit.
 *     - Jika kegagalan berlanjut hingga total 5 -> hard-ban dan wajib hubungi admin.
 */
class LoginRequest extends FormRequest
{
     private const BATAS_GAGAL_SEMENTARA = 3;
     private const DURASI_BAN_SEMENTARA_DETIK = 300; // 5 menit
     private const BATAS_GAGAL_BAN_ADMIN = 5;

    /* ------------------------------------------------------------------
     | Otorisasi
     | -----------------------------------------------------------------
     | Formulir login dapat diakses tamu - selalu izinkan.
     | ----------------------------------------------------------------*/

    public function authorize(): bool
    {
        return true;
    }

    /* ------------------------------------------------------------------
     | Aturan Validasi
     | -----------------------------------------------------------------
     | • `nomor_induk` - wajib diisi, string, maks. 100 karakter.
     | • `kata_sandi`  - wajib diisi, string, min. 8 karakter.
     | • `captcha`     - divalidasi oleh aturan bawaan mews/captcha
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
        // Menggunakan config() - bukan env() - agar kompatibel dengan config:cache
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
            'nomor_induk' => 'NIP / username',
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
            'nomor_induk.required' => 'NIP atau username wajib diisi.',
            'nomor_induk.max'      => 'NIP atau username maksimal 100 karakter.',
            'kata_sandi.required'  => 'Kata sandi wajib diisi.',
            'kata_sandi.min'       => 'Kata sandi minimal 8 karakter.',
            'captcha.required'     => 'Kode captcha wajib diisi.',
            'captcha.captcha'      => 'Kode captcha tidak sesuai. Silakan klik gambar untuk memperbarui.',
        ];
    }

    /* ------------------------------------------------------------------
     | Pembatasan Percobaan (Rate Limiting)
     | -----------------------------------------------------------------
    | Keamanan bertingkat:
    | 1) Throttle sementara setelah 3 gagal berturut-turut (5 menit).
    | 2) Hard-ban setelah total gagal mencapai 5 (hingga admin menangani).
    |
    | Jika melebihi batas:
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
        if ($this->isHardBanned()) {
            throw ValidationException::withMessages([
                'nomor_induk' => 'Akun Anda diblokir karena terlalu banyak percobaan gagal. Silakan temui admin untuk penanganan lebih lanjut.',
            ]);
        }

        if (! RateLimiter::tooManyAttempts($this->kunciThrottle(), maxAttempts: self::BATAS_GAGAL_SEMENTARA)) {
            return;
        }

        // Tembakkan peristiwa Lockout agar dapat diproses listener (misal: notifikasi).
        event(new Lockout($this));

        $detik = RateLimiter::availableIn($this->kunciThrottle());
        $menit = (int) ceil($detik / 60);

        throw ValidationException::withMessages([
            'nomor_induk' => __('Anda salah memasukkan NIP/username atau kata sandi sebanyak 3 kali. Silakan coba lagi dalam :menit menit.', [
                'menit' => $menit,
            ]),
        ]);
    }

    /**
     * Tambahkan satu hitungan gagal pada throttle percobaan ini.
     * Jendela waktu 10 menit (600 detik) - setiap percobaan gagal
     * memperpanjang durasi pemblokiran dari hitungan paling awal.
     */
    /**
     * Catat kegagalan login dan kembalikan status keamanan terkini.
     *
     * @return array{kena_ban_sementara: bool, kena_hard_ban: bool, sisa_menit: int}
     */
    public function tambahHitungGagal(): array
    {
        RateLimiter::hit($this->kunciThrottle(), decaySeconds: self::DURASI_BAN_SEMENTARA_DETIK);

        $jumlahGagalSementara = RateLimiter::attempts($this->kunciThrottle());
        $kenaBanSementara = $jumlahGagalSementara >= self::BATAS_GAGAL_SEMENTARA;
        $sisaMenit = (int) ceil(RateLimiter::availableIn($this->kunciThrottle()) / 60);

        $kunciTotalGagal = $this->kunciTotalGagal();
        $totalGagal = (int) Cache::increment($kunciTotalGagal);

        if ($totalGagal === 1) {
            // Pastikan key total gagal tidak kedaluwarsa selama user belum login sukses.
            Cache::forever($kunciTotalGagal, 1);
        }

        if ($totalGagal >= self::BATAS_GAGAL_BAN_ADMIN) {
            Cache::forever($this->kunciHardBan(), true);

            return [
                'kena_ban_sementara' => true,
                'kena_hard_ban'      => true,
                'sisa_menit'         => $sisaMenit,
            ];
        }

        return [
            'kena_ban_sementara' => $kenaBanSementara,
            'kena_hard_ban'      => false,
            'sisa_menit'         => $sisaMenit,
        ];
    }

    /**
     * Hapus catatan throttle setelah login berhasil.
     */
    public function hapusThrottle(): void
    {
        RateLimiter::clear($this->kunciThrottle());
        Cache::forget($this->kunciTotalGagal());
        Cache::forget($this->kunciHardBan());
    }

    /**
     * Buat kunci throttle unik dari nomor induk + alamat IP klien.
     * Menggunakan Str::transliterate agar karakter non-ASCII aman disimpan di cache.
     */
    public function kunciThrottle(): string
    {
        return self::kunciThrottleDariIdentifier((string) $this->string('nomor_induk'));
    }

    /**
     * Hapus seluruh status ban/login-failure untuk identifier tertentu.
     * Digunakan oleh fitur Unban Admin.
     */
    public static function resetKeamananLogin(string $identifier): void
    {
        RateLimiter::clear(self::kunciThrottleDariIdentifier($identifier));
        Cache::forget(self::kunciTotalGagalDariIdentifier($identifier));
        Cache::forget(self::kunciHardBanDariIdentifier($identifier));
    }

    /**
     * Cek apakah identifier saat ini berstatus hard-ban.
     */
    public static function isHardBannedIdentifier(string $identifier): bool
    {
        return (bool) Cache::get(self::kunciHardBanDariIdentifier($identifier), false);
    }

    /**
     * Kunci total kegagalan beruntun untuk kombinasi identifier + IP.
     */
    private function kunciTotalGagal(): string
    {
        return self::kunciTotalGagalDariIdentifier((string) $this->string('nomor_induk'));
    }

    /**
     * Kunci hard-ban yang aktif setelah total gagal >= 5.
     */
    private function kunciHardBan(): string
    {
        return self::kunciHardBanDariIdentifier((string) $this->string('nomor_induk'));
    }

    /**
     * Apakah kombinasi identifier + IP ini sudah terkena hard-ban.
     */
    private function isHardBanned(): bool
    {
        return (bool) Cache::get($this->kunciHardBan(), false);
    }

    private static function kunciThrottleDariIdentifier(string $identifier): string
    {
        return Str::transliterate('login|' . self::normalisasiIdentifier($identifier));
    }

    private static function kunciTotalGagalDariIdentifier(string $identifier): string
    {
        return Str::transliterate('login-total-gagal|' . self::normalisasiIdentifier($identifier));
    }

    private static function kunciHardBanDariIdentifier(string $identifier): string
    {
        return Str::transliterate('login-hard-ban|' . self::normalisasiIdentifier($identifier));
    }

    private static function normalisasiIdentifier(string $identifier): string
    {
        return Str::lower(trim($identifier));
    }
}
