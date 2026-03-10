<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\UnitKerjaData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PerbaruiUnitKerjaRequest;
use App\Http\Requests\Admin\SimpanUnitKerjaRequest;
use App\Services\UnitKerjaService;
use App\Support\Paginasi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller Manajemen Unit Kerja — CRUD master unit kerja oleh Admin.
 *
 * Alur: Controller → Service → Repository → Database
 * Audit log dicatat otomatis oleh Observer.
 */
class UnitKerjaController extends Controller
{
    public function __construct(
        private readonly UnitKerjaService $unitKerjaService,
    ) {}

    /* ------------------------------------------------------------------
     | INDEX — Daftar Unit Kerja + Filter
     | ----------------------------------------------------------------*/

    public function index(Request $request): View
    {
        $tenantId = auth()->user()->tenant_id;

        $filter = [
            'cari' => $request->input('cari'),
        ];

        $daftarUnit = $this->unitKerjaService->daftar($tenantId, $filter, Paginasi::perHalaman());

        return view('admin.unit-kerja.index', compact('daftarUnit', 'filter'));
    }

    /* ------------------------------------------------------------------
     | CREATE — Form Tambah Unit Kerja
     | ----------------------------------------------------------------*/

    public function buat(): View
    {
        return view('admin.unit-kerja.buat');
    }

    /* ------------------------------------------------------------------
     | STORE — Simpan Unit Kerja Baru
     | ----------------------------------------------------------------*/

    public function simpan(SimpanUnitKerjaRequest $request): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $dto = UnitKerjaData::dariArray($request->validated(), $tenantId);

        $this->unitKerjaService->buat($dto);

        return redirect()
            ->route('admin.unit-kerja.index')
            ->with('sukses', 'Unit kerja baru berhasil ditambahkan.');
    }

    /* ------------------------------------------------------------------
     | EDIT — Form Edit Unit Kerja
     | ----------------------------------------------------------------*/

    public function edit(int $unit_kerja): View
    {
        $tenantId  = auth()->user()->tenant_id;
        $dataUnit  = $this->unitKerjaService->cariBerdasarkanId($unit_kerja);

        abort_if(! $dataUnit || $dataUnit->tenant_id !== $tenantId, 404);

        return view('admin.unit-kerja.edit', compact('dataUnit'));
    }

    /* ------------------------------------------------------------------
     | UPDATE — Perbarui Data Unit Kerja
     | ----------------------------------------------------------------*/

    public function perbarui(PerbaruiUnitKerjaRequest $request, int $unit_kerja): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $dataUnit = $this->unitKerjaService->cariBerdasarkanId($unit_kerja);

        abort_if(! $dataUnit || $dataUnit->tenant_id !== $tenantId, 404);

        $dto = UnitKerjaData::dariArray($request->validated(), $tenantId);
        $this->unitKerjaService->perbarui($dataUnit, $dto);

        return redirect()
            ->route('admin.unit-kerja.index')
            ->with('sukses', 'Data unit kerja berhasil diperbarui.');
    }

    /* ------------------------------------------------------------------
     | DESTROY — Hapus Unit Kerja (Soft Delete)
     | ----------------------------------------------------------------*/

    public function hapus(int $unit_kerja): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $dataUnit = $this->unitKerjaService->cariBerdasarkanId($unit_kerja);

        abort_if(! $dataUnit || $dataUnit->tenant_id !== $tenantId, 404);

        $this->unitKerjaService->hapus($dataUnit);

        return redirect()
            ->route('admin.unit-kerja.index')
            ->with('sukses', "Unit kerja {$dataUnit->nama_unit} berhasil dihapus.");
    }
}
