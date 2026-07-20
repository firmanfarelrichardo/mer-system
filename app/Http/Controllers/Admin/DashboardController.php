<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use App\Services\PenggunaService;
use Illuminate\Contracts\View\View;

/**
 * Controller Dashboard Admin - menampilkan ringkasan statistik sistem.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly PenggunaService $penggunaService,
    ) {}

    /**
     * Tampilkan halaman dashboard admin.
     */
    public function index(): View
    {
        $tenantId   = auth()->user()->tenant_id;
        $statistik  = $this->penggunaService->statistik($tenantId);

        // Login terbaru (5 pengguna terakhir yang login)
        $loginTerbaru = Pengguna::where('tenant_id', $tenantId)
            ->whereNotNull('terakhir_login_pada')
            ->with('peran')
            ->orderByDesc('terakhir_login_pada')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact('statistik', 'loginTerbaru'));
    }
}
