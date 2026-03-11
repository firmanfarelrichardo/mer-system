<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /* ------------------------------------------------------------------
     | Relasi
     | ----------------------------------------------------------------*/

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Organisasi::class, 'tenant_id');
    }

    /**
     * Semua pengguna yang terdaftar di unit ini.
     *
     * @return HasMany<Pengguna, $this>
     */
    public function pengguna(): HasMany
    {
        return $this->hasMany(Pengguna::class, 'unit_id');
    }

    /* ------------------------------------------------------------------
     | Helper Kepala Ruangan
     | ----------------------------------------------------------------*/

    /**
     * Dapatkan Kepala Ruangan untuk unit ini (jika ada).
     *
     * Kepala Ruangan adalah pengguna yang:
     * - Terdaftar di unit ini (unit_id = this.id)
     * - Memiliki peran 'Kepala Ruangan' di pivot pengguna_peran
     *
     * @return Pengguna|null
     */
    public function kepalaRuangan(): ?Pengguna
    {
        return $this->pengguna()
            ->whereHas('peran', fn ($q) => $q->where('nama_peran', Peran::KEPALA_RUANGAN))
            ->first();
    }

    /**
     * Periksa apakah unit ini sudah memiliki Kepala Ruangan.
     */
    public function sudahAdaKaru(): bool
    {
        return $this->kepalaRuangan() !== null;
    }

    /**
     * Dapatkan semua unit yang belum memiliki Kepala Ruangan.
     *
     * @param  int  $tenantId
     * @param  int|null  $kecualiUnitId  ID unit yang dikecualikan dari filter (unit Karu saat ini)
     * @return Collection<int, UnitKerja>
     */
    public static function unitTanpaKaru(int $tenantId, ?int $kecualiUnitId = null): Collection
    {
        return static::where('tenant_id', $tenantId)
            ->where(function ($query) use ($kecualiUnitId): void {
                $query->whereDoesntHave('pengguna', function ($q): void {
                    $q->whereHas('peran', fn ($q2) => $q2->where('nama_peran', Peran::KEPALA_RUANGAN));
                });

                // Sertakan unit Karu saat ini agar tetap muncul di dropdown edit
                if ($kecualiUnitId) {
                    $query->orWhere('id', $kecualiUnitId);
                }
            })
            ->orderBy('nama_unit')
            ->get();
    }
}
