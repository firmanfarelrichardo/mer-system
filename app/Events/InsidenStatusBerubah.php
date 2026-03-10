<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Insiden;
use App\Models\Pengguna;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: InsidenStatusBerubah
 *
 * Di-dispatch setiap kali status insiden berubah:
 *   - kasus_baru  → laporan baru dibuat oleh Nakes
 *   - investigasi → Karu menindaklanjuti ke investigasi
 *   - tindak_lanjut → Komite memberikan tindak lanjut
 *   - selesai     → insiden ditutup
 *
 * Listener akan menentukan siapa yang harus dinotifikasi
 * berdasarkan state-routing logic.
 */
class InsidenStatusBerubah
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Insiden  $insiden     Model insiden yang statusnya berubah.
     * @param  string   $statusBaru  Status baru insiden (kasus_baru|investigasi|tindak_lanjut|selesai).
     * @param  Pengguna $pelaku      Pengguna yang memicu perubahan status.
     */
    public function __construct(
        public readonly Insiden  $insiden,
        public readonly string   $statusBaru,
        public readonly Pengguna $pelaku,
    ) {}
}
