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
            while (($line = fgets($file)) !== false) {
                $baris++;

                if ($baris <= 1) {
                    continue;
                }

                // Deteksi otomatis pembatas (Koma atau Titik Koma)
                $delimiter = str_contains($line, ';') ? ';' : ',';
                $data = str_getcsv($line, $delimiter);

                // PEMUTAKHIRAN INDEKS SESUAI GAMBAR TABEL KAMU
                $namaLengkap = isset($data[0]) ? trim($data[0]) : ''; // Kolom A
                $usernameRaw = isset($data[1]) ? trim($data[1]) : ''; // Kolom B
                $unitKerja   = isset($data[2]) ? trim($data[2]) : ''; // Kolom C
                $peran       = isset($data[3]) ? trim($data[3]) : ''; // Kolom D

                // Jika nama lengkap kosong atau berisi baris rusak, abaikan
                if (empty($namaLengkap) || $namaLengkap === '-' || trim($namaLengkap) === '') {
                    continue;
                }

                // Logika pembuatan Username otomatis jika kolom B kosong (seperti data Ruang VK)
                if (empty($usernameRaw) || $usernameRaw === '-' || trim($usernameRaw) === '') {
                    // Mengubah "Tania Cantika, A. Md. Keb" menjadi "tania.cantika"
                    $cleanName = preg_replace('/[^a-zA-Z\s]/', '', $namaLengkap);
                    $username = Str::slug(trim($cleanName), '.');
                } else {
                    $username = strtolower(trim($usernameRaw));
                }

                // Kata sandi default instansiasi awal: password
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

                // Kueri Relasi Unit Kerja
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

                // Suntik data ke database PostgreSQL
                DB::table('akun.pengguna')->insert([
                    'nama_lengkap'         => $namaLengkap,
                    'username'             => $username,
                    'master_unit_kerja_id' => $masterUnitKerjaId,
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