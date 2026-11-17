<?php

declare(strict_types=1);

use App\Models\Insiden;
use App\Models\Notifikasi;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Membersihkan notifikasi bodong / yatim (laporan tidak ada di pelaporan.insiden).
     */
    public function up(): void
    {
        try {
            if (! Schema::hasTable('akun.notifikasi') || ! Schema::hasTable('pelaporan.insiden')) {
                return;
            }

            // 1. Ambil seluruh id insiden dan nomor_laporan yang valid
            $validInsidenIds   = Insiden::pluck('id')->all();
            $validNomorLaporan = Insiden::pluck('nomor_laporan')->all();

            // 2. Scan tabel akun.notifikasi
            Notifikasi::chunk(100, function ($notifikasiList) use ($validInsidenIds, $validNomorLaporan): void {
                foreach ($notifikasiList as $notif) {
                    $data = $notif->data;
                    if (! is_array($data)) {
                        continue;
                    }

                    $insidenId    = isset($data['insiden_id']) ? (int) $data['insiden_id'] : null;
                    $nomorLaporan = $data['nomor_laporan'] ?? null;
                    $pesan        = $data['pesan'] ?? '';

                    $hapus = false;

                    // Jika ada insiden_id tapi tidak ada di tabel insiden
                    if ($insidenId !== null && ! in_array($insidenId, $validInsidenIds, true)) {
                        $hapus = true;
                    }

                    // Jika ada nomor_laporan tapi tidak ada di tabel insiden
                    if ($nomorLaporan !== null && ! in_array($nomorLaporan, $validNomorLaporan, true)) {
                        $hapus = true;
                    }

                    // Jika notifikasi Kasus Baru untuk Kepala Ruangan tapi penerima bukan Kepala Ruangan
                    if (! $hapus && str_contains($pesan, 'telah dilaporkan di unit kerja Anda')) {
                        $penerima = Pengguna::find($notif->notifiable_id);
                        if ($penerima && ! $penerima->memilikiPeran(Peran::KEPALA_RUANGAN)) {
                            $hapus = true;
                        }
                    }

                    if ($hapus) {
                        $notif->delete();
                    }
                }
            });
        } catch (\Throwable) {
            // Safety net jika environment pengujian tidak menggunakan skema PostgreSQL
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Notifikasi bodong yang telah dibersihkan tidak perlu dikembalikan.
    }
};
