<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\InsidenStatusBerubah;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Notifications\InsidenNotifikasi;
use Illuminate\Support\Facades\Notification;

/**
 * Listener: KirimNotifikasiInsiden
 *
 * State-based routing — menentukan SIAPA yang menerima notifikasi
 * berdasarkan status baru insiden:
 *
 *   kasus_baru     → Kepala Ruangan (unit kerja yang sama)
 *   investigasi    → Komite (eskalasi high-risk)
 *   tindak_lanjut  → Pelapor (Nakes) — ada tindak lanjut untuk laporannya
 *   selesai        → Pelapor (Nakes) — laporannya telah selesai
 *
 * Sentinel event (tipe_insiden = SENTINEL) juga mengirim ke Direktur.
 */
class KirimNotifikasiInsiden
{
    /**
     * Handle event InsidenStatusBerubah.
     */
    public function handle(InsidenStatusBerubah $event): void
    {
        $insiden  = $event->insiden;
        $pelaku   = $event->pelaku;
        $tenantId = $insiden->tenant_id;

        match ($event->statusBaru) {
            'kasus_baru'    => $this->notifikasiKasusBaru($insiden, $pelaku, $tenantId),
            'investigasi'   => $this->notifikasiInvestigasi($insiden, $pelaku, $tenantId),
            'tindak_lanjut' => $this->notifikasiTindakLanjut($insiden, $pelaku, $tenantId),
            'selesai'       => $this->notifikasiSelesai($insiden, $pelaku, $tenantId),
            default         => null,
        };
    }

    /* ------------------------------------------------------------------
     | State Handlers
     | ----------------------------------------------------------------*/

    /**
     * Kasus Baru: notifikasi ke Kepala Ruangan di unit kerja yang sama.
     */
    private function notifikasiKasusBaru(
        \App\Models\Insiden $insiden,
        Pengguna $pelaku,
        int $tenantId,
    ): void {
        $penerima = $this->penggunaPeran(Peran::KEPALA_RUANGAN, $tenantId)
            ->when($insiden->unit_id, fn ($q) => $q->where('unit_id', $insiden->unit_id))
            ->where('id', '!=', $pelaku->id)
            ->get();

        if ($penerima->isEmpty()) {
            return;
        }

        $nomor = $insiden->nomor_laporan;

        Notification::send($penerima, new InsidenNotifikasi(
            insiden:   $insiden,
            judul:     "Laporan baru {$nomor} diterima",
            pesan:     "Laporan insiden baru ({$insiden->labelTipeInsiden()}) telah dilaporkan di unit kerja Anda oleh {$pelaku->nama_lengkap}. Silakan tinjau dan lakukan verifikasi.",
            tipe:      'laporan',
            ikonWarna: 'bg-brand/10 text-brand',
        ));
    }

    /**
     * Investigasi: notifikasi ke Komite (eskalasi).
     * Jika tipe insiden = SENTINEL, juga kirim ke Direktur.
     */
    private function notifikasiInvestigasi(
        \App\Models\Insiden $insiden,
        Pengguna $pelaku,
        int $tenantId,
    ): void {
        $nomor = $insiden->nomor_laporan;

        // Kirim ke seluruh Komite dalam tenant.
        $komite = $this->penggunaPeran(Peran::KOMITE, $tenantId)
            ->where('id', '!=', $pelaku->id)
            ->get();

        if ($komite->isNotEmpty()) {
            Notification::send($komite, new InsidenNotifikasi(
                insiden:   $insiden,
                judul:     "Eskalasi investigasi {$nomor}",
                pesan:     "Laporan insiden {$nomor} ({$insiden->labelTipeInsiden()}) telah dieskalasi ke tahap investigasi oleh {$pelaku->nama_lengkap}.",
                tipe:      'status',
                ikonWarna: 'bg-blue-50 text-blue-500',
            ));
        }

        // SENTINEL: juga kirim notifikasi ke Direktur.
        if ($insiden->tipe_insiden === 'SENTINEL') {
            $direktur = $this->penggunaPeran(Peran::DIREKTUR, $tenantId)->get();

            if ($direktur->isNotEmpty()) {
                Notification::send($direktur, new InsidenNotifikasi(
                    insiden:   $insiden,
                    judul:     "SENTINEL EVENT: {$nomor}",
                    pesan:     "Sentinel event teridentifikasi pada laporan {$nomor}. Diperlukan perhatian segera dari pimpinan rumah sakit.",
                    tipe:      'tindakan',
                    ikonWarna: 'bg-red-50 text-red-500',
                ));
            }
        }

        // Kirim notifikasi balik ke pelapor (Nakes) bahwa laporannya diinvestigasi.
        $pelapor = $insiden->pelapor;
        if ($pelapor && $pelapor->id !== $pelaku->id) {
            $pelapor->notify(new InsidenNotifikasi(
                insiden:   $insiden,
                judul:     "Laporan {$nomor} sedang diinvestigasi",
                pesan:     "Laporan insiden Anda ({$nomor}) telah memasuki tahap investigasi oleh Kepala Ruangan.",
                tipe:      'status',
                ikonWarna: 'bg-blue-50 text-blue-500',
            ));
        }
    }

