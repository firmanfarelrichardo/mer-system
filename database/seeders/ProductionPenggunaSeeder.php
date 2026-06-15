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
        // Jalur file CSV di dalam container Docker
        $filePath = database_path('seeders/data/pengguna-staging.csv');

        if (!file_exists($filePath)) {
            $this->command->error("File CSV tidak ditemukan di: {$filePath}");
            return;
        }

        $this->command->info('🧹 Membersihkan database staging sebelum import...');

        // 1. CLEANSING DATABASE (Truncate dengan CASCADE untuk PostgreSQL)
        // RESTART IDENTITY digunakan untuk mengembalikan Auto-Increment ID ke angka 1
        DB::statement('TRUNCATE TABLE akun.pengguna RESTART IDENTITY CASCADE');

        $file = fopen($filePath, 'r');
        $baris = 0;
        $berhasil = 0;

        // Gunakan DB Transaction demi keamanan data mutlak
        DB::beginTransaction();

        try {
            while (($data = fgetcsv($file, 1000, ',')) !== false) {
                $baris++;

                // Lewati 4 baris pertama (baris kosong dan baris header kolom)
                if ($baris <= 4) {
                    continue;
                }

                // Sanitasi data dasar dari kolom CSV
                $namaLengkap = isset($data[1]) ? trim($data[1]) : '';
                $usernameRaw = isset($data[2]) ? trim($data[2]) : '';
                $unitKerja   = isset($data[3]) ? trim($data[3]) : '';
                $peran       = isset($data[4]) ? trim($data[4]) : '';

                // Jika nama lengkap kosong, lewati baris ini
                if (empty($namaLengkap)) {
                    continue;
                }

                // 2. LOGIKA USERNAME OTOMATIS (Jika di Excel kosong)
                if (empty($usernameRaw)) {
                    // Mengubah "Tania Cantika, A. Md. Keb" menjadi "tania.cantika"
                    $cleanName = preg_replace('/[^a-zA-Z\s]/', '', $namaLengkap);
                    $username = Str::slug(trim($cleanName), '.');
                } else {
                    $username = strtolower($usernameRaw);
                }

                // 3. ATURAN KATA SANDI DEFAULT SESUAI PERMINTAAN
                // Semua user tanpa terkecuali menggunakan kata sandi: "password"
                $passwordHashed = Hash::make('password');

                // 4. KUERI RELASI PERAN
                $peranId = null;
                if (!empty($peran)) {
                    $peranModel = Peran::where(DB::raw('lower(nama_peran)'), strtolower($peran))->first();
                    if (!$peranModel) {
                        $peranModel = Peran::create([
                            'nama_peran' => $peran,
                            'kode_peran' => strtoupper(Str::slug($peran, '_'))
                        ]);
                    }
                    $peranId = $peranModel->id;
                }

                // 5. KUERI RELASI UNIT KERJA
                $unitKerjaId = null;
                if (!empty($unitKerja)) {
                    $unitModel = UnitKerja::where(DB::raw('lower(nama_unit)'), strtolower($unitKerja))->first();
                    if (!$unitModel) {
                        $unitModel = UnitKerja::create(['nama_unit' => $unitKerja]);
                    }
                    $unitKerjaId = $unitModel->id;
                }

                // 6. INSERT DATA KE DATABASE
                Pengguna::create([
                    'nama_lengkap'      => $namaLengkap,
                    'username'          => $username,
                    'unit_kerja_id'     => $unitKerjaId,
                    'peran_id'          => $peranId,
                    'password'          => $passwordHashed,
                    'wajib_ganti_sandi' => true, // <<< KUNCI UTAMA INDIKATOR PAKSA UBAH SANDI
                    'is_aktif'          => true,
                ]);

                $berhasil++;
            }

            DB::commit();
            $this->command->info("🎉 Sukses! Database dibersihkan dan {$berhasil} pengguna baru berhasil di-seed.");

        } catch (Exception $e) {
            DB::rollBack();
            $this->command->error("❌ Terjadi kesalahan fatal pada baris ke-{$baris}: " . $e->getMessage());
        } finally {
            fclose($file);
        }
    }
}