<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LogAktivitas;
use Illuminate\Support\Facades\Auth;

/**
 * Service AuditLog — mencatat setiap aksi penting ke tabel audit.
 *
 * Log bersifat immutable (INSERT-only). Tidak ada method update/delete.
 * Digunakan oleh service-service lain untuk mencatat perubahan data.
 */
class AuditLogService
{
    /**
     * Catat aktivitas ke tabel audit.log_aktivitas.
     *
     * @param  string                   $namaTabel   Nama tabel yang terpengaruh (misal: 'akun.pengguna')
     * @param  string                   $aksi        Jenis aksi: 'CREATE', 'UPDATE', 'DELETE', 'LOGIN', dll.
     * @param  int|null                 $idData      ID record yang terpengaruh
     * @param  array<string,mixed>|null $dataLama    Snapshot data sebelum perubahan
     * @param  array<string,mixed>|null $dataBaru    Snapshot data setelah perubahan
     * @param  int|null                 $idPengguna  Override pengguna (bila bukan dari Auth)
     */
    public function catat(
        string $namaTabel,
        string $aksi,
        ?int $idData = null,
        ?array $dataLama = null,
        ?array $dataBaru = null,
        ?int $idPengguna = null,
    ): LogAktivitas {
        $pengguna = Auth::user();
        $tenantId = $pengguna?->tenant_id ?? 1;

        return LogAktivitas::create([
            'tenant_id'   => $tenantId,
            'nama_tabel'  => $namaTabel,
            'aksi'        => $aksi,
            'id_data'     => $idData,
            'id_pengguna' => $idPengguna ?? $pengguna?->id,
            'data_lama'   => $dataLama,
            'data_baru'   => $dataBaru,
        ]);
    }
}
