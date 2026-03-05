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
 *   - Simpan Draf: status = 'DRAF', tanpa notifikasi. Nomor laporan INC sudah
 *     digenerate sejak pertama kali dibuat — status-lah yang membedakan draf
 *     dengan laporan terkirim, bukan prefix di nomor_laporan.
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
                'obat_terkait'         => $dto->namaObat,                'dosis_obat'           => $dto->dosisObat,                'kronologi'            => $dto->kronologiKejadian,
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

                // nomor_laporan (INC-...) sudah ada sejak CREATE — jangan diubah.
                unset($dataInsiden['nomor_laporan']);

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
                // Selalu generate INC-... sejak awal — baik draf maupun kirim langsung.
                // Status (DRAF vs kasus_baru) sudah membedakan keduanya; nomor
                // laporan tidak perlu prefix berbeda.
                $dataInsiden['nomor_laporan'] = Insiden::generateNomorLaporan($pengguna->tenant_id);

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
     * Auto-save laporan insiden di background (AJAX).
     *
     * Perbedaan dengan save():
     *   - Selalu status DRAF — tidak pernah submit.
     *   - UPDATE menggunakan saveQuietly() agar InsidenObserver tidak dipicu
     *     (mencegah spam audit log setiap 2 detik).
     *   - Audit log HANYA dicatat pada CREATE pertama, bukan pada update berikutnya.
     *   - Tidak ada dispatch event InsidenStatusBerubah.
     *
     * @return Insiden  Model insiden yang tersimpan.
     */
    public function autoSave(InsidenData $dto, Pengguna $pengguna): Insiden
    {
        $isUpdate = $dto->insidenId !== null;

        return DB::transaction(function () use ($dto, $pengguna, $isUpdate): Insiden {

            // ── Bangun data insiden ─────────────────────────────────
            $tglKejadian = $dto->tanggalKejadian;
            if ($tglKejadian && $dto->waktuKejadian) {
                $tglKejadian .= ' ' . $dto->waktuKejadian . ':00';
            }

            $isAnonim = empty($dto->namaPelapor);

            $dataInsiden = [
                'tenant_id'       => $pengguna->tenant_id,
                'pelapor_id'      => $pengguna->id,
                'unit_id'         => null,
                'nama_unit_kerja' => $dto->unitKerja,
                'tipe_insiden'    => $dto->jenisInsiden,
                'fase_kesalahan'  => $dto->faseKesalahan,
                'status_saat_ini' => 'DRAF',
                'tgl_kejadian'    => $tglKejadian,
                'tgl_lapor'       => null,
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
                'dosis_obat'           => $dto->dosisObat,
                'kronologi'            => $dto->kronologiKejadian,
                'jenis_kesalahan'      => $dto->jenisKesalahanGabungan() ?: null,
                'cedera'               => $dto->cederaGabungan() ?: null,
                'faktor_penyebab'      => $dto->faktorPenyebabGabungan() ?: null,
                'intervensi_pasien'    => $dto->intervensiPasienGabungan() ?: null,
                'pernyataan_kronologi' => $dto->pernyataanKronologi,
            ];

            if ($isUpdate) {
                // ── UPDATE: Perbarui draf yang sudah ada ─────────────
                // Gunakan saveQuietly() agar InsidenObserver tidak terpicu
                // → mencegah spam audit log setiap siklus auto-save.
                $insiden = Insiden::where('id', $dto->insidenId)
                    ->where('pelapor_id', $pengguna->id)
                    ->where('status_saat_ini', 'DRAF')
                    ->firstOrFail();

                $insiden->fill($dataInsiden)->saveQuietly();

                // Update atau create detail pasien (juga quietly).
                if ($insiden->detailPasien) {
                    $insiden->detailPasien->fill($dataDetail)->saveQuietly();
                } else {
                    $dataDetail['insiden_id'] = $insiden->id;
                    $detail = new DetailPasien($dataDetail);
                    $detail->saveQuietly();
                }
            } else {
                // ── CREATE: Draf baru ────────────────────────────────
                // Biarkan Observer `created` terpicu untuk audit log pertama kali.
                // Nomor INC-... digenerate langsung — auto-save pun sudah mendapat
                // nomor laporan final; tidak ada tempat untuk prefix DRAF-.
                $dataInsiden['nomor_laporan'] = Insiden::generateNomorLaporan($pengguna->tenant_id);

                $insiden = Insiden::create($dataInsiden);

                $dataDetail['insiden_id'] = $insiden->id;
                DetailPasien::create($dataDetail);
            }

            return $insiden;
        });
    }

    /**
     * Generate PDF laporan insiden untuk dicetak / diunduh.
     *
     * @param  int  $id  ID insiden.
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generatePdfReport(int $id, array $margin = [], int $scale = 100): \Barryvdh\DomPDF\PDF
    {
        $insiden = $this->repository->getInsidenForPdf($id);

        if (! $insiden) {
            abort(404, 'Laporan insiden tidak ditemukan atau masih berstatus draf.');
        }

        $pdf = Pdf::loadView('laporan.pdf', compact('insiden', 'margin', 'scale'))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'Times New Roman')
            ->setOption('dpi', 150)
            ->setOption('isPhpEnabled', true);

        return $pdf;
    }
}
