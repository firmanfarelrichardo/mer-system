<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\UnitKerja;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Repository UnitKerja — akses data ke tabel `master.unit_kerja`.
 *
 * Layer ini hanya bertanggung jawab pada query & persistensi.
 * TIDAK boleh mengandung logika bisnis apapun.
 */
class UnitKerjaRepository
{
    /* ------------------------------------------------------------------
     | Query
     | ----------------------------------------------------------------*/

    /**
     * Ambil daftar unit kerja dengan paginasi dan pencarian.
     *
     * @param  int                  $tenantId
     * @param  array<string, mixed> $filter   Kunci opsional: cari
     * @param  int                  $perHalaman
     * @return LengthAwarePaginator<UnitKerja>
     */
    public function daftarDenganFilter(int $tenantId, array $filter = [], int $perHalaman = 15): LengthAwarePaginator
    {
        return UnitKerja::query()
            ->where('tenant_id', $tenantId)
            ->when($filter['cari'] ?? null, function (Builder $q, string $cari): void {
                $q->where(function (Builder $sub) use ($cari): void {
                    $sub->where('kode_unit', 'ilike', "%{$cari}%")
                        ->orWhere('nama_unit', 'ilike', "%{$cari}%");
                });
            })
            ->orderBy('kode_unit')
            ->paginate($perHalaman)
            ->withQueryString();
    }

    /**
     * Cari unit kerja berdasarkan ID.
     */
    public function cariBerdasarkanId(int $id): ?UnitKerja
    {
        return UnitKerja::find($id);
    }

    /* ------------------------------------------------------------------
     | Persistensi
     | ----------------------------------------------------------------*/

    /**
     * Simpan unit kerja baru ke database.
     *
     * @param  array<string, mixed> $atribut
     */
    public function buat(array $atribut): UnitKerja
    {
        return UnitKerja::create($atribut);
    }

    /**
     * Perbarui data unit kerja yang sudah ada.
     *
     * @param  UnitKerja             $unitKerja
     * @param  array<string, mixed>  $atribut
     */
    public function perbarui(UnitKerja $unitKerja, array $atribut): UnitKerja
    {
        $unitKerja->update($atribut);

        return $unitKerja->fresh();
    }

    /**
     * Hapus unit kerja (soft delete).
     */
    public function hapus(UnitKerja $unitKerja): bool
    {
        return (bool) $unitKerja->delete();
    }

    /* ------------------------------------------------------------------
     | Statistik
     | ----------------------------------------------------------------*/

    /**
     * Hitung total unit kerja aktif (belum soft-deleted) di tenant.
     */
    public function hitungTotal(int $tenantId): int
    {
        return UnitKerja::where('tenant_id', $tenantId)->count();
    }
}
