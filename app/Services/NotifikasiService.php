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
     * @return LengthAwarePaginator<NotifikasiData>
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
     *
     * @return string|null URL tujuan, atau null jika notifikasi tidak ditemukan.
     */
    public function bacaDanArahkan(Pengguna $pengguna, string $notifikasiId): ?string
    {
        $notif = $this->repository->cari($pengguna, $notifikasiId);

        if (! $notif) {
            return null;
        }

        $this->repository->tandaiDibaca($pengguna, $notifikasiId);

        return $notif->urlTujuan;
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
