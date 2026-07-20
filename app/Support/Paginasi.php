<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Helper Paginasi - sentralisasi konfigurasi per-halaman.
 *
 * Digunakan oleh seluruh controller yang menerima `per_halaman`
 * dari query string. Satu tempat untuk mengubah opsi/default global.
 */
final class Paginasi
{
    /** Opsi jumlah data per halaman yang tersedia di selector UI. */
    public const PILIHAN = [10, 25, 50, 100];

    /** Default jika query param `per_halaman` tidak disertakan. */
    public const DEFAULT = 25;

    /**
     * Baca & validasi nilai `per_halaman` dari query string saat ini.
     *
     * Hanya nilai yang ada di PILIHAN yang diterima; selain itu jatuh ke DEFAULT.
     *
     * @param  int|string|null $nilai  Override manual (opsional).
     * @return int
     */
    public static function perHalaman(int|string|null $nilai = null): int
    {
        $n = (int) ($nilai ?? request('per_halaman', self::DEFAULT));

        return in_array($n, self::PILIHAN, true) ? $n : self::DEFAULT;
    }
}
