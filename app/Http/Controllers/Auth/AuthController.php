<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Services\AuditLogService;
use App\Services\PenggunaService;
use App\Services\SuspiciousLoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * KontrollerAuth — Menangani proses Masuk dan Keluar berbasis sesi.
 *
 * Langkah keamanan yang diterapkan:
 *  1. Validasi input, captcha, dan pembatasan percobaan didelegasikan ke LoginRequest.
 *  2. Pencarian akun manual berdasarkan nomor_induk — aman untuk lingkungan multi-tenant.
 *  3. Pengecekan bendera `is_aktif` — akun nonaktif tidak dapat masuk.
 *  4. Perlindungan session fixation — ID sesi diperbarui setelah masuk berhasil.
 *  5. Keluar aman — sesi dihancurkan & token CSRF diperbarui.
 *  6. Setiap peristiwa autentikasi dicatat untuk jejak audit.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogService        $auditLog,
        private readonly SuspiciousLoginService $suspiciousLogin,
        private readonly PenggunaService        $penggunaService,
    ) {}

    /* ==================================================================
     | MASUK — Tampilkan Formulir
     | ================================================================*/

    /**
     * Tampilkan halaman formulir masuk.
     */
    public function tampilkanFormulirMasuk()
    {
        return view('auth.login');
    }

    /* ==================================================================
     | MASUK — Proses
     | ================================================================*/

    /**
     * Proses autentikasi pengguna.
     *
     * Alur:
     *  1. LoginRequest memvalidasi input, captcha, dan memeriksa batas percobaan.
     *  2. Cari pengguna berdasarkan nomor_induk secara manual — aman untuk
     *     lingkungan multi-tenant karena nomor_induk bisa sama antar tenant.
     *  3. Verifikasi kata sandi dengan Hash::check.
     *  4. Periksa status aktif akun.
     *  5. Masukkan pengguna ke sesi dan perbarui ID sesi.
     *  6. Perbarui timestamp terakhir masuk.
     *  7. Arahkan ke dasbor sesuai peran tertinggi.
     */
    public function masuk(LoginRequest $permintaan): RedirectResponse
    {
        // ----------------------------------------------------------
        // 1. Periksa batas percobaan (lempar ValidationException jika melebihi)
        // ----------------------------------------------------------
        $permintaan->pastikanBelumDibatasi();

        // ----------------------------------------------------------
        // 2. Cari akun berdasarkan nomor_induk.
        //    Pendekatan manual dipilih agar aman di lingkungan multi-tenant.
        //    Pada sistem multi-tenant, tambahkan filter tenant_id di sini.
        // ----------------------------------------------------------
        $pengguna = Pengguna::where('nomor_induk', $permintaan->validated('nomor_induk'))
            ->first();

        // ----------------------------------------------------------
        // 3. Verifikasi keberadaan akun dan kecocokan kata sandi.
        //    Pesan error digeneralisasi agar tidak mengekspos apakah
        //    nomor_induk terdaftar atau tidak (mencegah user enumeration).
        // ----------------------------------------------------------
        if (! $pengguna || ! Hash::check($permintaan->validated('kata_sandi'), $pengguna->kata_sandi)) {
            $permintaan->tambahHitungGagal();

            Log::warning('Percobaan masuk gagal: kredensial tidak valid.', [
                'nomor_induk' => $permintaan->validated('nomor_induk'),
                'ip'          => $permintaan->ip(),
            ]);

            return back()
                ->withInput($permintaan->only('nomor_induk'))
                ->withErrors([
                    'nomor_induk' => 'Nomor induk atau kata sandi salah.',
                ]);
        }

        // ----------------------------------------------------------
        // 4. Cek status aktif — akun nonaktif langsung ditolak.
        //    Throttle tetap dihitung agar tidak menjadi celah enumerasi akun.
        // ----------------------------------------------------------
        if (! $pengguna->is_aktif) {
            $permintaan->tambahHitungGagal();

            Log::notice('Percobaan masuk ditolak: akun tidak aktif.', [
                'pengguna_id' => $pengguna->id,
                'nomor_induk' => $pengguna->nomor_induk,
            ]);

            return back()
                ->withInput($permintaan->only('nomor_induk'))
                ->withErrors([
                    'nomor_induk' => 'Akun Anda tidak aktif. Silakan hubungi administrator.',
                ]);
        }

        // ----------------------------------------------------------
        // 5. Masukkan pengguna ke sesi.
        //    session()->regenerate() mengganti ID sesi — mencegah serangan
        //    session fixation dari sesi yang dibuat sebelum login.
        // ----------------------------------------------------------
        Auth::login($pengguna);
        $permintaan->session()->regenerate();

        // ----------------------------------------------------------
        // 6. Hapus catatan throttle setelah masuk berhasil.
        // ----------------------------------------------------------
        $permintaan->hapusThrottle();

        // ----------------------------------------------------------
        // 7. Perbarui timestamp terakhir masuk untuk keperluan audit.
        //    updateQuietly agar tidak memicu event/observer model.
        // ----------------------------------------------------------
        $pengguna->updateQuietly([
            'terakhir_login_pada' => now(),
        ]);

        // ----------------------------------------------------------
        // 8. Muat peran dan tentukan halaman tujuan berdasarkan peran.
        // ----------------------------------------------------------
        $pengguna->load('peran');

        Log::info('Pengguna berhasil masuk.', [
            'pengguna_id' => $pengguna->id,
            'nomor_induk' => $pengguna->nomor_induk,
            'peran'       => $pengguna->daftarPeran(),
            'ip'          => $permintaan->ip(),
        ]);

        // ----------------------------------------------------------
        // 9. Catat event LOGIN ke audit trail.
        // ----------------------------------------------------------
        $this->auditLog->catat(
            namaTabel:  'akun.pengguna',
            aksi:       'LOGIN',
            idData:     $pengguna->id,
            idPengguna: $pengguna->id,
        );

        // ----------------------------------------------------------
        // 10. Deteksi login mencurigakan (IP baru, jam aneh, dsb.).
        //     Skor >= 61 dicatat sebagai warning untuk investigasi admin.
        // ----------------------------------------------------------
        $risikoLogin = $this->suspiciousLogin->periksa(
            idPengguna: $pengguna->id,
            alamatIp:   (string) $permintaan->ip(),
            userAgent:  $permintaan->userAgent(),
        );

        if ($risikoLogin['level'] !== 'normal') {
            Log::warning('Login mencurigakan terdeteksi.', [
                'pengguna_id' => $pengguna->id,
                'skor_risiko' => $risikoLogin['skor'],
                'level'       => $risikoLogin['level'],
                'alasan'      => $risikoLogin['alasan'],
                'ip'          => $permintaan->ip(),
            ]);
            // TODO (fase berikutnya): kirim notifikasi email ke admin
            //      jika level === 'mencurigakan'
        }

        return redirect()
            ->intended($this->tentukanHalamanSesuaiPeran($pengguna))
            ->with('sukses', 'Selamat datang, ' . $pengguna->nama_lengkap . '.');
    }

    /* ==================================================================
     | KELUAR
     | ================================================================*/

    /**
     * Keluarkan pengguna dari aplikasi.
     *
     * Keamanan:
     *  - Menghapus seluruh data sesi (invalidate).
     *  - Memperbarui token CSRF agar tidak dapat digunakan ulang.
     */
    public function keluar(Request $permintaan): RedirectResponse
    {
        /** @var Pengguna|null $pengguna */
        $pengguna = Auth::user();

        if ($pengguna) {
            Log::info('Pengguna keluar.', [
                'pengguna_id' => $pengguna->id,
                'nomor_induk' => $pengguna->nomor_induk,
                'ip'          => $permintaan->ip(),
            ]);
        }

        // Hapus pengguna dari sesi
        Auth::logout();

        // Hancurkan seluruh sesi — mencegah session fixation setelah keluar
        $permintaan->session()->invalidate();

        // Perbarui token CSRF agar token lama tidak dapat diputar ulang
        $permintaan->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('sukses', 'Anda telah berhasil keluar.');
    }

    /* ==================================================================
     | KELUAR IDLE (Auto-Logout karena tidak ada aktivitas)
     | ================================================================*/

    /**
     * Logout otomatis yang dipicu oleh Alpine.js saat pengguna idle ≥ 5 menit.
     *
     * Dipanggil via: POST /logout-idle (Fetch API dari browser).
     * Selalu merespons JSON agar Alpine.js dapat menangani redirect di sisi klien.
     *
     * Perbedaan dengan keluar() biasa:
     *  - Konteks audit berbeda: aksi 'LOGOUT_IDLE' vs 'LOGOUT'.
     *  - Flash message berbeda: menjelaskan alasan sesi berakhir.
     */
    public function logoutIdle(Request $permintaan): JsonResponse
    {
        /** @var Pengguna|null $pengguna */
        $pengguna = Auth::user();

        // ----------------------------------------------------------
        // 1. Catat ke audit trail SEBELUM menghapus sesi.
        //    Penting: Auth::user() tidak lagi tersedia setelah logout.
        // ----------------------------------------------------------
        if ($pengguna) {
            $this->auditLog->catat(
                namaTabel:  'akun.pengguna',
                aksi:       'LOGOUT_IDLE',
                idData:     $pengguna->id,
                idPengguna: $pengguna->id,
                dataBaru:   [
                    'deskripsi' => 'Sistem melakukan auto-logout karena pengguna idle (tidak aktif) selama 5 menit.',
                ],
            );

            Log::info('Auto-logout idle dieksekusi.', [
                'pengguna_id' => $pengguna->id,
                'nomor_induk' => $pengguna->nomor_induk,
                'ip'          => $permintaan->ip(),
            ]);
        }

        // ----------------------------------------------------------
        // 2. Hancurkan sesi dan keluarkan pengguna.
        // ----------------------------------------------------------
        Auth::logout();
        $permintaan->session()->invalidate();
        $permintaan->session()->regenerateToken();

        // ----------------------------------------------------------
        // 3. Kembalikan JSON — Alpine.js yang menangani redirect.
        // ----------------------------------------------------------
        return response()->json([
            'pesan'    => 'Anda telah logout otomatis karena tidak ada aktivitas selama 5 menit.',
            'redirect' => route('login'),
        ]);
    }

    /* ==================================================================
     | PEMBANTU PRIVAT
     | ================================================================*/

    /**
     * Tentukan route dasbor tujuan berdasarkan peran pengguna.
     * Peran dengan kewenangan tertinggi diutamakan.
     */
    private function tentukanHalamanSesuaiPeran(Pengguna $pengguna): string
    {
        return match (true) {
            $pengguna->memilikiPeran('Direktur')       => route('direktur.dashboard'),
            $pengguna->memilikiPeran('Admin')          => route('admin.dashboard'),
            $pengguna->memilikiPeran('Komite')         => route('komite.dashboard'),
            $pengguna->memilikiPeran('Kepala Ruangan') => route('kepala-ruangan.dashboard'),
            $pengguna->memilikiPeran('Nakes')          => route('nakes.dashboard'),
            default                                    => route('dashboard'),
        };
    }

    /* ==================================================================
     | BANTUAN AKSES — Dynamic WhatsApp Redirect
     | ================================================================*/

    /**
     * Arahkan pengguna yang lupa sandi ke WhatsApp Admin aktif secara dinamis.
     *
     * Alur:
     *  1. Cari 1 Admin aktif yang memiliki nomor telepon.
     *  2. Sanitasi nomor HP (hapus spasi, strip, ubah awalan ke '62').
     *  3. Redirect ke wa.me dengan pesan otomatis.
     *  4. Fallback: kembali ke login jika Admin tidak tersedia.
     */
    public function bantuanLogin(): RedirectResponse
    {
        // Cari Admin aktif pertama yang memiliki nomor telepon
        $admin = Pengguna::whereHas('peran', function ($q): void {
            $q->where('nama_peran', Peran::ADMIN);
        })
            ->where('is_aktif', true)
            ->whereNotNull('nomor_hp')
            ->where('nomor_hp', '!=', '')
            ->first();

        if (! $admin) {
            return redirect()
                ->route('login')
                ->with('error', 'Kontak Admin tidak tersedia. Silakan hubungi tim IT.');
        }

        // Sanitasi nomor HP: hapus karakter non-digit, ubah awalan ke format internasional
        $nomorBersih = preg_replace('/[^0-9]/', '', $admin->nomor_hp);

        // Ubah awalan '0' → '62', '+62' sudah menjadi '62' setelah strip non-digit
        if (str_starts_with($nomorBersih, '0')) {
            $nomorBersih = '62' . substr($nomorBersih, 1);
        }

        $pesan = urlencode('Halo Admin, saya (Nama/NIP) merupakan staff RSD Ryacudu, memohon bantuan untuk mereset kata sandi MER System saya.');

        return redirect()->away("https://wa.me/{$nomorBersih}?text={$pesan}");
    }

    /* ==================================================================
     | GANTI SANDI PAKSA — Tampilkan Formulir
     | ================================================================*/

    /**
     * Tampilkan halaman ganti sandi paksa setelah emergency reset.
     */
    public function tampilkanFormGantiSandiPaksa()
    {
        return view('auth.force-change');
    }

    /* ==================================================================
     | GANTI SANDI PAKSA — Proses
     | ================================================================*/

    /**
     * Proses penggantian sandi paksa.
     *
     * Validasi:
     *  - kata_sandi_baru: min 8, mengandung huruf besar, kecil, dan angka.
     *  - kata_sandi_baru_confirmation: harus cocok.
     *
     * Setelah berhasil:
     *  - Hash sandi baru
     *  - Set wajib_ganti_sandi = false
     *  - Catat ke audit log
     *  - Redirect ke dashboard sesuai peran
     */
    public function prosesGantiSandiPaksa(Request $request): RedirectResponse
    {
        $request->validate([
            'kata_sandi_baru' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',      // minimal 1 huruf kecil
                'regex:/[A-Z]/',      // minimal 1 huruf besar
                'regex:/[0-9]/',      // minimal 1 angka
            ],
        ], [
            'kata_sandi_baru.required'  => 'Kata sandi baru wajib diisi.',
            'kata_sandi_baru.min'       => 'Kata sandi baru minimal 8 karakter.',
            'kata_sandi_baru.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'kata_sandi_baru.regex'     => 'Kata sandi harus mengandung huruf besar, huruf kecil, dan angka.',
        ]);

        /** @var Pengguna $pengguna */
        $pengguna = Auth::user();

        $this->penggunaService->selesaikanGantiSandiPaksa(
            $pengguna,
            $request->input('kata_sandi_baru'),
        );

        Log::info('Pengguna berhasil mengganti sandi darurat.', [
            'pengguna_id' => $pengguna->id,
        ]);

        $pengguna->load('peran');

        return redirect()
            ->to($this->tentukanHalamanSesuaiPeran($pengguna))
            ->with('sukses', 'Kata sandi berhasil diperbarui. Selamat datang kembali!');
    }
}
