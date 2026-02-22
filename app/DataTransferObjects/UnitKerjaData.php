<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * DTO untuk membuat atau memperbarui data Unit Kerja.
 *
 * Immutable value-object — sekali dibuat, tidak bisa diubah.
 */
final readonly class UnitKerjaData
{
    public function __construct(
        public string  $kode_unit,
        public string  $nama_unit,
        public ?string $keterangan,
        public int     $tenant_id,
    ) {}

    /**
     * Buat instance dari data request yang sudah tervalidasi.
     *
     * @param  array<string, mixed> $data
     */
    public static function dariArray(array $data, int $tenantId): self
    {
        return new self(
            kode_unit:  $data['kode_unit'],
            nama_unit:  $data['nama_unit'],
            keterangan: $data['keterangan'] ?? null,
            tenant_id:  $tenantId,
        );
    }
}
