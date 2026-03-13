<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\KategoriKesalahan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Repository KategoriKesalahan — akses data ke tabel `master.kategori_kesalahan`.
 *
 * Layer ini hanya bertanggung jawab pada query & persistensi.
 * TIDAK boleh mengandung logika bisnis apapun.
 */
class KategoriKesalahanRepository
{
    /* ------------------------------------------------------------------
     | Query
     | ----------------------------------------------------------------*/

    /**
     * Ambil daftar kategori kesalahan dengan paginasi dan pencarian.
     *
     * @param  int                  $tenantId
     * @param  array<string, mixed> $filter   Kunci opsional: cari
     * @param  int                  $perHalaman
     * @return LengthAwarePaginator<int, KategoriKesalahan>
     */
    public function daftarDenganFilter(int $tenantId, array $filter = [], int $perHalaman = 15): LengthAwarePaginator
    {
        return KategoriKesalahan::query()
            ->where('tenant_id', $tenantId)
            ->when($filter['cari'] ?? null, function (Builder $q, string $cari): void {
                $q->where('nama_kategori', 'ilike', "%{$cari}%");
            })
            ->orderBy('nama_kategori')
            ->paginate($perHalaman)
            ->withQueryString();
    }

    /**
     * Cari kategori berdasarkan ID.
     */
    public function cariBerdasarkanId(int $id): ?KategoriKesalahan
    {
        return KategoriKesalahan::find($id);
    }

    /* ------------------------------------------------------------------
     | Persistensi
     | ----------------------------------------------------------------*/

    /**
     * Simpan kategori baru ke database.
     *
     * @param  array<string, mixed> $atribut
     */
    public function buat(array $atribut): KategoriKesalahan
    {
        return KategoriKesalahan::create($atribut);
    }

    /**
     * Perbarui data kategori yang sudah ada.
     *
     * @param  KategoriKesalahan     $kategori
     * @param  array<string, mixed>  $atribut
     */
    public function perbarui(KategoriKesalahan $kategori, array $atribut): KategoriKesalahan
    {
        $kategori->update($atribut);

        return $kategori->fresh();
    }

    /**
     * Hapus kategori secara permanen.
     */
    public function hapus(KategoriKesalahan $kategori): bool
    {
        return (bool) $kategori->delete();
    }

    /* ------------------------------------------------------------------
     | Statistik
     | ----------------------------------------------------------------*/

    /**
     * Hitung total kategori di tenant.
     */
    public function hitungTotal(int $tenantId): int
    {
        return KategoriKesalahan::where('tenant_id', $tenantId)->count();
    }
}
