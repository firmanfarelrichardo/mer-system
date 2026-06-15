<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\UnitKerja;
use Exception;

class ProductionPenggunaSeeder extends Seeder
{
    /**
     * Jalankan database seeds.
     */
    public function run(): void
    {
        // Jalur file CSV di dalam container Docker Staging
        $filePath = database_path('seeders/data/pengguna-staging.csv');

        if (!file_exists($filePath)) {
            $this->command->error("❌ File CSV tidak ditemukan di: {$filePath}");
            return;
        }

        $this->command->info('🧹 Membersihkan database staging sebelum import...');

        // Membersihkan tabel pengguna secara aman dengan CASCADE di PostgreSQL
        DB::statement('TRUNCATE TABLE akun.pengguna RESTART IDENTITY CASCADE');

        // Mengambil tenant_id default dari organisasi yang sudah di-seed sebelumnya
        $tenantId = DB::table('tenant.organisasi')->value('id');
        if (!$tenantId) {
            $this->command->error("❌ Tenant ID tidak ditemukan. Jalankan OrganisasiSeeder terlebih dahulu.");
            return;
        }

        $file = fopen($filePath, 'r');
        $baris = 0;
        $berhasil = 0;

        DB::beginTransaction();

        try {
            while (($data = fgetcsv($file, 1000, ',')) !== false) {
                $baris++;

                // Lewati 4 baris pertama (baris kosong dan baris header kolom)
                if ($baris <= 4) {
                    continue;
                }

                // Ambil data dan bersihkan dari karakter non-UTF8 tersembunyi
                $namaLengkap = isset($data[1]) ? trim(mb_convert_encoding($data[1], 'UTF-8', 'UTF-8')) : '';
                $usernameRaw = isset($data[2]) ? trim(mb_convert_encoding($data[2], 'UTF-8', 'UTF-8')) : '';
                $unitKerja   = isset($data[3]) ? trim(mb_convert_encoding($data[3], 'UTF-8', 'UTF-8')) : '';
                $peran       = isset($data[4]) ? trim(mb_convert_encoding($data[4], 'UTF-8', 'UTF-8')) : '';

                // Jika nama kosong atau baris rusak, lewati
                if (empty($namaLengkap) || str_contains($namaLengkap, '')) {
                    continue;
                }

                // Generasi Username Otomatis
                if (empty($usernameRaw) || $usernameRaw === '-') {
                    $cleanName = preg_replace('/[^a-zA-Z\s]/', '', $namaLengkap);
                    $username = Str::slug(trim($cleanName), '.');
                } else {
                    $username = strtolower($usernameRaw);
                }

                // Kata sandi default sesuai instruksi pelaku industri
                $passwordHashed = Hash::make('password');

                // Kueri Relasi Peran
                $peranId = null;
                if (!empty($peran) && $peran !== '-') {
                    $peranModel = Peran::where(DB::raw('lower(nama_peran)'), strtolower($peran))->first();
                    if (!$peranModel) {
                        $peranModel = Peran::create([
                            'nama_peran' => $peran,
                            'kode_peran' => strtoupper(Str::slug($peran, '_'))
                        ]);
                    }
                    $peranId = $peranModel->id;
                }

                // Kueri Relasi Unit Kerja (Sesuai skema master.unit_kerja)
                $masterUnitKerjaId = null;
                if (!empty($unitKerja) && $unitKerja !== '-') {
                    $unitModel = UnitKerja::where(DB::raw('lower(nama_unit)'), strtolower($unitKerja))->first();
                    if (!$unitModel) {
                        $unitModel = UnitKerja::create([
                            'tenant_id' => $tenantId,
                            'kode_unit' => strtoupper(Str::slug($unitKerja, '_')),
                            'nama_unit' => $unitKerja
                        ]);
                    }
                    $masterUnitKerjaId = $unitModel->id;
                }

                // Query Insert menggunakan kolom yang tepat: master_unit_kerja_id
                DB::table('akun.pengguna')->insert([
                    'nama_lengkap'         => $namaLengkap,
                    'username'             => $username,
                    'master_unit_kerja_id' => $masterUnitKerjaId, // <<< PEMBETULAN NAMA KOLOM
                    'peran_id'             => $peranId,
                    'password'             => $passwordHashed,
                    'wajib_ganti_sandi'    => true,
                    'is_aktif'             => true,
                    'created_at'           => now(),
                    'updated_at'           => now()
                ]);

                $berhasil++;
            }

            DB::commit();
            $this->command->info("🎉 Sukses! Database dibersihkan dan {$berhasil} pengguna baru berhasil dikonversi.");

        } catch (Exception $e) {
            DB::rollBack();
            $this->command->error("❌ Terjadi kesalahan fatal pada baris ke-{$baris}: " . $e->getMessage());
        } finally {
            fclose($file);
        }
    }
}