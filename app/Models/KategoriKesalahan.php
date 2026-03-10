<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model KategoriKesalahan — maps to `master.kategori_kesalahan`.
 *
 * Tabel ini hanya memiliki `created_at` (immutable setelah dibuat
 * di tingkat skema DB), namun di level aplikasi kita izinkan
 * update via Service agar bisa mengedit nama_kategori.
 */
class KategoriKesalahan extends Model
{
    protected $table = 'master.kategori_kesalahan';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * Skema hanya punya `created_at` — tidak ada `updated_at`.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'nama_kategori',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------
     | Relasi
     | ----------------------------------------------------------------*/

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Organisasi::class, 'tenant_id');
    }
}
