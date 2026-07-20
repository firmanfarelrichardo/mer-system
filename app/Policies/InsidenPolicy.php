<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Insiden;
use App\Models\Pengguna;
use App\Models\Peran;

/**
 * InsidenPolicy - Otorisasi aksi terhadap laporan insiden.
 *
 * Prinsip:
 *   - Direktur hanya READ-ONLY (monitoring).
 *   - Kepala Ruangan & Komite dapat mengubah status dan menambah catatan tindak lanjut.
 *   - Nakes tidak bisa mengubah status (hanya membuat & melihat laporan sendiri).
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
     | Direktur TIDAK boleh - read-only monitoring.
     | Nakes TIDAK boleh - hanya membuat laporan.
     | ----------------------------------------------------------------*/

    /**
     * Apakah pengguna boleh melakukan tindak lanjut (ubah status + catatan)?
     *
     * Karu dan Komite memiliki alur status yang INDEPENDEN satu sama lain.
     * Pemblokiran 'selesai' hanya berlaku per peran:
     *   - Karu diblokir hanya jika KARU sendiri sudah menandai selesai.
     *   - Komite diblokir hanya jika KOMITE sendiri sudah menandai selesai.
     */
    public function tindakLanjut(Pengguna $pengguna, Insiden $insiden): bool
    {
        // Harus di tenant yang sama.
        if ($pengguna->tenant_id !== $insiden->tenant_id) {
            return false;
        }

        // Hanya Kepala Ruangan atau Komite yang diizinkan.
        $isKaru   = $pengguna->memilikiPeran(Peran::KEPALA_RUANGAN);
        $isKomite = $pengguna->memilikiPeran(Peran::KOMITE);

        if (! $isKaru && ! $isKomite) {
            return false;
        }

        // Tentukan peran aktif pengguna (Karu mendapat prioritas jika memegang keduanya).
        $namaPeran = $isKaru ? Peran::KEPALA_RUANGAN : Peran::KOMITE;

        // Blokir hanya jika PERAN INI sudah menandai insiden sebagai selesai.
        // Status peran lain tidak memengaruhi.
        return $insiden->statusTerakhirOlehPeran($namaPeran) !== 'selesai';
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

    /* ------------------------------------------------------------------
     | Eskalasi ke Direktur
     | -----------------------------------------------------------------
     | HANYA Komite yang boleh mengaktifkan eskalasi ke Direktur.
     | ----------------------------------------------------------------*/

    /**
     * Apakah pengguna (Komite) boleh mengubah status eskalasi laporan?
     */
    public function eskalasi(Pengguna $pengguna, Insiden $insiden): bool
    {
        if ($pengguna->tenant_id !== $insiden->tenant_id) {
            return false;
        }

        return $pengguna->memilikiPeran(Peran::KOMITE);
    }

    /* ------------------------------------------------------------------
     | Solusi Direktur
     | -----------------------------------------------------------------
     | HANYA Direktur yang boleh mengisi solusi, dan HANYA jika laporan
     | sudah dieskalasikan oleh Komite.
     | ----------------------------------------------------------------*/

    /**
     * Apakah pengguna (Direktur) boleh mengisi arahan/solusi eksekutif?
     */
    public function solusiDirektur(Pengguna $pengguna, Insiden $insiden): bool
    {
        if ($pengguna->tenant_id !== $insiden->tenant_id) {
            return false;
        }

        return $pengguna->memilikiPeran(Peran::DIREKTUR)
            && $insiden->is_eskalasi_direktur;
    }
}
