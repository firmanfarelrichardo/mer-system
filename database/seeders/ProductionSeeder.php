<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    /**
     * Entry point seeder khusus environment production.
     *
     * Dijalankan via: php artisan db:seed --class=ProductionSeeder --force
     *
     * Seeder ini HANYA memanggil data nyata untuk production.
     * Tidak ada data dummy, akun test, atau fixture development.
     *
     * Urutan pemanggilan mengikuti urutan dependency FK:
     *   1. Data master (organisasi, peran, unit kerja) → sudah ada via migration
     *      dan tidak membutuhkan seeder ulang jika sudah berjalan sebelumnya.
     *   2. Akun pengguna production (ProductionPenggunaSeeder)
     *   3. Data insiden awal production (ProductionInsidenSeeder)
     */
    public function run(): void
    {
        $this->call([
            OrganisasiSeeder::class,          // 1. Tenant — FK root untuk semua tabel
            PeranSeeder::class,               // 2. Hierarki peran (role)
            UnitKerjaSeeder::class,           // 3. Master unit kerja
            MasterFormSeeder::class,          // 4. Master data formulir (kategori, intervensi, dll)
            ProductionPenggunaSeeder::class,  // 5. Akun pengguna resmi production
            ProductionInsidenSeeder::class,   // 6. Data insiden awal production
        ]);
    }
}
