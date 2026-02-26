<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Insiden;
use App\Support\Paginasi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * InsidenRepository — akses data insiden yang terpisah per kebutuhan peran.
 *
 * Separation of Concern:
 *   - getDraftLaporan()   → Nakes: hanya draf miliknya.
 *   - getRiwayatLaporan()  → Nakes: hanya laporan yang sudah di-submit.
 *   - getLaporanMasuk()    → Kepala Ruangan / Komite: hanya laporan yang bukan draf.
 */
class InsidenRepository
{
    /**
     * Ambil daftar draf laporan milik Nakes tertentu.
     *
     * @param  int    $nakesId   ID pengguna Nakes.
     * @param  int    $tenantId  ID tenant.
     * @param  string|null $cari  Kata kunci pencarian opsional.
     */
    public function getDraftLaporan(
        int $nakesId,
        int $tenantId,
        ?string $cari = null,
    ): LengthAwarePaginator {
        $query = Insiden::with(['detailPasien', 'unitKerja'])
            ->where('tenant_id', $tenantId)
            ->where('pelapor_id', $nakesId)
            ->where('status_saat_ini', 'DRAF')
            ->latest('updated_at');

        if ($cari) {
            $query->where(function ($q) use ($cari) {
                $q->where('nomor_laporan', 'ilike', "%{$cari}%")
                  ->orWhereHas('detailPasien', fn ($dp) => $dp->where('nama_pasien', 'ilike', "%{$cari}%"));
            });
        }

        return $query->paginate(Paginasi::perHalaman())->withQueryString();
    }

    /**
     * Ambil riwayat laporan Nakes yang sudah di-submit (bukan draf).
     *
     * @param  int    $nakesId   ID pengguna Nakes.
     * @param  int    $tenantId  ID tenant.
     * @param  string|null $cari  Kata kunci pencarian opsional.
     * @param  string|null $status Filter status opsional.
     * @param  string|null $tipe  Filter tipe insiden opsional.
     */
    public function getRiwayatLaporan(
        int $nakesId,
        int $tenantId,
        ?string $cari = null,
        ?string $status = null,
        ?string $tipe = null,
    ): LengthAwarePaginator {
        $query = Insiden::with(['detailPasien', 'unitKerja'])
            ->where('tenant_id', $tenantId)
            ->where('pelapor_id', $nakesId)
            ->where('status_saat_ini', '!=', 'DRAF')
            ->latest('tgl_lapor');

        if ($cari) {
            $query->where(function ($q) use ($cari) {
                $q->where('nomor_laporan', 'ilike', "%{$cari}%")
                  ->orWhereHas('detailPasien', fn ($dp) => $dp->where('nama_pasien', 'ilike', "%{$cari}%"));
            });
        }

        if ($status) {
            $query->where('status_saat_ini', $status);
        }

        if ($tipe) {
            $query->where('tipe_insiden', $tipe);
        }

        return $query->paginate(Paginasi::perHalaman())->withQueryString();
    }

    /**
     * Ambil laporan masuk untuk Kepala Ruangan / Komite (selalu filter DRAF).
     * Dipanggil oleh scopeUntukPeran() secara otomatis di model, method ini
     * sebagai shorthand eksplisit jika perlu digunakan langsung.
     */
    public function getLaporanMasuk(
        int $tenantId,
        ?int $unitId = null,
        ?string $cari = null,
        ?string $status = null,
        ?string $tipe = null,
    ): LengthAwarePaginator {
        $query = Insiden::with(['detailPasien', 'unitKerja'])
            ->where('tenant_id', $tenantId)
            ->where('status_saat_ini', '!=', 'DRAF')
            ->latest('tgl_lapor');

        if ($unitId) {
            $query->where('unit_id', $unitId);
        }

        if ($cari) {
            $query->where(function ($q) use ($cari) {
                $q->where('nomor_laporan', 'ilike', "%{$cari}%")
                  ->orWhereHas('detailPasien', fn ($dp) => $dp->where('nama_pasien', 'ilike', "%{$cari}%"));
            });
        }

        if ($status) {
            $query->where('status_saat_ini', $status);
        }

        if ($tipe) {
            $query->where('tipe_insiden', $tipe);
        }

        return $query->paginate(Paginasi::perHalaman())->withQueryString();
    }

    /**
     * Hitung jumlah draf milik Nakes (untuk badge sidebar).
     */
    public function countDraft(int $nakesId, int $tenantId): int
    {
        return Insiden::where('tenant_id', $tenantId)
            ->where('pelapor_id', $nakesId)
            ->where('status_saat_ini', 'DRAF')
            ->count();
    }

    /**
     * Ambil satu insiden draf milik Nakes (untuk edit).
     */
    public function findDraftById(int $insidenId, int $nakesId): ?Insiden
    {
        return Insiden::with('detailPasien')
            ->where('id', $insidenId)
            ->where('pelapor_id', $nakesId)
            ->where('status_saat_ini', 'DRAF')
            ->first();
    }
}
