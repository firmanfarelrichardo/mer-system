<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Insiden;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notification: InsidenNotifikasi
 *
 * Notifikasi database untuk setiap perubahan status insiden.
 * Hanya menggunakan channel 'database' — ditulis ke tabel `akun.notifikasi`.
 *
 * Payload `data` yang disimpan:
 *   - insiden_id   : ID insiden terkait
 *   - nomor_laporan: Nomor laporan untuk tampilan
 *   - judul        : Judul ringkas notifikasi
 *   - pesan        : Pesan deskriptif
 *   - tipe         : Tipe notifikasi (laporan|status|tindakan|selesai)
 *   - ikon_warna   : Kelas CSS warna ikon
 *   - url_tujuan   : URL untuk navigasi saat notifikasi diklik
 */
class InsidenNotifikasi extends Notification
{
    use Queueable;

    /**
     * @param  Insiden $insiden      Model insiden terkait.
     * @param  string  $judul        Judul ringkas notifikasi.
     * @param  string  $pesan        Pesan deskriptif.
     * @param  string  $tipe         Tipe notifikasi (laporan|status|tindakan|selesai).
     * @param  string  $ikonWarna    Kelas CSS untuk ikon (mis. 'bg-brand/10 text-brand').
     */
    public function __construct(
        private readonly Insiden $insiden,
        private readonly string  $judul,
        private readonly string  $pesan,
        private readonly string  $tipe,
        private readonly string  $ikonWarna,
    ) {}

    /**
     * Channel notifikasi: hanya database.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Payload yang disimpan ke kolom `data` di tabel notifikasi.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'insiden_id'    => $this->insiden->id,
            'nomor_laporan' => $this->insiden->nomor_laporan,
            'judul'         => $this->judul,
            'pesan'         => $this->pesan,
            'tipe'          => $this->tipe,
            'ikon_warna'    => $this->ikonWarna,
            'url_tujuan'    => route('laporan.tampil', $this->insiden->id),
        ];
    }
}
