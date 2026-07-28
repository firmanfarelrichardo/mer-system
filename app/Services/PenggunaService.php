<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\PenggunaData;
use App\Models\Pengguna;
use App\Repositories\PenggunaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service Pengguna - logika bisnis manajemen akun pengguna.
 *
 * Menjembatani Controller ↔ Repository dengan menambahkan:
 *   - Validasi bisnis
 *   - Transaksi database
 *   - Audit logging
 */
class PenggunaService
{
    public function __construct(
        private readonly PenggunaRepository $repository,
        private readonly AuditLogService    $auditLog,
    ) {}

    /* ------------------------------------------------------------------
     | Query
     | ----------------------------------------------------------------*/

    /**
     * Daftar pengguna dengan paginasi dan filter (delegasi ke repository).
     *
     * @param  int                  $tenantId
     * @param  array<string,mixed>  $filter
     * @param  int                  $perHalaman
     * @return LengthAwarePaginator<int, Pengguna>
     */
    public function daftar(int $tenantId, array $filter = [], int $perHalaman = 15): LengthAwarePaginator
    {
        return $this->repository->daftarDenganFilter($tenantId, $filter, $perHalaman);
    }

    /**
     * Cari pengguna berdasarkan ID.
     */
    public function cariBerdasarkanId(int $id): ?Pengguna
    {
        return $this->repository->cariBerdasarkanId($id);
    }

    /* ------------------------------------------------------------------
     | Command - Buat Pengguna
     | ----------------------------------------------------------------*/

    /**
     * Buat pengguna baru beserta assignment perannya.
     */
    public function buat(PenggunaData $dto): Pengguna
    {
        return DB::transaction(function () use ($dto): Pengguna {
            $unitId = $dto->unit_id;
            
            // Periksa peran jika mengandung Admin, Komite, atau Direktur, set unit_id ke null
            if (! empty($dto->peran_ids)) {
                $namaPeranTerpilih = \App\Models\Peran::whereIn('id', $dto->peran_ids)->pluck('nama_peran')->toArray();
                if (count(array_intersect([\App\Models\Peran::ADMIN, \App\Models\Peran::KOMITE, \App\Models\Peran::DIREKTUR], $namaPeranTerpilih)) > 0) {
                    $unitId = null;
                }
            }

            $pengguna = $this->repository->buat([
                'tenant_id'    => $dto->tenant_id,
                'unit_id'      => $unitId,
                'nomor_induk'  => $dto->nomor_induk,
                'username'     => $dto->username ?: null,
                'email'        => $dto->email,
                'nomor_hp'     => $dto->nomor_hp,
                'alamat'       => $dto->alamat,
                'nama_lengkap' => $dto->nama_lengkap,
                'kata_sandi'   => $dto->kata_sandi,
                'is_aktif'     => $dto->is_aktif,
            ]);

            if (! empty($dto->peran_ids)) {
                $this->repository->sinkronPeran($pengguna, $dto->peran_ids);
            }

            $this->auditLog->catat(
                namaTabel: 'akun.pengguna',
                aksi:      'CREATE',
                idData:    $pengguna->id,
                dataBaru:  $pengguna->fresh(['peran'])->toArray(),
            );

            return $pengguna->load('peran', 'unitKerja');
        });
    }

    /* ------------------------------------------------------------------
     | Command - Perbarui Pengguna
     | ----------------------------------------------------------------*/

    /**
     * Perbarui data pengguna yang sudah ada.
     */
    public function perbarui(Pengguna $pengguna, PenggunaData $dto): Pengguna
    {
        return DB::transaction(function () use ($pengguna, $dto): Pengguna {
            $dataLama = $pengguna->load('peran')->toArray();

            $unitId = $dto->unit_id;
            
            // Periksa peran jika mengandung Admin, Komite, atau Direktur, set unit_id ke null
            if (! empty($dto->peran_ids)) {
                $namaPeranTerpilih = \App\Models\Peran::whereIn('id', $dto->peran_ids)->pluck('nama_peran')->toArray();
                if (count(array_intersect([\App\Models\Peran::ADMIN, \App\Models\Peran::KOMITE, \App\Models\Peran::DIREKTUR], $namaPeranTerpilih)) > 0) {
                    $unitId = null;
                }
            }

            $atribut = [
                'nama_lengkap' => $dto->nama_lengkap,
                'nomor_induk'  => $dto->nomor_induk,
                'email'        => $dto->email,
                'nomor_hp'     => $dto->nomor_hp,
                'alamat'       => $dto->alamat,
                'unit_id'      => $unitId,
                'is_aktif'     => $dto->is_aktif,
                'username'     => $dto->username ?: null,
            ];

            // Hanya perbarui kata sandi jika diisi
            if ($dto->kata_sandi !== null && $dto->kata_sandi !== '') {
                $atribut['kata_sandi'] = $dto->kata_sandi;
            }

            $pengguna = $this->repository->perbarui($pengguna, $atribut);

            if (! empty($dto->peran_ids)) {
                $this->repository->sinkronPeran($pengguna, $dto->peran_ids);
            }

            $this->auditLog->catat(
                namaTabel: 'akun.pengguna',
                aksi:      'UPDATE',
                idData:    $pengguna->id,
                dataLama:  $dataLama,
                dataBaru:  $pengguna->fresh(['peran'])->toArray(),
            );

            return $pengguna->load('peran', 'unitKerja');
        });
    }

