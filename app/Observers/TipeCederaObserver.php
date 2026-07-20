<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\TipeCedera;
use App\Services\AuditLogService;

/**
 * Observer TipeCedera - mencatat audit log secara otomatis
 * setiap kali record tipe cedera dibuat, diubah, atau dihapus.
 */
class TipeCederaObserver
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function created(TipeCedera $tipeCedera): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.tipe_cedera',
            aksi:      'CREATE',
            idData:    $tipeCedera->id,
            dataBaru:  $tipeCedera->toArray(),
        );
    }

    public function updated(TipeCedera $tipeCedera): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.tipe_cedera',
            aksi:      'UPDATE',
            idData:    $tipeCedera->id,
            dataLama:  $tipeCedera->getOriginal(),
            dataBaru:  $tipeCedera->getChanges(),
        );
    }

    public function deleted(TipeCedera $tipeCedera): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.tipe_cedera',
            aksi:      'DELETE',
            idData:    $tipeCedera->id,
            dataLama:  $tipeCedera->toArray(),
        );
    }
}
