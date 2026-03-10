<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\KategoriKesalahan;
use App\Services\AuditLogService;

/**
 * Observer KategoriKesalahan — mencatat audit log secara otomatis
 * setiap kali record kategori kesalahan dibuat, diubah, atau dihapus.
 */
class KategoriKesalahanObserver
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function created(KategoriKesalahan $kategori): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.kategori_kesalahan',
            aksi:      'CREATE',
            idData:    $kategori->id,
            dataBaru:  $kategori->toArray(),
        );
    }

    public function updated(KategoriKesalahan $kategori): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.kategori_kesalahan',
            aksi:      'UPDATE',
            idData:    $kategori->id,
            dataLama:  $kategori->getOriginal(),
            dataBaru:  $kategori->getChanges(),
        );
    }

    public function deleted(KategoriKesalahan $kategori): void
    {
        $this->auditLog->catat(
            namaTabel: 'master.kategori_kesalahan',
            aksi:      'DELETE',
            idData:    $kategori->id,
            dataLama:  $kategori->toArray(),
        );
    }
}
