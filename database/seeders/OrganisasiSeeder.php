<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organisasi;
use Illuminate\Database\Seeder;

/**
 * OrganisasiSeeder - Seeds the default tenant organisation.
 *
 * A tenant record is required before any user or role can be created
 * (foreign-key constraint on `akun.pengguna.tenant_id`).
 */
class OrganisasiSeeder extends Seeder
{
    public function run(): void
    {
        Organisasi::firstOrCreate(
            ['kode_organisasi' => 'default'],
            ['nama_organisasi' => 'Rumah Sakit Default'],
        );
    }
}
