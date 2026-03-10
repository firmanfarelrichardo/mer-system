<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Http\Request;

/**
 * DTO untuk parameter filter log aktivitas.
 *
 * Immutable value-object: dibuat sekali, tidak berubah.
 * Menangkap semua parameter filter dari Request lalu
 * diteruskan ke Service → Repository tanpa coupling ke HTTP.
 */
final readonly class LogAktivitasFilterDTO
{
    /**
     * @param 'admin'|'pengguna' $tipePeran  Jenis log: aktivitas admin atau pengguna biasa
     */
    public function __construct(
        public int     $tenantId,
        public string  $tipePeran,
        public ?string $dariTanggal   = null,
        public ?string $sampaiTanggal = null,
        public ?string $cari          = null,
    ) {}

    /**
     * Factory: buat DTO dari HTTP Request + konteks.
     */
    public static function dariRequest(Request $request, int $tenantId, string $tipePeran): self
    {
        return new self(
            tenantId:      $tenantId,
            tipePeran:     $tipePeran,
            dariTanggal:   $request->input('dari_tanggal'),
            sampaiTanggal: $request->input('sampai_tanggal'),
            cari:          $request->input('cari'),
        );
    }

    /**
     * Apakah filter rentang waktu aktif?
     */
    public function adaFilterWaktu(): bool
    {
        return $this->dariTanggal !== null || $this->sampaiTanggal !== null;
    }
}
