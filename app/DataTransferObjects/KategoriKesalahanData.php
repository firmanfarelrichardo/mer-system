<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * DTO untuk membuat atau memperbarui data Kategori Kesalahan.
 *
 * Immutable value-object — sekali dibuat, tidak bisa diubah.
 */
final readonly class KategoriKesalahanData
{
    public function __construct(
        public string $nama_kategori,
        public int    $tenant_id,
    ) {}

    /**
     * Buat instance dari data request yang sudah tervalidasi.
     *
     * @param  array<string, mixed> $data
     */
    public static function dariArray(array $data, int $tenantId): self
    {
        return new self(
            nama_kategori: $data['nama_kategori'],
            tenant_id:     $tenantId,
        );
    }
}