    /**
     * Tindak Lanjut: notifikasi ke Pelapor (Nakes).
     */
    private function notifikasiTindakLanjut(
        \App\Models\Insiden $insiden,
        Pengguna $pelaku,
        int $tenantId,
    ): void {
        $nomor   = $insiden->nomor_laporan;
        $pelapor = $insiden->pelapor;

        if ($pelapor && $pelapor->id !== $pelaku->id) {
            $pelapor->notify(new InsidenNotifikasi(
                insiden:   $insiden,
                judul:     "Tindak lanjut pada {$nomor}",
                pesan:     "Laporan insiden Anda ({$nomor}) telah mendapat tindak lanjut dari {$pelaku->nama_lengkap}. Silakan periksa perkembangan terbaru.",
                tipe:      'tindakan',
                ikonWarna: 'bg-amber-50 text-amber-500',
            ));
        }
    }

    /**
     * Selesai: notifikasi ke Pelapor (Nakes) bahwa laporannya telah ditutup.
     */
    private function notifikasiSelesai(
        \App\Models\Insiden $insiden,
        Pengguna $pelaku,
        int $tenantId,
    ): void {
        $nomor   = $insiden->nomor_laporan;
        $pelapor = $insiden->pelapor;

        if ($pelapor && $pelapor->id !== $pelaku->id) {
            $pelapor->notify(new InsidenNotifikasi(
                insiden:   $insiden,
                judul:     "Laporan {$nomor} telah selesai",
                pesan:     "Proses investigasi dan tindak lanjut untuk laporan insiden {$nomor} telah selesai dilaksanakan. Terima kasih atas laporan Anda.",
                tipe:      'selesai',
                ikonWarna: 'bg-emerald-50 text-emerald-500',
            ));
        }

        // Juga beritahu Kepala Ruangan unit terkait.
        $karu = $this->penggunaPeran(Peran::KEPALA_RUANGAN, $tenantId)
            ->when($insiden->unit_id, fn ($q) => $q->where('unit_id', $insiden->unit_id))
            ->where('id', '!=', $pelaku->id)
            ->get();

        if ($karu->isNotEmpty()) {
            Notification::send($karu, new InsidenNotifikasi(
                insiden:   $insiden,
                judul:     "Laporan {$nomor} telah selesai",
                pesan:     "Proses investigasi dan tindak lanjut untuk laporan insiden {$nomor} ({$insiden->labelTipeInsiden()}) telah selesai dilaksanakan.",
                tipe:      'selesai',
                ikonWarna: 'bg-emerald-50 text-emerald-500',
            ));
        }
    }

    /* ------------------------------------------------------------------
     | Helper
     | ----------------------------------------------------------------*/

    /**
     * Query builder: pengguna aktif dengan peran tertentu dalam tenant.
     */
    private function penggunaPeran(string $namaPeran, int $tenantId): \Illuminate\Database\Eloquent\Builder
    {
        return Pengguna::where('tenant_id', $tenantId)
            ->where('is_aktif', true)
            ->whereHas('peran', fn ($q) => $q->where('nama_peran', $namaPeran));
    }
}
