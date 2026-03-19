<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model JenisKesalahan — maps to `master.jenis_kesalahan`.
 *
 * Master data untuk jenis/tipe kesalahan obat yang muncul
 * sebagai checkbox di formulir pelaporan insiden Nakes.
 */
class JenisKesalahan extends Model
{
    use SoftDeletes;

    protected $table = 'master.jenis_kesalahan';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'tenant_id',
        'nama',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'is_aktif'   => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
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
