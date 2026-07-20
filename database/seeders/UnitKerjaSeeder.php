<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organisasi;
use Database\Seeders\Concerns\NormalizesUnitKerjaNames;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * UnitKerjaSeeder - Seed unit kerja rumah sakit dari NormalizesUnitKerjaNames.
 *
 * Sumber data tunggal: Database\Seeders\Concerns\NormalizesUnitKerjaNames.
 * Idempotent: menggunakan firstOrCreate berdasarkan kode_unit per tenant.
 */
class UnitKerjaSeeder extends Seeder
{
    use WithoutModelEvents;
    use NormalizesUnitKerjaNames;

    public function run(): void
    {
        $tenant = Organisasi::where('kode_organisasi', 'default')->firstOrFail();

        $this->command->info('  ▶ Seeding unit kerja dari NormalizesUnitKerjaNames...');

        $result = $this->ensureCanonicalUnitKerja($tenant->id);
        $this->command->info("  ✔ UnitKerjaSeeder selesai ({$result['created']} baru, {$result['updated']} diperbarui, {$result['unchanged']} sudah sesuai).");
    }
}
