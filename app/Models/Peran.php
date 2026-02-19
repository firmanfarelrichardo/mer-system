<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Model Peran — maps to the `akun.peran` table.
 *
 * Represents an application role such as:
 *   Perawat, Kepala Ruangan, Komite, Admin, Direktur.
 */
class Peran extends Model
{
    protected $table = 'akun.peran';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * The `akun.peran` table only has `created_at`; there is no `updated_at`.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'nama_peran',
    ];

    /* ------------------------------------------------------------------
     | Role Name Constants
     | -----------------------------------------------------------------
     | Centralise role identifiers to prevent magic strings across
     | the codebase. Seeders and policies should reference these.
     | ----------------------------------------------------------------*/

    public const PERAWAT         = 'Perawat';
    public const KEPALA_RUANGAN  = 'Kepala Ruangan';
    public const KOMITE          = 'Komite';
    public const ADMIN           = 'Admin';
    public const DIREKTUR        = 'Direktur';

    /* ------------------------------------------------------------------
     | Relationships
     | ----------------------------------------------------------------*/

    public function pengguna(): BelongsToMany
    {
        return $this->belongsToMany(
            related:    Pengguna::class,
            table:      'akun.pengguna_peran',
            foreignPivotKey: 'peran_id',
            relatedPivotKey: 'pengguna_id',
        );
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Organisasi::class, 'tenant_id');
    }
}
