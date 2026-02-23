<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * DTO: NotifikasiData
 *
 * Merepresentasikan satu notifikasi yang siap ditampilkan di view.
 * Dihasilkan dari NotifikasiRepository dan dikonsumsi oleh view layer.
 */
final readonly class NotifikasiData
{
    public function __construct(
        public string  $id,
        public string  $judul,
        public string  $pesan,
        public string  $tipe,
        public string  $ikonWarna,
        public string  $urlTujuan,
        public ?string $nomorLaporan,
        public ?int    $insidenId,
        public bool    $dibaca,
        public string  $waktuRelatif,
        public string  $waktuLengkap,
    ) {}
}
