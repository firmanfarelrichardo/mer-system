<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\MasterFormDataDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanMasterFormRequest;
use App\Services\FaktorPenyebabService;
use App\Support\Paginasi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller Manajemen Faktor Penyebab - CRUD master faktor penyebab oleh Admin.
 *
 * Alur: Controller → DTO → Service → Repository → Database
 * Audit log dicatat otomatis oleh Observer.
 */
class FaktorPenyebabController extends Controller
{
    public function __construct(
        private readonly FaktorPenyebabService $service,
    ) {}

    /* ------------------------------------------------------------------
     | INDEX - Daftar Faktor Penyebab + Filter
     | ----------------------------------------------------------------*/

    public function index(Request $request): View
    {
        $tenantId = auth()->user()->tenant_id;

        $filter = [
            'cari' => $request->input('cari'),
        ];

        $daftarData = $this->service->daftar($tenantId, $filter, Paginasi::perHalaman());

        return view('admin.master-form.faktor-penyebab', compact('daftarData', 'filter'));
    }

    /* ------------------------------------------------------------------
     | STORE - Simpan Faktor Penyebab Baru
     | ----------------------------------------------------------------*/

    public function simpan(SimpanMasterFormRequest $request): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $dto = MasterFormDataDTO::dariArray($request->validated(), $tenantId);

        $this->service->buat($dto);

        return redirect()
            ->route('admin.faktor-penyebab.index')
            ->with('sukses', 'Faktor penyebab baru berhasil ditambahkan.');
    }

    /* ------------------------------------------------------------------
     | UPDATE - Perbarui Data Faktor Penyebab
     | ----------------------------------------------------------------*/

    public function perbarui(SimpanMasterFormRequest $request, int $id): RedirectResponse
    {
        $tenantId      = auth()->user()->tenant_id;
        $faktorPenyebab = $this->service->cariBerdasarkanId($id);

        abort_if(! $faktorPenyebab || $faktorPenyebab->tenant_id !== $tenantId, 404);

        $dto = MasterFormDataDTO::dariArray($request->validated(), $tenantId);
        $this->service->perbarui($faktorPenyebab, $dto);

        return redirect()
            ->route('admin.faktor-penyebab.index')
            ->with('sukses', 'Faktor penyebab berhasil diperbarui.');
    }

    /* ------------------------------------------------------------------
     | TOGGLE - Ubah Status Aktif/Nonaktif
     | ----------------------------------------------------------------*/

    public function toggleAktif(int $id): RedirectResponse
    {
        $tenantId      = auth()->user()->tenant_id;
        $faktorPenyebab = $this->service->cariBerdasarkanId($id);

        abort_if(! $faktorPenyebab || $faktorPenyebab->tenant_id !== $tenantId, 404);

        $result = $this->service->toggleAktif($faktorPenyebab);
        $status = $result->is_aktif ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->route('admin.faktor-penyebab.index')
            ->with('sukses', "Faktor penyebab \"{$result->nama}\" berhasil {$status}.");
    }

    /* ------------------------------------------------------------------
     | DESTROY - Hapus Faktor Penyebab (Soft Delete)
     | ----------------------------------------------------------------*/

    public function hapus(int $id): RedirectResponse
    {
        $tenantId      = auth()->user()->tenant_id;
        $faktorPenyebab = $this->service->cariBerdasarkanId($id);

        abort_if(! $faktorPenyebab || $faktorPenyebab->tenant_id !== $tenantId, 404);

        $this->service->hapus($faktorPenyebab);

        return redirect()
            ->route('admin.faktor-penyebab.index')
            ->with('sukses', "Faktor penyebab \"{$faktorPenyebab->nama}\" berhasil dihapus.");
    }
}
