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
 *  fase penelitian. Akun ini memiliki hak akses universal melalui
 *  mekanisme Gate::before di AppServiceProvider, bukan melalui peran
 *  database — sehingga aman dihapus tanpa mengubah skema apapun.
 *
 *  Prosedur penghapusan akses setelah penelitian selesai:
 *    1. Hapus baris PENELITI_NIP dari file .env (atau kosongkan nilainya).
 *    2. Hapus manual via UI Admin > Manajemen Pengguna,
 *       ATAU jalankan query: DELETE FROM akun.pengguna WHERE nomor_induk = '...';
 *    3. Hapus (atau comment-out) blok Gate::before di AppServiceProvider
 *       jika bypass tidak lagi diperlukan.
 *
 *  Kredensial login akun peneliti:
 * ┌──────────────────────────┬────────────────────┬────────────────────────────┐
 * │ Field                    │ Nilai              │ Keterangan                 │
 * ├──────────────────────────┼────────────────────┼────────────────────────────┤
 * │ nomor_induk (username)   │ dosenpeneliti      │ NIP dosen pembimbing       │
 * │ kata_sandi (password)    │ Peneliti@2026!     │ Ganti sebelum demo/serah   │
 * │ Peran database           │ Peneliti           │ Label UI: "Dosen Peneliti" │
 * └──────────────────────────┴────────────────────┴────────────────────────────┘
 *
 *  Catatan: Peran "Peneliti" di atas berfungsi sebagai label UI dan penentu
 *  routing sidebar/dashboard. Akses sesungguhnya diberikan oleh Gate::before
 *  berdasarkan nomor_induk, bukan oleh peran ini.
 * =====================================================================
 *
 * Cara menjalankan seeder ini secara mandiri:
 *   php artisan db:seed --class=PenelitiSeeder
 */
class PenelitiSeeder extends Seeder
{
    /**
     * NIP peneliti — harus sama persis dengan nilai PENELITI_NIP di .env.
     * Didefinisikan di sini sebagai konstanta agar mudah diubah di satu tempat.
     */
    public const NIP_PENELITI = 'dosenpeneliti';

    public function run(): void
    {
        // ── 1. Ambil tenant default ────────────────────────────────────────
        $tenant = Organisasi::where('kode_organisasi', 'default')->firstOrFail();

        // ── 2. Buat peran "Peneliti" jika belum ada (idempotent) ──────────
        // Peran ini hanya digunakan sebagai label UI dan penentu routing.
        // Akses sesungguhnya diberikan oleh Gate::before di AppServiceProvider.
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
        $this->command->line("  Peran database : Peneliti (akses via Gate::before)");
        $this->command->warn('  ⚠  Pastikan PENELITI_NIP=' . self::NIP_PENELITI . ' sudah ada di .env');
        $this->command->warn('  ⚠  Hapus PENELITI_NIP dari .env setelah penelitian selesai.');
    }
}
