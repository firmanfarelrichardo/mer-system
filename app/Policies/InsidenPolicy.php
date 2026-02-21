<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Insiden;
use App\Models\Pengguna;
use App\Models\Peran;

/**
 * InsidenPolicy — Otorisasi aksi terhadap laporan insiden.
 *
 * Prinsip:
 *   - Direktur hanya READ-ONLY (monitoring).
 *   - Kepala Ruangan & Komite dapat mengubah status dan menambah catatan tindak lanjut.
 *   - Perawat tidak bisa mengubah status (hanya membuat & melihat laporan sendiri).
 *
 * Catatan: Policy ini auto-discovered oleh Laravel (konvensi penamaan).
 * Jika auto-discovery gagal, daftarkan manual di AuthServiceProvider.
 */
class InsidenPolicy
{
    /* ------------------------------------------------------------------
     | Melihat Daftar Laporan
     | ----------------------------------------------------------------*/

    /**
     * Semua peran yang terautentikasi dapat melihat daftar laporan.
     * Pembatasan data (scope) dilakukan oleh scopeUntukPeran() di model.
     */
    public function viewAny(Pengguna $pengguna): bool
    {
        return true;
    }

    /* ------------------------------------------------------------------
     | Melihat Detail Laporan
     | ----------------------------------------------------------------*/

    /**
     * Semua peran dapat melihat detail laporan selama berada
     * di tenant yang sama. Scope model menjamin data yang benar.
     */
    public function view(Pengguna $pengguna, Insiden $insiden): bool
    {
        return $pengguna->tenant_id === $insiden->tenant_id;
    }

    /* ------------------------------------------------------------------
     | Tindak Lanjut & Ubah Status
     | -----------------------------------------------------------------
     | HANYA Kepala Ruangan dan Komite yang diizinkan.
     | Direktur TIDAK boleh — read-only monitoring.
     | Perawat TIDAK boleh — hanya membuat laporan.
     | ----------------------------------------------------------------*/

    /**
     * Apakah pengguna boleh melakukan tindak lanjut (ubah status + catatan)?
     */
    public function tindakLanjut(Pengguna $pengguna, Insiden $insiden): bool
    {
        // Harus di tenant yang sama.
        if ($pengguna->tenant_id !== $insiden->tenant_id) {
            return false;
        }

        // Insiden yang sudah selesai tidak bisa ditindaklanjuti lagi.
        if ($insiden->status_saat_ini === 'selesai') {
            return false;
        }

        // Hanya Kepala Ruangan atau Komite yang diizinkan.
        return $pengguna->memilikiPeran(Peran::KEPALA_RUANGAN)
            || $pengguna->memilikiPeran(Peran::KOMITE);
    }

    /* ------------------------------------------------------------------
     | Tandai Dibaca
     | ----------------------------------------------------------------*/

    /**
     * Apakah pengguna boleh menandai laporan sebagai sudah dibaca?
     */
    public function tandaiDibaca(Pengguna $pengguna, Insiden $insiden): bool
    {
        if ($pengguna->tenant_id !== $insiden->tenant_id) {
            return false;
        }

        return $pengguna->memilikiPeran(Peran::KEPALA_RUANGAN)
            || $pengguna->memilikiPeran(Peran::KOMITE)
            || $pengguna->memilikiPeran(Peran::ADMIN);
    }
}
