<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\MasterFormDataDTO;
use App\Models\JenisKesalahan;
use App\Repositories\JenisKesalahanRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service JenisKesalahan — logika bisnis manajemen jenis kesalahan obat.
 *
 * Menjembatani Controller ↔ Repository dengan menambahkan:
 *   - Transaksi database (ACID)
 *   - Audit logging (otomatis via Observer)
 */
class JenisKesalahanService
{
    public function __construct(
        private readonly JenisKesalahanRepository $repository,
    ) {}

    /* ------------------------------------------------------------------
     | Query
     | ----------------------------------------------------------------*/

    public function daftar(int $tenantId, array $filter = [], int $perHalaman = 15): LengthAwarePaginator
    {
        return $this->repository->getPaginatedAdmin($tenantId, $filter, $perHalaman);
    }

    /**
     * @return Collection<int, JenisKesalahan>
     */
    public function getAktifUntukForm(int $tenantId): Collection
    {
        return $this->repository->getActiveForForm($tenantId);
    }

    public function cariBerdasarkanId(int $id): ?JenisKesalahan
    {
        return $this->repository->cariBerdasarkanId($id);
    }

    /* ------------------------------------------------------------------
     | Command
     | ----------------------------------------------------------------*/

    public function buat(MasterFormDataDTO $dto): JenisKesalahan
    {
        return DB::transaction(fn (): JenisKesalahan => $this->repository->store([
            'tenant_id' => $dto->tenantId,
            'nama'      => $dto->nama,
            'is_aktif'  => $dto->isAktif,
        ]));
    }

    public function perbarui(JenisKesalahan $jenisKesalahan, MasterFormDataDTO $dto): JenisKesalahan
    {
        return DB::transaction(fn (): JenisKesalahan => $this->repository->update($jenisKesalahan, [
            'nama'     => $dto->nama,
            'is_aktif' => $dto->isAktif,
        ]));
    }

    public function toggleAktif(JenisKesalahan $jenisKesalahan): JenisKesalahan
    {
        return DB::transaction(fn (): JenisKesalahan => $this->repository->toggleActive($jenisKesalahan));
    }

    public function hapus(JenisKesalahan $jenisKesalahan): bool
    {
        return DB::transaction(fn (): bool => $this->repository->hapus($jenisKesalahan));
    }
}
