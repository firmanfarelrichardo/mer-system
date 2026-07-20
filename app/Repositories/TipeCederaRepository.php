<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TipeCedera;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repository TipeCedera - akses data ke tabel `master.tipe_cedera`.
 *
 * Layer ini hanya bertanggung jawab pada query & persistensi.
 * TIDAK boleh mengandung logika bisnis apapun.
 */
class TipeCederaRepository
{
    /* ------------------------------------------------------------------
     | Query
     | ----------------------------------------------------------------*/

    /**
     * Ambil daftar tipe cedera untuk halaman admin (paginasi + filter).
     *
     * @param  int                  $tenantId
     * @param  array<string, mixed> $filter   Kunci opsional: cari
     * @param  int                  $perHalaman
     * @return LengthAwarePaginator<int, TipeCedera>
     */
    public function getPaginatedAdmin(int $tenantId, array $filter = [], int $perHalaman = 15): LengthAwarePaginator
    {
        return TipeCedera::query()
            ->where('tenant_id', $tenantId)
            ->when($filter['cari'] ?? null, function (Builder $q, string $cari): void {
                $q->where('nama', 'ilike', "%{$cari}%");
            })
            ->orderBy('nama')
            ->paginate($perHalaman)
            ->withQueryString();
    }

    /**
     * Ambil semua tipe cedera yang aktif untuk form input Nakes.
     *
     * @return Collection<int, TipeCedera>
     */
    public function getActiveForForm(int $tenantId): Collection
    {
        return TipeCedera::query()
            ->where('tenant_id', $tenantId)
            ->where('is_aktif', true)
            ->orderBy('nama')
            ->get();
    }

    /**
     * Cari tipe cedera berdasarkan ID.
     */
    public function cariBerdasarkanId(int $id): ?TipeCedera
    {
        return TipeCedera::find($id);
    }

    /* ------------------------------------------------------------------
     | Persistensi
     | ----------------------------------------------------------------*/

    /**
     * Simpan tipe cedera baru ke database.
     *
     * @param  array<string, mixed> $atribut
     */
    public function store(array $atribut): TipeCedera
    {
        return TipeCedera::create($atribut);
    }

    /**
     * Perbarui data tipe cedera yang sudah ada.
     *
     * @param  TipeCedera           $tipeCedera
     * @param  array<string, mixed> $atribut
     */
    public function update(TipeCedera $tipeCedera, array $atribut): TipeCedera
    {
        $tipeCedera->update($atribut);

        return $tipeCedera->fresh();
    }

    /**
     * Toggle status aktif (is_aktif) tipe cedera.
     */
    public function toggleActive(TipeCedera $tipeCedera): TipeCedera
    {
        $tipeCedera->update(['is_aktif' => ! $tipeCedera->is_aktif]);

        return $tipeCedera->fresh();
    }

    /**
     * Hapus tipe cedera (soft delete).
     */
    public function hapus(TipeCedera $tipeCedera): bool
    {
        return (bool) $tipeCedera->delete();
    }
}
