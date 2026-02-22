<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\LogAktivitas;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Repository LogAktivitas — akses data ke tabel `audit.log_aktivitas`.
 *
 * Hanya query (read-only). Tidak ada persistensi — insert dilakukan
 * oleh AuditLogService dan Observer secara otomatis.
 */
class LogAktivitasRepository
{
    /**
     * Ambil daftar log aktivitas dengan paginasi dan filter.
     *
     * @param  int                  $tenantId
     * @param  array<string, mixed> $filter   Kunci opsional: cari, aksi, pengguna_id, dari_tanggal, sampai_tanggal
     * @param  int                  $perHalaman
     * @return LengthAwarePaginator<LogAktivitas>
     */
    public function daftarDenganFilter(int $tenantId, array $filter = [], int $perHalaman = 20): LengthAwarePaginator
    {
        return LogAktivitas::query()
            ->with(['pengguna' => fn ($q) => $q->withTrashed()])
            ->where('tenant_id', $tenantId)
            ->when($filter['aksi'] ?? null, function (Builder $q, string $aksi): void {
                $q->where('aksi', $aksi);
            })
            ->when($filter['pengguna_id'] ?? null, function (Builder $q, int $id): void {
                $q->where('id_pengguna', $id);
            })
            ->when($filter['cari'] ?? null, function (Builder $q, string $cari): void {
                $q->where(function (Builder $sub) use ($cari): void {
                    $sub->where('nama_tabel', 'ilike', "%{$cari}%")
                        ->orWhereHas('pengguna', fn (Builder $p) => $p->withTrashed()->where('nama_lengkap', 'ilike', "%{$cari}%"));
                });
            })
            ->when($filter['dari_tanggal'] ?? null, function (Builder $q, string $tanggal): void {
                $q->whereDate('created_at', '>=', $tanggal);
            })
            ->when($filter['sampai_tanggal'] ?? null, function (Builder $q, string $tanggal): void {
                $q->whereDate('created_at', '<=', $tanggal);
            })
            ->orderByDesc('created_at')
            ->paginate($perHalaman)
            ->withQueryString();
    }

    /**
     * Ambil riwayat aktivitas seorang pengguna.
     *
     * @param  int $tenantId
     * @param  int $penggunaId
     * @param  int $perHalaman
     * @return LengthAwarePaginator<LogAktivitas>
     */
    public function riwayatPengguna(int $tenantId, int $penggunaId, int $perHalaman = 20): LengthAwarePaginator
    {
        return LogAktivitas::query()
            ->with(['pengguna' => fn ($q) => $q->withTrashed()])
            ->where('tenant_id', $tenantId)
            ->where('id_pengguna', $penggunaId)
            ->orderByDesc('created_at')
            ->paginate($perHalaman)
            ->withQueryString();
    }

    /**
     * Daftar aksi unik yang ada di log (untuk filter dropdown).
     *
     * @return Collection<int, string>
     */
    public function daftarAksiUnik(int $tenantId): Collection
    {
        return LogAktivitas::where('tenant_id', $tenantId)
            ->distinct()
            ->orderBy('aksi')
            ->pluck('aksi');
    }
}
