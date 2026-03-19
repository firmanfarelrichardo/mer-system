<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\JenisKesalahan;
use App\Services\AuditLogService;

/**
 * Observer JenisKesalahan — mencatat audit log secara otomatis
 * setiap kali record jenis kesalahan dibuat, diubah, atau dihapus.
 */
class JenisKesalahanObserver
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function created(JenisKesalahan $jenisKesalahan): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.jenis_kesalahan',
            aksi:      'CREATE',
            idData:    $jenisKesalahan->id,
            dataBaru:  $jenisKesalahan->toArray(),
        );
    }

    public function updated(JenisKesalahan $jenisKesalahan): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.jenis_kesalahan',
            aksi:      'UPDATE',
            idData:    $jenisKesalahan->id,
            dataLama:  $jenisKesalahan->getOriginal(),
            dataBaru:  $jenisKesalahan->getChanges(),
        );
    }

    public function deleted(JenisKesalahan $jenisKesalahan): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.jenis_kesalahan',
            aksi:      'DELETE',
            idData:    $jenisKesalahan->id,
            dataLama:  $jenisKesalahan->toArray(),
        );
    }
}