    /* ------------------------------------------------------------------
     | Command - Perbarui Profil Mandiri (self-service)
     | ----------------------------------------------------------------*/

    /**
     * Perbarui data profil oleh pengguna sendiri.
     *
     * Hanya field yang diizinkan untuk self-service yang diperbarui:
     * nomor_hp, jabatan, dan tanggal_bergabung_unit.
     * Field sensitif (nomor_induk, unit_id, peran) hanya bisa diubah Admin.
     *
     * @param  array{nama_lengkap?: string, username?: string|null, email?: string, nomor_hp?: string|null, jabatan?: string|null, tanggal_bergabung_unit?: string|null}  $data
     */
    public function perbaruiProfil(Pengguna $pengguna, array $data): Pengguna
    {
        $dataLama = $pengguna->only(['nama_lengkap', 'username', 'email', 'nomor_hp', 'jabatan', 'tanggal_bergabung_unit']);

        $atribut = [
            'nama_lengkap'           => $data['nama_lengkap'] ?? $pengguna->nama_lengkap,
            'username'               => $data['username'] ?: null,
            'email'                  => $data['email'] ?? $pengguna->email,
            'nomor_hp'               => $data['nomor_hp'] ?? null,
            'jabatan'                => $data['jabatan'] ?? null,
            'tanggal_bergabung_unit' => $data['tanggal_bergabung_unit'] ?? null,
        ];

        $pengguna = $this->repository->perbarui($pengguna, $atribut);

        $this->auditLog->catat(
            namaTabel: 'akun.pengguna',
            aksi:      'UPDATE_PROFIL',
            idData:    $pengguna->id,
            dataLama:  $dataLama,
            dataBaru:  $pengguna->fresh()->only(['nama_lengkap', 'username', 'email', 'nomor_hp', 'jabatan', 'tanggal_bergabung_unit']),
        );

        return $pengguna->load('peran', 'unitKerja');
    }

    /* ------------------------------------------------------------------
     | Command - Ubah Status Aktif (Soft-toggle)
     | ----------------------------------------------------------------*/

    /**
     * Non-aktifkan atau aktifkan kembali akun pengguna.
     */
    public function ubahStatusAktif(Pengguna $pengguna, bool $aktif): Pengguna
    {
        $dataLama = ['is_aktif' => $pengguna->is_aktif];

        $pengguna = $this->repository->perbarui($pengguna, ['is_aktif' => $aktif]);

        $this->auditLog->catat(
            namaTabel: 'akun.pengguna',
            aksi:      $aktif ? 'ACTIVATE' : 'DEACTIVATE',
            idData:    $pengguna->id,
            dataLama:  $dataLama,
            dataBaru:  ['is_aktif' => $aktif],
        );

        return $pengguna;
    }

    /* ------------------------------------------------------------------
     | Command - Reset Kata Sandi
     | ----------------------------------------------------------------*/

    /**
     * Reset kata sandi pengguna ke nilai baru.
     */
    public function resetKataSandi(Pengguna $pengguna, string $kataSandiBaru): Pengguna
    {
        $pengguna = $this->repository->perbarui($pengguna, [
            'kata_sandi' => $kataSandiBaru,
        ]);

        $this->auditLog->catat(
            namaTabel: 'akun.pengguna',
            aksi:      'RESET_PASSWORD',
            idData:    $pengguna->id,
        );

        return $pengguna;
    }

    /* ------------------------------------------------------------------
     | Command - Reset Kata Sandi Darurat (Emergency Password Reset)
     | ----------------------------------------------------------------*/

