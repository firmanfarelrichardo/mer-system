<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organisasi;
use App\Models\Peran;
use Illuminate\Database\Seeder;

/**
 * PeranSeeder — Seeds all application roles for the default tenant.
 *
 * Role names are sourced from the Peran model constants so that
 * this seeder and the codebase never drift out of sync.
 */
class PeranSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Organisasi::where('kode_organisasi', 'default')->firstOrFail();

        $roles = [
            Peran::NAKES,
            Peran::KEPALA_RUANGAN,
            Peran::KOMITE,
            Peran::ADMIN,
            Peran::DIREKTUR,
        ];

        foreach ($roles as $namaPeran) {
            Peran::firstOrCreate(
                ['tenant_id' => $tenant->id, 'nama_peran' => $namaPeran],
            );
        }
    }
}
