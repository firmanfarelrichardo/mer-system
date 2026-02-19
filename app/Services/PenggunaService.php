<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\PenggunaData;
use App\Models\Pengguna;
use App\Repositories\PenggunaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Service Pengguna — logika bisnis manajemen akun pengguna.
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
     * @return LengthAwarePaginator<Pengguna>
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
     | Command — Buat Pengguna
     | ----------------------------------------------------------------*/

    /**
     * Buat pengguna baru beserta assignment perannya.
     */
    public function buat(PenggunaData $dto): Pengguna
    {
        return DB::transaction(function () use ($dto): Pengguna {
            $pengguna = $this->repository->buat([
                'tenant_id'    => $dto->tenant_id,
                'unit_id'      => $dto->unit_id,
                'nomor_induk'  => $dto->nomor_induk,
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
     | Command — Perbarui Pengguna
     | ----------------------------------------------------------------*/

    /**
     * Perbarui data pengguna yang sudah ada.
     */
    public function perbarui(Pengguna $pengguna, PenggunaData $dto): Pengguna
    {
        return DB::transaction(function () use ($pengguna, $dto): Pengguna {
            $dataLama = $pengguna->load('peran')->toArray();

            $atribut = [
                'nama_lengkap' => $dto->nama_lengkap,
                'nomor_induk'  => $dto->nomor_induk,
                'email'        => $dto->email,
                'nomor_hp'     => $dto->nomor_hp,
                'alamat'       => $dto->alamat,
                'unit_id'      => $dto->unit_id,
                'is_aktif'     => $dto->is_aktif,
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
     | Command — Ubah Status Aktif (Soft-toggle)
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
     | Command — Reset Kata Sandi
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
}
