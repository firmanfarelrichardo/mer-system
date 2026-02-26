<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\MasterFormDataDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanMasterFormRequest;
use App\Services\TipeCederaService;
use App\Support\Paginasi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller Manajemen Tipe Cedera — CRUD master tipe cedera oleh Admin.
 *
 * Alur: Controller → DTO → Service → Repository → Database
 * Audit log dicatat otomatis oleh Observer.
 */
class TipeCederaController extends Controller
{
    public function __construct(
        private readonly TipeCederaService $service,
    ) {}

    /* ------------------------------------------------------------------
     | INDEX — Daftar Tipe Cedera + Filter
     | ----------------------------------------------------------------*/

    public function index(Request $request): View
    {
        $tenantId = auth()->user()->tenant_id;

        $filter = [
            'cari' => $request->input('cari'),
        ];

        $daftarData = $this->service->daftar($tenantId, $filter, Paginasi::perHalaman());

        return view('admin.master-form.tipe-cedera', compact('daftarData', 'filter'));
    }

    /* ------------------------------------------------------------------
     | STORE — Simpan Tipe Cedera Baru
     | ----------------------------------------------------------------*/

    public function simpan(SimpanMasterFormRequest $request): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $dto = MasterFormDataDTO::dariArray($request->validated(), $tenantId);

        $this->service->buat($dto);

        return redirect()
            ->route('admin.tipe-cedera.index')
            ->with('sukses', 'Tipe cedera baru berhasil ditambahkan.');
    }

    /* ------------------------------------------------------------------
     | UPDATE — Perbarui Data Tipe Cedera
     | ----------------------------------------------------------------*/

    public function perbarui(SimpanMasterFormRequest $request, int $id): RedirectResponse
    {
        $tenantId  = auth()->user()->tenant_id;
        $tipeCedera = $this->service->cariBerdasarkanId($id);

        abort_if(! $tipeCedera || $tipeCedera->tenant_id !== $tenantId, 404);

        $dto = MasterFormDataDTO::dariArray($request->validated(), $tenantId);
        $this->service->perbarui($tipeCedera, $dto);

        return redirect()
            ->route('admin.tipe-cedera.index')
            ->with('sukses', 'Tipe cedera berhasil diperbarui.');
    }

    /* ------------------------------------------------------------------
     | TOGGLE — Ubah Status Aktif/Nonaktif
     | ----------------------------------------------------------------*/

    public function toggleAktif(int $id): RedirectResponse
    {
        $tenantId  = auth()->user()->tenant_id;
        $tipeCedera = $this->service->cariBerdasarkanId($id);

        abort_if(! $tipeCedera || $tipeCedera->tenant_id !== $tenantId, 404);

        $result = $this->service->toggleAktif($tipeCedera);
        $status = $result->is_aktif ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->route('admin.tipe-cedera.index')
            ->with('sukses', "Tipe cedera \"{$result->nama}\" berhasil {$status}.");
    }

    /* ------------------------------------------------------------------
     | DESTROY — Hapus Tipe Cedera (Soft Delete)
     | ----------------------------------------------------------------*/

    public function hapus(int $id): RedirectResponse
    {
        $tenantId  = auth()->user()->tenant_id;
        $tipeCedera = $this->service->cariBerdasarkanId($id);

        abort_if(! $tipeCedera || $tipeCedera->tenant_id !== $tenantId, 404);

        $this->service->hapus($tipeCedera);

        return redirect()
            ->route('admin.tipe-cedera.index')
            ->with('sukses', "Tipe cedera \"{$tipeCedera->nama}\" berhasil dihapus.");
    }
}
