<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Http\Requests\SimpanLaporanRequest;
use Illuminate\Http\Request;

/**
 * DTO Insiden — memindahkan data form ke layer service secara immutable.
 *
 * Properti `$isDraft` di-mapping dari parameter `action` pada request.
 * Properti `$insidenId` diisi jika Nakes melanjutkan draf (update existing).
 * Properti `$isAutoSave` menandakan request berasal dari auto-save background.
 */
final readonly class InsidenData
{
    public function __construct(
        public bool    $isDraft,
        public ?int    $insidenId,
        public ?string $unitKerja,
        public ?string $tanggalKejadian,
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
        public bool    $isAutoSave = false,
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
     * Buat DTO dari Request biasa (auto-save background).
     *
     * Tidak melakukan validasi ketat — menerima data parsial apa adanya.
     * Semua field nullable sehingga form bisa di-auto-save kapan saja.
     */
    public static function fromAutoSaveRequest(Request $request): self
    {
        return new self(
            isDraft:                 true,
            insidenId:               $request->filled('insiden_id') ? (int) $request->input('insiden_id') : null,
            unitKerja:               $request->input('unit_kerja') ?: null,
            tanggalKejadian:         $request->input('tanggal_kejadian') ?: null,
            waktuKejadian:           $request->input('waktu_kejadian') ?: null,
            jenisInsiden:            $request->input('jenis_insiden') ?: null,
            faseKesalahan:           $request->input('fase_kesalahan') ?: null,
            namaPasien:              $request->input('nama_pasien') ?: null,
            nomorRekamMedis:         $request->input('nomor_rekam_medis') ?: null,
            namaObat:                $request->input('nama_obat') ?: null,
            kronologiKejadian:       $request->input('kronologi_kejadian') ?: null,
            namaPelapor:             $request->input('nama_pelapor') ?: null,
            kontakPelapor:           $request->input('kontak_pelapor') ?: null,
            jenisKesalahan:          $request->input('jenis_kesalahan') ?: null,
            jenisKesalahanLainnya:   $request->input('jenis_kesalahan_lainnya') ?: null,
            cedera:                  $request->input('cedera') ?: null,
            cederaLainnya:           $request->input('cedera_lainnya') ?: null,
            faktorPenyebab:          $request->input('faktor_penyebab') ?: null,
            faktorPenyebabLainnya:   $request->input('faktor_penyebab_lainnya') ?: null,
            intervensiPasien:        $request->input('intervensi_pasien') ?: null,
            intervensiPasienLainnya: $request->input('intervensi_pasien_lainnya') ?: null,
            pernyataanKronologi:     ! empty($request->input('pernyataan_kronologi')),
            isAutoSave:              true,
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
