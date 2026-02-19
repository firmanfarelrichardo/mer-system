<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\PenggunaData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PerbaruiPenggunaRequest;
use App\Http\Requests\Admin\SimpanPenggunaRequest;
use App\Models\Peran;
use App\Models\UnitKerja;
use App\Services\PenggunaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller Manajemen Pengguna — CRUD akun pengguna oleh Admin.
 *
 * Mengikuti alur: Controller → Service → Repository → Database
 * Data dikirim via DTO (PenggunaData) untuk kontrak yang bersih.
 */
class PenggunaController extends Controller
{
    public function __construct(
        private readonly PenggunaService $penggunaService,
    ) {}

    /* ------------------------------------------------------------------
     | INDEX — Daftar Pengguna + Filter
     | ----------------------------------------------------------------*/

    /**
     * Tampilkan daftar pengguna dengan pencarian dan filter.
     */
    public function index(Request $request): View
    {
        $tenantId = auth()->user()->tenant_id;

        $filter = [
            'cari'     => $request->input('cari'),
            'peran_id' => $request->input('peran_id') ? (int) $request->input('peran_id') : null,
            'unit_id'  => $request->input('unit_id') ? (int) $request->input('unit_id') : null,
        ];

        // Filter status: '' = semua, '1' = aktif, '0' = nonaktif
        if ($request->input('status') !== null && $request->input('status') !== '') {
            $filter['status'] = (int) $request->input('status');
        }

        $daftarPengguna = $this->penggunaService->daftar($tenantId, $filter);
        $daftarPeran    = Peran::where('tenant_id', $tenantId)->orderBy('nama_peran')->get();
        $daftarUnit     = UnitKerja::where('tenant_id', $tenantId)->orderBy('nama_unit')->get();

        return view('admin.pengguna.index', compact(
            'daftarPengguna',
            'daftarPeran',
            'daftarUnit',
            'filter',
        ));
    }

    /* ------------------------------------------------------------------
     | CREATE — Form Tambah Pengguna Baru
     | ----------------------------------------------------------------*/

    /**
     * Tampilkan formulir pembuatan pengguna baru.
     */
    public function buat(): View
    {
        $tenantId   = auth()->user()->tenant_id;
        $daftarPeran = Peran::where('tenant_id', $tenantId)->orderBy('nama_peran')->get();
        $daftarUnit  = UnitKerja::where('tenant_id', $tenantId)->orderBy('nama_unit')->get();

        return view('admin.pengguna.buat', compact('daftarPeran', 'daftarUnit'));
    }

    /* ------------------------------------------------------------------
     | STORE — Simpan Pengguna Baru
     | ----------------------------------------------------------------*/

    /**
     * Proses penyimpanan pengguna baru.
     */
    public function simpan(SimpanPenggunaRequest $request): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $dto = PenggunaData::dariArray($request->validated(), $tenantId);

        $this->penggunaService->buat($dto);

        return redirect()
            ->route('admin.pengguna.index')
            ->with('sukses', 'Pengguna baru berhasil ditambahkan.');
    }

    /* ------------------------------------------------------------------
     | EDIT — Form Edit Pengguna
     | ----------------------------------------------------------------*/

    /**
     * Tampilkan formulir edit pengguna.
     */
    public function edit(int $pengguna): View
    {
        $tenantId    = auth()->user()->tenant_id;
        $dataPengguna = $this->penggunaService->cariBerdasarkanId($pengguna);

        abort_if(! $dataPengguna || $dataPengguna->tenant_id !== $tenantId, 404);

        $daftarPeran = Peran::where('tenant_id', $tenantId)->orderBy('nama_peran')->get();
        $daftarUnit  = UnitKerja::where('tenant_id', $tenantId)->orderBy('nama_unit')->get();

        return view('admin.pengguna.edit', compact('dataPengguna', 'daftarPeran', 'daftarUnit'));
    }

    /* ------------------------------------------------------------------
     | UPDATE — Perbarui Data Pengguna
     | ----------------------------------------------------------------*/

    /**
     * Proses pembaruan data pengguna.
     */
    public function perbarui(PerbaruiPenggunaRequest $request, int $pengguna): RedirectResponse
    {
        $tenantId     = auth()->user()->tenant_id;
        $dataPengguna = $this->penggunaService->cariBerdasarkanId($pengguna);

        abort_if(! $dataPengguna || $dataPengguna->tenant_id !== $tenantId, 404);

        $dto = PenggunaData::dariArray($request->validated(), $tenantId);

        $this->penggunaService->perbarui($dataPengguna, $dto);

        return redirect()
            ->route('admin.pengguna.index')
            ->with('sukses', 'Data pengguna berhasil diperbarui.');
    }

    /* ------------------------------------------------------------------
     | TOGGLE STATUS — Aktifkan / Non-aktifkan Akun
     | ----------------------------------------------------------------*/

    /**
     * Toggle status aktif/nonaktif pengguna (soft-toggle).
     */
    public function toggleStatus(int $pengguna): RedirectResponse
    {
        $tenantId     = auth()->user()->tenant_id;
        $dataPengguna = $this->penggunaService->cariBerdasarkanId($pengguna);

        abort_if(! $dataPengguna || $dataPengguna->tenant_id !== $tenantId, 404);

        $statusBaru = ! $dataPengguna->is_aktif;
        $this->penggunaService->ubahStatusAktif($dataPengguna, $statusBaru);

        $label = $statusBaru ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->route('admin.pengguna.index')
            ->with('sukses', "Akun {$dataPengguna->nama_lengkap} berhasil {$label}.");
    }

    /* ------------------------------------------------------------------
     | RESET PASSWORD
     | ----------------------------------------------------------------*/

    /**
     * Reset kata sandi pengguna ke nilai default.
     */
    public function resetKataSandi(int $pengguna): RedirectResponse
    {
        $tenantId     = auth()->user()->tenant_id;
        $dataPengguna = $this->penggunaService->cariBerdasarkanId($pengguna);

        abort_if(! $dataPengguna || $dataPengguna->tenant_id !== $tenantId, 404);

        // Reset ke nomor induk + '123' sebagai kata sandi default
        $kataSandiDefault = $dataPengguna->nomor_induk . '123';
        $this->penggunaService->resetKataSandi($dataPengguna, $kataSandiDefault);

        return redirect()
            ->route('admin.pengguna.index')
            ->with('sukses', "Kata sandi {$dataPengguna->nama_lengkap} berhasil direset.");
    }
}
