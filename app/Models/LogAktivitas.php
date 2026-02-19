<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model LogAktivitas — memetakan tabel `audit.log_aktivitas`.
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
}
