<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\KategoriKesalahanData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PerbaruiKategoriRequest;
use App\Http\Requests\Admin\SimpanKategoriRequest;
use App\Services\KategoriKesalahanService;
use App\Support\Paginasi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller Manajemen Kategori Kesalahan - CRUD master kategori oleh Admin.
 *
 * Alur: Controller → Service → Repository → Database
 * Audit log dicatat otomatis oleh Observer.
 */
class KategoriController extends Controller
{
    public function __construct(
        private readonly KategoriKesalahanService $kategoriService,
    ) {}

    /* ------------------------------------------------------------------
     | INDEX - Daftar Kategori + Filter
     | ----------------------------------------------------------------*/

    public function index(Request $request): View
    {
        $tenantId = auth()->user()->tenant_id;

        $filter = [
            'cari' => $request->input('cari'),
        ];

        $daftarKategori = $this->kategoriService->daftar($tenantId, $filter, Paginasi::perHalaman());

        return view('admin.kategori.index', compact('daftarKategori', 'filter'));
    }

    /* ------------------------------------------------------------------
     | CREATE - Form Tambah Kategori
     | ----------------------------------------------------------------*/

    public function buat(): View
    {
        return view('admin.kategori.buat');
    }

    /* ------------------------------------------------------------------
     | STORE - Simpan Kategori Baru
     | ----------------------------------------------------------------*/

    public function simpan(SimpanKategoriRequest $request): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $dto = KategoriKesalahanData::dariArray($request->validated(), $tenantId);

        $this->kategoriService->buat($dto);

        return redirect()
            ->route('admin.kategori.index')
            ->with('sukses', 'Kategori kesalahan baru berhasil ditambahkan.');
    }

    /* ------------------------------------------------------------------
     | EDIT - Form Edit Kategori
     | ----------------------------------------------------------------*/

    public function edit(int $kategori): View
    {
        $tenantId     = auth()->user()->tenant_id;
        $dataKategori = $this->kategoriService->cariBerdasarkanId($kategori);

        abort_if(! $dataKategori || $dataKategori->tenant_id !== $tenantId, 404);

        return view('admin.kategori.edit', compact('dataKategori'));
    }

    /* ------------------------------------------------------------------
     | UPDATE - Perbarui Data Kategori
     | ----------------------------------------------------------------*/

    public function perbarui(PerbaruiKategoriRequest $request, int $kategori): RedirectResponse
    {
        $tenantId     = auth()->user()->tenant_id;
        $dataKategori = $this->kategoriService->cariBerdasarkanId($kategori);

        abort_if(! $dataKategori || $dataKategori->tenant_id !== $tenantId, 404);

        $dto = KategoriKesalahanData::dariArray($request->validated(), $tenantId);
        $this->kategoriService->perbarui($dataKategori, $dto);

        return redirect()
            ->route('admin.kategori.index')
            ->with('sukses', 'Kategori kesalahan berhasil diperbarui.');
    }

    /* ------------------------------------------------------------------
     | DESTROY - Hapus Kategori (Permanent)
     | ----------------------------------------------------------------*/

    public function hapus(int $kategori): RedirectResponse
    {
        $tenantId     = auth()->user()->tenant_id;
        $dataKategori = $this->kategoriService->cariBerdasarkanId($kategori);

        abort_if(! $dataKategori || $dataKategori->tenant_id !== $tenantId, 404);

        $this->kategoriService->hapus($dataKategori);

        return redirect()
            ->route('admin.kategori.index')
            ->with('sukses', "Kategori {$dataKategori->nama_kategori} berhasil dihapus.");
    }
}
