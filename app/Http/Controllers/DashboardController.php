<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Insiden;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\TindakLanjut;
use App\Models\UnitKerja;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * DashboardController - "Morning Briefing" / Action Center.
 *
 * SATU controller, SATU route, SATU view - konten di-render secara
 * kondisional menggunakan Blade Components berdasarkan peran pengguna.
 *
 * ┌─────────────────────────┬──────────────────────────────────────────┐
 * │ Peran                   │ Fokus Dashboard                          │
 * ├─────────────────────────┼──────────────────────────────────────────┤
 * │ Nakes                   │ Reassurance & Edukasi - rekap pribadi.   │
 * │ Kepala Ruangan (Karu)   │ To-Do & SLA Alerts - unit kerjanya.     │
 * │ Komite                  │ Radar & Bottleneck - seluruh RS.        │
 * │ Direktur                │ Executive Vitals - read-only.           │
 * └─────────────────────────┴──────────────────────────────────────────┘
 *
 * Prinsip:
 *   - Tidak ada tabel data besar, form, atau deep analytics chart.
 *   - Setiap peran mendapat data lightweight yang ringkas.
 *   - Semua data sudah di-scope per tenant (multi-tenant safe).
 */
class DashboardController extends Controller
{
    /**
     * Tampilkan dashboard berdasarkan peran pengguna yang terautentikasi.
     */
    public function index(): View
    {
        /** @var Pengguna $pengguna */
        $pengguna = Auth::user();
        $tenantId = $pengguna->tenant_id;

        // Kirim data spesifik sesuai peran - hierarki tertinggi diutamakan.
        $data = match (true) {
            $pengguna->memilikiPeran(Peran::DIREKTUR)        => $this->dataDirektur($tenantId),
            $pengguna->memilikiPeran(Peran::KOMITE)          => $this->dataKomite($tenantId),
            $pengguna->memilikiPeran(Peran::KEPALA_RUANGAN)  => $this->dataKaru($pengguna),
            default                                          => $this->dataNakes($pengguna),
        };

        return view('dashboard', array_merge(['pengguna' => $pengguna], $data));
    }

    /* ==================================================================
     | DATA BUILDERS - Satu method per peran (DRY: masing-masing
     | hanya menyiapkan data yang benar-benar dibutuhkan view-nya).
     | ================================================================*/

    /**
     * Nakes: rekap laporan pribadi bulan ini + motivasi.
     */
    private function dataNakes(Pengguna $pengguna): array
    {
        $bulanIni = Carbon::now()->startOfMonth();

        $base = Insiden::where('pelapor_id', $pengguna->id)
            ->where('tenant_id', $pengguna->tenant_id)
            ->bukanDraf()
            ->where('tgl_lapor', '>=', $bulanIni);

        return [
            'nakes' => [
                'total_bulan_ini'   => (clone $base)->count(),
                'selesai_bulan_ini' => (clone $base)->where('status_saat_ini', 'selesai')->count(),
                'dalam_proses'      => (clone $base)->whereIn('status_saat_ini', ['investigasi', 'tindak_lanjut'])->count(),
            ],
        ];
    }

    /**
     * Karu: SLA alerts, pending counter, aktivitas terbaru unit.
     */
    private function dataKaru(Pengguna $pengguna): array
    {
        $tenantId = $pengguna->tenant_id;
        $unitId   = $pengguna->unit_id;
        $namaUnit = $pengguna->unitKerja?->nama_unit;

        // Base query: insiden dari unit kerja Karu, bukan draf.
        $base = Insiden::where('tenant_id', $tenantId)
            ->bukanDraf()
            ->where(function ($q) use ($unitId, $namaUnit) {
                $q->where('unit_id', $unitId)
                  ->orWhere('nama_unit_kerja', $namaUnit);
            });

        // Laporan yang belum ditindaklanjuti = masih berstatus kasus_baru.
        $menungguTindakLanjut = (clone $base)
            ->where('status_saat_ini', 'kasus_baru')
            ->count();

        // SLA: laporan KTD/SENTINEL yang belum direspons > 24 jam.
        $slaBreachCount = (clone $base)
            ->where('status_saat_ini', 'kasus_baru')
            ->whereIn('tipe_insiden', ['KTD', 'SENTINEL'])
            ->where('tgl_lapor', '<', Carbon::now()->subHours(24))
            ->count();

        // 5 tindak lanjut terbaru di unit ini - timeline ringkas.
        $aktivitasTerbaru = TindakLanjut::whereHas('insiden', function ($q) use ($tenantId, $unitId, $namaUnit) {
                $q->where('tenant_id', $tenantId)
                  ->where(fn ($sq) => $sq->where('unit_id', $unitId)->orWhere('nama_unit_kerja', $namaUnit));
            })
            ->with(['insiden:id,nomor_laporan,tipe_insiden', 'pengguna:id,nama_lengkap'])
            ->latest()
            ->limit(5)
            ->get();

        // Ringkasan status keseluruhan di unit.
        $ringkasanStatus = [
            'kasus_baru'    => (clone $base)->where('status_saat_ini', 'kasus_baru')->count(),
            'investigasi'   => (clone $base)->where('status_saat_ini', 'investigasi')->count(),
            'tindak_lanjut' => (clone $base)->where('status_saat_ini', 'tindak_lanjut')->count(),
            'selesai'       => (clone $base)->where('status_saat_ini', 'selesai')->count(),
        ];

        return [
            'karu' => [
                'menunggu_tindak_lanjut' => $menungguTindakLanjut,
                'sla_breach'             => $slaBreachCount,
                'aktivitas_terbaru'      => $aktivitasTerbaru,
                'ringkasan_status'       => $ringkasanStatus,
            ],
        ];
    }

