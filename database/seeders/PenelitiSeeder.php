<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organisasi;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Database\Seeder;

/**
 * PenelitiSeeder — Akun akses sementara untuk peneliti/dosen pembimbing.
 *
 * =====================================================================
 *  PERHATIAN — KEAMANAN & SIKLUS HIDUP AKUN INI
 * =====================================================================
 *  Seeder ini membuat satu akun khusus untuk monitoring sistem selama
 *  fase penelitian. Akses universal diberikan melalui peran "Peneliti"
 *  di database — Gate::before di AppServiceProvider mengecek peran ini
 *  via Pengguna::isPeneliti().
 *
 *  Prosedur penghapusan akses setelah penelitian selesai:
 *    Hapus akun via UI Admin > Manajemen Pengguna.
 *    Semua akses universal otomatis tercabut — tidak perlu mengubah
 *    file .env, kode, atau konfigurasi server.
 *
 *  Kredensial login akun peneliti:
 * ┌──────────────────────────┬────────────────────┬────────────────────────────┐
 * │ Field                    │ Nilai              │ Keterangan                 │
 * ├──────────────────────────┼────────────────────┼────────────────────────────┤
 * │ nomor_induk (username)   │ dosenpeneliti      │ NIP dosen pembimbing       │
 * │ kata_sandi (password)    │ Peneliti@2026!     │ Ganti sebelum demo/serah   │
 * │ Peran database           │ Peneliti           │ Label UI: "Dosen Peneliti" │
 * └──────────────────────────┴────────────────────┴────────────────────────────┘
 * =====================================================================
 *
 * Cara menjalankan seeder ini secara mandiri:
 *   php artisan db:seed --class=PenelitiSeeder
 */
class PenelitiSeeder extends Seeder
{
    /**
     * NIP peneliti — identifier untuk akun peneliti.
     */
    public const NIP_PENELITI = 'dosenpeneliti';

    public function run(): void
    {
        // ── 1. Ambil tenant default ────────────────────────────────────────
        $tenant = Organisasi::where('kode_organisasi', 'default')->firstOrFail();

        // ── 2. Buat peran "Peneliti" jika belum ada (idempotent) ──────────
        // Peran ini menjadi sumber kebenaran untuk akses universal peneliti.
        // Gate::before di AppServiceProvider mengecek peran ini via isPeneliti().
        $peranPeneliti = Peran::firstOrCreate(
            [
                'tenant_id'  => $tenant->id,
                'nama_peran' => Peran::PENELITI,
            ],
        );

        // ── 3. Buat atau perbarui akun peneliti (idempotent) ───────────────
        $peneliti = Pengguna::updateOrCreate(
            [
                'tenant_id'   => $tenant->id,
                'nomor_induk' => self::NIP_PENELITI,
            ],
            [
                'nama_lengkap' => 'Dosen Peneliti',
                'email'        => 'peneliti.mer@universitas.ac.id',
                'nomor_hp'     => '08119999001',
                'alamat'       => 'Kampus Universitas, Gedung Kesehatan',
                'jabatan'      => 'Dosen Peneliti',
                'kata_sandi'   => 'Peneliti@2026!',
                'is_aktif'     => true,
            ],
        );

        // ── 4. Tetapkan peran Peneliti ke akun ────────────────────────────
        // sync() memastikan HANYA peran Peneliti yang terpasang,
        // menggantikan peran lama (mis. Direktur) jika seeder dijalankan ulang.
        $peneliti->peran()->sync([$peranPeneliti->id]);

        // ── 5. Informasi ke console ────────────────────────────────────────
        $this->command->info('✓ Akun peneliti berhasil dibuat/diperbarui.');
        $this->command->line("  NIP (username) : " . self::NIP_PENELITI);
        $this->command->line("  Nama           : {$peneliti->nama_lengkap}");
        $this->command->line("  Peran database : Peneliti (akses universal via Gate::before)");
        $this->command->warn('  ⚠  Hapus akun ini via UI Admin setelah penelitian selesai.');
    }
}
