<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organisasi;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Database\Seeder;

/**
 * PenggunaSeeder - Seeds test user accounts for every application role.
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │  CREDENTIAL REFERENCE - DEVELOPMENT / STAGING ONLY         │
 * │  Remove this seeder from production or guard it with        │
 * │  `if (app()->isLocal()) { ... }` inside DatabaseSeeder.     │
 * ├────────────────┬──────────────────────────────┬────────────┤
 * │ Role           │ Username (nomor_induk)       │ Password   │
 * ├────────────────┼──────────────────────────────┼────────────┤
 * │ Admin          │ admin                        │ password   │
 * │ Direktur       │ direktur                     │ password   │
 * │ Komite         │ komite                       │ password   │
 * │ Kepala Ruangan │ kepalaruangan                │ password   │
 * │ Nakes          │ nakes                        │ password   │
 * └────────────────┴──────────────────────────────┴────────────┘
 */
class PenggunaSeeder extends Seeder
{
    /**
     * User definitions: each entry becomes one Pengguna + one Peran assignment.
     * Using the Peran constants guarantees role names stay consistent.
     *
     * @return list<array<string, mixed>>
     */
    private function userDefinitions(int $tenantId): array
    {
        // Plain-text password - the Pengguna model's `hashed` cast on `kata_sandi`
        // will call Hash::make() automatically on Eloquent create/update.
        // Do NOT pre-hash here; that would cause a double-bcrypt.
        $devPassword = 'password';

        return [
            [
                'pengguna' => [
                    'tenant_id'   => $tenantId,
                    'nomor_induk' => 'admin',
                    'email'       => 'admin@mer.test',
                    'nomor_hp'    => '08100000001',
                    'alamat'      => 'Jl. Admin No. 1',
                    'nama_lengkap'=> 'Administrator Sistem',
                    'kata_sandi'  => $devPassword,
                    'is_aktif'    => true,
                ],
                'peran' => Peran::ADMIN,
            ],
            [
                'pengguna' => [
                    'tenant_id'   => $tenantId,
                    'nomor_induk' => 'direktur',
                    'email'       => 'direktur@mer.test',
                    'nomor_hp'    => '08100000002',
                    'alamat'      => 'Jl. Direktur No. 1',
                    'nama_lengkap'=> 'Direktur Rumah Sakit',
                    'kata_sandi'  => $devPassword,
                    'is_aktif'    => true,
                ],
                'peran' => Peran::DIREKTUR,
            ],
            [
                'pengguna' => [
                    'tenant_id'   => $tenantId,
                    'nomor_induk' => 'komite',
                    'email'       => 'komite@mer.test',
                    'nomor_hp'    => '08100000003',
                    'alamat'      => 'Jl. Komite No. 1',
                    'nama_lengkap'=> 'Anggota Komite',
                    'kata_sandi'  => $devPassword,
                    'is_aktif'    => true,
                ],
                'peran' => Peran::KOMITE,
            ],
            [
                'pengguna' => [
                    'tenant_id'   => $tenantId,
                    'nomor_induk' => 'kepalaruangan',
                    'email'       => 'kepala.ruangan@mer.test',
                    'nomor_hp'    => '08100000004',
                    'alamat'      => 'Jl. Kepala Ruangan No. 1',
                    'nama_lengkap'=> 'Kepala Ruangan IGD',
                    'kata_sandi'  => $devPassword,
                    'is_aktif'    => true,
                ],
                'peran' => Peran::KEPALA_RUANGAN,
            ],
            [
                'pengguna' => [
                    'tenant_id'   => $tenantId,
                    'nomor_induk' => 'nakes',
                    'email'       => 'nakes@mer.test',
                    'nomor_hp'    => '08100000005',
                    'alamat'      => 'Jl. Nakes No. 1',
                    'nama_lengkap'=> 'Tenaga Kesehatan IGD',
                    'kata_sandi'  => $devPassword,
                    'is_aktif'    => true,
                ],
                'peran' => Peran::NAKES,
            ],
        ];
    }

    public function run(): void
    {
        $tenant = Organisasi::where('kode_organisasi', 'default')->firstOrFail();

        // Cari unit IGD untuk test user Kepala Ruangan & Nakes.
        // Menggunakan query langsung agar tidak bergantung pada urutan seeder.
        $unitIgd = \Illuminate\Support\Facades\DB::table('master.unit_kerja')
            ->where('tenant_id', $tenant->id)
            ->where('kode_unit', 'IGD')
            ->first();

        foreach ($this->userDefinitions($tenant->id) as $definition) {
            // Tetapkan unit_id untuk Kepala Ruangan & Nakes ke IGD (jika tersedia).
            if ($unitIgd && in_array($definition['peran'], [Peran::KEPALA_RUANGAN, Peran::NAKES], true)) {
                $definition['pengguna']['unit_id'] = $unitIgd->id;
            }

            // firstOrCreate prevents duplicates on re-runs (idempotent)
            $pengguna = Pengguna::firstOrCreate(
                [
                    'tenant_id' => $definition['pengguna']['tenant_id'],
                    'email'     => $definition['pengguna']['email'],
                ],
                $definition['pengguna'],
            );

            $peran = Peran::where([
                'tenant_id'  => $tenant->id,
                'nama_peran' => $definition['peran'],
            ])->firstOrFail();

            // Sync prevents duplicate pivot rows on re-runs
            $pengguna->peran()->syncWithoutDetaching([$peran->id]);
        }
    }
}
