<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ExportSummaryRequest;
use App\Services\LaporanService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * LaporanExportController — menangani ekspor laporan rekapitulasi ke PDF.
 *
 * Hanya dapat diakses oleh peran 'Komite' dan 'Direktur'.
 * Otorisasi dilakukan secara eksplisit di setiap method menggunakan
 * `abort_unless()` agar tidak bergantung pada middleware saja.
 */
class LaporanExportController extends Controller
{
    public function __construct(
        private readonly LaporanService $laporanService,
    ) {}

    /**
     * Cetak laporan rekapitulasi insiden berdasarkan rentang tanggal.
     *
     * GET /laporan/ekspor/rekapitulasi?tanggal_mulai=2026-01-01&tanggal_akhir=2026-03-31
     *
     * - Validasi input dilakukan oleh ExportSummaryRequest (server-side).
     * - Orientasi lanskap otomatis ditangani di LaporanService.
     * - PDF dikirim langsung ke browser sebagai response inline agar
     *   pengguna bisa melihat preview sebelum mencetak.
     */
    public function exportSummary(ExportSummaryRequest $request): Response
    {
        /** @var \App\Models\Pengguna $pengguna */
        $pengguna = Auth::user();

        abort_unless(
            $pengguna->memilikiPeran('Komite') || $pengguna->memilikiPeran('Direktur'),
            403,
            'Akses ditolak. Fitur ini hanya tersedia untuk Komite dan Direktur.',
        );

        $startDate   = $request->date('tanggal_mulai')->toDateString();
        $endDate     = $request->date('tanggal_akhir')->toDateString();
        $orientation = $request->input('orientation', 'portrait');

        $pdf = $this->laporanService->generateSummaryPdf(
            (int) $pengguna->tenant_id,
            $startDate,
            $endDate,
            $orientation,
        );

        $namaFile = 'rekapitulasi-insiden_' . $startDate . '_sd_' . $endDate . '.pdf';

        return $pdf->stream($namaFile);
    }
}
