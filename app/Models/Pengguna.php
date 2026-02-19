<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Model Pengguna — memetakan tabel `akun.pengguna`.
 *
 * Mewarisi Authenticatable agar guard Auth Laravel dapat menggunakannya
 * untuk autentikasi berbasis sesi secara langsung.
 */
class Pengguna extends Authenticatable
{
    use HasFactory;
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
            'is_aktif'            => 'boolean',
            'terakhir_login_pada' => 'datetime',
            'kata_sandi'          => 'hashed',   // Hash otomatis saat di-set (Laravel 10+)
            'created_at'          => 'datetime',
            'updated_at'          => 'datetime',
            'deleted_at'          => 'datetime',
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
     * Kembalikan daftar nama peran pengguna.
     *
     * @return list<string>
     */
    public function daftarPeran(): array
    {
        return $this->peran->pluck('nama_peran')->all();
    }
}
