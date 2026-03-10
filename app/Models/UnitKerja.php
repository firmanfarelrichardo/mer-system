<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model UnitKerja — maps to the `master.unit_kerja` table.
 */
class UnitKerja extends Model
{
    use SoftDeletes;

    protected $table = 'master.unit_kerja';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'tenant_id',
        'kode_unit',
        'nama_unit',
        'keterangan',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Organisasi::class, 'tenant_id');
    }
}
