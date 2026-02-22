<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use App\Services\LogAktivitasService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Controller Log Aktivitas — dashboard audit trail oleh Admin.
 *
 * Read-only: hanya menampilkan log yang sudah dicatat oleh Observer & Service.
 */
class LogAktivitasController extends Controller
{
    public function __construct(
        private readonly LogAktivitasService $logService,
    ) {}

    /* ------------------------------------------------------------------
     | INDEX — Daftar Semua Log Aktivitas
     | ----------------------------------------------------------------*/

    public function index(Request $request): View
    {
        $tenantId = auth()->user()->tenant_id;

        $filter = [
            'cari'           => $request->input('cari'),
            'aksi'           => $request->input('aksi'),
            'pengguna_id'    => $request->input('pengguna_id') ? (int) $request->input('pengguna_id') : null,
            'dari_tanggal'   => $request->input('dari_tanggal'),
            'sampai_tanggal' => $request->input('sampai_tanggal'),
        ];

        $daftarLog      = $this->logService->daftar($tenantId, $filter);
        $daftarAksi     = $this->logService->daftarAksiUnik($tenantId);
        $daftarPengguna = $this->logService->daftarPengguna($tenantId);

        return view('admin.log-aktivitas.index', compact(
            'daftarLog',
            'daftarAksi',
            'daftarPengguna',
            'filter',
        ));
    }

    /* ------------------------------------------------------------------
     | DETAIL — Riwayat Aktivitas Per Pengguna
     | ----------------------------------------------------------------*/

    public function detail(int $pengguna): View
    {
        $tenantId       = auth()->user()->tenant_id;
        $dataPengguna   = Pengguna::withTrashed()->find($pengguna);

        abort_if(! $dataPengguna || $dataPengguna->tenant_id !== $tenantId, 404);

        $daftarLog = $this->logService->riwayatPengguna($tenantId, $pengguna);

        return view('admin.log-aktivitas.detail', compact('dataPengguna', 'daftarLog'));
    }
}
