<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\JenisKesalahan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repository JenisKesalahan - akses data ke tabel `master.jenis_kesalahan`.
 *
 * Layer ini hanya bertanggung jawab pada query & persistensi.
 * TIDAK boleh mengandung logika bisnis apapun.
 */
class JenisKesalahanRepository
{
    /* ------------------------------------------------------------------
     | Query
     | ----------------------------------------------------------------*/

    /**
     * Ambil daftar jenis kesalahan untuk halaman admin (paginasi + filter).
     */
    public function getPaginatedAdmin(int $tenantId, array $filter = [], int $perHalaman = 15): LengthAwarePaginator
    {
        return JenisKesalahan::query()
            ->where('tenant_id', $tenantId)
            ->when($filter['cari'] ?? null, function (Builder $q, string $cari): void {
                $q->where('nama', 'ilike', "%{$cari}%");
            })
            ->orderBy('nama')
            ->paginate($perHalaman)
            ->withQueryString();
    }

    /**
     * Ambil semua jenis kesalahan yang aktif untuk form input Nakes.
     *
     * @return Collection<int, JenisKesalahan>
     */
    public function getActiveForForm(int $tenantId): Collection
    {
        return JenisKesalahan::query()
            ->where('tenant_id', $tenantId)
            ->where('is_aktif', true)
            ->orderBy('nama')
            ->get();
    }

    /**
     * Cari jenis kesalahan berdasarkan ID.
     */
    public function cariBerdasarkanId(int $id): ?JenisKesalahan
    {
        return JenisKesalahan::find($id);
    }

    /* ------------------------------------------------------------------
     | Persistensi
     | ----------------------------------------------------------------*/

    public function store(array $atribut): JenisKesalahan
    {
        return JenisKesalahan::create($atribut);
    }

    public function update(JenisKesalahan $jenisKesalahan, array $atribut): JenisKesalahan
    {
        $jenisKesalahan->update($atribut);

        return $jenisKesalahan->fresh();
    }

    public function toggleActive(JenisKesalahan $jenisKesalahan): JenisKesalahan
    {
        $jenisKesalahan->update(['is_aktif' => ! $jenisKesalahan->is_aktif]);

        return $jenisKesalahan->fresh();
    }

    public function hapus(JenisKesalahan $jenisKesalahan): bool
    {
        return (bool) $jenisKesalahan->delete();
    }
}
