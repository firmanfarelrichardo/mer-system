<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SimpanLaporanRequest;
use App\Models\DetailPasien;
use App\Models\Insiden;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * LaporanController — menangani CRUD laporan insiden medication error.
 *
 * Fitur:
 *   - Riwayat laporan dengan pagination, filter, & pencarian
 *   - Formulir multi-step untuk membuat laporan baru
 *   - Simpan laporan ke database (pelaporan.insiden + pelaporan.detail_pasien)
 *   - Tandai laporan sudah dibaca
 *   - Tampilkan detail laporan
 */
class LaporanController extends Controller
{
    /**
     * Tampilkan halaman riwayat / daftar laporan insiden.
     *
     * Mendukung:
     *   - Pencarian berdasarkan nama pasien / nomor laporan
     *   - Filter berdasarkan status & tipe insiden
     *   - Pagination (10 per halaman)
     */
    public function index(Request $permintaan): View
    {
        $pengguna = Auth::user();

        $query = Insiden::with('detailPasien')
            ->where('tenant_id', $pengguna->tenant_id)
            ->latest('tgl_lapor');

        // Perawat hanya melihat laporan mereka sendiri.
        if ($pengguna->memilikiPeran('Perawat') && ! $pengguna->memilikiPeran('Kepala Ruangan') && ! $pengguna->memilikiPeran('Komite') && ! $pengguna->memilikiPeran('Admin')) {
            $query->where('pelapor_id', $pengguna->id);
        }

        // Filter: pencarian.
        if ($cari = $permintaan->input('cari')) {
            $query->where(function ($q) use ($cari) {
                $q->where('nomor_laporan', 'ilike', "%{$cari}%")
                  ->orWhereHas('detailPasien', fn ($dp) => $dp->where('nama_pasien', 'ilike', "%{$cari}%"));
            });
        }

        // Filter: status.
        if ($status = $permintaan->input('status')) {
            $query->where('status_saat_ini', $status);
        }

        // Filter: tipe insiden.
        if ($tipe = $permintaan->input('tipe')) {
            $query->where('tipe_insiden', $tipe);
        }

        $daftarLaporan = $query->paginate(10)->withQueryString();

        // Statistik ringkasan.
        $baseQuery = Insiden::where('tenant_id', $pengguna->tenant_id);
        if ($pengguna->memilikiPeran('Perawat') && ! $pengguna->memilikiPeran('Kepala Ruangan') && ! $pengguna->memilikiPeran('Komite') && ! $pengguna->memilikiPeran('Admin')) {
            $baseQuery->where('pelapor_id', $pengguna->id);
        }

        $statistik = [
            'total'          => (clone $baseQuery)->count(),
            'kasus_baru'     => (clone $baseQuery)->where('status_saat_ini', 'kasus_baru')->count(),
            'sedang_diproses' => (clone $baseQuery)->whereIn('status_saat_ini', ['investigasi', 'tindak_lanjut'])->count(),
            'selesai'        => (clone $baseQuery)->where('status_saat_ini', 'selesai')->count(),
        ];

        return view('laporan.index', [
            'daftarLaporan' => $daftarLaporan,
            'statistik'     => $statistik,
            'pengguna'      => $pengguna,
        ]);
    }

    /**
     * Tampilkan formulir pembuatan laporan insiden baru.
     */
    public function buat(Request $permintaan): View
    {
        return view('laporan.buat');
    }

    /**
     * Simpan laporan insiden baru ke database.
     *
     * Menerima data dari formulir 4-tahap wizard, menyimpan ke
     * tabel pelaporan.insiden dan pelaporan.detail_pasien dalam transaksi.
     */
    public function simpan(SimpanLaporanRequest $permintaan): RedirectResponse
    {
        $data     = $permintaan->validated();
        $pengguna = Auth::user();

        $isAnonim = empty($data['nama_pelapor']);

        $insiden = DB::transaction(function () use ($data, $pengguna, $isAnonim) {
            // Buat record insiden.
            $insiden = Insiden::create([
                'tenant_id'       => $pengguna->tenant_id,
                'nomor_laporan'   => Insiden::generateNomorLaporan($pengguna->tenant_id),
                'pelapor_id'      => $pengguna->id,
                'unit_id'         => null,
                'nama_unit_kerja' => $data['unit_kerja'],
                'tipe_insiden'    => $data['jenis_insiden'],
                'fase_kesalahan'  => $data['fase_kesalahan'],
                'status_saat_ini' => 'kasus_baru',
                'tgl_kejadian'    => $data['tanggal_kejadian'] . ' ' . $data['waktu_kejadian'] . ':00',
                'tgl_lapor'       => now(),
                'nama_pelapor'    => $data['nama_pelapor'] ?? null,
                'kontak_pelapor'  => $data['kontak_pelapor'] ?? null,
                'is_anonim'       => $isAnonim,
                'sudah_dibaca'    => false,
            ]);

            // Gabungkan array checkbox dengan nilai "lainnya".
            $jenisKesalahan   = $data['jenis_kesalahan'] ?? [];
            $cedera           = $data['cedera'] ?? [];
            $faktorPenyebab   = $data['faktor_penyebab'] ?? [];
            $intervensiPasien = $data['intervensi_pasien'] ?? [];

            if (! empty($data['jenis_kesalahan_lainnya'])) {
                $jenisKesalahan[] = $data['jenis_kesalahan_lainnya'];
            }
            if (! empty($data['cedera_lainnya'])) {
                $cedera[] = $data['cedera_lainnya'];
            }
            if (! empty($data['faktor_penyebab_lainnya'])) {
                $faktorPenyebab[] = $data['faktor_penyebab_lainnya'];
            }
            if (! empty($data['intervensi_pasien_lainnya'])) {
                $intervensiPasien[] = $data['intervensi_pasien_lainnya'];
            }

            // Buat record detail pasien.
            DetailPasien::create([
                'insiden_id'           => $insiden->id,
                'nama_pasien'          => $data['nama_pasien'],
                'nomor_rekam_medis'    => $data['nomor_rekam_medis'],
                'obat_terkait'         => $data['nama_obat'],
                'kronologi'            => $data['kronologi_kejadian'],
                'jenis_kesalahan'      => $jenisKesalahan,
                'cedera'               => $cedera,
                'faktor_penyebab'      => $faktorPenyebab,
                'intervensi_pasien'    => $intervensiPasien,
                'pernyataan_kronologi' => true,
            ]);

            return $insiden;
        });

        return redirect()
            ->route('laporan.index')
            ->with('sukses', "Laporan insiden {$insiden->nomor_laporan} berhasil dikirim.");
    }

    /**
     * Tandai laporan sebagai sudah dibaca.
     */
    public function tandaiDibaca(Request $permintaan, string $laporan): RedirectResponse
    {
        $insiden = Insiden::findOrFail($laporan);
        $insiden->update(['sudah_dibaca' => true]);

        return back()->with('sukses', 'Laporan ditandai sudah dibaca.');
    }

    /**
     * Tampilkan detail satu laporan insiden.
     */
    public function tampil(Request $permintaan, string $laporan): View
    {
        $insiden = Insiden::with('detailPasien', 'pelapor')
            ->findOrFail($laporan);

        return view('laporan.tampil', [
            'insiden' => $insiden,
        ]);
    }
}
