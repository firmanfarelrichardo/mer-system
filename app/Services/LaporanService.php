<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\InsidenData;
use App\Events\InsidenStatusBerubah;
use App\Models\DetailPasien;
use App\Models\Insiden;
use App\Models\Pengguna;
use App\Repositories\InsidenRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * LaporanService — orkestrasi bisnis logik penyimpanan laporan insiden.
 *
 * Mendukung dua mode:
 *   - Simpan Draf: status = 'DRAF', tanpa notifikasi, tanpa nomor laporan definitif.
 *   - Kirim Laporan: status = 'kasus_baru', dengan notifikasi ke Kepala Ruangan.
 *
 * Logika dinamis berdasarkan DTO:
 *   - Jika `$dto->insidenId` ada → update (Nakes melanjutkan/mengirim draf).
 *   - Jika `$dto->insidenId` null → create record baru.
 */
class LaporanService
{
    public function __construct(
        private readonly InsidenRepository $repository,
        private readonly AuditLogService   $auditLog,
    ) {}

    /**
     * Simpan laporan insiden (baru atau update draf).
     *
     * @return Insiden  Model insiden yang tersimpan.
     */
    public function save(InsidenData $dto, Pengguna $pengguna): Insiden
    {
        $isDraft   = $dto->isDraft;
        $isUpdate  = $dto->insidenId !== null;
        $isAnonim  = empty($dto->namaPelapor);
        $statusBaru = $isDraft ? 'DRAF' : 'kasus_baru';

        $insiden = DB::transaction(function () use ($dto, $pengguna, $isDraft, $isUpdate, $isAnonim, $statusBaru): Insiden {

            // ── Bangun data insiden ─────────────────────────────────
            $tglKejadian = $dto->tanggalKejadian;
            if ($dto->waktuKejadian) {
                $tglKejadian .= ' ' . $dto->waktuKejadian . ':00';
            }

            $dataInsiden = [
                'tenant_id'       => $pengguna->tenant_id,
                'pelapor_id'      => $pengguna->id,
                'unit_id'         => null,
                'nama_unit_kerja' => $dto->unitKerja,
                'tipe_insiden'    => $dto->jenisInsiden,
                'fase_kesalahan'  => $dto->faseKesalahan,
                'status_saat_ini' => $statusBaru,
                'tgl_kejadian'    => $tglKejadian,
                'tgl_lapor'       => $isDraft ? null : now(),
                'nama_pelapor'    => $dto->namaPelapor,
                'kontak_pelapor'  => $dto->kontakPelapor,
                'is_anonim'       => $isAnonim,
                'sudah_dibaca'    => false,
            ];

            // ── Bangun data detail pasien ───────────────────────────
            $dataDetail = [
                'nama_pasien'          => $dto->namaPasien,
                'nomor_rekam_medis'    => $dto->nomorRekamMedis,
                'obat_terkait'         => $dto->namaObat,
                'kronologi'            => $dto->kronologiKejadian,
                'jenis_kesalahan'      => $dto->jenisKesalahanGabungan() ?: null,
                'cedera'               => $dto->cederaGabungan() ?: null,
                'faktor_penyebab'      => $dto->faktorPenyebabGabungan() ?: null,
                'intervensi_pasien'    => $dto->intervensiPasienGabungan() ?: null,
                'pernyataan_kronologi' => $dto->pernyataanKronologi,
            ];

            if ($isUpdate) {
                // ── UPDATE: Nakes melanjutkan draf yang sudah ada ────
                $insiden = Insiden::where('id', $dto->insidenId)
                    ->where('pelapor_id', $pengguna->id)
                    ->where('status_saat_ini', 'DRAF')
                    ->firstOrFail();

                // Generate nomor laporan saat pertama kali di-submit (bukan draf lagi).
                if (! $isDraft && empty($insiden->nomor_laporan)) {
                    $dataInsiden['nomor_laporan'] = Insiden::generateNomorLaporan($pengguna->tenant_id);
                } elseif (! $isDraft && ! empty($insiden->nomor_laporan)) {
                    // Nomor sudah ada (draf pernah di-generate), tetap pertahankan.
                    unset($dataInsiden['nomor_laporan']);
                }

                $insiden->update($dataInsiden);

                // Update atau create detail pasien.
                if ($insiden->detailPasien) {
                    $insiden->detailPasien->update($dataDetail);
                } else {
                    $dataDetail['insiden_id'] = $insiden->id;
                    DetailPasien::create($dataDetail);
                }
            } else {
                // ── CREATE: Laporan baru ────────────────────────────
                // Generate nomor laporan hanya jika bukan draf.
                $dataInsiden['nomor_laporan'] = $isDraft
                    ? 'DRAF-' . now()->format('YmdHis') . '-' . $pengguna->id
                    : Insiden::generateNomorLaporan($pengguna->tenant_id);

                $insiden = Insiden::create($dataInsiden);

                $dataDetail['insiden_id'] = $insiden->id;
                DetailPasien::create($dataDetail);
            }

            return $insiden;
        });

        // ── Post-transaction: Audit log & Event dispatch ────────────
        $aksiLog = $isDraft
            ? ($isUpdate ? 'UPDATE_DRAF' : 'CREATE_DRAF')
            : ($isUpdate ? 'SUBMIT_DRAF' : 'CREATE');

        $this->auditLog->catat(
            namaTabel: 'pelaporan.insiden',
            aksi:      $aksiLog,
            idData:    $insiden->id,
            dataBaru:  $insiden->toArray(),
        );

        // Notifikasi HANYA saat kirim laporan (bukan draf).
        if (! $isDraft) {
            InsidenStatusBerubah::dispatch($insiden->fresh(), 'kasus_baru', $pengguna);
        }

        return $insiden;
    }

    /**
     * Generate PDF laporan insiden untuk dicetak / diunduh.
     *
     * @param  int  $id  ID insiden.
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generatePdfReport(int $id): \Barryvdh\DomPDF\PDF
    {
        $insiden = $this->repository->getInsidenForPdf($id);

        if (! $insiden) {
            abort(404, 'Laporan insiden tidak ditemukan atau masih berstatus draf.');
        }

        $pdf = Pdf::loadView('laporan.pdf', compact('insiden'))
            ->setPaper('a4', 'portrait');

        return $pdf;
    }
}
