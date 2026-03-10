<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\MasterFormDataDTO;
use App\Models\TipeCedera;
use App\Repositories\TipeCederaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service TipeCedera — logika bisnis manajemen tipe cedera pasien.
 *
 * Menjembatani Controller ↔ Repository dengan menambahkan:
 *   - Transaksi database (ACID)
 *   - Audit logging (otomatis via Observer)
 */
class TipeCederaService
{
    public function __construct(
        private readonly TipeCederaRepository $repository,
    ) {}

    /* ------------------------------------------------------------------
     | Query
     | ----------------------------------------------------------------*/

    /**
     * Daftar tipe cedera dengan paginasi dan filter (admin panel).
     *
     * @param  int                  $tenantId
     * @param  array<string, mixed> $filter
     * @param  int                  $perHalaman
     * @return LengthAwarePaginator<TipeCedera>
     */
    public function daftar(int $tenantId, array $filter = [], int $perHalaman = 15): LengthAwarePaginator
    {
        return $this->repository->getPaginatedAdmin($tenantId, $filter, $perHalaman);
    }

    /**
     * Ambil semua tipe cedera aktif untuk form input Nakes.
     *
     * @return Collection<int, TipeCedera>
     */
    public function getAktifUntukForm(int $tenantId): Collection
    {
        return $this->repository->getActiveForForm($tenantId);
    }

    /**
     * Cari tipe cedera berdasarkan ID.
     */
    public function cariBerdasarkanId(int $id): ?TipeCedera
    {
        return $this->repository->cariBerdasarkanId($id);
    }

    /* ------------------------------------------------------------------
     | Command — Buat
     | ----------------------------------------------------------------*/

    /**
     * Buat tipe cedera baru. Audit log dicatat otomatis oleh Observer.
     */
    public function buat(MasterFormDataDTO $dto): TipeCedera
    {
        return DB::transaction(fn (): TipeCedera => $this->repository->store([
            'tenant_id' => $dto->tenantId,
            'nama'      => $dto->nama,
            'is_aktif'  => $dto->isAktif,
        ]));
    }

    /* ------------------------------------------------------------------
     | Command — Perbarui
     | ----------------------------------------------------------------*/

    /**
     * Perbarui data tipe cedera. Audit log dicatat otomatis oleh Observer.
     */
    public function perbarui(TipeCedera $tipeCedera, MasterFormDataDTO $dto): TipeCedera
    {
        return DB::transaction(fn (): TipeCedera => $this->repository->update($tipeCedera, [
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
    public function toggleAktif(TipeCedera $tipeCedera): TipeCedera
    {
        return DB::transaction(fn (): TipeCedera => $this->repository->toggleActive($tipeCedera));
    }

    /* ------------------------------------------------------------------
     | Command — Hapus (Soft Delete)
     | ----------------------------------------------------------------*/

    /**
     * Hapus tipe cedera. Audit log dicatat otomatis oleh Observer.
     */
    public function hapus(TipeCedera $tipeCedera): bool
    {
        return DB::transaction(fn (): bool => $this->repository->hapus($tipeCedera));
    }
}
