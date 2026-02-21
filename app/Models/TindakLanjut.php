<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model TindakLanjut — memetakan tabel `pelaporan.tindak_lanjut`.
 *
 * Merepresentasikan satu catatan umpan balik / tindak lanjut
 * yang diberikan oleh Kepala Ruangan atau Komite terhadap insiden.
 *
 * Immutable by design: hanya INSERT yang diizinkan.
 * Tidak menggunakan updated_at maupun soft-deletes.
 */
class TindakLanjut extends Model
{
    /* ------------------------------------------------------------------
     | Konfigurasi Tabel & Kunci
     | ----------------------------------------------------------------*/

    protected $table = 'pelaporan.tindak_lanjut';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * Hanya `created_at` yang ada; tidak ada `updated_at`.
     */
    public const UPDATED_AT = null;

    /* ------------------------------------------------------------------
     | Mass Assignment
     | ----------------------------------------------------------------*/

    protected $fillable = [
        'insiden_id',
        'pengguna_id',
        'status_baru',
        'catatan',
    ];

    /* ------------------------------------------------------------------
     | Konversi Atribut
     | ----------------------------------------------------------------*/

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------
     | Relasi
     | ----------------------------------------------------------------*/

    /**
     * Insiden yang ditindaklanjuti.
     */
    public function insiden(): BelongsTo
    {
        return $this->belongsTo(Insiden::class, 'insiden_id');
    }

    /**
     * Pengguna (Karu/Komite) yang memberikan tindak lanjut.
     */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }

    /* ------------------------------------------------------------------
     | Pembantu
     | ----------------------------------------------------------------*/

    /**
     * Label status dalam bahasa Indonesia.
     */
    public function labelStatus(): string
    {
        return match ($this->status_baru) {
            'kasus_baru'    => 'Kasus Baru',
            'investigasi'   => 'Investigasi',
            'tindak_lanjut' => 'Tindak Lanjut',
            'selesai'       => 'Selesai',
            default         => $this->status_baru ?? '—',
        };
    }

    /**
     * Warna badge berdasarkan status.
     */
    public function warnaStatus(): string
    {
        return match ($this->status_baru) {
            'kasus_baru'    => 'bg-amber-100 text-amber-700',
            'investigasi'   => 'bg-blue-100 text-blue-700',
            'tindak_lanjut' => 'bg-violet-100 text-violet-700',
            'selesai'       => 'bg-emerald-100 text-emerald-700',
            default         => 'bg-slate-100 text-slate-700',
        };
    }
}
