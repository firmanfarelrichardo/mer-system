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

    /* ------------------------------------------------------------------
     | Transformer
     | ----------------------------------------------------------------*/

    /**
     * Konversi model notifikasi ke DTO.
     */
    private function keDTO(object $notif): NotifikasiData
    {
        $data = $notif->data;

        return new NotifikasiData(
            id:           $notif->id,
            judul:        $data['judul'] ?? '-',
            pesan:        $data['pesan'] ?? '',
            tipe:         $data['tipe'] ?? 'default',
            ikonWarna:    $data['ikon_warna'] ?? 'bg-slate-50 text-slate-500',
            urlTujuan:    $data['url_tujuan'] ?? '#',
            nomorLaporan: $data['nomor_laporan'] ?? null,
            insidenId:    $data['insiden_id'] ?? null,
            dibaca:       $notif->read_at !== null,
            waktuRelatif: $notif->created_at->diffForHumans(),
            waktuLengkap: $notif->created_at->format('d M Y H:i'),
        );
    }
}
