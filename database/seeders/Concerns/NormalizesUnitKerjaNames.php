<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

trait NormalizesUnitKerjaNames
{
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
