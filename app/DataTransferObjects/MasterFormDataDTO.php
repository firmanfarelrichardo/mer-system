<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * DTO untuk membuat atau memperbarui master data formulir
 * (Tipe Cedera, Faktor Penyebab, Tindakan Intervensi).
 *
 * Immutable value-object - sekali dibuat, tidak bisa diubah.
 */
final readonly class MasterFormDataDTO
{
    public function __construct(
        public string $nama,
        public bool   $isAktif,
        public int    $tenantId,
    ) {}

    /**
     * Buat instance dari data request yang sudah tervalidasi.
     *
     * @param  array<string, mixed> $data
     */
    public static function dariArray(array $data, int $tenantId): self
    {
        return new self(
            nama:     $data['nama'],
            isAktif:  (bool) ($data['is_aktif'] ?? true),
            tenantId: $tenantId,
        );
    }
}
