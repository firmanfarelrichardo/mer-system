<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pengguna;
use App\Repositories\LogAktivitasRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Service LogAktivitas — logika bisnis untuk dashboard audit trail.
 *
 * Read-only service. Insert dilakukan oleh AuditLogService & Observer.
 */
class LogAktivitasService
{
    public function __construct(
        private readonly LogAktivitasRepository $repository,
    ) {}

    /**
     * Daftar log aktivitas dengan filter dan paginasi.
     *
     * @param  int                  $tenantId
     * @param  array<string, mixed> $filter
     * @param  int                  $perHalaman
     */
    public function daftar(int $tenantId, array $filter = [], int $perHalaman = 20): LengthAwarePaginator
    {
        return $this->repository->daftarDenganFilter($tenantId, $filter, $perHalaman);
    }

    /**
     * Riwayat aktivitas per pengguna.
     */
    public function riwayatPengguna(int $tenantId, int $penggunaId, int $perHalaman = 20): LengthAwarePaginator
    {
        return $this->repository->riwayatPengguna($tenantId, $penggunaId, $perHalaman);
    }

    /**
     * Daftar aksi unik untuk filter dropdown.
     *
     * @return Collection<int, string>
     */
    public function daftarAksiUnik(int $tenantId): Collection
    {
        return $this->repository->daftarAksiUnik($tenantId);
    }

    /**
     * Ambil daftar pengguna untuk filter (termasuk yang sudah dihapus).
     *
     * @return Collection<int, Pengguna>
     */
    public function daftarPengguna(int $tenantId): Collection
    {
        return Pengguna::withTrashed()
            ->where('tenant_id', $tenantId)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nomor_induk']);
    }
}
