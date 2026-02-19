<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organisasi;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Database\Seeder;

/**
 * PenggunaSeeder — Seeds test user accounts for every application role.
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │  CREDENTIAL REFERENCE — DEVELOPMENT / STAGING ONLY         │
 * │  Remove this seeder from production or guard it with        │
 * │  `if (app()->isLocal()) { ... }` inside DatabaseSeeder.     │
 * ├────────────────┬──────────────────────────────┬────────────┤
 * │ Role           │ Email                        │ Password   │
 * ├────────────────┼──────────────────────────────┼────────────┤
 * │ Admin          │ admin@mer.test                │ Admin123!  │
 * │ Direktur       │ direktur@mer.test             │ Admin123!  │
 * │ Komite         │ komite@mer.test               │ Admin123!  │
 * │ Kepala Ruangan │ kepala.ruangan@mer.test       │ Admin123!  │
 * │ Perawat        │ perawat@mer.test              │ Admin123!  │
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
        // Plain-text password — the Pengguna model's `hashed` cast on `kata_sandi`
        // will call Hash::make() automatically on Eloquent create/update.
        // Do NOT pre-hash here; that would cause a double-bcrypt.
        $devPassword = 'Admin123!';

        return [
            [
                'pengguna' => [
                    'tenant_id'   => $tenantId,
                    'nomor_induk' => 'ADM-001',
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
                    'nomor_induk' => 'DIR-001',
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
                    'nomor_induk' => 'KOM-001',
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
                    'nomor_induk' => 'KR-001',
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
                    'nomor_induk' => 'PRW-001',
                    'email'       => 'perawat@mer.test',
                    'nomor_hp'    => '08100000005',
                    'alamat'      => 'Jl. Perawat No. 1',
                    'nama_lengkap'=> 'Perawat IGD',
                    'kata_sandi'  => $devPassword,
                    'is_aktif'    => true,
                ],
                'peran' => Peran::PERAWAT,
            ],
        ];
    }

    public function run(): void
    {
        $tenant = Organisasi::where('kode_organisasi', 'default')->firstOrFail();

        foreach ($this->userDefinitions($tenant->id) as $definition) {
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
