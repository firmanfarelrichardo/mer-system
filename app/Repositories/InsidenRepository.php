<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Insiden;
use App\Support\Paginasi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * InsidenRepository - akses data insiden yang terpisah per kebutuhan peran.
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

    /**
     * Ambil daftar insiden dalam rentang tanggal kejadian untuk laporan rekapitulasi.
     *
     * Aturan query:
     *   - Filter berdasarkan tgl_kejadian (whereBetween).
     *   - Abaikan status DRAF - hanya laporan yang sudah dikirim.
     *   - Eager-load relasi untuk mencegah N+1: unitKerja, detailPasien, kategoriKesalahans.
     *   - Diurutkan ASC berdasarkan tgl_kejadian agar tampil kronologis di PDF.
     *
     * @param  int    $tenantId
     * @param  string $startDate  Format Y-m-d
     * @param  string $endDate    Format Y-m-d
     * @return \Illuminate\Database\Eloquent\Collection<int, Insiden>
     */
    public function getSummaryByDateRange(int $tenantId, string $startDate, string $endDate): \Illuminate\Database\Eloquent\Collection
    {
        return Insiden::with(['unitKerja', 'detailPasien', 'kategoriKesalahans'])
            ->where('tenant_id', $tenantId)
            ->where('status_saat_ini', '!=', 'DRAF')
            ->whereBetween('tgl_kejadian', [
                $startDate . ' 00:00:00',
                $endDate   . ' 23:59:59',
            ])
            ->orderBy('tgl_kejadian', 'ASC')
            ->get();
    }

    /**
     * Ambil satu insiden lengkap untuk cetak PDF.
     *
     * Eager-load semua relasi yang dibutuhkan view PDF agar bebas N+1:
     *   - detailPasien       → data pasien, kronologi & klasifikasi.
     *   - pelapor            → nama & kontak pelapor.
     *   - unitKerja          → nama unit kerja.
     *   - tindakLanjut       → histori tindak lanjut beserta pelaku & perannya.
     */
    public function getInsidenForPdf(int $id): ?Insiden
    {
        return Insiden::with([
                'detailPasien',
                'pelapor',
                'unitKerja',
                'tindakLanjut.pengguna.peran',
                'tindakLanjut.pengguna.unitKerja',
            ])
            ->where('status_saat_ini', '!=', 'DRAF')
            ->find($id);
    }
}
