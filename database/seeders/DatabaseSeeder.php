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
     * roles, and roles before users.
     *
     * IMPORTANT: PenggunaSeeder creates dev-only test accounts.
     * Never run db:seed on production without excluding it.
     */
    public function run(): void
    {
        $this->call([
            OrganisasiSeeder::class,  // 1. Tenant (required FK for all below)
            PeranSeeder::class,        // 2. Roles  (required FK for user-role pivot)
            PenggunaSeeder::class,     // 3. Users + pivot assignments
            InsidenSeeder::class,      // 4. 100 sample incident reports (2 years)
        ]);
    }
}
