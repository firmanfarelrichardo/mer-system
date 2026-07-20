<?php

declare(strict_types=1);

namespace App\Http\Controllers\Peneliti;

use App\Http\Controllers\Controller;
use App\Models\Insiden;
use App\Models\Pengguna;
use App\Services\PenggunaService;
use Illuminate\Contracts\View\View;

/**
 * Controller Dashboard Peneliti - ringkasan sistem & laporan terbaru.
 *
 * Dashboard ini ditampilkan saat peneliti dalam mode penuh (belum memilih
 * simulasi peran via "Lihat Sebagai"). Menampilkan statistik pengguna
 * dan daftar laporan insiden terbaru yang masuk ke sistem.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly PenggunaService $penggunaService,
    ) {}

    public function index(): View
    {
        $tenantId = auth()->user()->tenant_id;

        $statistik = $this->penggunaService->statistik($tenantId);

        $loginTerbaru = Pengguna::where('tenant_id', $tenantId)
            ->whereNotNull('terakhir_login_pada')
            ->with('peran')
            ->orderByDesc('terakhir_login_pada')
            ->limit(5)
            ->get();

        $laporanTerbaru = Insiden::where('tenant_id', $tenantId)
            ->bukanDraf()
            ->with('pelapor:id,nama_lengkap,nomor_induk')
            ->latest('tgl_lapor')
            ->limit(10)
            ->get();

        return view('peneliti.dashboard', compact('statistik', 'loginTerbaru', 'laporanTerbaru'));
    }
}
