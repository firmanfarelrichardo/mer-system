<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\FaktorPenyebab;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repository FaktorPenyebab - akses data ke tabel `master.faktor_penyebab`.
 *
 * Layer ini hanya bertanggung jawab pada query & persistensi.
 * TIDAK boleh mengandung logika bisnis apapun.
 */
class FaktorPenyebabRepository
{
    /* ------------------------------------------------------------------
     | Query
     | ----------------------------------------------------------------*/

    /**
     * Ambil daftar faktor penyebab untuk halaman admin (paginasi + filter).
     *
     * @param  int                  $tenantId
     * @param  array<string, mixed> $filter   Kunci opsional: cari
     * @param  int                  $perHalaman
     * @return LengthAwarePaginator<int, FaktorPenyebab>
     */
    public function getPaginatedAdmin(int $tenantId, array $filter = [], int $perHalaman = 15): LengthAwarePaginator
    {
        return FaktorPenyebab::query()
            ->where('tenant_id', $tenantId)
            ->when($filter['cari'] ?? null, function (Builder $q, string $cari): void {
                $q->where('nama', 'ilike', "%{$cari}%");
            })
            ->orderBy('nama')
            ->paginate($perHalaman)
            ->withQueryString();
    }

    /**
     * Ambil semua faktor penyebab yang aktif untuk form input Nakes.
     *
     * @return Collection<int, FaktorPenyebab>
     */
    public function getActiveForForm(int $tenantId): Collection
    {
        return FaktorPenyebab::query()
            ->where('tenant_id', $tenantId)
            ->where('is_aktif', true)
            ->orderBy('nama')
            ->get();
    }

    /**
     * Cari faktor penyebab berdasarkan ID.
     */
    public function cariBerdasarkanId(int $id): ?FaktorPenyebab
    {
        return FaktorPenyebab::find($id);
    }

    /* ------------------------------------------------------------------
     | Persistensi
     | ----------------------------------------------------------------*/

    /**
     * Simpan faktor penyebab baru ke database.
     *
     * @param  array<string, mixed> $atribut
     */
    public function store(array $atribut): FaktorPenyebab
    {
        return FaktorPenyebab::create($atribut);
    }

    /**
     * Perbarui data faktor penyebab yang sudah ada.
     *
     * @param  FaktorPenyebab       $faktorPenyebab
     * @param  array<string, mixed> $atribut
     */
    public function update(FaktorPenyebab $faktorPenyebab, array $atribut): FaktorPenyebab
    {
        $faktorPenyebab->update($atribut);

        return $faktorPenyebab->fresh();
    }

    /**
     * Toggle status aktif (is_aktif) faktor penyebab.
     */
    public function toggleActive(FaktorPenyebab $faktorPenyebab): FaktorPenyebab
    {
        $faktorPenyebab->update(['is_aktif' => ! $faktorPenyebab->is_aktif]);

        return $faktorPenyebab->fresh();
    }

    /**
     * Hapus faktor penyebab (soft delete).
     */
    public function hapus(FaktorPenyebab $faktorPenyebab): bool
    {
        return (bool) $faktorPenyebab->delete();
    }
}
