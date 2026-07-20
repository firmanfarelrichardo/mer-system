<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Insiden;
use App\Services\AuditLogService;

/**
 * Observer Insiden - mencatat audit log secara otomatis
 * setiap kali record insiden dimanipulasi.
 *
 * Catatan: Penyimpanan draf dan submit laporan sudah di-log oleh
 * LaporanService. Observer ini berfungsi sebagai safety net untuk
 * operasi yang terjadi di luar service (misal: tindak lanjut, update status).
 */
class InsidenObserver
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * Setelah insiden dibuat.
     */
    public function created(Insiden $insiden): void
    {
        $deskripsi = $insiden->status_saat_ini === 'DRAF'
            ? 'CREATE_DRAF'
            : 'CREATE';

        $this->auditLog->catat(
            namaTabel: 'pelaporan.insiden',
            aksi:      $deskripsi,
            idData:    $insiden->id,
            dataBaru:  $insiden->toArray(),
        );
    }

    /**
     * Setelah insiden diperbarui.
     */
    public function updated(Insiden $insiden): void
    {
        // Deteksi transisi dari DRAF ke status lain (submit).
        $statusLama = $insiden->getOriginal('status_saat_ini');
        $statusBaru = $insiden->status_saat_ini;

        $aksi = match (true) {
            $statusLama === 'DRAF' && $statusBaru === 'DRAF'       => 'UPDATE_DRAF',
            $statusLama === 'DRAF' && $statusBaru !== 'DRAF'       => 'SUBMIT_DRAF',
            default                                                  => 'UPDATE',
        };

        $this->auditLog->catat(
            namaTabel: 'pelaporan.insiden',
            aksi:      $aksi,
            idData:    $insiden->id,
            dataLama:  $insiden->getOriginal(),
            dataBaru:  $insiden->getChanges(),
        );
    }

    /**
     * Setelah insiden di-soft-delete.
     */
    public function deleted(Insiden $insiden): void
    {
        $this->auditLog->catat(
            namaTabel: 'pelaporan.insiden',
            aksi:      'DELETE',
            idData:    $insiden->id,
            dataLama:  $insiden->toArray(),
        );
    }
}
