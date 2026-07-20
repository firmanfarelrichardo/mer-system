<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\LogAktivitasFilterDTO;
use App\Http\Controllers\Controller;
use App\Services\LogAktivitasService;
use App\Support\Paginasi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Controller Log Aktivitas - dashboard audit trail oleh Admin.
 *
 * Read-only: hanya menampilkan log yang sudah dicatat oleh Observer & Service.
 * Dipisah menjadi 2 halaman: Log Pengguna (non-admin) dan Log Admin.
 */
class LogAktivitasController extends Controller
{
    public function __construct(
        private readonly LogAktivitasService $logService,
    ) {}

    /* ------------------------------------------------------------------
     | Log Aktivitas Pengguna (seluruh user KECUALI Admin)
     | ----------------------------------------------------------------*/

    public function indexPengguna(Request $request): View
    {
        return $this->tampilkanLog($request, 'pengguna');
    }

    /* ------------------------------------------------------------------
     | Log Aktivitas Admin (khusus IT Admin)
     | ----------------------------------------------------------------*/

    public function indexAdmin(Request $request): View
    {
        return $this->tampilkanLog($request, 'admin');
    }

    /* ------------------------------------------------------------------
     | Private: Logika bersama - DRY
     | ----------------------------------------------------------------*/

    private function tampilkanLog(Request $request, string $tipePeran): View
    {
        $dto = LogAktivitasFilterDTO::dariRequest(
            request:  $request,
            tenantId: (int) auth()->user()->tenant_id,
            tipePeran: $tipePeran,
        );

        $daftarLog = $this->logService->daftar($dto, Paginasi::perHalaman());

        return view('admin.log-aktivitas.index', [
            'daftarLog'  => $daftarLog,
            'filter'     => [
                'dari_tanggal'   => $dto->dariTanggal,
                'sampai_tanggal' => $dto->sampaiTanggal,
                'cari'           => $dto->cari,
            ],
            'tipePeran'  => $tipePeran,
        ]);
    }
}
