<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\MasterFormDataDTO;
use App\Models\FaktorPenyebab;
use App\Repositories\FaktorPenyebabRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service FaktorPenyebab — logika bisnis manajemen faktor penyebab insiden.
 *
 * Menjembatani Controller ↔ Repository dengan menambahkan:
 *   - Transaksi database (ACID)
 *   - Audit logging (otomatis via Observer)
 */
class FaktorPenyebabService
{
    public function __construct(
        private readonly FaktorPenyebabRepository $repository,
    ) {}

    /* ------------------------------------------------------------------
     | Query
     | ----------------------------------------------------------------*/

    /**
     * Daftar faktor penyebab dengan paginasi dan filter (admin panel).
     *
     * @param  int                  $tenantId
     * @param  array<string, mixed> $filter
     * @param  int                  $perHalaman
     * @return LengthAwarePaginator<int, FaktorPenyebab>
     */
    public function daftar(int $tenantId, array $filter = [], int $perHalaman = 15): LengthAwarePaginator
    {
        return $this->repository->getPaginatedAdmin($tenantId, $filter, $perHalaman);
    }

    /**
     * Ambil semua faktor penyebab aktif untuk form input Nakes.
     *
     * @return Collection<int, FaktorPenyebab>
     */
    public function getAktifUntukForm(int $tenantId): Collection
    {
        return $this->repository->getActiveForForm($tenantId);
    }

    /**
     * Cari faktor penyebab berdasarkan ID.
     */
    public function cariBerdasarkanId(int $id): ?FaktorPenyebab
    {
        return $this->repository->cariBerdasarkanId($id);
    }

    /* ------------------------------------------------------------------
     | Command — Buat
     | ----------------------------------------------------------------*/

    /**
     * Buat faktor penyebab baru. Audit log dicatat otomatis oleh Observer.
     */
    public function buat(MasterFormDataDTO $dto): FaktorPenyebab
    {
        return DB::transaction(fn (): FaktorPenyebab => $this->repository->store([
            'tenant_id' => $dto->tenantId,
            'nama'      => $dto->nama,
            'is_aktif'  => $dto->isAktif,
        ]));
    }

    /* ------------------------------------------------------------------
     | Command — Perbarui
     | ----------------------------------------------------------------*/

    /**
     * Perbarui data faktor penyebab. Audit log dicatat otomatis oleh Observer.
     */
    public function perbarui(FaktorPenyebab $faktorPenyebab, MasterFormDataDTO $dto): FaktorPenyebab
    {
        return DB::transaction(fn (): FaktorPenyebab => $this->repository->update($faktorPenyebab, [
            'nama'     => $dto->nama,
            'is_aktif' => $dto->isAktif,
        ]));
    }

    /* ------------------------------------------------------------------
     | Command — Toggle Aktif
     | ----------------------------------------------------------------*/

    /**
     * Ubah status aktif/nonaktif. Audit log dicatat otomatis oleh Observer.
     */
    public function toggleAktif(FaktorPenyebab $faktorPenyebab): FaktorPenyebab
    {
        return DB::transaction(fn (): FaktorPenyebab => $this->repository->toggleActive($faktorPenyebab));
    }

    /* ------------------------------------------------------------------
     | Command — Hapus (Soft Delete)
     | ----------------------------------------------------------------*/

    /**
     * Hapus faktor penyebab. Audit log dicatat otomatis oleh Observer.
     */
    public function hapus(FaktorPenyebab $faktorPenyebab): bool
    {
        return DB::transaction(fn (): bool => $this->repository->hapus($faktorPenyebab));
    }
}
