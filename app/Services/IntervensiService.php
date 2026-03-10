<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\MasterFormDataDTO;
use App\Models\TindakanIntervensi;
use App\Repositories\IntervensiRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service TindakanIntervensi — logika bisnis manajemen intervensi pasien.
 *
 * Menjembatani Controller ↔ Repository dengan menambahkan:
 *   - Transaksi database (ACID)
 *   - Audit logging (otomatis via Observer)
 */
class IntervensiService
{
    public function __construct(
        private readonly IntervensiRepository $repository,
    ) {}

    /* ------------------------------------------------------------------
     | Query
     | ----------------------------------------------------------------*/

    /**
     * Daftar tindakan intervensi dengan paginasi dan filter (admin panel).
     *
     * @param  int                  $tenantId
     * @param  array<string, mixed> $filter
     * @param  int                  $perHalaman
     * @return LengthAwarePaginator<TindakanIntervensi>
     */
    public function daftar(int $tenantId, array $filter = [], int $perHalaman = 15): LengthAwarePaginator
    {
        return $this->repository->getPaginatedAdmin($tenantId, $filter, $perHalaman);
    }

    /**
     * Ambil semua tindakan intervensi aktif untuk form input Nakes.
     *
     * @return Collection<int, TindakanIntervensi>
     */
    public function getAktifUntukForm(int $tenantId): Collection
    {
        return $this->repository->getActiveForForm($tenantId);
    }

    /**
     * Cari tindakan intervensi berdasarkan ID.
     */
    public function cariBerdasarkanId(int $id): ?TindakanIntervensi
    {
        return $this->repository->cariBerdasarkanId($id);
    }

    /* ------------------------------------------------------------------
     | Command — Buat
     | ----------------------------------------------------------------*/

    /**
     * Buat tindakan intervensi baru. Audit log dicatat otomatis oleh Observer.
     */
    public function buat(MasterFormDataDTO $dto): TindakanIntervensi
    {
        return DB::transaction(fn (): TindakanIntervensi => $this->repository->store([
            'tenant_id' => $dto->tenantId,
            'nama'      => $dto->nama,
            'is_aktif'  => $dto->isAktif,
        ]));
    }

    /* ------------------------------------------------------------------
     | Command — Perbarui
     | ----------------------------------------------------------------*/

    /**
     * Perbarui data tindakan intervensi. Audit log dicatat otomatis oleh Observer.
     */
    public function perbarui(TindakanIntervensi $intervensi, MasterFormDataDTO $dto): TindakanIntervensi
    {
        return DB::transaction(fn (): TindakanIntervensi => $this->repository->update($intervensi, [
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
    public function toggleAktif(TindakanIntervensi $intervensi): TindakanIntervensi
    {
        return DB::transaction(fn (): TindakanIntervensi => $this->repository->toggleActive($intervensi));
    }

    /* ------------------------------------------------------------------
     | Command — Hapus (Soft Delete)
     | ----------------------------------------------------------------*/

    /**
     * Hapus tindakan intervensi. Audit log dicatat otomatis oleh Observer.
     */
    public function hapus(TindakanIntervensi $intervensi): bool
    {
        return DB::transaction(fn (): bool => $this->repository->hapus($intervensi));
    }
}
