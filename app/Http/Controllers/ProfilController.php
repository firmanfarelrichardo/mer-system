<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PerbaruiProfilRequest;
use App\Models\Pengguna;
use App\Services\PenggunaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ProfilController — tampilan dan pembaruan profil mandiri.
 *
 * Semua peran yang terautentikasi dapat melihat dan mengedit profil
 * mereka sendiri. Field terlindungi (nomor_induk, email, unit, peran)
 * hanya dapat diubah oleh Admin melalui panel admin.
 */
class ProfilController extends Controller
{
    public function __construct(
        private readonly PenggunaService $penggunaService,
    ) {}

    /**
     * Tampilkan halaman profil pengguna.
     */
    public function index(Request $permintaan): View
    {
        /** @var Pengguna $pengguna */
        $pengguna = auth()->user();
        $pengguna->loadMissing('peran', 'unitKerja');

        return view('profil.index', compact('pengguna'));
    }

    /**
     * Tampilkan formulir edit profil.
     */
    public function edit(Request $permintaan): View
    {
        /** @var Pengguna $pengguna */
        $pengguna = auth()->user();
        $pengguna->loadMissing('peran', 'unitKerja');

        return view('profil.edit', compact('pengguna'));
    }

    /**
     * Simpan perubahan profil.
     */
    public function perbarui(PerbaruiProfilRequest $permintaan): RedirectResponse
    {
        /** @var Pengguna $pengguna */
        $pengguna = auth()->user();

        $this->penggunaService->perbaruiProfil($pengguna, $permintaan->validated());

        return redirect()
            ->route('profil.index')
            ->with('sukses', 'Profil Anda berhasil diperbarui.');
    }
}
