<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\NotifikasiData;
use App\Models\Pengguna;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Repository: NotifikasiRepository
 *
 * Mengelola akses data notifikasi dari tabel `akun.notifikasi`.
 * Menggunakan relasi Notifiable bawaan Laravel pada model Pengguna.
 */
class NotifikasiRepository
{
    /**
     * Ambil notifikasi pengguna dengan paginasi, sudah di-mapping ke DTO.
     *
     * @return LengthAwarePaginator<int, mixed>
     */
    public function daftarNotifikasi(Pengguna $pengguna, int $perHalaman = 15): LengthAwarePaginator
    {
        // Bersihkan notifikasi yatim jika ada
        $this->bersihkanNotifikasiYatim($pengguna);

        $paginator = $pengguna->notifications()->paginate($perHalaman);

        $paginator->getCollection()->transform(
            fn ($notif) => $this->keDTO($notif),
        );

        return $paginator;
    }

    /**
     * Hitung notifikasi yang belum dibaca.
     */
    public function hitungBelumDibaca(Pengguna $pengguna): int
    {
        return $pengguna->unreadNotifications()->count();
    }

    /**
     * Tandai satu notifikasi sebagai dibaca.
     */
    public function tandaiDibaca(Pengguna $pengguna, string $notifikasiId): bool
    {
        $notif = $pengguna->notifications()->find($notifikasiId);

        if (! $notif) {
            return false;
        }

        $notif->markAsRead();

        return true;
    }

    /**
     * Tandai semua notifikasi sebagai dibaca.
     */
    public function tandaiSemuaDibaca(Pengguna $pengguna): int
    {
        $count = $pengguna->unreadNotifications()->count();
        $pengguna->unreadNotifications->markAsRead();

        return $count;
    }

    /**
     * Ambil satu notifikasi milik pengguna.
     */
    public function cari(Pengguna $pengguna, string $notifikasiId): ?NotifikasiData
    {
        $notif = $pengguna->notifications()->find($notifikasiId);

        return $notif ? $this->keDTO($notif) : null;
    }

    /**
     * Hapus satu notifikasi milik pengguna.
     */
    public function hapus(Pengguna $pengguna, string $notifikasiId): bool
    {
        $notif = $pengguna->notifications()->find($notifikasiId);

        if (! $notif) {
            return false;
        }

        return (bool) $notif->delete();
    }

    /**
     * Bersihkan notifikasi yang merujuk pada insiden yang sudah tidak ada.
     * Jika $pengguna diberikan, hanya membersihkan notifikasi milik pengguna tersebut.
     */
    public function bersihkanNotifikasiYatim(?Pengguna $pengguna = null): int
    {
        try {
            $query = $pengguna ? $pengguna->notifications() : \App\Models\Notifikasi::query();

            $validInsidenIds   = \App\Models\Insiden::pluck('id')->all();
            $validNomorLaporan = \App\Models\Insiden::pluck('nomor_laporan')->all();

            $terhapus = 0;

            $query->chunk(100, function ($notifikasiList) use ($validInsidenIds, $validNomorLaporan, &$terhapus): void {
                foreach ($notifikasiList as $notif) {
                    $data         = $notif->data;
                    $insidenId    = isset($data['insiden_id']) ? (int) $data['insiden_id'] : null;
                    $nomorLaporan = $data['nomor_laporan'] ?? null;
                    $pesan        = $data['pesan'] ?? '';

                    $hapus = false;

                    if ($insidenId !== null && ! in_array($insidenId, $validInsidenIds, true)) {
                        $hapus = true;
                    }

                    if ($nomorLaporan !== null && ! in_array($nomorLaporan, $validNomorLaporan, true)) {
                        $hapus = true;
                    }

                    // Notifikasi khusus Karu tapi penerima bukan Karu
                    if (! $hapus && str_contains($pesan, 'telah dilaporkan di unit kerja Anda')) {
                        $penerima = \App\Models\Pengguna::find($notif->notifiable_id);
                        if ($penerima && ! $penerima->memilikiPeran(\App\Models\Peran::KEPALA_RUANGAN)) {
                            $hapus = true;
                        }
                    }

                    if ($hapus) {
                        $notif->delete();
                        $terhapus++;
                    }
                }
            });

            return $terhapus;
        } catch (\Throwable) {
            return 0;
        }
    }

    /* ------------------------------------------------------------------
     | Transformer
     | ----------------------------------------------------------------*/

    /**
     * Konversi model notifikasi ke DTO.
     */
    private function keDTO(object $notif): NotifikasiData
    {
        $data = $notif->data;
        $insidenId = isset($data['insiden_id']) ? (int) $data['insiden_id'] : null;

        $urlTujuan = $insidenId !== null
            ? route('laporan.tampil', $insidenId)
            : ($data['url_tujuan'] ?? '#');

        return new NotifikasiData(
            id:           $notif->id,
            judul:        $data['judul'] ?? '-',
            pesan:        $data['pesan'] ?? '',
            tipe:         $data['tipe'] ?? 'default',
            ikonWarna:    $data['ikon_warna'] ?? 'bg-slate-50 text-slate-500',
            urlTujuan:    $urlTujuan,
            nomorLaporan: $data['nomor_laporan'] ?? null,
            insidenId:    $insidenId,
            dibaca:       $notif->read_at !== null,
            waktuRelatif: $notif->created_at->diffForHumans(),
            waktuLengkap: $notif->created_at->format('d M Y H:i'),
        );
    }
}
