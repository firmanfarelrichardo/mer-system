<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UnitKerja;
use App\Services\AuditLogService;

/**
 * Observer UnitKerja - mencatat audit log secara otomatis
 * setiap kali record unit kerja dibuat, diubah, atau dihapus.
 *
 * Decoupled dari Service agar logging terjadi di mana pun
 * model dimanipulasi (tinker, seeder, queue, dsb.).
 */
class UnitKerjaObserver
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function created(UnitKerja $unitKerja): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.unit_kerja',
            aksi:      'CREATE',
            idData:    $unitKerja->id,
            dataBaru:  $unitKerja->toArray(),
        );
    }

    public function updated(UnitKerja $unitKerja): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.unit_kerja',
            aksi:      'UPDATE',
            idData:    $unitKerja->id,
            dataLama:  $unitKerja->getOriginal(),
            dataBaru:  $unitKerja->getChanges(),
        );
    }

    public function deleted(UnitKerja $unitKerja): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.unit_kerja',
            aksi:      'DELETE',
            idData:    $unitKerja->id,
            dataLama:  $unitKerja->toArray(),
        );
    }
}
