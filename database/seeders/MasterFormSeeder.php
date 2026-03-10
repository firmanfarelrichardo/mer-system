<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organisasi;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * MasterFormSeeder — Seed data awal untuk 4 master formulir:
 *   1. Jenis Kesalahan (Medication Error Types)
 *   2. Tipe Cedera (Injuries)
 *   3. Faktor Penyebab (Contributing Factors)
 *   4. Tindakan Intervensi (Patient Interventions)
 *
 * Idempotent: menggunakan firstOrCreate berdasarkan nama per tenant.
 */
class MasterFormSeeder extends Seeder
{
    use WithoutModelEvents;

    /* ------------------------------------------------------------------
     | DATA: Jenis Kesalahan / Medication Error Types
     | ----------------------------------------------------------------*/

    private const JENIS_KESALAHAN = [
        'Salah Pasien',
        'Salah Obat',
        'Salah Dosis',
        'Salah Frekuensi/Interval',
        'Salah Formula',
        'Salah Rute',
        'Salah Nomor',
        'Salah Label',
        'Kontraindikasi',
        'Salah Penyimpanan',
        'Obat Terlewat/Tidak Diberikan',
        'Obat Kadaluarsa',
        'Reaksi Obat Merugikan (ROM)',
    ];

    /* ------------------------------------------------------------------
     | DATA: Tipe Cedera / Injuries
     | ----------------------------------------------------------------*/

    private const TIPE_CEDERA = [
        'Tidak Ada Cedera',
        'Luka Bakar',
        'Edema',
        'Hipoksia',
        'Kegagalan Jalur IV',
        'Blister (Gelembung Berisi Cairan di Kulit)',
        'Perubahan Kesadaran',
        'Hematoma',
        'Nyeri',
        'Mual',
        'Kehilangan Darah',
        'Meninggal',
        'Gatal-gatal',
        'Infiltrasi/Ekstravasasi',
        'Perubahan Nilai Lab Signifikan',
        'Perubahan TTV',
    ];

    /* ------------------------------------------------------------------
     | DATA: Faktor Penyebab / Contributing Factors
     | ----------------------------------------------------------------*/

    private const FAKTOR_PENYEBAB = [
        'Alergi Obat Tidak Tercatat',
        'Kesalahan Perhitungan Dosis',
        'Tidak Mengikuti SPO/Prosedur',
        'Tidak Baca Label Etiket Obat',
        'Resep Obat Tidak Terbaca',
        'Monitoring Tidak Adekuat',
        'Defisit Pengetahuan',
        'Label Etiket Obat Tidak Terbaca',
        'Tidak Diresepkan',
        'Masalah Stok',
        'Distribusi Obat',
        'Salah Baca/Interpretasi Resep',
        'Alat Kesehatan Rusak/Tidak Layak Pakai',
        'Ada Distraksi (Manusia)',
    ];

    /* ------------------------------------------------------------------
     | DATA: Tindakan Intervensi / Patient Interventions
     | ----------------------------------------------------------------*/

    private const TINDAKAN_INTERVENSI = [
        'Transfusi Darah',
        'Konsultasi ke DPJP',
        'Konsultasi ke Apoteker',
        'Intubasi',
        'Dibawa ke IGD',
        'Lama Rawat Bertambah',
        'Melakukan Uji Lab Tambahan',
        'Kunjungan Tambahan',
        'Tingkatkan Monitoring',
        'Dibawa ke OK',
        'Memerlukan Pengobatan Tambahan',
        'Prosedur Tambahan Dilakukan',
        'Dilakukan Rawat Inap',
        'Transfer ke ICU',
    ];

    /**
     * Seed the database.
     */
    public function run(): void
    {
        // Ambil tenant pertama (default). Sesuaikan jika multi-tenant.
        $tenant = Organisasi::first();

        if (! $tenant) {
            $this->command->warn('Tidak ada organisasi ditemukan. Jalankan OrganisasiSeeder terlebih dahulu.');
            return;
        }

        $tenantId = $tenant->id;
        $now      = now();

        DB::transaction(function () use ($tenantId, $now): void {
            // --- Jenis Kesalahan ---
            foreach (self::JENIS_KESALAHAN as $nama) {
                DB::table('master.jenis_kesalahan')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'nama' => $nama],
                    ['is_aktif' => true, 'created_at' => $now, 'updated_at' => $now],
                );
            }
            $this->command->info('  Jenis Kesalahan: ' . count(self::JENIS_KESALAHAN) . ' data berhasil di-seed.');

            // --- Tipe Cedera ---
            foreach (self::TIPE_CEDERA as $nama) {
                DB::table('master.tipe_cedera')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'nama' => $nama],
                    ['is_aktif' => true, 'created_at' => $now, 'updated_at' => $now],
                );
            }
            $this->command->info('  Tipe Cedera: ' . count(self::TIPE_CEDERA) . ' data berhasil di-seed.');

            // --- Faktor Penyebab ---
            foreach (self::FAKTOR_PENYEBAB as $nama) {
                DB::table('master.faktor_penyebab')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'nama' => $nama],
                    ['is_aktif' => true, 'created_at' => $now, 'updated_at' => $now],
                );
            }
            $this->command->info('  Faktor Penyebab: ' . count(self::FAKTOR_PENYEBAB) . ' data berhasil di-seed.');

            // --- Tindakan Intervensi ---
            foreach (self::TINDAKAN_INTERVENSI as $nama) {
                DB::table('master.tindakan_intervensi')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'nama' => $nama],
                    ['is_aktif' => true, 'created_at' => $now, 'updated_at' => $now],
                );
            }
            $this->command->info('  Tindakan Intervensi: ' . count(self::TINDAKAN_INTERVENSI) . ' data berhasil di-seed.');
        });
    }
}
