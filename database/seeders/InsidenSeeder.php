<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Insiden;
use App\Models\Organisasi;
use App\Models\Pengguna;
use Illuminate\Database\Seeder;

/**
 * InsidenSeeder — Mengisi 100 data insiden tiruan yang realistis.
 *
 * Distribusi (sesuai InsidenFactory):
 *   Tipe   — KPC 20%, KNC 30%, KTC 25%, KTD 20%, SENTINEL 5%
 *   Status — Selesai 40%, Tindak Lanjut 25%, Investigasi 20%, Kasus Baru 15%
 *
 * Tujuan:
 *   - Mengisi halaman Statistik dengan data yang cukup untuk semua grafik.
 *   - Idempoten: aman dijalankan berulang (dengan truncate opsional).
 *
 * Penggunaan:
 *   php artisan db:seed --class=InsidenSeeder
 */
class InsidenSeeder extends Seeder
{
    /** Jumlah insiden yang akan dibuat. */
    private const JUMLAH_INSIDEN = 100;

    public function run(): void
    {
        $tenant = Organisasi::where('kode_organisasi', 'default')->firstOrFail();

        // Ambil pelapor valid (perawat/karu) milik tenant ini.
        // Jika tidak ada, biarkan pelapor_id NULL (kolom memang nullable).
        $pelaporIds = Pengguna::where('tenant_id', $tenant->id)
            ->whereHas('peran', fn ($q) => $q->whereIn('nama_peran', ['Perawat', 'Kepala Ruangan']))
            ->pluck('id')
            ->all();

        $this->command?->info("Membuat " . self::JUMLAH_INSIDEN . " insiden dummy ...");

        $tahun = now()->format('Y');

        // Buat dalam kelompok kecil agar nomor laporan unik & rapi.
        for ($i = 1; $i <= self::JUMLAH_INSIDEN; $i++) {
            $nomorLaporan = sprintf('INC-%s-DEMO-%03d', $tahun, $i);

            // Lewati jika sudah ada (idempoten).
            if (Insiden::where('nomor_laporan', $nomorLaporan)
                       ->where('tenant_id', $tenant->id)
                       ->exists()) {
                continue;
            }

            Insiden::factory()->create([
                'tenant_id'     => $tenant->id,
                'nomor_laporan' => $nomorLaporan,
                'pelapor_id'    => $pelaporIds ? $pelaporIds[array_rand($pelaporIds)] : null,
            ]);
        }

        $this->command?->info("✓ " . self::JUMLAH_INSIDEN . " insiden berhasil dibuat.");
    }
}
