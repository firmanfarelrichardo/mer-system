<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Http\Requests\SimpanLaporanRequest;

/**
 * DTO Insiden — memindahkan data form ke layer service secara immutable.
 *
 * Properti `$isDraft` di-mapping dari parameter `action` pada request.
 * Properti `$insidenId` diisi jika Nakes melanjutkan draf (update existing).
 */
final readonly class InsidenData
{
    public function __construct(
        public bool    $isDraft,
        public ?int    $insidenId,
        public string  $unitKerja,
        public string  $tanggalKejadian,
        public ?string $waktuKejadian,
        public ?string $jenisInsiden,
        public ?string $faseKesalahan,
        public ?string $namaPasien,
        public ?string $nomorRekamMedis,
        public ?string $namaObat,
        public ?string $kronologiKejadian,
        public ?string $namaPelapor,
        public ?string $kontakPelapor,
        public ?array  $jenisKesalahan,
        public ?string $jenisKesalahanLainnya,
        public ?array  $cedera,
        public ?string $cederaLainnya,
        public ?array  $faktorPenyebab,
        public ?string $faktorPenyebabLainnya,
        public ?array  $intervensiPasien,
        public ?string $intervensiPasienLainnya,
        public bool    $pernyataanKronologi = false,
    ) {}

    /**
     * Buat DTO dari SimpanLaporanRequest.
     */
    public static function fromRequest(SimpanLaporanRequest $request): self
    {
        $data = $request->validated();

        return new self(
            isDraft:                 $request->isDraf(),
            insidenId:               isset($data['insiden_id']) ? (int) $data['insiden_id'] : null,
            unitKerja:               $data['unit_kerja'],
            tanggalKejadian:         $data['tanggal_kejadian'],
            waktuKejadian:           $data['waktu_kejadian'] ?? null,
            jenisInsiden:            $data['jenis_insiden'] ?? null,
            faseKesalahan:           $data['fase_kesalahan'] ?? null,
            namaPasien:              $data['nama_pasien'] ?? null,
            nomorRekamMedis:         $data['nomor_rekam_medis'] ?? null,
            namaObat:                $data['nama_obat'] ?? null,
            kronologiKejadian:       $data['kronologi_kejadian'] ?? null,
            namaPelapor:             $data['nama_pelapor'] ?? null,
            kontakPelapor:           $data['kontak_pelapor'] ?? null,
            jenisKesalahan:          $data['jenis_kesalahan'] ?? null,
            jenisKesalahanLainnya:   $data['jenis_kesalahan_lainnya'] ?? null,
            cedera:                  $data['cedera'] ?? null,
            cederaLainnya:           $data['cedera_lainnya'] ?? null,
            faktorPenyebab:          $data['faktor_penyebab'] ?? null,
            faktorPenyebabLainnya:   $data['faktor_penyebab_lainnya'] ?? null,
            intervensiPasien:        $data['intervensi_pasien'] ?? null,
            intervensiPasienLainnya: $data['intervensi_pasien_lainnya'] ?? null,
            pernyataanKronologi:     ! empty($data['pernyataan_kronologi']),
        );
    }

    /**
     * Gabungkan array checkbox utama dengan nilai "lainnya".
     */
    public function jenisKesalahanGabungan(): array
    {
        $hasil = $this->jenisKesalahan ?? [];
        if ($this->jenisKesalahanLainnya) {
            $hasil[] = $this->jenisKesalahanLainnya;
        }

        return $hasil;
    }

    public function cederaGabungan(): array
    {
        $hasil = $this->cedera ?? [];
        if ($this->cederaLainnya) {
            $hasil[] = $this->cederaLainnya;
        }

        return $hasil;
    }

    public function faktorPenyebabGabungan(): array
    {
        $hasil = $this->faktorPenyebab ?? [];
        if ($this->faktorPenyebabLainnya) {
            $hasil[] = $this->faktorPenyebabLainnya;
        }

        return $hasil;
    }

    public function intervensiPasienGabungan(): array
    {
        $hasil = $this->intervensiPasien ?? [];
        if ($this->intervensiPasienLainnya) {
            $hasil[] = $this->intervensiPasienLainnya;
        }

        return $hasil;
    }
}
