<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * LaporanController — menangani halaman-halaman terkait laporan insiden.
 *
 * Saat ini menampilkan view statis dengan data dummy.
 * Nanti akan diintegrasikan dengan model PelaporanInsiden setelah
 * fitur CRUD selesai dibuat sepenuhnya.
 */
class LaporanController extends Controller
{
    /**
     * Tampilkan halaman riwayat / daftar laporan insiden.
     */
    public function index(Request $permintaan): View
    {
        // TODO: Ganti dengan query Eloquent ke tabel pelaporan.insiden
        // $daftarLaporan = PelaporanInsiden::latest()->paginate(15);

        return view('laporan.index');
    }

    /**
     * Tampilkan formulir pembuatan laporan insiden baru.
     */
    public function buat(Request $permintaan): View
    {
        // TODO: Siapkan data master (kategori, unit kerja, dll.) untuk formulir
        return view('laporan.buat');
    }

    /**
     * Tampilkan detail satu laporan insiden.
     */
    public function tampil(Request $permintaan, string $laporan): View
    {
        // TODO: Ambil data laporan dari database berdasarkan ID
        // $dataLaporan = PelaporanInsiden::findOrFail($laporan);

        return view('laporan.tampil', [
            'idLaporan' => $laporan,
        ]);
    }
}
