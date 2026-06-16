<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ProductionSeeder — Menggabungkan semua seeder yang diperlukan
 * untuk inisialisasi lingkungan Production secara urut dan otomatis.
 *
 * Seeder ini memastikan fondasi database tecipta dengan benar sebelum
 * memasukkan data pengguna.
 *
 * Cara menjalankan:
 * php artisan db:seed --class=ProductionSeeder --force
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('=============================================');
        $this->command->warn('  MEMULAI INISIALISASI DATA PRODUCTION');
        $this->command->warn('=============================================');

        // Menjalankan seeder dalam urutan hierarki constraint yang benar
        $this->call([
            OrganisasiSeeder::class,           // 1. Fondasi Tenant (Wajib Pertama)
            UnitKerjaSeeder::class,            // 2. Daftar Ruangan/Unit medis
            MasterFormSeeder::class,           // 3. Data Dropdown Formulir Laporan (Tipe Cedera, dll)
            ProductionPenggunaSeeder::class,   // 4. Data Pengguna Rumah Sakit beserta Dosen Peneliti
        ]);

        $this->command->info('=============================================');
        $this->command->info('  SEMUA DATA PRODUCTION BERHASIL DI-SEED!');
        $this->command->info('=============================================');
    }
}
