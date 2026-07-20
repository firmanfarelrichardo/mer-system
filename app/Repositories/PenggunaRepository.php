<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Pengguna;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Repository Pengguna - akses data ke tabel `akun.pengguna`.
 *
 * Layer ini hanya bertanggung jawab pada query & persistensi.
 * TIDAK boleh mengandung logika bisnis apapun.
 */
class PenggunaRepository
{
    /* ------------------------------------------------------------------
     | Query
     | ----------------------------------------------------------------*/

    /**
     * Ambil daftar pengguna dengan paginasi, pencarian, dan filter.
     *
     * @param  int                         $tenantId
     * @param  array<string, mixed>        $filter   Kunci opsional: cari, peran_id, unit_id, status
     * @param  int                         $perHalaman
     * @return LengthAwarePaginator<int, Pengguna>
     */
    public function daftarDenganFilter(int $tenantId, array $filter = [], int $perHalaman = 15): LengthAwarePaginator
    {
        return Pengguna::query()
            ->with(['peran', 'unitKerja'])
            ->where('tenant_id', $tenantId)
            ->when($filter['cari'] ?? null, function (Builder $q, string $cari): void {
                $q->where(function (Builder $sub) use ($cari): void {
                    $sub->where('nama_lengkap', 'ilike', "%{$cari}%")
                        ->orWhere('nomor_induk', 'ilike', "%{$cari}%")
                        ->orWhere('email', 'ilike', "%{$cari}%");
                });
            })
            ->when($filter['peran_id'] ?? null, function (Builder $q, int $peranId): void {
                $q->whereHas('peran', fn (Builder $p) => $p->where('akun.peran.id', $peranId));
            })
            ->when($filter['unit_id'] ?? null, function (Builder $q, int $unitId): void {
                $q->where('unit_id', $unitId);
            })
            ->when(isset($filter['status']), function (Builder $q) use ($filter): void {
                $q->where('is_aktif', (bool) $filter['status']);
            })
            ->orderBy('nama_lengkap')
            ->paginate($perHalaman)
            ->withQueryString();
    }

    /**
     * Cari pengguna berdasarkan ID (dengan relasi).
     */
    public function cariBerdasarkanId(int $id): ?Pengguna
    {
        return Pengguna::with(['peran', 'unitKerja'])->find($id);
    }

    /* ------------------------------------------------------------------
     | Persistensi
     | ----------------------------------------------------------------*/

    /**
     * Simpan pengguna baru ke database.
     *
     * @param  array<string, mixed> $atribut
     */
    public function buat(array $atribut): Pengguna
    {
        return Pengguna::create($atribut);
    }

    /**
     * Perbarui data pengguna yang sudah ada.
     *
     * @param  Pengguna              $pengguna
     * @param  array<string, mixed>  $atribut
     */
    public function perbarui(Pengguna $pengguna, array $atribut): Pengguna
    {
        $pengguna->update($atribut);

        return $pengguna->fresh(['peran', 'unitKerja']);
    }

    /**
     * Sinkronkan peran pengguna melalui pivot table.
     *
     * @param  Pengguna   $pengguna
     * @param  list<int>  $peranIds
     */
    public function sinkronPeran(Pengguna $pengguna, array $peranIds): void
    {
        $pengguna->peran()->sync($peranIds);
    }

    /* ------------------------------------------------------------------
     | Statistik
     | ----------------------------------------------------------------*/

    /**
     * Hitung total pengguna aktif di tenant.
     */
    public function hitungAktif(int $tenantId): int
    {
        return Pengguna::where('tenant_id', $tenantId)->where('is_aktif', true)->count();
    }

    /**
     * Hitung total pengguna nonaktif di tenant.
     */
    public function hitungNonaktif(int $tenantId): int
    {
        return Pengguna::where('tenant_id', $tenantId)->where('is_aktif', false)->count();
    }

    /**
     * Hitung total pengguna (termasuk nonaktif) di tenant.
     */
    public function hitungTotal(int $tenantId): int
    {
        return Pengguna::where('tenant_id', $tenantId)->count();
    }
}
