<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model TipeCedera — maps to `master.tipe_cedera`.
 *
 * Master data untuk jenis cedera pasien yang muncul
 * sebagai checkbox di formulir pelaporan insiden Nakes.
 */
class TipeCedera extends Model
{
    use SoftDeletes;

    protected $table = 'master.tipe_cedera';

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
