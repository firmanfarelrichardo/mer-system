<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\MasterFormDataDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanMasterFormRequest;
use App\Services\JenisKesalahanService;
use App\Support\Paginasi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller Manajemen Jenis Kesalahan - CRUD master jenis kesalahan oleh Admin.
 *
 * Alur: Controller → DTO → Service → Repository → Database
 * Audit log dicatat otomatis oleh Observer.
 */
class JenisKesalahanController extends Controller
{
    public function __construct(
        private readonly JenisKesalahanService $service,
    ) {}

    /* ------------------------------------------------------------------
     | INDEX - Daftar Jenis Kesalahan + Filter
     | ----------------------------------------------------------------*/

    public function index(Request $request): View
    {
        $tenantId = auth()->user()->tenant_id;

        $filter = [
            'cari' => $request->input('cari'),
        ];

        $daftarData = $this->service->daftar($tenantId, $filter, Paginasi::perHalaman());

        return view('admin.master-form.jenis-kesalahan', compact('daftarData', 'filter'));
    }

    /* ------------------------------------------------------------------
     | STORE - Simpan Jenis Kesalahan Baru
     | ----------------------------------------------------------------*/

    public function simpan(SimpanMasterFormRequest $request): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $dto = MasterFormDataDTO::dariArray($request->validated(), $tenantId);

        $this->service->buat($dto);

        return redirect()
            ->route('admin.jenis-kesalahan.index')
            ->with('sukses', 'Jenis kesalahan baru berhasil ditambahkan.');
    }

    /* ------------------------------------------------------------------
     | UPDATE - Perbarui Data Jenis Kesalahan
     | ----------------------------------------------------------------*/

    public function perbarui(SimpanMasterFormRequest $request, int $id): RedirectResponse
    {
        $tenantId        = auth()->user()->tenant_id;
        $jenisKesalahan  = $this->service->cariBerdasarkanId($id);

        abort_if(! $jenisKesalahan || $jenisKesalahan->tenant_id !== $tenantId, 404);

        $dto = MasterFormDataDTO::dariArray($request->validated(), $tenantId);
        $this->service->perbarui($jenisKesalahan, $dto);

        return redirect()
            ->route('admin.jenis-kesalahan.index')
            ->with('sukses', 'Jenis kesalahan berhasil diperbarui.');
    }

    /* ------------------------------------------------------------------
     | TOGGLE - Ubah Status Aktif/Nonaktif
     | ----------------------------------------------------------------*/

    public function toggleAktif(int $id): RedirectResponse
    {
        $tenantId        = auth()->user()->tenant_id;
        $jenisKesalahan  = $this->service->cariBerdasarkanId($id);

        abort_if(! $jenisKesalahan || $jenisKesalahan->tenant_id !== $tenantId, 404);

        $result = $this->service->toggleAktif($jenisKesalahan);
        $status = $result->is_aktif ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->route('admin.jenis-kesalahan.index')
            ->with('sukses', "Jenis kesalahan \"{$result->nama}\" berhasil {$status}.");
    }

    /* ------------------------------------------------------------------
     | DESTROY - Hapus Jenis Kesalahan (Soft Delete)
     | ----------------------------------------------------------------*/

    public function hapus(int $id): RedirectResponse
    {
        $tenantId        = auth()->user()->tenant_id;
        $jenisKesalahan  = $this->service->cariBerdasarkanId($id);

        abort_if(! $jenisKesalahan || $jenisKesalahan->tenant_id !== $tenantId, 404);

        $this->service->hapus($jenisKesalahan);

        return redirect()
            ->route('admin.jenis-kesalahan.index')
            ->with('sukses', "Jenis kesalahan \"{$jenisKesalahan->nama}\" berhasil dihapus.");
    }
}
