<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\NotifikasiData;
use App\Models\Pengguna;
use App\Repositories\NotifikasiRepository;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Service: NotifikasiService
 *
 * Lapisan bisnis untuk fitur notifikasi.
 * Mendelegasikan akses data ke NotifikasiRepository.
 */
class NotifikasiService
{
    public function __construct(
        private readonly NotifikasiRepository $repository,
    ) {}

    /**
     * Dapatkan daftar notifikasi pengguna (paginasi).
     *
     * @return LengthAwarePaginator<int, mixed>
     */
    public function daftarNotifikasi(Pengguna $pengguna, int $perHalaman = 15): LengthAwarePaginator
    {
        return $this->repository->daftarNotifikasi($pengguna, $perHalaman);
    }

    /**
     * Hitung jumlah notifikasi belum dibaca.
     */
    public function hitungBelumDibaca(Pengguna $pengguna): int
    {
        return $this->repository->hitungBelumDibaca($pengguna);
    }

    /**
     * Tandai satu notifikasi sebagai dibaca dan kembalikan URL tujuan.
     * Jika laporan yang dirujuk tidak ditemukan, hapus notifikasi bodong/yatim
     * dan kembalikan null agar controller dapat melakukan graceful redirect.
     *
     * @return string|null URL tujuan, atau null jika notifikasi/laporan tidak ditemukan.
     */
    public function bacaDanArahkan(Pengguna $pengguna, string $notifikasiId): ?string
    {
        $notif = $this->repository->cari($pengguna, $notifikasiId);

        if (! $notif) {
            return null;
        }

        // Jika notifikasi merujuk ke insiden tertentu, pastikan datanya masih ada
        if ($notif->insidenId !== null) {
            $insiden = \App\Models\Insiden::find($notif->insidenId);
            if (! $insiden) {
                // Hapus notifikasi bodong/yatim ini agar tidak muncul lagi
                $this->repository->hapus($pengguna, $notifikasiId);

                return null;
            }
        }

        $this->repository->tandaiDibaca($pengguna, $notifikasiId);

        return $notif->urlTujuan;
    }

    /**
     * Hapus satu notifikasi.
     */
    public function hapus(Pengguna $pengguna, string $notifikasiId): bool
    {
        return $this->repository->hapus($pengguna, $notifikasiId);
    }

    /**
     * Bersihkan notifikasi yatim (laporan sudah tidak ada).
     */
    public function bersihkanNotifikasiYatim(?Pengguna $pengguna = null): int
    {
        return $this->repository->bersihkanNotifikasiYatim($pengguna);
    }

    /**
     * Tandai satu notifikasi sebagai dibaca.
     */
    public function tandaiDibaca(Pengguna $pengguna, string $notifikasiId): bool
    {
        return $this->repository->tandaiDibaca($pengguna, $notifikasiId);
    }

    /**
     * Tandai semua notifikasi sebagai dibaca.
     */
    public function tandaiSemuaDibaca(Pengguna $pengguna): int
    {
        return $this->repository->tandaiSemuaDibaca($pengguna);
    }
}
