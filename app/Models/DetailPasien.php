<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model DetailPasien — memetakan tabel `pelaporan.detail_pasien`.
 *
 * Menyimpan data pasien terkait insiden serta detail klasifikasi
 * kesalahan obat (jenis kesalahan, cedera, faktor penyebab, dll.).
 */
class DetailPasien extends Model
{
    /* ------------------------------------------------------------------
     | Konfigurasi Tabel & Kunci
     | ----------------------------------------------------------------*/

    protected $table = 'pelaporan.detail_pasien';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * Tabel ini hanya punya `created_at`, bukan `updated_at`.
     */
    public $timestamps = false;

    /* ------------------------------------------------------------------
     | Mass Assignment
     | ----------------------------------------------------------------*/

    protected $fillable = [
        'insiden_id',
        'nama_pasien',
        'nomor_rekam_medis',
        'obat_terkait',
        'dokter_penulis_resep',
        'kronologi',
        'tindakan_awal',
        'jenis_kesalahan',
        'cedera',
        'faktor_penyebab',
        'intervensi_pasien',
        'pernyataan_kronologi',
    ];

    /* ------------------------------------------------------------------
     | Konversi Atribut
     | ----------------------------------------------------------------*/

    protected function casts(): array
    {
        return [
            'jenis_kesalahan'      => 'array',
            'cedera'               => 'array',
            'faktor_penyebab'      => 'array',
            'intervensi_pasien'    => 'array',
            'pernyataan_kronologi' => 'boolean',
            'created_at'           => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------
     | Relasi
     | ----------------------------------------------------------------*/

    /**
     * Insiden yang memiliki detail pasien ini.
     */
    public function insiden(): BelongsTo
    {
        return $this->belongsTo(Insiden::class, 'insiden_id');
    }
}
