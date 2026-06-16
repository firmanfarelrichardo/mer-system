<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

trait NormalizesUnitKerjaNames
{
    /**
     * Daftar resmi unit kerja yang menjadi satu-satunya sumber nama unit seed.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private static function canonicalUnitKerjaData(): array
    {
        return [
            // Poliklinik
            ['POLI-ANAK', 'Poliklinik Anak', 'Layanan rawat jalan pediatri'],
            ['POLI-KULIT', 'Poliklinik Kulit Kelamin', 'Layanan rawat jalan dermatologi & venereologi'],
            ['POLI-SARAF', 'Poliklinik Saraf', 'Layanan rawat jalan neurologi'],
            ['POLI-PD', 'Poliklinik Penyakit Dalam', 'Layanan rawat jalan internis'],
            ['POLI-GIGI', 'Poliklinik Gigi', 'Layanan rawat jalan gigi & mulut'],
            ['POLI-MATA', 'Poliklinik Mata', 'Layanan rawat jalan oftalmologi'],
            ['POLI-THT', 'Poliklinik THT', 'Layanan rawat jalan THT-KL'],
            ['POLI-KBD', 'Poliklinik Kebidanan', 'Layanan rawat jalan obstetri & ginekologi'],
            ['POLI-BEDAH', 'Poliklinik Bedah', 'Layanan rawat jalan bedah umum'],
            ['POLI-PARU', 'Poliklinik Paru', 'Layanan rawat jalan pulmonologi'],
            ['POLI-TKA', 'Poliklinik Tumbuh Kembang Anak', 'Layanan rawat jalan tumbuh kembang pediatri'],
            ['POLI-ORTHO', 'Poliklinik Orthopedi', 'Layanan rawat jalan orthopedi & traumatologi'],
            ['POLI-ANEST', 'Poliklinik Anestesi', 'Layanan rawat jalan anestesiologi & terapi nyeri'],
            ['POLI-JIWA', 'Poliklinik Jiwa', 'Layanan rawat jalan psikiatri'],

            // Rawat inap dan unit layanan
            ['RW-SARAF', 'Ruang Saraf', 'Rawat inap neurologi'],
            ['RW-ANAK', 'Ruang Anak', 'Rawat inap pediatri'],
            ['RW-KBD', 'Ruang Kebidanan', 'Rawat inap obstetri & ginekologi'],
            ['RW-ANEST', 'Ruang Anestesi', 'Ruang rawat pasca-anestesi'],
            ['RW-BEDAH', 'Ruang Bedah', 'Rawat inap bedah'],
            ['RW-PD', 'Ruang Penyakit Dalam', 'Rawat inap internis'],
            ['RW-PARU', 'Ruang Paru', 'Rawat inap pulmonologi'],
            ['RW-CESE-MANAGER', 'Ruang Cese Manager', 'Case manager pasien'],
            ['RW-VIP', 'VIP', 'Rawat inap kelas VIP'],
            ['RW-NEONAT', 'Neonatus', 'Ruang perawatan neonatus'],
            ['RW-PONEK', 'PONEK', 'Pelayanan Obstetri Neonatal Emergensi Komprehensif'],
            ['RW-HD', 'Hemodialisa', 'Ruang hemodialisis'],
            ['RW-VK', 'VK', 'Kamar bersalin (Verlos Kamer)'],
            ['RW-ISOLASI-B', 'Isolasi B', 'Ruang isolasi pasien infeksius'],

            // Instalasi dan unit khusus
            ['IBS', 'Instalasi Bedah Sentral', 'Ruang operasi dan pemulihan'],
            ['FARMASI', 'Instalasi Farmasi', 'Dispensing dan manajemen obat'],
            ['ICU', 'ICU', 'Intensive Care Unit'],
            ['IGD', 'IGD', 'Instalasi Gawat Darurat 24 jam'],
            ['DOKTER-UMUM', 'Dokter Umum', 'Dokter umum'],
        ];
    }

    /**
     * Seed/update daftar unit resmi dari canonicalUnitKerjaData().
     *
     * @return array{created: int, updated: int, unchanged: int}
     */
    private function ensureCanonicalUnitKerja(int $tenantId): array
    {
        $created = 0;
        $updated = 0;
        $unchanged = 0;

        foreach (self::canonicalUnitKerjaData() as [$kode, $nama, $keterangan]) {
            $nama = $this->normalizeUnitKerjaName($nama) ?? $nama;
            $existing = \Illuminate\Support\Facades\DB::table('master.unit_kerja')
                ->where('tenant_id', $tenantId)
                ->where('kode_unit', $kode)
                ->first();

            if ($existing) {
                if ($existing->nama_unit !== $nama || $existing->keterangan !== $keterangan || $existing->deleted_at !== null) {
                    \Illuminate\Support\Facades\DB::table('master.unit_kerja')
                        ->where('id', $existing->id)
                        ->update([
                            'nama_unit' => $nama,
                            'keterangan' => $keterangan,
                            'deleted_at' => null,
                            'updated_at' => now(),
                        ]);
                    $updated++;
                } else {
                    $unchanged++;
                }

                continue;
            }

            \Illuminate\Support\Facades\DB::table('master.unit_kerja')->insert([
                'tenant_id' => $tenantId,
                'kode_unit' => $kode,
                'nama_unit' => $nama,
                'keterangan' => $keterangan,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created++;
        }

        return compact('created', 'updated', 'unchanged');
    }

    /**
     * Normalisasi nama unit kerja dari sumber Excel/CSV yang bervariasi.
     *
     * Aturan utama:
     * - Poli/Poliklinik dengan layanan yang sama memakai "Poliklinik".
     * - Tanda ":" pada nama ruang diabaikan.
     * - PONEK, VK, VIP, Isolasi B, Neonatus, dan Hemodialisa tidak memakai awalan "Ruang".
     * - IBS memakai nama lengkap "Instalasi Bedah Sentral" tanpa singkatan.
     */
    private function normalizeUnitKerjaName(?string $namaUnit): ?string
    {
        if ($namaUnit === null) {
            return null;
        }

        $clean = trim((string) preg_replace('/\s+/', ' ', str_replace(':', ' ', $namaUnit)));
        $clean = rtrim($clean, ". \t\n\r\0\x0B");

        if ($clean === '' || $clean === '-') {
            return null;
        }

        return self::canonicalUnitKerjaMap()[$this->unitKerjaLookupKey($clean)] ?? $clean;
    }

    private function unitKerjaLookupKey(string $namaUnit): string
    {
        $key = strtolower($namaUnit);
        $key = str_replace([':', '.', ',', '(', ')'], ' ', $key);
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;

        return trim($key);
    }

    private function canonicalUnitKerjaCodeForName(string $namaUnit): ?string
    {
        $canonicalName = $this->normalizeUnitKerjaName($namaUnit);
        if ($canonicalName === null) {
            return null;
        }

        return self::canonicalUnitKerjaCodeMap()[$this->unitKerjaLookupKey($canonicalName)] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private static function canonicalUnitKerjaCodeMap(): array
    {
        $map = [];

        foreach (self::canonicalUnitKerjaData() as [$kode, $nama]) {
            $key = strtolower($nama);
            $key = str_replace([':', '.', ',', '(', ')'], ' ', $key);
            $key = preg_replace('/\s+/', ' ', $key) ?? $key;
            $map[trim($key)] = $kode;
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private static function canonicalUnitKerjaMap(): array
    {
        return [
            'poli anak' => 'Poliklinik Anak',
            'poliklinik anak' => 'Poliklinik Anak',
            'poli anestesi' => 'Poliklinik Anestesi',
            'poliklinik anestesi' => 'Poliklinik Anestesi',
            'poli bedah' => 'Poliklinik Bedah',
            'poliklinik bedah' => 'Poliklinik Bedah',
            'poli gigi' => 'Poliklinik Gigi',
            'poliklinik gigi' => 'Poliklinik Gigi',
            'poli jiwa' => 'Poliklinik Jiwa',
            'poliklinik jiwa' => 'Poliklinik Jiwa',
            'poli kebidanan' => 'Poliklinik Kebidanan',
            'poliklinik kebidanan' => 'Poliklinik Kebidanan',
            'poli kulit kelamin' => 'Poliklinik Kulit Kelamin',
            'poliklinik kulit kelamin' => 'Poliklinik Kulit Kelamin',
            'poli mata' => 'Poliklinik Mata',
            'poliklinik mata' => 'Poliklinik Mata',
            'poli orthopedi' => 'Poliklinik Orthopedi',
            'poliklinik orthopedi' => 'Poliklinik Orthopedi',
            'poli paru' => 'Poliklinik Paru',
            'poliklinik paru' => 'Poliklinik Paru',
            'poli penyakit dalam' => 'Poliklinik Penyakit Dalam',
            'poliklinik penyakit dalam' => 'Poliklinik Penyakit Dalam',
            'poli saraf' => 'Poliklinik Saraf',
            'poliklinik saraf' => 'Poliklinik Saraf',
            'poli tht' => 'Poliklinik THT',
            'poliklinik tht' => 'Poliklinik THT',
            'poli tumbuh kembang anak' => 'Poliklinik Tumbuh Kembang Anak',
            'poliklinik tumbuh kembang anak' => 'Poliklinik Tumbuh Kembang Anak',

            'ruang saraf' => 'Ruang Saraf',
            'ruang anak' => 'Ruang Anak',
            'ruang anestesi' => 'Ruang Anestesi',
            'ruang bedah' => 'Ruang Bedah',
            'ruang kebidanan' => 'Ruang Kebidanan',
            'ruang paru' => 'Ruang Paru',
            'ruang penyakit dalam' => 'Ruang Penyakit Dalam',
            'ruang cese manager' => 'Ruang Cese Manager',
            'cese manager' => 'Ruang Cese Manager',

            'ruang ponek' => 'PONEK',
            'ponek' => 'PONEK',
            'ruang vk' => 'VK',
            'vk' => 'VK',
            'ruang vip' => 'VIP',
            'vip' => 'VIP',
            'ruang isolasi b' => 'Isolasi B',
            'isolasi b' => 'Isolasi B',
            'ruang neonatus' => 'Neonatus',
            'neonatus' => 'Neonatus',
            'ruang hd' => 'Hemodialisa',
            'hd' => 'Hemodialisa',
            'hemodialisa' => 'Hemodialisa',

            'ibs' => 'Instalasi Bedah Sentral',
            'instalasi bedah sentral' => 'Instalasi Bedah Sentral',
            'instalasi bedah sentral ibs' => 'Instalasi Bedah Sentral',

            'instalasi farmasi' => 'Instalasi Farmasi',
            'icu' => 'ICU',
            'igd' => 'IGD',
            'dokter umum' => 'Dokter Umum',
        ];
    }
}
