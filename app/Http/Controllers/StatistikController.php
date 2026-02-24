<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Insiden;
use App\Models\Peran;
use App\Models\Pengguna;
use App\Models\UnitKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * StatistikController — Dashboard analitik insiden medication errors.
 *
 * ┌─────────────────────────────────────────────────────────────────┐
 * │  RBAC — Visibilitas Data & Filter                               │
 * ├─────────────────────────┬───────────────────────────────────────┤
 * │ Kepala Ruangan (karu)   │ Hanya unit kerjanya sendiri.          │
 * │                         │ Filter unit & grafik distribusi per   │
 * │                         │ ruangan TIDAK ditampilkan.            │
 * ├─────────────────────────┼───────────────────────────────────────┤
 * │ Komite & Direktur       │ Semua unit/seluruh tenant.            │
 * │                         │ Semua filter & grafik aktif.          │
 * └─────────────────────────┴───────────────────────────────────────┘
 *
 * Prinsip DRY:
 *   - Satu $baseQuery yang di-clone untuk setiap metrik.
 *   - Satu method privat per jenis data agregat.
 *   - Konstanta WARNA_CHART_* dipusatkan agar konsisten di seluruh grafik.
 *   - Flag $bisaLihatSemua mengontrol visibilitas filter & grafik.
 *
 * Filter URL yang didukung:
 *   ?start_date   — Format: Y-m-d
 *   ?end_date     — Format: Y-m-d
 *   ?unit_kerja   — Nama unit (hanya aktif untuk direktur & komite)
 *   ?tipe_insiden — Salah satu dari: KPC, KNC, KTC, KTD, SENTINEL
 */
class StatistikController extends Controller
{
    // ----------------------------------------------------------------
    // Konstanta — DRY untuk palet warna grafik Chart.js
    // ----------------------------------------------------------------

    /**
     * Warna RGBA tiap tipe insiden — Semantic by severity.
     *
     * KPC (Potensial Cedera) → Biru     — Informasi
     * KNC (Nyaris Cedera)    → Kuning   — Waspada
     * KTC (Tidak Cedera)     → Oranye   — Peringatan
     * KTD (Tidak Diharapkan) → Merah    — Kritis
     * SENTINEL               → Merah Gelap — Sangat Kritis
     *
     * @var array<string, string>
     */
    private const WARNA_TIPE = [
        'KPC'      => 'rgba(59,  130, 246, 0.85)',  // blue-500   — Informasi/Potensial
        'KNC'      => 'rgba(234, 179,   8, 0.85)',  // yellow-500 — Waspada
        'KTC'      => 'rgba(249, 115,  22, 0.85)',  // orange-500 — Peringatan
        'KTD'      => 'rgba(220,  38,  38, 0.85)',  // red-600    — Kritis
        'SENTINEL' => 'rgba(127,  29,  29, 0.92)',  // red-900    — Sangat Kritis
    ];

    /**
     * Warna RGBA tiap status insiden — Corporate blue palette.
     *
     * Menggunakan gradasi blue → indigo → violet → hijau sebagai
     * representasi alur penanganan (masuk → proses → selesai).
     *
     * @var array<string, string>
     */
    private const WARNA_STATUS = [
        'kasus_baru'    => 'rgba(59,  130, 246, 0.85)',  // blue-500   — Masuk
        'investigasi'   => 'rgba(99,  102, 241, 0.85)',  // indigo-500 — Analisis
        'tindak_lanjut' => 'rgba(139,  92, 246, 0.85)',  // violet-500 — Proses
        'selesai'       => 'rgba(34,  197,  94, 0.85)',  // green-500  — Tuntas
    ];

    /** @var array<string, string>  Label tampilan tiap status. */
    private const LABEL_STATUS = [
        'kasus_baru'    => 'Kasus Baru',
        'investigasi'   => 'Investigasi',
        'tindak_lanjut' => 'Tindak Lanjut',
        'selesai'       => 'Selesai',
    ];

