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
            ProductionPenggunaSeeder::class,  // 1. Akun pengguna nyata production
            ProductionInsidenSeeder::class,   // 2. Data insiden awal production
        ]);
    }
}
