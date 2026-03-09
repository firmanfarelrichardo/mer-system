<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * DTO untuk membuat atau memperbarui data Pengguna.
 *
 * Immutable value-object — sekali dibuat, tidak bisa diubah.
 * Digunakan oleh Service Layer sebagai kontrak data yang bersih
 * antara Controller dan Repository.
 */
final readonly class PenggunaData
{
    public function __construct(
        public string  $nama_lengkap,
        public string  $nomor_induk,
        public ?string $email,
        public ?string $nomor_hp,
        public string  $alamat,
        public ?int    $unit_id,
        public ?string $kata_sandi,
        public bool    $is_aktif,
        public int     $tenant_id,
        public ?string $username = null,
        /** @var list<int> ID peran yang di-assign */
        public array   $peran_ids = [],
    ) {}

    /**
     * Buat instance dari data request yang sudah tervalidasi.
     *
     * @param  array<string, mixed> $data
     */
    public static function dariArray(array $data, int $tenantId): self
    {
        return new self(
            nama_lengkap: $data['nama_lengkap'],
            nomor_induk:  $data['nomor_induk'],
            email:        $data['email'] ?? null,
            nomor_hp:     $data['nomor_hp'] ?? null,
            alamat:       $data['alamat'] ?? '',
            unit_id:      isset($data['unit_id']) ? (int) $data['unit_id'] : null,
            kata_sandi:   $data['kata_sandi'] ?? null,
            is_aktif:     (bool) ($data['is_aktif'] ?? true),
            tenant_id:    $tenantId,
            username:     $data['username'] ?? null,
            peran_ids:    array_map('intval', $data['peran_ids'] ?? []),
        );
    }
}
