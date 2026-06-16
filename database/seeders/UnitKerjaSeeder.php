<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organisasi;
use Database\Seeders\Concerns\NormalizesUnitKerjaNames;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * UnitKerjaSeeder — Seed 31 unit kerja rumah sakit.
 *
 * Sumber data: Referensi struktur organisasi RS.
 * Idempotent: menggunakan firstOrCreate berdasarkan kode_unit per tenant.
 */
class UnitKerjaSeeder extends Seeder
{
    use WithoutModelEvents;
    use NormalizesUnitKerjaNames;

    /**
     * Daftar unit kerja rumah sakit.
     * Format: [kode_unit, nama_unit, keterangan]
     */
    private const UNIT_DATA = [
        // ── Poliklinik (Rawat Jalan) ────────────────────────────────
        ['POLI-ANAK',     'Poliklinik Anak',                 'Layanan rawat jalan pediatri'],
        ['POLI-KULIT',    'Poliklinik Kulit Kelamin',         'Layanan rawat jalan dermatologi & venereologi'],
        ['POLI-SARAF',    'Poliklinik Saraf',                 'Layanan rawat jalan neurologi'],
        ['POLI-PD',       'Poliklinik Penyakit Dalam',        'Layanan rawat jalan internis'],
        ['POLI-GIGI',     'Poliklinik Gigi',                  'Layanan rawat jalan gigi & mulut'],
        ['POLI-MATA',     'Poliklinik Mata',                  'Layanan rawat jalan oftalmologi'],
        ['POLI-THT',      'Poliklinik THT',                   'Layanan rawat jalan THT-KL'],
        ['POLI-KBD',      'Poliklinik Kebidanan',             'Layanan rawat jalan obstetri & ginekologi'],
        ['POLI-BEDAH',    'Poliklinik Bedah',                 'Layanan rawat jalan bedah umum'],
        ['POLI-PARU',     'Poliklinik Paru',                  'Layanan rawat jalan pulmonologi'],
        ['POLI-TKA',      'Poliklinik Tumbuh Kembang Anak',   'Layanan rawat jalan tumbuh kembang pediatri'],
        ['POLI-ORTHO',    'Poliklinik Orthopedi',             'Layanan rawat jalan orthopedi & traumatologi'],
        ['POLI-ANEST',    'Poliklinik Anestesi',              'Layanan rawat jalan anestesiologi & terapi nyeri'],
        ['POLI-JIWA',     'Poliklinik Jiwa',                  'Layanan rawat jalan psikiatri'],

        // ── Ruang Rawat Inap ────────────────────────────────────────
        ['RW-SARAF',      'Ruang Saraf',                  'Rawat inap neurologi'],
        ['RW-ANAK',       'Ruang Anak',                   'Rawat inap pediatri'],
        ['RW-KBD',        'Ruang Kebidanan',              'Rawat inap obstetri & ginekologi'],
        ['RW-ANEST',      'Ruang Anestesi',               'Ruang rawat pasca-anestesi'],
        ['RW-BEDAH',      'Ruang Bedah',                  'Rawat inap bedah'],
        ['RW-PD',         'Ruang Penyakit Dalam',         'Rawat inap internis'],
        ['RW-VIP',        'VIP',                          'Rawat inap kelas VIP'],
        ['RW-NEONAT',     'Neonatus',                     'Ruang perawatan neonatus'],
        ['RW-PARU',       'Ruang Paru',                   'Rawat inap pulmonologi'],
        ['RW-PONEK',      'PONEK',                        'Pelayanan Obstetri Neonatal Emergensi Komprehensif'],
        ['RW-HD',         'Hemodialisa',                  'Ruang hemodialisis'],
        ['RW-VK',         'VK',                           'Kamar bersalin (Verlos Kamer)'],
        ['RW-ISOLASI-B',  'Isolasi B',                    'Ruang isolasi pasien infeksius'],

        // ── Instalasi & Unit Khusus ─────────────────────────────────
        ['IBS',           'Instalasi Bedah Sentral',       'Ruang operasi dan pemulihan'],
        ['FARMASI',       'Instalasi Farmasi',             'Dispensing dan manajemen obat'],
        ['ICU',           'ICU',                           'Intensive Care Unit'],
        ['IGD',           'IGD',                           'Instalasi Gawat Darurat 24 jam'],
    ];

    public function run(): void
    {
        $tenant = Organisasi::where('kode_organisasi', 'default')->firstOrFail();

        $this->command->info('  ▶ Seeding unit kerja (' . count(self::UNIT_DATA) . ' unit) ...');

        $created = 0;
        $updated = 0;
        foreach (self::UNIT_DATA as [$kode, $nama, $keterangan]) {
            $nama = $this->normalizeUnitKerjaName($nama) ?? $nama;

            $existing = DB::table('master.unit_kerja')
                ->where('tenant_id', $tenant->id)
                ->where('kode_unit', $kode)
                ->first();

            if ($existing) {
                if ($existing->nama_unit !== $nama || $existing->keterangan !== $keterangan) {
                    DB::table('master.unit_kerja')
                        ->where('id', $existing->id)
                        ->update([
                            'nama_unit' => $nama,
                            'keterangan' => $keterangan,
                            'updated_at' => now(),
                        ]);
                    $updated++;
                }
            } else {
                DB::table('master.unit_kerja')->insert([
                    'tenant_id'   => $tenant->id,
                    'kode_unit'   => $kode,
                    'nama_unit'   => $nama,
                    'keterangan'  => $keterangan,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
                $created++;
            }
        }

        $existing = count(self::UNIT_DATA) - $created - $updated;
        $this->command->info("  ✔ UnitKerjaSeeder selesai ({$created} baru, {$updated} diperbarui, {$existing} sudah sesuai).");
    }
}