    /**
     * Reset sandi pengguna ke sandi acak sementara yang aman secara kriptografi.
     *
     * Alur:
     *  1. Generate sandi acak 8 karakter (huruf besar, kecil, angka, simbol).
     *  2. Update DB: kata_sandi = hashed, wajib_ganti_sandi = true.
     *  3. Catat ke audit log.
     *  4. Return sandi acak (plain text) untuk ditampilkan 1 kali ke Admin.
     *
     * @throws \Throwable
     */
    public function resetSandiDarurat(int $penggunaId): string
    {
        $pengguna = $this->repository->cariBerdasarkanId($penggunaId);

        if (! $pengguna) {
            throw new \InvalidArgumentException("Pengguna dengan ID {$penggunaId} tidak ditemukan.");
        }

        return DB::transaction(function () use ($pengguna): string {
            // Generate sandi acak 8 karakter yang aman secara kriptografi
            $sandiAcak = $this->generateSandiAcak(8);

            $this->repository->perbarui($pengguna, [
                'kata_sandi'        => $sandiAcak,
                'wajib_ganti_sandi' => true,
            ]);

            $this->auditLog->catat(
                namaTabel: 'akun.pengguna',
                aksi:      'EMERGENCY_RESET',
                idData:    $pengguna->id,
                dataBaru:  [
                    'deskripsi' => "Admin mereset kata sandi darurat untuk pengguna ID: {$pengguna->id} ({$pengguna->nama_lengkap})",
                ],
            );

            Log::info('Admin melakukan reset sandi darurat.', [
                'pengguna_id'   => $pengguna->id,
                'nama_lengkap'  => $pengguna->nama_lengkap,
            ]);

            return $sandiAcak;
        });
    }

    /**
     * Selesaikan proses ganti sandi paksa setelah emergency reset.
     *
     * @throws \InvalidArgumentException
     * @throws \Throwable
     */
    public function selesaikanGantiSandiPaksa(Pengguna $pengguna, string $kataSandiBaru): void
    {
        DB::transaction(function () use ($pengguna, $kataSandiBaru): void {
            $this->repository->perbarui($pengguna, [
                'kata_sandi'        => $kataSandiBaru,
                'wajib_ganti_sandi' => false,
            ]);

            $this->auditLog->catat(
                namaTabel: 'akun.pengguna',
                aksi:      'FORCE_CHANGE_PW',
                idData:    $pengguna->id,
                dataBaru:  [
                    'deskripsi' => 'Pengguna berhasil mengganti sandi darurat menjadi permanen',
                ],
                idPengguna: $pengguna->id,
            );
        });
    }

    /* ------------------------------------------------------------------
     | Statistik
     | ----------------------------------------------------------------*/

    /**
     * Ambil ringkasan statistik pengguna untuk dashboard.
     *
     * @return array{total: int, aktif: int, nonaktif: int}
     */
    public function statistik(int $tenantId): array
    {
        return [
            'total'    => $this->repository->hitungTotal($tenantId),
            'aktif'    => $this->repository->hitungAktif($tenantId),
            'nonaktif' => $this->repository->hitungNonaktif($tenantId),
        ];
    }

    /* ------------------------------------------------------------------
     | Private Helpers
     | ----------------------------------------------------------------*/

    /**
     * Generate sandi acak yang aman secara kriptografi.
     *
     * Menjamin minimal 1 huruf besar, 1 huruf kecil, 1 angka, 1 simbol,
     * lalu sisa karakter diisi secara acak dari seluruh pool.
     */
    private function generateSandiAcak(int $panjang): string
    {
        $hurufBesar = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $hurufKecil = 'abcdefghjkmnpqrstuvwxyz';
        $angka      = '23456789';
        $simbol     = '!@#$%&*?';

        // Jamin minimal 1 karakter dari setiap kategori
        $sandi = [
            $hurufBesar[random_int(0, strlen($hurufBesar) - 1)],
            $hurufKecil[random_int(0, strlen($hurufKecil) - 1)],
            $angka[random_int(0, strlen($angka) - 1)],
            $simbol[random_int(0, strlen($simbol) - 1)],
        ];

        // Isi sisa karakter dari gabungan seluruh pool
        $semuaKarakter = $hurufBesar . $hurufKecil . $angka . $simbol;
        for ($i = count($sandi); $i < $panjang; $i++) {
            $sandi[] = $semuaKarakter[random_int(0, strlen($semuaKarakter) - 1)];
        }

        // Acak urutan agar posisi karakter tidak terprediksi
        shuffle($sandi);

        return implode('', $sandi);
    }
}
