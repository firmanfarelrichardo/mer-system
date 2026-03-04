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
 *   Nakes, Kepala Ruangan, Komite, Admin, Direktur.
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

    public const NAKES            = 'Nakes';
    public const KEPALA_RUANGAN  = 'Kepala Ruangan';
    public const KOMITE          = 'Komite';
    public const ADMIN           = 'Admin';
    public const DIREKTUR        = 'Direktur';

    /**
     * Peta nama peran internal → label tampilan di UI.
     * Sentralisasi di sini agar seluruh aplikasi konsisten.
     */
    public const PETA_LABEL_DISPLAY = [
        'Nakes'          => 'Tenaga Medis/Tenaga Kesehatan',
        'Kepala Ruangan' => 'Kepala Ruangan',
        'Komite'         => 'Komite',
        'Admin'          => 'Admin',
        'Direktur'       => 'Direktur',
    ];

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

    /* ------------------------------------------------------------------
     | Accessors
     | ----------------------------------------------------------------*/

    /**
     * Accessor: nama peran yang ramah pengguna untuk ditampilkan di UI.
     *
     * Contoh: 'Nakes' → 'Tenaga Medis/Tenaga Kesehatan'
     * Gunakan $peran->nama_display di Blade.
     */
    public function getNamaDisplayAttribute(): string
    {
        return self::PETA_LABEL_DISPLAY[$this->nama_peran] ?? $this->nama_peran;
    }
}