    /**
     * Warna monokromatik biru→cyan untuk grafik distribusi ruangan.
     * Gradasi gelap-ke-terang agar bar mudah dibedakan tanpa ramai warna.
     *
     * @var list<string>
     */
    private const WARNA_RUANGAN = [
        'rgba(30,   64, 175, 0.9)',   // blue-800
        'rgba(29,   78, 216, 0.9)',   // blue-700
        'rgba(37,   99, 235, 0.9)',   // blue-600
        'rgba(59,  130, 246, 0.9)',   // blue-500
        'rgba(96,  165, 250, 0.9)',   // blue-400
        'rgba(147, 197, 253, 0.9)',   // blue-300
        'rgba(14,  116, 144, 0.9)',   // cyan-700
        'rgba(8,   145, 178, 0.9)',   // cyan-600
        'rgba(6,   182, 212, 0.9)',   // cyan-500
        'rgba(34,  211, 238, 0.9)',   // cyan-400
        'rgba(15,  118, 110, 0.9)',   // teal-700
        'rgba(20,  184, 166, 0.9)',   // teal-500
    ];

    /**
     * Warna monokromatik biru untuk grafik tahapan kesalahan.
     * Gradasi gelap-ke-terang: prescribing (paling awal) → administration.
     *
     * @var array<string, string>
     */
    private const WARNA_TAHAPAN = [
        'prescribing'    => 'rgba(29,  78, 216, 0.85)',  // blue-700
        'transcribing'   => 'rgba(37,  99, 235, 0.85)',  // blue-600
        'dispensing'     => 'rgba(59, 130, 246, 0.85)',  // blue-500
        'administration' => 'rgba(96, 165, 250, 0.85)',  // blue-400
    ];

    /** @var array<string, string>  Label tampilan tiap fase. */
    private const LABEL_TAHAPAN = [
        'prescribing'    => 'Peresepan',
        'transcribing'   => 'Penerjemahan',
        'dispensing'     => 'Peracikan',
        'administration' => 'Penyerahan',
    ];

    // ----------------------------------------------------------------
    // Public Action
    // ----------------------------------------------------------------

    /**
     * Tampilkan halaman statistik & analisis insiden.
     */
    public function index(Request $request): View|Response
    {
        /** @var Pengguna $pengguna */
        $pengguna = Auth::user();

        // Gate: hanya tiga peran ini yang boleh mengakses statistik.
        if (! $this->dapatMengaksesStatistik($pengguna)) {
            abort(403, 'Anda tidak memiliki akses ke halaman statistik.');
        }

        // Flag RBAC: true → direktur/komite; false → kepala ruangan.
        $bisaLihatSemua = $pengguna->memilikiPeran(Peran::KOMITE)
            || $pengguna->memilikiPeran(Peran::DIREKTUR);

        // Base query (scoped per peran via Eloquent scope).
        $baseQuery = Insiden::untukPeran($pengguna);

        // Terapkan filter dari request.
        [$baseQuery, $filterAktif, $filterAktifData] = $this->terapkanFilter(
            $request,
            $baseQuery,
            $bisaLihatSemua,
        );

        // Hitung semua data metrik — tiap kali clone agar query tidak tercampur.
        $totalInsiden      = (clone $baseQuery)->count();
        $ringkasanAngka    = $this->hitungRingkasanAngka(clone $baseQuery, $totalInsiden);
        $trenBulanan       = $this->hitungTrenBulanan(clone $baseQuery);
        $distribusiStatus  = $this->hitungDistribusiStatus(clone $baseQuery);
        $distribusiTipe    = $this->hitungDistribusiTipe(clone $baseQuery);
        $distribusiTahapan = $this->hitungDistribusiPerTahapan(clone $baseQuery, $totalInsiden);
        $ringkasanPerUnit  = $this->hitungRingkasanPerUnit(clone $baseQuery, $pengguna);

        // Distribusi per ruangan hanya untuk direktur & komite.
        $distribusiRuangan = $bisaLihatSemua
            ? $this->hitungDistribusiRuangan(clone $baseQuery)
            : null;

        // Daftar unit untuk dropdown filter (hanya direktur & komite).
        $daftarUnit = $bisaLihatSemua
            ? $this->getDaftarUnit($pengguna)
            : collect();

        return view('statistik.index', compact(
            'pengguna',
            'bisaLihatSemua',
            'ringkasanAngka',
            'trenBulanan',
            'distribusiStatus',
            'distribusiTipe',
            'distribusiTahapan',
            'distribusiRuangan',
            'ringkasanPerUnit',
            'daftarUnit',
            'filterAktif',
            'filterAktifData',
        ));
    }

