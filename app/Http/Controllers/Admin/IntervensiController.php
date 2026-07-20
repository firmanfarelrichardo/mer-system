<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\MasterFormDataDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanMasterFormRequest;
use App\Services\IntervensiService;
use App\Support\Paginasi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller Manajemen Tindakan Intervensi - CRUD master intervensi oleh Admin.
 *
 * Alur: Controller → DTO → Service → Repository → Database
 * Audit log dicatat otomatis oleh Observer.
 */
class IntervensiController extends Controller
{
    public function __construct(
        private readonly IntervensiService $service,
    ) {}

    /* ------------------------------------------------------------------
     | INDEX - Daftar Tindakan Intervensi + Filter
     | ----------------------------------------------------------------*/

    public function index(Request $request): View
    {
        $tenantId = auth()->user()->tenant_id;

        $filter = [
            'cari' => $request->input('cari'),
        ];

        $daftarData = $this->service->daftar($tenantId, $filter, Paginasi::perHalaman());

        return view('admin.master-form.intervensi', compact('daftarData', 'filter'));
    }

    /* ------------------------------------------------------------------
     | STORE - Simpan Tindakan Intervensi Baru
     | ----------------------------------------------------------------*/

    public function simpan(SimpanMasterFormRequest $request): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $dto = MasterFormDataDTO::dariArray($request->validated(), $tenantId);

        $this->service->buat($dto);

        return redirect()
            ->route('admin.intervensi.index')
            ->with('sukses', 'Tindakan intervensi baru berhasil ditambahkan.');
    }

    /* ------------------------------------------------------------------
     | UPDATE - Perbarui Data Tindakan Intervensi
     | ----------------------------------------------------------------*/

    public function perbarui(SimpanMasterFormRequest $request, int $id): RedirectResponse
    {
        $tenantId  = auth()->user()->tenant_id;
        $intervensi = $this->service->cariBerdasarkanId($id);

        abort_if(! $intervensi || $intervensi->tenant_id !== $tenantId, 404);

        $dto = MasterFormDataDTO::dariArray($request->validated(), $tenantId);
        $this->service->perbarui($intervensi, $dto);

        return redirect()
            ->route('admin.intervensi.index')
            ->with('sukses', 'Tindakan intervensi berhasil diperbarui.');
    }

    /* ------------------------------------------------------------------
     | TOGGLE - Ubah Status Aktif/Nonaktif
     | ----------------------------------------------------------------*/

    public function toggleAktif(int $id): RedirectResponse
    {
        $tenantId  = auth()->user()->tenant_id;
        $intervensi = $this->service->cariBerdasarkanId($id);

        abort_if(! $intervensi || $intervensi->tenant_id !== $tenantId, 404);

        $result = $this->service->toggleAktif($intervensi);
        $status = $result->is_aktif ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->route('admin.intervensi.index')
            ->with('sukses', "Tindakan intervensi \"{$result->nama}\" berhasil {$status}.");
    }

    /* ------------------------------------------------------------------
     | DESTROY - Hapus Tindakan Intervensi (Soft Delete)
     | ----------------------------------------------------------------*/

    public function hapus(int $id): RedirectResponse
    {
        $tenantId  = auth()->user()->tenant_id;
        $intervensi = $this->service->cariBerdasarkanId($id);

        abort_if(! $intervensi || $intervensi->tenant_id !== $tenantId, 404);

        $this->service->hapus($intervensi);

        return redirect()
            ->route('admin.intervensi.index')
            ->with('sukses', "Tindakan intervensi \"{$intervensi->nama}\" berhasil dihapus.");
    }
}
