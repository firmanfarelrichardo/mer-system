<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model Organisasi — maps to the `tenant.organisasi` table.
 */
class Organisasi extends Model
{
    protected $table = 'tenant.organisasi';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * Only `created_at` exists in this table.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'kode_organisasi',
        'nama_organisasi',
    ];
}
