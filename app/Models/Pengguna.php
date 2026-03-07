<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Model Pengguna — memetakan tabel `akun.pengguna`.
 *
 * Mewarisi Authenticatable agar guard Auth Laravel dapat menggunakannya
 * untuk autentikasi berbasis sesi secara langsung.
 */
class Pengguna extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    /* ------------------------------------------------------------------
     | Konfigurasi Tabel & Kunci
     | ----------------------------------------------------------------*/

    protected $table = 'akun.pengguna';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    /* ------------------------------------------------------------------
     | Mass Assignment
     | ----------------------------------------------------------------*/

    protected $fillable = [
        'tenant_id',
        'unit_id',
        'nomor_induk',
        'email',
        'nomor_hp',
        'alamat',
        'nama_lengkap',
        'kata_sandi',
        'is_aktif',
        'terakhir_login_pada',
        'jabatan',
        'tanggal_bergabung_unit',
    ];

    protected $hidden = [
        'kata_sandi',
    ];

    /* ------------------------------------------------------------------
     | Konversi Atribut
     | ----------------------------------------------------------------*/

    protected function casts(): array
    {
        return [
            'is_aktif'               => 'boolean',
            'terakhir_login_pada'    => 'datetime',
            'kata_sandi'             => 'hashed',   // Hash otomatis saat di-set (Laravel 10+)
            'tanggal_bergabung_unit' => 'date',
            'created_at'             => 'datetime',
            'updated_at'             => 'datetime',
            'deleted_at'             => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------
     | Override Kolom Kata Sandi
     | -----------------------------------------------------------------
     | Kontrak Authenticatable mencari getAuthPassword().
     | Kolom kita adalah `kata_sandi`, bukan kolom default `password`.
     | ----------------------------------------------------------------*/

    public function getAuthPassword(): string
    {
        return $this->kata_sandi;
    }

    /* ------------------------------------------------------------------
     | Relasi
     | ----------------------------------------------------------------*/

    /**
     * Peran yang dimiliki pengguna melalui tabel pivot.
     */
    public function peran(): BelongsToMany
    {
        return $this->belongsToMany(
            related:         Peran::class,
            table:           'akun.pengguna_peran',
            foreignPivotKey: 'pengguna_id',
            relatedPivotKey: 'peran_id',
        );
    }

    /**
     * Tenant (organisasi) tempat pengguna terdaftar.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Organisasi::class, 'tenant_id');
    }

    /**
     * Unit kerja yang opsional untuk pengguna ini.
     */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_id');
    }

    /* ------------------------------------------------------------------
     | Pembantu
     | ----------------------------------------------------------------*/

    /**
     * Periksa apakah pengguna memiliki peran tertentu berdasarkan nama.
     */
    public function memilikiPeran(string $namaPeran): bool
    {
        return $this->peran->contains('nama_peran', $namaPeran);
    }

    /**
     * Kembalikan daftar label tampilan peran pengguna (menggunakan accessor nama_display).
     * Contoh: ['Tenaga Kesehatan'] bukan ['Nakes'].
     *
     * @return list<string>
     */
    public function daftarPeran(): array
    {
        return $this->peran->map(fn (Peran $p) => $p->nama_display)->all();
    }

    /**
     * Label ringkas untuk histori tindak lanjut (Karu / Komite).
     * Format: "[Peran] [NamaUnit]"
     * Contoh: "Kepala Ruangan ICU", "Komite"
     *
     * Nama pemilik akun sengaja tidak ditampilkan untuk menjaga
     * netralitas posisi jabatan dalam alur tindak lanjut.
     */
    public function labelPeranDanUnit(): string
    {
        $peran = $this->daftarPeran()[0] ?? null;
        $unit  = $this->unitKerja?->nama_unit;

        return collect([$peran, $unit])->filter()->implode(' ') ?: '—';
    }

    /**
     * Label untuk entri unggah laporan (Nakes).
     * Format: "[Jabatan] - [NamaLengkap]"
     * Contoh: "Perawat - Budi Santoso"
     */
    public function labelPelapor(): string
    {
        $jabatan = $this->jabatan ?? null;
        $nama    = $this->nama_lengkap ?? '—';

        return $jabatan ? "{$jabatan} - {$nama}" : $nama;
    }

    /**
     * Apakah pengguna ini adalah akun peneliti sementara?
     *
     * Cek dilakukan terhadap env PENELITI_NIP — satu-satunya sumber
     * kebenaran untuk identitas peneliti — bukan terhadap peran database.
     * Dengan demikian, metode ini tetap benar meski peran database diubah.
     */
    public function isPeneliti(): bool
    {
        $nipPeneliti = env('PENELITI_NIP');

        return ! empty($nipPeneliti) && $this->nomor_induk === $nipPeneliti;
    }

    /**
     * Kembalikan nama peran yang sedang aktif untuk pengguna ini.
     *
     * - Untuk peneliti: kembalikan peran yang dipilih via dropdown "Ganti Peran"
     *   (disimpan di sesi), atau Peran::PENELITI jika belum ada pilihan aktif.
     * - Untuk pengguna biasa: kembalikan peran pertama dari database.
     *
     * Digunakan oleh navbar (label peran), sidebar composer (routing &
     * filter menu), dan dasbor hub untuk menentukan redirect yang tepat.
     */
    public function peranAktif(): string
    {
        if ($this->isPeneliti()) {
            return session('active_role', Peran::PENELITI);
        }

        return $this->peran->first()?->nama_peran ?? '—';
    }

    /* ------------------------------------------------------------------
     | Override Notifiable: Custom Notification Table
     | -----------------------------------------------------------------
     | Mengarahkan ke tabel `akun.notifikasi` alih-alih `notifications`.
     | ----------------------------------------------------------------*/

    /**
     * Dapatkan relasi notifications dari tabel akun.notifikasi.
     */
    public function notifications(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(
            Notifikasi::class,
            'notifiable',
        )->orderBy('created_at', 'desc');
    }
}
