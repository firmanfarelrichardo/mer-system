<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters - foreign-key constraints require tenants before
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
            UnitKerjaSeeder::class,      // 3. Unit kerja (required FK for pengguna.unit_id)
            PenggunaSeeder::class,       // 4. Users + pivot assignments
            MasterFormSeeder::class,     // 5. Master data formulir (cedera, faktor, intervensi)
            InsidenSeeder::class,        // 6. 500 sample incident reports (2024-2026)
            LogAktivitasSeeder::class,   // 7. Audit log entries (2024-2026)
            PenelitiSeeder::class,       // 8. Akun sementara peneliti/dosen pembimbing
            ProductionInsidenSeeder::class, // 9. Production incident reports
            ProductionPenggunaSeeder::class,  // 10. Production user accounts
        ]);
    }
}
