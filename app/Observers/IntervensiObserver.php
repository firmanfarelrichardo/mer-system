<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\TindakanIntervensi;
use App\Services\AuditLogService;

/**
 * Observer TindakanIntervensi — mencatat audit log secara otomatis
 * setiap kali record tindakan intervensi dibuat, diubah, atau dihapus.
 */
class IntervensiObserver
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function created(TindakanIntervensi $intervensi): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.tindakan_intervensi',
            aksi:      'CREATE',
            idData:    $intervensi->id,
            dataBaru:  $intervensi->toArray(),
        );
    }

    public function updated(TindakanIntervensi $intervensi): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.tindakan_intervensi',
            aksi:      'UPDATE',
            idData:    $intervensi->id,
            dataLama:  $intervensi->getOriginal(),
            dataBaru:  $intervensi->getChanges(),
        );
    }

    public function deleted(TindakanIntervensi $intervensi): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.tindakan_intervensi',
            aksi:      'DELETE',
            idData:    $intervensi->id,
            dataLama:  $intervensi->toArray(),
        );
    }
}
