<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\LogAktivitasFilterDTO;
use App\Models\LogAktivitas;
use App\Models\Peran;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Repository LogAktivitas — akses data ke tabel `audit.log_aktivitas`.
 *
 * Hanya query (read-only). Tidak ada persistensi — insert dilakukan
 * oleh AuditLogService dan Observer secara otomatis.
 */
class LogAktivitasRepository
{
    /**
     * Ambil daftar log aktivitas dengan paginasi, filter waktu,
     * dan pemisahan berdasarkan tipe peran (admin vs pengguna).
     *
     * Filter peran menggunakan `whereHas` pada relasi pengguna → peran.
     *
     * @return LengthAwarePaginator<int, LogAktivitas>
     * @phpstan-return LengthAwarePaginator<int, LogAktivitas>
     */
    public function getPaginatedLogs(LogAktivitasFilterDTO $dto, int $perHalaman = 20): LengthAwarePaginator
    {
        // @phpstan-ignore return.type
        return LogAktivitas::query()
            // @phpstan-ignore argument.type
            ->with(['pengguna' => function (Relation $q): void {
                // @phpstan-ignore method.notFound
                $q->withTrashed()->with('peran');
            }])
            ->where('tenant_id', $dto->tenantId)
            ->when(true, fn (Builder $q) => $this->filterPeran($q, $dto->tipePeran))
            ->when($dto->dariTanggal, fn (Builder $q, string $tgl) => $q->whereDate('created_at', '>=', $tgl))
            ->when($dto->sampaiTanggal, fn (Builder $q, string $tgl) => $q->whereDate('created_at', '<=', $tgl))
            ->when($dto->cari, function (Builder $q, string $cari): void {
                $q->where(function (Builder $sub) use ($cari): void {
                    $sub->where('nama_tabel', 'ilike', "%{$cari}%")
                        ->orWhere('aksi', 'ilike', "%{$cari}%")
                        ->orWhereHas('pengguna', function (Builder $p) use ($cari): void {
                            // @phpstan-ignore method.notFound
                            $p->withTrashed()->where('nama_lengkap', 'ilike', "%{$cari}%");
                        });
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perHalaman)
            ->withQueryString();
    }

    /**
     * Filter log berdasarkan tipe peran: admin atau pengguna biasa.
     *
     * - 'admin'    → hanya log dari pengguna yang memiliki peran Admin
     * - 'pengguna' → hanya log dari pengguna yang TIDAK memiliki peran Admin
     */
    private function filterPeran(Builder $query, string $tipePeran): Builder
    {
        $constraint = fn (Builder $q) => $q->where('nama_peran', Peran::ADMIN);

        return $tipePeran === 'admin'
            ? $query->whereHas('pengguna', function (Builder $q) use ($constraint): Builder {
                // @phpstan-ignore method.notFound
                return $q->withTrashed()->whereHas('peran', $constraint);
            })
            : $query->whereHas('pengguna', function (Builder $q) use ($constraint): Builder {
                // @phpstan-ignore method.notFound
                return $q->withTrashed()->whereDoesntHave('peran', $constraint);
            });
    }
}
