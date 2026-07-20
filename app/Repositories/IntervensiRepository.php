<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TindakanIntervensi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repository TindakanIntervensi - akses data ke tabel `master.tindakan_intervensi`.
 *
 * Layer ini hanya bertanggung jawab pada query & persistensi.
 * TIDAK boleh mengandung logika bisnis apapun.
 */
class IntervensiRepository
{
    /* ------------------------------------------------------------------
     | Query
     | ----------------------------------------------------------------*/

    /**
     * Ambil daftar tindakan intervensi untuk halaman admin (paginasi + filter).
     *
     * @param  int                  $tenantId
     * @param  array<string, mixed> $filter   Kunci opsional: cari
     * @param  int                  $perHalaman
     * @return LengthAwarePaginator<int, TindakanIntervensi>
     */
    public function getPaginatedAdmin(int $tenantId, array $filter = [], int $perHalaman = 15): LengthAwarePaginator
    {
        return TindakanIntervensi::query()
            ->where('tenant_id', $tenantId)
            ->when($filter['cari'] ?? null, function (Builder $q, string $cari): void {
                $q->where('nama', 'ilike', "%{$cari}%");
            })
            ->orderBy('nama')
            ->paginate($perHalaman)
            ->withQueryString();
    }

    /**
     * Ambil semua tindakan intervensi yang aktif untuk form input Nakes.
     *
     * @return Collection<int, TindakanIntervensi>
     */
    public function getActiveForForm(int $tenantId): Collection
    {
        return TindakanIntervensi::query()
            ->where('tenant_id', $tenantId)
            ->where('is_aktif', true)
            ->orderBy('nama')
            ->get();
    }

    /**
     * Cari tindakan intervensi berdasarkan ID.
     */
    public function cariBerdasarkanId(int $id): ?TindakanIntervensi
    {
        return TindakanIntervensi::find($id);
    }

    /* ------------------------------------------------------------------
     | Persistensi
     | ----------------------------------------------------------------*/

    /**
     * Simpan tindakan intervensi baru ke database.
     *
     * @param  array<string, mixed> $atribut
     */
    public function store(array $atribut): TindakanIntervensi
    {
        return TindakanIntervensi::create($atribut);
    }

    /**
     * Perbarui data tindakan intervensi yang sudah ada.
     *
     * @param  TindakanIntervensi   $intervensi
     * @param  array<string, mixed> $atribut
     */
    public function update(TindakanIntervensi $intervensi, array $atribut): TindakanIntervensi
    {
        $intervensi->update($atribut);

        return $intervensi->fresh();
    }

    /**
     * Toggle status aktif (is_aktif) tindakan intervensi.
     */
    public function toggleActive(TindakanIntervensi $intervensi): TindakanIntervensi
    {
        $intervensi->update(['is_aktif' => ! $intervensi->is_aktif]);

        return $intervensi->fresh();
    }

    /**
     * Hapus tindakan intervensi (soft delete).
     */
    public function hapus(TindakanIntervensi $intervensi): bool
    {
        return (bool) $intervensi->delete();
    }
}
