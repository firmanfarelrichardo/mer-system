<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organisasi;
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

    /**
     * Daftar unit kerja rumah sakit.
     * Format: [kode_unit, nama_unit, keterangan]
     */
    private const UNIT_DATA = [
        // ── Poli (Rawat Jalan) ──────────────────────────────────────
        ['POLI-ANAK',     'Poli Anak',                   'Layanan rawat jalan pediatri'],
        ['POLI-KULIT',    'Poli Kulit Kelamin',           'Layanan rawat jalan dermatologi & venereologi'],
        ['POLI-SARAF',    'Poli Saraf',                   'Layanan rawat jalan neurologi'],
        ['POLI-PD',       'Poli Penyakit Dalam',          'Layanan rawat jalan internis'],
        ['POLI-GIGI',     'Poli Gigi',                    'Layanan rawat jalan gigi & mulut'],
        ['POLI-MATA',     'Poli Mata',                    'Layanan rawat jalan oftalmologi'],
        ['POLI-THT',      'Poli THT',                     'Layanan rawat jalan THT-KL'],
        ['POLI-KBD',      'Poli Kebidanan',               'Layanan rawat jalan obstetri & ginekologi'],
        ['POLI-BEDAH',    'Poli Bedah',                   'Layanan rawat jalan bedah umum'],
        ['POLI-PARU',     'Poli Paru',                    'Layanan rawat jalan pulmonologi'],
        ['POLI-TKA',      'Poli Tumbuh Kembang Anak',     'Layanan rawat jalan tumbuh kembang pediatri'],
        ['POLI-ORTHO',    'Poli Orthopedi',               'Layanan rawat jalan orthopedi & traumatologi'],
        ['POLI-ANEST',    'Poli Anestesi',                'Layanan rawat jalan anestesiologi & terapi nyeri'],
        ['POLI-JIWA',     'Poli Jiwa',                    'Layanan rawat jalan psikiatri'],

        // ── Ruang Rawat Inap ────────────────────────────────────────
        ['RW-SARAF',      'Ruang Saraf',                  'Rawat inap neurologi'],
        ['RW-ANAK',       'Ruang Anak',                   'Rawat inap pediatri'],
        ['RW-KBD',        'Ruang Kebidanan',              'Rawat inap obstetri & ginekologi'],
        ['RW-ANEST',      'Ruang Anestesi',               'Ruang rawat pasca-anestesi'],
        ['RW-BEDAH',      'Ruang Bedah',                  'Rawat inap bedah'],
        ['RW-PD',         'Ruang Penyakit Dalam',         'Rawat inap internis'],
        ['RW-VIP',        'Ruang VIP',                    'Rawat inap kelas VIP'],
        ['RW-NEONAT',     'Ruang Neonatus',               'Ruang perawatan neonatus'],
        ['RW-PARU',       'Ruang Paru',                   'Rawat inap pulmonologi'],
        ['RW-PONEK',      'Ruang PONEK',                  'Pelayanan Obstetri Neonatal Emergensi Komprehensif'],
        ['RW-HD',         'Ruang HD',                     'Ruang hemodialisis'],
        ['RW-VK',         'Ruang VK',                     'Kamar bersalin (Verlos Kamer)'],
        ['RW-ISOLASI-B',  'Ruang Isolasi B',              'Ruang isolasi pasien infeksius'],

        // ── Instalasi & Unit Khusus ─────────────────────────────────
        ['IBS',           'Instalasi Bedah Sentral (IBS)', 'Ruang operasi dan pemulihan'],
        ['FARMASI',       'Instalasi Farmasi',             'Dispensing dan manajemen obat'],
        ['ICU',           'ICU',                           'Intensive Care Unit'],
        ['IGD',           'IGD',                           'Instalasi Gawat Darurat 24 jam'],
    ];

    public function run(): void
    {
        $tenant = Organisasi::where('kode_organisasi', 'default')->firstOrFail();

        $this->command->info('  ▶ Seeding unit kerja (' . count(self::UNIT_DATA) . ' unit) ...');

        $created = 0;
        foreach (self::UNIT_DATA as [$kode, $nama, $keterangan]) {
            $existing = DB::table('master.unit_kerja')
                ->where('tenant_id', $tenant->id)
                ->where('kode_unit', $kode)
                ->first();

            if (! $existing) {
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

        $this->command->info("  ✔ UnitKerjaSeeder selesai ({$created} baru, " . (count(self::UNIT_DATA) - $created) . ' sudah ada).');
    }
}
