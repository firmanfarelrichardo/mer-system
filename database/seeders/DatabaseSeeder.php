<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters — foreign-key constraints require tenants before
     * roles, and roles before users. Unit kerja must exist before
     * incidents reference them.
     *
     * IMPORTANT: PenggunaSeeder creates dev-only test accounts.
     * Never run db:seed on production without excluding it.
     */
    public function run(): void
    {
        $this->call([
            OrganisasiSeeder::class,    // 1. Tenant (required FK for all below)
            PeranSeeder::class,          // 2. Roles  (required FK for user-role pivot)
            PenggunaSeeder::class,       // 3. Users + pivot assignments
            UnitKerjaSeeder::class,      // 4. 31 unit kerja rumah sakit
            InsidenSeeder::class,        // 5. 500 sample incident reports (2024-2026)
            LogAktivitasSeeder::class,   // 6. Audit log entries (2024-2026)
        ]);
    }
}
