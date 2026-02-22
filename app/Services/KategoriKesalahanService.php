<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\KategoriKesalahanData;
use App\Models\KategoriKesalahan;
use App\Repositories\KategoriKesalahanRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Service KategoriKesalahan — logika bisnis manajemen kategori kesalahan.
 *
 * Menjembatani Controller ↔ Repository dengan menambahkan:
 *   - Transaksi database
 *   - Audit logging (otomatis via Observer)
 */
class KategoriKesalahanService
{
    public function __construct(
        private readonly KategoriKesalahanRepository $repository,
    ) {}

    /* ------------------------------------------------------------------
     | Query
     | ----------------------------------------------------------------*/

    /**
     * Daftar kategori dengan paginasi dan filter.
     *
     * @param  int                  $tenantId
     * @param  array<string, mixed> $filter
     * @param  int                  $perHalaman
     * @return LengthAwarePaginator<KategoriKesalahan>
     */
    public function daftar(int $tenantId, array $filter = [], int $perHalaman = 15): LengthAwarePaginator
    {
        return $this->repository->daftarDenganFilter($tenantId, $filter, $perHalaman);
    }

    /**
     * Cari kategori berdasarkan ID.
     */
    public function cariBerdasarkanId(int $id): ?KategoriKesalahan
    {
        return $this->repository->cariBerdasarkanId($id);
    }

    /* ------------------------------------------------------------------
     | Command — Buat Kategori
     | ----------------------------------------------------------------*/

    /**
     * Buat kategori baru. Audit log dicatat otomatis oleh Observer.
     */
    public function buat(KategoriKesalahanData $dto): KategoriKesalahan
    {
        return DB::transaction(fn (): KategoriKesalahan => $this->repository->buat([
            'tenant_id'     => $dto->tenant_id,
            'nama_kategori' => $dto->nama_kategori,
        ]));
    }

    /* ------------------------------------------------------------------
     | Command — Perbarui Kategori
     | ----------------------------------------------------------------*/

    /**
     * Perbarui data kategori. Audit log dicatat otomatis oleh Observer.
     */
    public function perbarui(KategoriKesalahan $kategori, KategoriKesalahanData $dto): KategoriKesalahan
    {
        return DB::transaction(fn (): KategoriKesalahan => $this->repository->perbarui($kategori, [
            'nama_kategori' => $dto->nama_kategori,
        ]));
    }

    /* ------------------------------------------------------------------
     | Command — Hapus Kategori
     | ----------------------------------------------------------------*/

    /**
     * Hapus kategori secara permanen. Audit log dicatat otomatis oleh Observer.
     */
    public function hapus(KategoriKesalahan $kategori): bool
    {
        return DB::transaction(fn (): bool => $this->repository->hapus($kategori));
    }

    /* ------------------------------------------------------------------
     | Statistik
     | ----------------------------------------------------------------*/

    /**
     * Hitung total kategori di tenant.
     */
    public function hitungTotal(int $tenantId): int
    {
        return $this->repository->hitungTotal($tenantId);
    }
}
