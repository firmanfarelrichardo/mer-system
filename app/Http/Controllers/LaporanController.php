<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\InsidenData;
use App\Events\InsidenStatusBerubah;
use App\Http\Requests\SimpanLaporanRequest;
use App\Models\DetailPasien;
use App\Models\Insiden;
use App\Models\Peran;
use App\Models\TindakLanjut;
use App\Repositories\InsidenRepository;
use App\Services\LaporanService;
use App\Support\Paginasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * LaporanController — menangani CRUD laporan insiden medication error.
 *
 * Prinsip DRY (Don't Repeat Yourself):
 *   - SATU controller untuk SEMUA peran (Nakes, Karu, Komite, Direktur).
 *   - Data di-filter secara otomatis di level database menggunakan
 *     local scope `scopeUntukPeran()` pada model Insiden.
 *   - Otorisasi aksi (tindak lanjut, ubah status) melalui InsidenPolicy.
 *   - View yang sama merender UI secara kondisional via @can / @if.
 *
 * Fitur:
 *   1. Index     — daftar laporan + statistik (scoped per peran)
 *   2. Buat      — formulir multi-step
 *   3. Simpan    — simpan laporan baru
 *   4. Tampil    — detail laporan + histori tindak lanjut
 *   5. TindakLanjut — ubah status + catatan (Karu & Komite only)
 *   6. Tandai Dibaca — tandai laporan sudah dibaca
 */
class LaporanController extends Controller
{
    public function __construct(
        private readonly LaporanService    $laporanService,
        private readonly InsidenRepository $insidenRepository,
    ) {}

    /* ==================================================================
     | INDEX — Daftar Laporan & Statistik (hanya yang sudah di-submit)
     | =================================================================*/

    /**
     * Tampilkan halaman daftar laporan insiden.
     *
     * Query SELALU melewati scopeUntukPeran() sehingga:
     *   - Nakes     → hanya laporan miliknya
     *   - Karu       → hanya laporan dari unit kerjanya
     *   - Komite     → semua laporan tenant
     *   - Direktur   → semua laporan tenant (read-only)
     */
    public function index(Request $permintaan): View
    {
        $pengguna = Auth::user();

        // ── Query utama (scoped per peran, selalu kecualikan DRAF) ───
        $query = Insiden::with(['detailPasien', 'unitKerja'])
            ->untukPeran($pengguna)
            ->bukanDraf()
            ->latest('tgl_lapor');

        // Filter: pencarian nama pasien atau nomor laporan.
        if ($cari = $permintaan->input('cari')) {
            $query->where(function ($q) use ($cari) {
                $q->where('nomor_laporan', 'ilike', "%{$cari}%")
                  ->orWhereHas('detailPasien', fn ($dp) => $dp->where('nama_pasien', 'ilike', "%{$cari}%"));
            });
        }

        // Filter: status insiden.
        if ($status = $permintaan->input('status')) {
            $query->where('status_saat_ini', $status);
        }

        // Filter: tipe insiden (KPC/KNC/KTC/KTD/Sentinel).
        if ($tipe = $permintaan->input('tipe')) {
            $query->where('tipe_insiden', $tipe);
        }

        $daftarLaporan = $query->paginate(Paginasi::perHalaman())->withQueryString();

        // ── Statistik ringkasan (juga scoped per peran, tanpa draf) ───
        $baseQuery = Insiden::untukPeran($pengguna)->bukanDraf();

        $statistik = [
            'total'           => (clone $baseQuery)->count(),
            'kasus_baru'      => (clone $baseQuery)->where('status_saat_ini', 'kasus_baru')->count(),
            'sedang_diproses' => (clone $baseQuery)->whereIn('status_saat_ini', ['investigasi', 'tindak_lanjut'])->count(),
            'selesai'         => (clone $baseQuery)->where('status_saat_ini', 'selesai')->count(),
        ];

        return view('laporan.index', compact('daftarLaporan', 'statistik', 'pengguna'));
    }

    /* ==================================================================
     | BUAT — Formulir Pembuatan Laporan
     | =================================================================*/

    /**
     * Tampilkan formulir pembuatan laporan insiden baru.
     */
    public function buat(Request $permintaan): View
    {
        return view('laporan.buat');
    }

    /* ==================================================================
     | SIMPAN — Proses & Simpan Laporan Baru
     | =================================================================*/

    /**
     * Simpan laporan insiden baru / draf ke database.
     *
     * Mendelegasikan ke LaporanService yang menangani:
     *   - Buat baru vs update draf (berdasarkan insiden_id).
     *   - Set status DRAF vs kasus_baru (berdasarkan action).
     *   - Audit log & notifikasi secara kondisional.
     */
    public function simpan(SimpanLaporanRequest $permintaan): RedirectResponse
    {
        $pengguna = Auth::user();
        $dto      = InsidenData::fromRequest($permintaan);

        $insiden = $this->laporanService->save($dto, $pengguna);

        if ($dto->isDraft) {
            return redirect()
                ->route('laporan.draf')
                ->with('sukses', 'Draf laporan berhasil disimpan.');
        }

        return redirect()
            ->route('laporan.index')
            ->with('sukses', "Laporan insiden {$insiden->nomor_laporan} berhasil dikirim.");
    }

    /* ==================================================================
     | DRAF — Daftar Draf Laporan (Nakes Only)
     | =================================================================*/

    /**
     * Tampilkan halaman daftar draf laporan milik Nakes.
     */
    public function draf(Request $permintaan): View
    {
        $pengguna = Auth::user();

        $daftarDraf = $this->insidenRepository->getDraftLaporan(
            nakesId:  $pengguna->id,
            tenantId: $pengguna->tenant_id,
            cari:     $permintaan->input('cari'),
        );

        return view('laporan.draf', compact('daftarDraf', 'pengguna'));
    }

    /* ==================================================================
     | EDIT — Form Edit Draf (Nakes melanjutkan pengisian)
     | =================================================================*/

    /**
     * Tampilkan formulir edit untuk melanjutkan pengisian draf.
     */
    public function edit(Request $permintaan, string $laporan): View
    {
        $pengguna = Auth::user();

        $insiden = $this->insidenRepository->findDraftById(
            insidenId: (int) $laporan,
            nakesId:   $pengguna->id,
        );

        if (! $insiden) {
            abort(404, 'Draf laporan tidak ditemukan.');
        }

        return view('laporan.edit', compact('insiden', 'pengguna'));
    }

    /* ==================================================================
     | TAMPIL — Detail Laporan + Histori Tindak Lanjut
     | =================================================================*/

    /**
     * Tampilkan detail satu laporan insiden.
     *
     * Memuat relasi detailPasien, pelapor, unitKerja, dan tindakLanjut
     * agar view dapat menampilkan data lengkap termasuk histori tindak lanjut.
     */
    public function tampil(Request $permintaan, string $laporan): View
    {
        $pengguna = Auth::user();

        $insiden = Insiden::with([
                'detailPasien',
                'pelapor',
                'unitKerja',
                // Muat pengguna beserta perannya agar statusTerakhirOlehPeran()
                // dapat menggunakan collection tanpa query N+1.
                'tindakLanjut.pengguna.peran',
            ])
            ->untukPeran($pengguna)
            ->findOrFail($laporan);

        // Otomatis tandai sudah dibaca jika Karu/Komite/Admin membuka.
        if (! $insiden->sudah_dibaca && (
            $pengguna->memilikiPeran(Peran::KEPALA_RUANGAN)
            || $pengguna->memilikiPeran(Peran::KOMITE)
            || $pengguna->memilikiPeran(Peran::ADMIN)
        )) {
            $insiden->update(['sudah_dibaca' => true]);
        }

        return view('laporan.tampil', compact('insiden', 'pengguna'));
    }

    /* ==================================================================
     | TINDAK LANJUT — Ubah Status + Catatan (Karu & Komite Only)
     | =================================================================*/

    /**
     * Proses tindak lanjut: ubah status insiden dan simpan catatan.
     *
     * Dilindungi oleh InsidenPolicy@tindakLanjut sehingga Direktur
     * dan Nakes TIDAK dapat mengakses endpoint ini meskipun
     * mencoba bypass UI.
     */
    public function tindakLanjut(Request $permintaan, string $laporan): RedirectResponse
    {
        $insiden  = Insiden::findOrFail($laporan);
        $pengguna = Auth::user();

        // Otorisasi via Policy — menolak Direktur & Nakes.
        $this->authorize('tindakLanjut', $insiden);

        // Validasi input.
        $data = $permintaan->validate([
            'status_baru' => ['required', 'in:investigasi,tindak_lanjut,selesai'],
            'catatan'     => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'status_baru.required' => 'Status baru wajib dipilih.',
            'status_baru.in'       => 'Status yang dipilih tidak valid.',
            'catatan.required'     => 'Catatan tindak lanjut wajib diisi.',
            'catatan.min'          => 'Catatan minimal 10 karakter.',
            'catatan.max'          => 'Catatan maksimal 2000 karakter.',
        ]);

        DB::transaction(function () use ($insiden, $pengguna, $data) {
            // Simpan catatan tindak lanjut.
            TindakLanjut::create([
                'insiden_id'  => $insiden->id,
                'pengguna_id' => $pengguna->id,
                'status_baru' => $data['status_baru'],
                'catatan'     => $data['catatan'],
            ]);

            // Perbarui status insiden.
            $insiden->update([
                'status_saat_ini' => $data['status_baru'],
                'sudah_dibaca'    => true,
            ]);
        });

        // Dispatch event — listener akan mengirim notifikasi sesuai state-routing.
        InsidenStatusBerubah::dispatch($insiden->fresh(), $data['status_baru'], $pengguna);

        return back()->with('sukses', 'Tindak lanjut berhasil disimpan.');
    }

    /* ==================================================================
     | TANDAI DIBACA
     | =================================================================*/

    /**
     * Tandai laporan sebagai sudah dibaca.
     */
    public function tandaiDibaca(Request $permintaan, string $laporan): RedirectResponse
    {
        $insiden = Insiden::findOrFail($laporan);

        // Otorisasi via Policy.
        $this->authorize('tandaiDibaca', $insiden);

        $insiden->update(['sudah_dibaca' => true]);

        return back()->with('sukses', 'Laporan ditandai sudah dibaca.');
    }

    /* ==================================================================
     | CETAK PDF — Stream PDF Laporan ke Browser
     | =================================================================*/

    /**
     * Generate dan stream PDF laporan insiden ke browser (tab baru).
     *
     * Hanya dapat diakses untuk laporan yang bukan DRAF.
     */
    public function cetakPdf(string $laporan): Response
    {
        $pdf = $this->laporanService->generatePdfReport((int) $laporan);

        $insiden = $this->insidenRepository->getInsidenForPdf((int) $laporan);

        $namaFile = 'Laporan-Insiden-' . str_replace('/', '-', $insiden->nomor_laporan) . '.pdf';

        return $pdf->stream($namaFile);
    }
}