    /**
     * Komite: alert severity tinggi, bottleneck unit, rasio resolusi.
     */
    private function dataKomite(int $tenantId): array
    {
        $base = Insiden::where('tenant_id', $tenantId)->bukanDraf();
        $hariIni = Carbon::today();

        // Alert: kasus KTD/Sentinel yang masih BELUM selesai.
        $alertSeveritas = (clone $base)
            ->whereIn('tipe_insiden', ['KTD', 'SENTINEL'])
            ->where('status_saat_ini', '!=', 'selesai')
            ->latest('tgl_lapor')
            ->limit(5)
            ->get(['id', 'nomor_laporan', 'tipe_insiden', 'status_saat_ini', 'nama_unit_kerja', 'tgl_lapor']);

        // Bottleneck: 5 unit kerja dengan jumlah laporan "kasus_baru" terbanyak
        // (menunggu paling lama tanpa tindak lanjut).
        $bottleneck = (clone $base)
            ->where('status_saat_ini', 'kasus_baru')
            ->select('nama_unit_kerja', DB::raw('COUNT(*) as total'), DB::raw('MIN(tgl_lapor) as tertua'))
            ->groupBy('nama_unit_kerja')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Rasio resolusi hari ini: selesai / total masuk hari ini.
        $masukHariIni  = (clone $base)->whereDate('tgl_lapor', $hariIni)->count();
        $selesaiHariIni = (clone $base)->whereDate('tgl_lapor', $hariIni)->where('status_saat_ini', 'selesai')->count();

        // Total belum selesai seluruh RS.
        $belumSelesai = (clone $base)->where('status_saat_ini', '!=', 'selesai')->count();

        return [
            'komite' => [
                'alert_severitas'  => $alertSeveritas,
                'bottleneck'       => $bottleneck,
                'masuk_hari_ini'   => $masukHariIni,
                'selesai_hari_ini' => $selesaiHariIni,
                'belum_selesai'    => $belumSelesai,
            ],
        ];
    }

    /**
     * Direktur: health status, rata-rata respons, ringkasan eksekutif.
     */
    private function dataDirektur(int $tenantId): array
    {
        $base    = Insiden::where('tenant_id', $tenantId)->bukanDraf();
        $hariIni = Carbon::today();

        // Kasus severity tinggi hari ini.
        $highSevHariIni = (clone $base)
            ->whereIn('tipe_insiden', ['KTD', 'SENTINEL'])
            ->whereDate('tgl_lapor', $hariIni)
            ->count();

        // Tentukan level status kesehatan sistem.
        $statusKesehatan = match (true) {
            $highSevHariIni >= 3 => 'kritis',
            $highSevHariIni >= 1 => 'waspada',
            default              => 'aman',
        };

        // Rata-rata waktu respons (jam) - dari tgl_lapor ke tindak lanjut pertama.
        // Menggunakan sub-query untuk efisiensi pada dataset besar.
        $rataResponsJam = TindakLanjut::join('pelaporan.insiden as i', 'i.id', '=', 'pelaporan.tindak_lanjut.insiden_id')
            ->where('i.tenant_id', $tenantId)
            ->whereNotNull('i.tgl_lapor')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (pelaporan.tindak_lanjut.created_at - i.tgl_lapor)) / 3600) as rata_jam')
            ->value('rata_jam');

        // Ringkasan eksekutif: jumlah per status.
        $ringkasan = [
            'total'         => (clone $base)->count(),
            'kasus_baru'    => (clone $base)->where('status_saat_ini', 'kasus_baru')->count(),
            'dalam_proses'  => (clone $base)->whereIn('status_saat_ini', ['investigasi', 'tindak_lanjut'])->count(),
            'selesai'       => (clone $base)->where('status_saat_ini', 'selesai')->count(),
        ];

        // Insiden masuk 7 hari terakhir per hari (sparkline data).
        $trenMingguan = (clone $base)
            ->where('tgl_lapor', '>=', Carbon::now()->subDays(6)->startOfDay())
            ->select(DB::raw("DATE(tgl_lapor) as tanggal"), DB::raw('COUNT(*) as jumlah'))
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->pluck('jumlah', 'tanggal');

        return [
            'direktur' => [
                'status_kesehatan'  => $statusKesehatan,
                'high_sev_hari_ini' => $highSevHariIni,
                'rata_respons_jam'  => round((float) ($rataResponsJam ?? 0), 1),
                'ringkasan'         => $ringkasan,
                'tren_mingguan'     => $trenMingguan,
            ],
        ];
    }
}
