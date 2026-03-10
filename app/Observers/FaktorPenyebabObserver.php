<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\FaktorPenyebab;
use App\Services\AuditLogService;

/**
 * Observer FaktorPenyebab — mencatat audit log secara otomatis
 * setiap kali record faktor penyebab dibuat, diubah, atau dihapus.
 */
class FaktorPenyebabObserver
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function created(FaktorPenyebab $faktorPenyebab): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.faktor_penyebab',
            aksi:      'CREATE',
            idData:    $faktorPenyebab->id,
            dataBaru:  $faktorPenyebab->toArray(),
        );
    }

    public function updated(FaktorPenyebab $faktorPenyebab): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.faktor_penyebab',
            aksi:      'UPDATE',
            idData:    $faktorPenyebab->id,
            dataLama:  $faktorPenyebab->getOriginal(),
            dataBaru:  $faktorPenyebab->getChanges(),
        );
    }

    public function deleted(FaktorPenyebab $faktorPenyebab): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.faktor_penyebab',
            aksi:      'DELETE',
            idData:    $faktorPenyebab->id,
            dataLama:  $faktorPenyebab->toArray(),
        );
    }
}
