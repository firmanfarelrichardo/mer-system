<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\InsidenData;
use App\Events\InsidenStatusBerubah;
use App\Http\Requests\SimpanLaporanRequest;
use App\Models\DetailPasien;
use App\Models\Insiden;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\UnitKerja;
use App\Models\TindakLanjut;
use App\Notifications\InsidenNotifikasi;
use App\Repositories\InsidenRepository;
use Illuminate\Support\Facades\Notification;
use App\Services\FaktorPenyebabService;
use App\Services\IntervensiService;
use App\Services\JenisKesalahanService;
use App\Services\LaporanService;
use App\Services\TipeCederaService;
use App\Support\Paginasi;
use Database\Seeders\Concerns\NormalizesUnitKerjaNames;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * LaporanController - menangani CRUD laporan insiden medication error.
 *
 * Prinsip DRY (Don't Repeat Yourself):
 *   - SATU controller untuk SEMUA peran (Nakes, Karu, Komite, Direktur).
 *   - Data di-filter secara otomatis di level database menggunakan
 *     local scope `scopeUntukPeran()` pada model Insiden.
 *   - Otorisasi aksi (tindak lanjut, ubah status) melalui InsidenPolicy.
 *   - View yang sama merender UI secara kondisional via @can / @if.
 *
 * Fitur:
 *   1. Index     - daftar laporan + statistik (scoped per peran)
 *   2. Buat      - formulir multi-step
 *   3. Simpan    - simpan laporan baru
 *   4. Tampil    - detail laporan + histori tindak lanjut
 *   5. TindakLanjut - ubah status + catatan (Karu & Komite only)
 *   6. Tandai Dibaca - tandai laporan sudah dibaca
 */
class LaporanController extends Controller
{
    use NormalizesUnitKerjaNames;

    public function __construct(
        private readonly LaporanService          $laporanService,
        private readonly InsidenRepository       $insidenRepository,
        private readonly JenisKesalahanService   $jenisKesalahanService,
        private readonly TipeCederaService       $tipeCederaService,
        private readonly FaktorPenyebabService   $faktorPenyebabService,
        private readonly IntervensiService       $intervensiService,
    ) {}

    /* ==================================================================
     | INDEX - Daftar Laporan & Statistik (hanya yang sudah di-submit)
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
     | BUAT - Formulir Pembuatan Laporan
     | =================================================================*/

    /**
     * Tampilkan formulir pembuatan laporan insiden baru.
     */
    public function buat(Request $permintaan): View
    {
        $tenantId = Auth::user()->tenant_id;

        $masterJenisKesalahan  = $this->jenisKesalahanService->getAktifUntukForm($tenantId);
        $masterTipeCedera      = $this->tipeCederaService->getAktifUntukForm($tenantId);
        $masterFaktorPenyebab  = $this->faktorPenyebabService->getAktifUntukForm($tenantId);
        $masterIntervensi      = $this->intervensiService->getAktifUntukForm($tenantId);
        $daftarUnitKerja       = $this->daftarUnitKerjaUntukForm($tenantId);

        return view('laporan.buat', compact(
            'masterJenisKesalahan',
            'masterTipeCedera',
            'masterFaktorPenyebab',
            'masterIntervensi',
            'daftarUnitKerja',
        ));
    }

    /* ==================================================================
     | SIMPAN - Proses & Simpan Laporan Baru
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
     | AUTO-SAVE - Simpan Draf Otomatis via AJAX/Fetch (Background)
     | =================================================================*/

    /**
     * Simpan draf laporan secara otomatis di background.
     *
     * Endpoint ini dipanggil oleh Alpine.js autoSaveForm() setiap kali
     * Nakes berhenti mengetik (debounce 2 detik). Menerima data parsial
     * tanpa validasi ketat - tujuannya mencegah data hilang.
     *
     * Anti-spam: LaporanService->autoSave() menggunakan saveQuietly()
     * pada UPDATE sehingga InsidenObserver tidak terpicu berulang kali.
     */
    public function autoSave(Request $permintaan): JsonResponse
    {
        // Hanya terima request AJAX / JSON.
        if (! $permintaan->ajax() && ! $permintaan->wantsJson()) {
            return response()->json([
                'status' => 'error',
                'pesan'  => 'Hanya menerima request AJAX.',
            ], 400);
        }

        try {
            $pengguna = Auth::user();
            $dto      = InsidenData::fromAutoSaveRequest($permintaan);
            $insiden  = $this->laporanService->autoSave($dto, $pengguna);

            return response()->json([
                'status'    => 'ok',
                'pesan'     => 'Draf tersimpan otomatis.',
                'insiden_id' => $insiden->id,
                'waktu'     => now()->format('H:i:s'),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'error',
                'pesan'  => 'Gagal menyimpan draf otomatis.',
            ], 500);
        }
    }

    /* ==================================================================
     | DRAF - Daftar Draf Laporan (Nakes Only)
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

    /**
     * Hapus draf laporan secara permanen (force delete).
     */
    public function hapusDraf(Insiden $insiden): \Illuminate\Http\RedirectResponse
    {
        $pengguna = Auth::user();

        abort_if($insiden->tenant_id !== $pengguna->tenant_id, 404);
        abort_if($insiden->pelapor_id !== $pengguna->id, 403, 'Anda tidak berhak menghapus draf ini.');
        abort_if($insiden->status_saat_ini !== 'DRAF', 400, 'Hanya draf yang dapat dihapus.');

        \Illuminate\Support\Facades\DB::transaction(function () use ($insiden) {
            // Hapus detail pasien jika ada
            if ($insiden->detailPasien) {
                $insiden->detailPasien()->forceDelete();
            }
            
            // Hapus insiden secara permanen
            $insiden->forceDelete();
            
            app(\App\Services\AuditLogService::class)->catat(
                namaTabel: 'pelaporan.insiden',
                aksi:      'DELETE_DRAF',
                idData:    $insiden->id,
                dataLama:  $insiden->toArray(),
            );
        });

        return redirect()
            ->route('laporan.draf')
            ->with('sukses', 'Draf laporan berhasil dihapus.');
    }

    /* ==================================================================
     | RIWAYAT SAYA - Laporan yang Dibuat Sendiri (Kepala Ruangan)
     | =================================================================*/

    /**
     * Tampilkan daftar laporan yang dikirim oleh pengguna saat ini sebagai pelapor.
     *
     * Digunakan oleh Kepala Ruangan yang juga dapat bertindak sebagai pelapor
     * seperti halnya Nakes. Berbeda dengan laporan.index (yang untuk Karu
     * menampilkan seluruh laporan unit), method ini SELALU mem-filter berdasarkan
     * pelapor_id sehingga hanya laporan milik sendiri yang tampil.
     */
    public function riwayatSaya(Request $permintaan): View
    {
        $pengguna = Auth::user();

        $query = Insiden::with(['detailPasien', 'unitKerja'])
            ->where('tenant_id', $pengguna->tenant_id)
            ->where('pelapor_id', $pengguna->id)
            ->bukanDraf()
            ->latest('tgl_lapor');

        if ($cari = $permintaan->input('cari')) {
            $query->where(function ($q) use ($cari) {
                $q->where('nomor_laporan', 'ilike', "%{$cari}%")
                  ->orWhereHas('detailPasien', fn ($dp) => $dp->where('nama_pasien', 'ilike', "%{$cari}%"));
            });
        }

        if ($status = $permintaan->input('status')) {
            $query->where('status_saat_ini', $status);
        }

        if ($tipe = $permintaan->input('tipe')) {
            $query->where('tipe_insiden', $tipe);
        }

        $daftarLaporan = $query->paginate(Paginasi::perHalaman())->withQueryString();

        $baseQuery = Insiden::where('tenant_id', $pengguna->tenant_id)
            ->where('pelapor_id', $pengguna->id)
            ->bukanDraf();

        $statistik = [
            'total'           => (clone $baseQuery)->count(),
            'kasus_baru'      => (clone $baseQuery)->where('status_saat_ini', 'kasus_baru')->count(),
            'sedang_diproses' => (clone $baseQuery)->whereIn('status_saat_ini', ['investigasi', 'tindak_lanjut'])->count(),
            'selesai'         => (clone $baseQuery)->where('status_saat_ini', 'selesai')->count(),
        ];

        return view('laporan.index', compact('daftarLaporan', 'statistik', 'pengguna'));
    }

    /* ==================================================================
     | EDIT - Form Edit Draf (Nakes melanjutkan pengisian)
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

        $namaUnitDraf = $this->normalizeUnitKerjaName($insiden->nama_unit_kerja);
        if ($namaUnitDraf !== null) {
            $insiden->setAttribute('nama_unit_kerja', $namaUnitDraf);
        }

        $tenantId = $pengguna->tenant_id;

        $masterJenisKesalahan  = $this->jenisKesalahanService->getAktifUntukForm($tenantId);
        $masterTipeCedera      = $this->tipeCederaService->getAktifUntukForm($tenantId);
        $masterFaktorPenyebab  = $this->faktorPenyebabService->getAktifUntukForm($tenantId);
        $masterIntervensi      = $this->intervensiService->getAktifUntukForm($tenantId);
        $daftarUnitKerja       = $this->daftarUnitKerjaUntukForm($tenantId);

        return view('laporan.edit', compact(
            'insiden',
            'pengguna',
            'masterJenisKesalahan',
            'masterTipeCedera',
            'masterFaktorPenyebab',
            'masterIntervensi',
            'daftarUnitKerja',
        ));
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function daftarUnitKerjaUntukForm(int $tenantId)
    {
        return UnitKerja::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderBy('nama_unit')
            ->pluck('nama_unit')
            ->values();
    }

    /* ==================================================================
     | TAMPIL - Detail Laporan + Histori Tindak Lanjut
     | =================================================================*/

    /**
     * Tampilkan detail satu laporan insiden.
     *
     * Memuat relasi detailPasien, pelapor, unitKerja, dan tindakLanjut
     * agar view dapat menampilkan data lengkap termasuk histori tindak lanjut.
     */
    public function tampil(Request $permintaan, string $laporan): View|RedirectResponse
    {
        /** @var \App\Models\Pengguna $pengguna */
        $pengguna = Auth::user();

        // 1. Cek apakah dengan peran aktif saat ini, pengguna bisa mengakses laporan
        $bisaAkses = Insiden::where('id', $laporan)->untukPeran($pengguna)->exists();

        if (! $bisaAkses) {
            // 2. Jika tidak bisa, periksa apakah pengguna punya peran lain yang bisa mengakses
            // (Kasus: Notifikasi masuk untuk peran Kepala Ruangan, tapi user sedang aktif sebagai Nakes)
            if ($pengguna->bisaGantiPeran()) {
                $peranAwal = $pengguna->peranAktif();
                $peranTersedia = $pengguna->peranYangDapatDipilih();

                foreach ($peranTersedia as $peranCek) {
                    if ($peranCek === $peranAwal) continue;
                    
                    $pengguna->setPeranAktif($peranCek);
                    if (Insiden::where('id', $laporan)->untukPeran($pengguna)->exists()) {
                        return redirect()->route('laporan.tampil', $laporan)
                            ->with('sukses', "Sistem otomatis menyesuaikan peran Anda menjadi {$peranCek} untuk mengakses laporan ini.");
                    }
                }
                
                // Kembalikan ke peran awal jika ternyata tidak ada satupun yang punya akses
                $pengguna->setPeranAktif($peranAwal);
            }

            // Laporan tidak ada atau memang dilarang
            abort(404);
        }

        // 3. Muat data lengkap karena sudah dipastikan punya akses
        $insiden = Insiden::with([
                'detailPasien',
                'pelapor',
                'unitKerja',
                // Muat pengguna beserta peran & unit kerjanya agar labelPeranDanUnit()
                // dapat menggunakan collection tanpa query N+1.
                'tindakLanjut.pengguna.peran',
                'tindakLanjut.pengguna.unitKerja',
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
     | TINDAK LANJUT - Ubah Status + Catatan (Karu & Komite Only)
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

        // Otorisasi via Policy - menolak Direktur & Nakes.
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

        // Dispatch event - listener akan mengirim notifikasi sesuai state-routing.
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
     | CETAK PDF - Stream PDF Laporan ke Browser
     | =================================================================*/

    /**
     * Generate dan stream PDF laporan insiden ke browser (tab baru).
     *
     * Hanya dapat diakses untuk laporan yang bukan DRAF.
     */
    public function cetakPdf(Request $request, string $laporan): Response
    {
        $margin = [
            'top'    => (float) $request->query('mt', 0.5),
            'right'  => (float) $request->query('mr', 1.0),
            'bottom' => (float) $request->query('mb', 0.5),
            'left'   => (float) $request->query('ml', 1.0),
        ];

        // Skala cetak (%) - opsional via query string: ?s=90  (default 100)
        $scale = (int) $request->query('s', 100);
        $scale = max(50, min(150, $scale)); // clamp 50–150 %

        $pdf = $this->laporanService->generatePdfReport((int) $laporan, $margin, $scale);

        $insiden = $this->insidenRepository->getInsidenForPdf((int) $laporan);

        $namaFile = 'Laporan-Insiden-' . str_replace('/', '-', $insiden->nomor_laporan) . '.pdf';

        return $pdf->stream($namaFile);
    }

    /* ==================================================================
     | ESKALASI KE DIREKTUR - Toggle Eskalasi (Komite Only)
     | =================================================================*/

    /**
     * Toggle status eskalasi laporan ke Direktur.
     *
     * Dilindungi oleh InsidenPolicy@eskalasi - hanya Komite yang diizinkan.
     */
    public function eskalasi(Request $permintaan, string $laporan): RedirectResponse
    {
        $insiden = Insiden::findOrFail($laporan);

        $this->authorize('eskalasi', $insiden);

        $aktifkan = ! $insiden->is_eskalasi_direktur;

        $insiden->update(['is_eskalasi_direktur' => $aktifkan]);

        // Kirim notifikasi ke semua Direktur aktif di tenant saat laporan dieskalasikan.
        if ($aktifkan) {
            $direktur = Pengguna::where('tenant_id', $insiden->tenant_id)
                ->where('is_aktif', true)
                ->whereHas('peran', fn ($q) => $q->where('nama_peran', Peran::DIREKTUR))
                ->get();

            if ($direktur->isNotEmpty()) {
                Notification::send($direktur, new InsidenNotifikasi(
                    insiden:   $insiden,
                    judul:     'Eskalasi - Butuh Arahan Eksekutif',
                    pesan:     "Laporan insiden {$insiden->nomor_laporan} ({$insiden->labelTipeInsiden()}) membutuhkan arahan/kebijakan eksekutif Direktur. Komite telah mengeskalasikan laporan ini kepada Anda.",
                    tipe:      'tindakan',
                    ikonWarna: 'bg-amber-50 text-amber-500',
                ));
            }
        }

        $pesan = $aktifkan
            ? 'Laporan berhasil dieskalasikan ke Direktur.'
            : 'Eskalasi ke Direktur telah dibatalkan.';

        return back()->with('sukses', $pesan);
    }

    /* ==================================================================
     | SOLUSI DIREKTUR - Arahan / Feedback Eksekutif (Direktur Only)
     | =================================================================*/

    /**
     * Simpan arahan/solusi eksekutif dari Direktur.
     *
     * Dilindungi oleh InsidenPolicy@solusiDirektur - hanya Direktur yang
     * diizinkan, dan HANYA jika laporan sudah dieskalasikan oleh Komite.
     */
    public function solusiDirektur(Request $permintaan, string $laporan): RedirectResponse
    {
        $insiden = Insiden::findOrFail($laporan);

        $this->authorize('solusiDirektur', $insiden);

        $data = $permintaan->validate([
            'solusi_direktur' => ['required', 'string', 'min:10', 'max:5000'],
        ], [
            'solusi_direktur.required' => 'Arahan/solusi Direktur wajib diisi.',
            'solusi_direktur.min'      => 'Arahan/solusi minimal 10 karakter.',
            'solusi_direktur.max'      => 'Arahan/solusi maksimal 5000 karakter.',
        ]);

        $insiden->update([
            'solusi_direktur'       => $data['solusi_direktur'],
            'waktu_solusi_direktur' => now(),
        ]);

        return back()->with('sukses', 'Arahan Direktur berhasil disimpan.');
    }
}
