<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\CatatLogAktivitasJob;
use App\Models\LogAktivitas;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Service AuditLog — mencatat setiap aksi penting ke tabel audit.
 *
 * Log bersifat immutable (INSERT-only). Tidak ada method update/delete.
 *
 * MODE OPERASI:
 *   - AUDIT_LOG_ASYNC=false (default/local): INSERT synchronous — mudah di-debug.
 *   - AUDIT_LOG_ASYNC=true  (production)   : Dispatch ke queue 'audit' via Redis
 *     agar respons user tidak tertahan oleh operasi database logging.
 *
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
    ): LogAktivitas|null {
        $pengguna = Auth::user();
        $tenantId = $pengguna?->tenant_id ?? 1;

        $payload = [
            'tenant_id'   => $tenantId,
            'nama_tabel'  => $namaTabel,
            'aksi'        => $aksi,
            'id_data'     => $idData,
            'id_pengguna' => $idPengguna ?? $pengguna?->id,
            'alamat_ip'   => Request::ip(),
            // Potong di 512 karakter — kolom DB tidak boleh overflow
            'user_agent'  => mb_substr((string) Request::userAgent(), 0, 512) ?: null,
            'data_lama'   => $dataLama,
            'data_baru'   => $dataBaru,
        ];

        // Mode async (production): lempar ke queue, respons user tidak tertahan
        if (config('audit.async', false)) {
            CatatLogAktivitasJob::dispatch($payload);

            return null; // Job berjalan di background
        }

        // Mode sync (local/testing): INSERT langsung, mudah di-debug
        return LogAktivitas::create($payload);
    }
}