    // ----------------------------------------------------------------
    // Private Helpers — Filter
    // ----------------------------------------------------------------

    /**
     * Terapkan semua filter request ke query utama.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return array{0: \Illuminate\Database\Eloquent\Builder, 1: bool, 2: array<string, string>}
     */
    private function terapkanFilter(Request $request, $query, bool $bisaLihatSemua): array
    {
        $filterAktif = false;
        $data        = [];

        if ($request->filled('start_date')) {
            $query->whereDate('tgl_lapor', '>=', $request->input('start_date'));
            $filterAktif          = true;
            $data['start_date']   = $request->input('start_date');
        }

        if ($request->filled('end_date')) {
            $query->whereDate('tgl_lapor', '<=', $request->input('end_date'));
            $filterAktif        = true;
            $data['end_date']   = $request->input('end_date');
        }

        // Unit kerja hanya bisa difilter oleh direktur & komite.
        if ($bisaLihatSemua && $request->filled('unit_kerja')) {
            $namaUnit = $request->input('unit_kerja');
            $query->where(function ($q) use ($namaUnit) {
                $q->where('nama_unit_kerja', $namaUnit)
                  ->orWhereHas('unitKerja', fn ($uk) => $uk->where('nama_unit', $namaUnit));
            });
            $filterAktif          = true;
            $data['unit_kerja']   = $namaUnit;
        }

        if ($request->filled('tipe_insiden')) {
            $tipe = strtoupper((string) $request->input('tipe_insiden'));
            if (array_key_exists($tipe, self::WARNA_TIPE)) {
                $query->where('tipe_insiden', $tipe);
                $filterAktif            = true;
                $data['tipe_insiden']   = $tipe;
            }
        }

        return [$query, $filterAktif, $data];
    }

    // ----------------------------------------------------------------
    // Private Helpers — Kalkulasi Metrik
    // ----------------------------------------------------------------

    /** Verifikasi hak akses halaman statistik. */
    private function dapatMengaksesStatistik(Pengguna $pengguna): bool
    {
        return $pengguna->memilikiPeran(Peran::KEPALA_RUANGAN)
            || $pengguna->memilikiPeran(Peran::KOMITE)
            || $pengguna->memilikiPeran(Peran::DIREKTUR);
    }

    /**
     * Hitung 6 angka ringkasan untuk summary cards.
     *
     * @return array<string, int|float>
     */
    private function hitungRingkasanAngka($query, int $total): array
    {
        $kasusBaru = (clone $query)->where('status_saat_ini', 'kasus_baru')->count();
        $selesai   = (clone $query)->where('status_saat_ini', 'selesai')->count();

        $insidenBulanIni = (clone $query)
            ->whereMonth('tgl_lapor', now()->month)
            ->whereYear('tgl_lapor', now()->year)
            ->count();

        $rataRata = round(
            (clone $query)->where('tgl_lapor', '>=', now()->subMonths(5)->startOfMonth())->count() / 6,
            1,
        );

        return [
            'total'                => $total,
            'kasus_baru'           => $kasusBaru,
            'selesai'              => $selesai,
            'tingkat_penyelesaian' => $total > 0 ? (int) round(($selesai / $total) * 100) : 0,
            'insiden_bulan_ini'    => $insidenBulanIni,
            'rata_rata_per_bulan'  => $rataRata,
        ];
    }

