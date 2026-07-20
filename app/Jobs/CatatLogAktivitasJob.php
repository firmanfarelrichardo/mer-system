<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\LogAktivitas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job: Catat Log Aktivitas secara Asynchronous.
 *
 * Memindahkan INSERT audit log ke background queue (Redis/database)
 * sehingga respons ke pengguna tidak tertahan oleh operasi logging.
 *
 * KAPAN DIGUNAKAN:
 *   - Aktifkan (gunakan dispatch) saat load produksi tinggi.
 *   - Nonaktifkan (pakai synchronous) saat debugging audit trail.
 *
 * CATATAN PENTING untuk Sistem Rumah Sakit:
 *   - Gunakan queue connection 'redis' dengan queue 'audit' yang terpisah
 *     dari queue utama, agar backlog log tidak memblokir job kritis lain.
 *   - Set 'tries' => 3 dan 'backoff' agar log tidak hilang jika Redis restart.
 *   - Gunakan horizon atau queue:work dengan --queue=audit,default
 */
class CatatLogAktivitasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jumlah percobaan ulang jika job gagal.
     * Log audit terlalu penting untuk dibuang begitu saja.
     */
    public int $tries = 3;

    /**
     * Jeda (detik) sebelum percobaan ulang: 5s, 15s, 30s.
     * @var array<int, int>
     */
    public array $backoff = [5, 15, 30];

    /**
     * Queue khusus untuk audit - pisahkan dari job bisnis utama.
     */
    public string $queue = 'audit';

    public function __construct(
        private readonly array $payload,
    ) {}

    public function handle(): void
    {
        LogAktivitas::create($this->payload);
    }
}
