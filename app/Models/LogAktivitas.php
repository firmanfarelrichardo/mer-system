<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model LogAktivitas - memetakan tabel `audit.log_aktivitas`.
 *
 * Immutable by design: hanya INSERT yang diizinkan.
 * Tidak menggunakan updated_at maupun soft-deletes.
 */
class LogAktivitas extends Model
{
    protected $table = 'audit.log_aktivitas';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * Hanya `created_at` yang ada; tidak ada `updated_at`.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'nama_tabel',
        'aksi',
        'id_data',
        'id_pengguna',
        'alamat_ip',
        'user_agent',
        'data_lama',
        'data_baru',
    ];

    protected function casts(): array
    {
        return [
            'data_lama'  => 'array',
            'data_baru'  => 'array',
            'created_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------
     | Relasi
     | ----------------------------------------------------------------*/

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'id_pengguna');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Organisasi::class, 'tenant_id');
    }

    /* ------------------------------------------------------------------
     | Accessor: Deskripsi Lengkap (Human-Readable)
     | -----------------------------------------------------------------
     | Mengubah kombinasi `aksi` + `nama_tabel` menjadi kalimat Bahasa
     | Indonesia yang ramah pengguna. Logika terjemahan terpusat di sini
     | agar tidak tersebar di Blade view.
     | ----------------------------------------------------------------*/

    protected function deskripsiLengkap(): Attribute
    {
        return Attribute::get(fn (): string => self::terjemahkanAksi(
            $this->aksi,
            $this->nama_tabel,
        ));
    }

    /**
     * Peta terjemahan aksi + tabel → kalimat Bahasa Indonesia.
     *
     * Format kunci: "AKSI:schema.tabel" untuk presisi.
     * Fallback generic disediakan per aksi jika tabel tidak dikenal.
     *
     * @var array<string, string>
     */
    private const PETA_TERJEMAHAN = [
        // ── Autentikasi ───────────────────────────────────
        'LOGIN'  => 'Login ke dalam sistem',
        'LOGOUT' => 'Logout dari sistem',

        // ── Pelaporan ─────────────────────────────────────
        'CREATE:pelaporan.insiden'             => 'Membuat Laporan Insiden Baru',
        'UPDATE:pelaporan.insiden'             => 'Memperbarui Data Laporan Insiden',
        'DELETE:pelaporan.insiden'             => 'Menghapus Laporan Insiden',
        'CREATE:pelaporan.detail_pasien'       => 'Menambahkan Detail Pasien pada Insiden',
        'UPDATE:pelaporan.detail_pasien'       => 'Memperbarui Detail Pasien pada Insiden',
        'CREATE:pelaporan.insiden_kategori'    => 'Menambahkan Kategori pada Insiden',
        'DELETE:pelaporan.insiden_kategori'    => 'Menghapus Kategori dari Insiden',
        'CREATE:pelaporan.penilaian_risiko'    => 'Membuat Penilaian Risiko Baru',
        'UPDATE:pelaporan.penilaian_risiko'    => 'Melakukan Grading / Penilaian Risiko',
        'CREATE:pelaporan.tindak_lanjut'       => 'Menambahkan Tindak Lanjut Insiden',
        'UPDATE:pelaporan.tindak_lanjut'       => 'Memperbarui Tindak Lanjut Insiden',

        // ── Master Data ───────────────────────────────────
        'CREATE:master.unit_kerja'             => 'Menambahkan Unit Kerja Baru',
        'UPDATE:master.unit_kerja'             => 'Memperbarui Data Unit Kerja',
        'DELETE:master.unit_kerja'             => 'Menghapus Unit Kerja',
        'CREATE:master.kategori_kesalahan'     => 'Menambahkan Kategori Kesalahan Baru',
        'UPDATE:master.kategori_kesalahan'     => 'Memperbarui Data Kategori Kesalahan',
        'DELETE:master.kategori_kesalahan'     => 'Menghapus Kategori Kesalahan',
        'CREATE:master.matriks_dampak'         => 'Menambahkan Matriks Dampak',
        'UPDATE:master.matriks_dampak'         => 'Memperbarui Matriks Dampak',
        'CREATE:master.matriks_probabilitas'   => 'Menambahkan Matriks Probabilitas',
        'UPDATE:master.matriks_probabilitas'   => 'Memperbarui Matriks Probabilitas',

        // ── Manajemen Akun ────────────────────────────────
        'CREATE:akun.pengguna'                 => 'Menambahkan Pengguna Baru',
        'UPDATE:akun.pengguna'                 => 'Memperbarui Data Pengguna',
        'DELETE:akun.pengguna'                 => 'Menghapus Pengguna',
        'ACTIVATE:akun.pengguna'               => 'Mengaktifkan Akun Pengguna',
        'DEACTIVATE:akun.pengguna'             => 'Menonaktifkan Akun Pengguna',
        'RESET_PASSWORD:akun.pengguna'         => 'Mereset Kata Sandi Pengguna',
        'CREATE:akun.peran'                    => 'Menambahkan Peran Baru',
        'UPDATE:akun.peran'                    => 'Memperbarui Data Peran',
        'DELETE:akun.peran'                    => 'Menghapus Peran',

        // ── Tenant / Organisasi ───────────────────────────
        'CREATE:tenant.organisasi'             => 'Menambahkan Organisasi Baru',
        'UPDATE:tenant.organisasi'             => 'Memperbarui Data Organisasi',
    ];

    /**
     * Fallback generic per aksi.
     *
     * @var array<string, string>
     */
    private const FALLBACK_AKSI = [
        'CREATE'         => 'Menambahkan data baru',
        'UPDATE'         => 'Memperbarui data',
        'DELETE'         => 'Menghapus data',
        'ACTIVATE'       => 'Mengaktifkan data',
        'DEACTIVATE'     => 'Menonaktifkan data',
        'RESET_PASSWORD' => 'Mereset kata sandi',
    ];

    /**
     * Terjemahkan aksi + tabel menjadi kalimat ramah pengguna.
     */
    private static function terjemahkanAksi(string $aksi, ?string $namaTabel): string
    {
        // 1. Padanan spesifik "AKSI:tabel"
        if ($namaTabel !== null && isset(self::PETA_TERJEMAHAN["{$aksi}:{$namaTabel}"])) {
            return self::PETA_TERJEMAHAN["{$aksi}:{$namaTabel}"];
        }

        // 2. Padanan aksi-only (LOGIN, LOGOUT, dll)
        if (isset(self::PETA_TERJEMAHAN[$aksi])) {
            return self::PETA_TERJEMAHAN[$aksi];
        }

        // 3. Fallback generic + nama tabel yang diformat
        $base = self::FALLBACK_AKSI[$aksi] ?? $aksi;

        return $namaTabel !== null
            ? "{$base} pada " . self::formatNamaTabel($namaTabel)
            : $base;
    }

    /**
     * Format "schema.snake_case" → "Title Case" (buang schema).
     */
    private static function formatNamaTabel(string $namaTabel): string
    {
        $bagian = str_contains($namaTabel, '.') ? explode('.', $namaTabel, 2)[1] : $namaTabel;

        return str_replace('_', ' ', ucwords($bagian, '_'));
    }
}
