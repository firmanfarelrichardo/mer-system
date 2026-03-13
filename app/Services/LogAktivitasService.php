<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\LogAktivitasFilterDTO;
use App\Repositories\LogAktivitasRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Service LogAktivitas — logika bisnis untuk dashboard audit trail.
 *
 * Read-only service. Insert dilakukan oleh AuditLogService & Observer.
 * Menerima DTO dari Controller, meneruskan ke Repository.
 */
class LogAktivitasService
{
    public function __construct(
        private readonly LogAktivitasRepository $repository,
    ) {}

    /**
     * Ambil daftar log aktivitas berdasarkan filter DTO.
     *
     * @return LengthAwarePaginator<int, \App\Models\LogAktivitas>
     */
    public function daftar(LogAktivitasFilterDTO $dto, int $perHalaman = 20): LengthAwarePaginator
    {
        return $this->repository->getPaginatedLogs($dto, $perHalaman);
    }
}
