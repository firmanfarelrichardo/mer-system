<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\UnitKerjaData;
use App\Models\UnitKerja;
use App\Repositories\UnitKerjaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Service UnitKerja — logika bisnis manajemen master unit kerja.
 *
 * Menjembatani Controller ↔ Repository dengan menambahkan:
 *   - Transaksi database
 *   - Audit logging (otomatis via Observer)
 */
class UnitKerjaService
{
    public function __construct(
        private readonly UnitKerjaRepository $repository,
    ) {}

    /* ------------------------------------------------------------------
     | Query
     | ----------------------------------------------------------------*/

    /**
     * Daftar unit kerja dengan paginasi dan filter.
     *
     * @param  int                  $tenantId
     * @param  array<string, mixed> $filter
     * @param  int                  $perHalaman
     * @return LengthAwarePaginator<UnitKerja>
     */
    public function daftar(int $tenantId, array $filter = [], int $perHalaman = 15): LengthAwarePaginator
    {
        return $this->repository->daftarDenganFilter($tenantId, $filter, $perHalaman);
    }

    /**
     * Cari unit kerja berdasarkan ID.
     */
    public function cariBerdasarkanId(int $id): ?UnitKerja
    {
        return $this->repository->cariBerdasarkanId($id);
    }

    /* ------------------------------------------------------------------
     | Command — Buat Unit Kerja
     | ----------------------------------------------------------------*/

    /**
     * Buat unit kerja baru. Audit log dicatat otomatis oleh Observer.
     */
    public function buat(UnitKerjaData $dto): UnitKerja
    {
        return DB::transaction(fn (): UnitKerja => $this->repository->buat([
            'tenant_id'  => $dto->tenant_id,
            'kode_unit'  => $dto->kode_unit,
            'nama_unit'  => $dto->nama_unit,
            'keterangan' => $dto->keterangan,
        ]));
    }

    /* ------------------------------------------------------------------
     | Command — Perbarui Unit Kerja
     | ----------------------------------------------------------------*/

    /**
     * Perbarui data unit kerja. Audit log dicatat otomatis oleh Observer.
     */
    public function perbarui(UnitKerja $unitKerja, UnitKerjaData $dto): UnitKerja
    {
        return DB::transaction(fn (): UnitKerja => $this->repository->perbarui($unitKerja, [
            'kode_unit'  => $dto->kode_unit,
            'nama_unit'  => $dto->nama_unit,
            'keterangan' => $dto->keterangan,
        ]));
    }

    /* ------------------------------------------------------------------
     | Command — Hapus Unit Kerja (Soft Delete)
     | ----------------------------------------------------------------*/

    /**
     * Hapus unit kerja. Audit log dicatat otomatis oleh Observer.
     */
    public function hapus(UnitKerja $unitKerja): bool
    {
        return DB::transaction(fn (): bool => $this->repository->hapus($unitKerja));
    }

    /* ------------------------------------------------------------------
     | Statistik
     | ----------------------------------------------------------------*/

    /**
     * Hitung total unit kerja di tenant.
     */
    public function hitungTotal(int $tenantId): int
    {
        return $this->repository->hitungTotal($tenantId);
    }
}