    /**
     * Hitung tren insiden 6 bulan terakhir.
     *
     * @return array{labels: list<string>, data: list<int>}
     */
    private function hitungTrenBulanan($query): array
    {
        $labels    = [];
        $data      = [];
        $namaBulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

        for ($i = 5; $i >= 0; $i--) {
            $tgl    = now()->subMonths($i);
            $bulan  = (int) $tgl->format('m');
            $tahun  = (int) $tgl->format('Y');

            $labels[] = $namaBulan[$bulan - 1] . ' ' . $tgl->format('Y');
            $data[]   = (clone $query)->whereMonth('tgl_lapor', $bulan)->whereYear('tgl_lapor', $tahun)->count();
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Hitung distribusi status untuk Doughnut Chart.
     *
     * @return array{labels: list<string>, data: list<int>, colors: list<string>}
     */
    private function hitungDistribusiStatus($query): array
    {
        $labels = [];
        $data   = [];
        $colors = [];

        foreach (self::LABEL_STATUS as $kode => $label) {
            $labels[] = $label;
            $data[]   = (clone $query)->where('status_saat_ini', $kode)->count();
            $colors[] = self::WARNA_STATUS[$kode];
        }

        return ['labels' => $labels, 'data' => $data, 'colors' => $colors];
    }

    /**
     * Hitung distribusi tipe insiden untuk Bar Chart.
     *
     * @return array{labels: list<string>, data: list<int>, colors: list<string>}
     */
    private function hitungDistribusiTipe($query): array
    {
        $labels = [];
        $data   = [];
        $colors = [];

        foreach (array_keys(self::WARNA_TIPE) as $tipe) {
            $labels[] = $tipe;
            $data[]   = (clone $query)->where('tipe_insiden', $tipe)->count();
            $colors[] = self::WARNA_TIPE[$tipe];
        }

        return ['labels' => $labels, 'data' => $data, 'colors' => $colors];
    }

    /**
     * Hitung distribusi per tahapan kesalahan.
     *
     * @return array{labels: list<string>, data: list<int>, colors: list<string>, persen: list<int>}
     */
    private function hitungDistribusiPerTahapan($query, int $total): array
    {
        $labels = [];
        $data   = [];
        $colors = [];
        $persen = [];

        foreach (self::LABEL_TAHAPAN as $kode => $label) {
            $jumlah   = (clone $query)->where('fase_kesalahan', $kode)->count();
            $labels[] = $label;
            $data[]   = $jumlah;
            $colors[] = self::WARNA_TAHAPAN[$kode];
            $persen[] = $total > 0 ? (int) round(($jumlah / $total) * 100) : 0;
        }

        return ['labels' => $labels, 'data' => $data, 'colors' => $colors, 'persen' => $persen];
    }

    /**
     * Hitung distribusi per unit kerja untuk Horizontal Bar Chart.
     * Hanya untuk direktur & komite.
     *
     * @return array{labels: list<string>, data: list<int>, colors: list<string>}
     */
    private function hitungDistribusiRuangan($query): array
    {
        $rows = (clone $query)
            ->select('nama_unit_kerja', DB::raw('COUNT(*) as jumlah'))
            ->whereNotNull('nama_unit_kerja')
            ->groupBy('nama_unit_kerja')
            ->orderByDesc('jumlah')
            ->limit(12)
            ->get();

        $labels = [];
        $data   = [];
        $colors = [];

        foreach ($rows as $i => $row) {
            $labels[] = $row->nama_unit_kerja;
            $data[]   = (int) $row->jumlah;
            $colors[] = self::WARNA_RUANGAN[$i % count(self::WARNA_RUANGAN)];
        }

        return ['labels' => $labels, 'data' => $data, 'colors' => $colors];
    }

    /**
     * Hitung ringkasan insiden per unit kerja untuk tabel.
     *
     * @return array<int, array{unit: string, total: int, kpc: int, knc: int, ktc: int, ktd: int, sentinel: int, selesai: int}>
     */
    private function hitungRingkasanPerUnit($query, Pengguna $pengguna): array
    {
        // Unit kerja dari master sebagai basis (agar semua unit selalu muncul).
        $masterUnits = UnitKerja::where('tenant_id', $pengguna->tenant_id)
            ->orderBy('nama_unit')
            ->pluck('nama_unit')
            ->all();

        // Hitung insiden per nama_unit_kerja dari data aktual.
        $rows = (clone $query)
            ->select(
                'nama_unit_kerja',
                DB::raw("COUNT(*) as total"),
                DB::raw("SUM(CASE WHEN tipe_insiden='KPC'      THEN 1 ELSE 0 END) as kpc"),
                DB::raw("SUM(CASE WHEN tipe_insiden='KNC'      THEN 1 ELSE 0 END) as knc"),
                DB::raw("SUM(CASE WHEN tipe_insiden='KTC'      THEN 1 ELSE 0 END) as ktc"),
                DB::raw("SUM(CASE WHEN tipe_insiden='KTD'      THEN 1 ELSE 0 END) as ktd"),
                DB::raw("SUM(CASE WHEN tipe_insiden='SENTINEL' THEN 1 ELSE 0 END) as sentinel"),
                DB::raw("SUM(CASE WHEN status_saat_ini='selesai' THEN 1 ELSE 0 END) as selesai"),
            )
            ->whereNotNull('nama_unit_kerja')
            ->groupBy('nama_unit_kerja')
            ->orderByDesc('total')
            ->get()
            ->keyBy('nama_unit_kerja');

        // Bangun map: insiden aktual lebih prioritas daripada master kosong.
        $hasilMap = [];

        foreach ($rows as $namaUnit => $row) {
            $hasilMap[$namaUnit] = $this->barisTabel($namaUnit, $row);
        }

        foreach ($masterUnits as $namaUnit) {
            if (! isset($hasilMap[$namaUnit])) {
                $hasilMap[$namaUnit] = $this->barisTabel($namaUnit);
            }
        }

        usort($hasilMap, fn ($a, $b) => $b['total'] <=> $a['total']);

        return array_slice($hasilMap, 0, 20);
    }

    /**
     * Bangun satu baris array untuk tabel ringkasan per unit.
     *
     * @return array{unit: string, total: int, kpc: int, knc: int, ktc: int, ktd: int, sentinel: int, selesai: int}
     */
    private function barisTabel(string $namaUnit, ?object $row = null): array
    {
        return [
            'unit'     => $namaUnit,
            'total'    => (int) ($row?->total    ?? 0),
            'kpc'      => (int) ($row?->kpc      ?? 0),
            'knc'      => (int) ($row?->knc      ?? 0),
            'ktc'      => (int) ($row?->ktc      ?? 0),
            'ktd'      => (int) ($row?->ktd      ?? 0),
            'sentinel' => (int) ($row?->sentinel  ?? 0),
            'selesai'  => (int) ($row?->selesai   ?? 0),
        ];
    }

    /**
     * Ambil daftar nama unit kerja untuk dropdown filter.
     *
     * Strategi dual-source (DRY fallback):
     *   1. Utama  : master.unit_kerja — data master yang terkurasi.
     *   2. Fallback: distinct nama_unit_kerja dari tabel insiden itu sendiri.
     *              Berguna saat master belum di-seed atau data demo dipakai.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function getDaftarUnit(Pengguna $pengguna): \Illuminate\Support\Collection
    {
        $masterUnits = UnitKerja::where('tenant_id', $pengguna->tenant_id)
            ->orderBy('nama_unit')
            ->pluck('nama_unit');

        if ($masterUnits->isNotEmpty()) {
            return $masterUnits;
        }

        // Fallback: tarik nilai distinct dari insiden yang sudah ada.
        // Cocok untuk environment dev/demo yang belum populate master unit_kerja.
        return Insiden::untukPeran($pengguna)
            ->whereNotNull('nama_unit_kerja')
            ->distinct()
            ->orderBy('nama_unit_kerja')
            ->pluck('nama_unit_kerja');
    }
}
