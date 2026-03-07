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
 *    2. Jalankan: php artisan db:seed --class=PenelitiSeeder --force
 *       ATAU hapus manual via UI Admin > Manajemen Pengguna.
 *    3. Hapus (atau comment-out) blok Gate::before di AppServiceProvider
 *       jika bypass tidak lagi diperlukan.
 *
 *  Kredensial login akun peneliti:
 * ┌──────────────────────────┬────────────────────┬────────────────────────────┐
 * │ Field                    │ Nilai              │ Keterangan                 │
 * ├──────────────────────────┼────────────────────┼────────────────────────────┤
 * │ nomor_induk (username)   │ 198012312005011001 │ NIP dosen pembimbing       │
 * │ kata_sandi (password)    │ Peneliti@2026!     │ Ganti sebelum demo/serah   │
 * │ Peran database           │ Direktur           │ Hanya sebagai fallback UI  │
 * └──────────────────────────┴────────────────────┴────────────────────────────┘
 *
 *  Catatan: Peran "Direktur" di atas hanya berfungsi sebagai label UI
 *  dan penentu routing dashboard. Akses sesungguhnya diberikan oleh
 *  Gate::before berdasarkan nomor_induk, bukan oleh peran ini.
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
    public const NIP_PENELITI = '198012312005011001';

    public function run(): void
    {
        // ── 1. Ambil tenant default ────────────────────────────────────────
        // Gunakan firstOrFail() agar seeder gagal dengan pesan jelas jika
        // OrganisasiSeeder belum dijalankan (mencegah silent data inconsistency).
        $tenant = Organisasi::where('kode_organisasi', 'default')->firstOrFail();

        // ── 2. Ambil peran Direktur untuk keperluan routing dashboard & UI ─
        // Peneliti mendapatkan akses "Direktur" di UI (monitoring read-only),
        // namun Gate::before yang sesungguhnya memberikan bypas universal.
        $peranDirektur = Peran::where([
            'tenant_id'  => $tenant->id,
            'nama_peran' => Peran::DIREKTUR,
        ])->firstOrFail();

        // ── 3. Buat atau perbarui akun peneliti (idempotent) ───────────────
        // Menggunakan updateOrCreate sehingga aman dijalankan berulang kali,
        // termasuk saat me-refresh database lingkungan staging.
        $peneliti = Pengguna::updateOrCreate(
            // Kunci pencarian: nomor_induk unik dalam satu tenant.
            [
                'tenant_id'   => $tenant->id,
                'nomor_induk' => self::NIP_PENELITI,
            ],
            // Nilai yang di-set / di-update.
            [
                'nama_lengkap' => 'Prof. Dr. Dosen Pembimbing, M.Kes.',
                'email'        => 'peneliti.mer@universitas.ac.id',
                'nomor_hp'     => '08119999001',
                'alamat'       => 'Kampus Universitas, Gedung Kesehatan',
                'jabatan'      => 'Peneliti / Dosen Pembimbing',
                // Kata sandi di-hash otomatis oleh cast 'hashed' pada model Pengguna.
                // PENTING: Ganti kata sandi ini sebelum deployment ke lingkungan produksi.
                'kata_sandi'   => 'Peneliti@2026!',
                'is_aktif'     => true,
            ],
        );

        // ── 4. Tetapkan peran Direktur ke akun peneliti ───────────────────
        // syncWithoutDetaching: menambahkan peran jika belum ada,
        // tanpa mencabut peran lain yang mungkin sudah ada.
        $peneliti->peran()->syncWithoutDetaching([$peranDirektur->id]);

        // ── 5. Informasi ke console ────────────────────────────────────────
        $this->command->info('✓ Akun peneliti berhasil dibuat/diperbarui.');
        $this->command->line("  NIP (username) : " . self::NIP_PENELITI);
        $this->command->line("  Nama           : {$peneliti->nama_lengkap}");
        $this->command->line("  Peran database : Direktur (bypass via Gate::before)");
        $this->command->warn('  ⚠  Pastikan PENELITI_NIP=' . self::NIP_PENELITI . ' sudah ada di .env');
        $this->command->warn('  ⚠  Hapus PENELITI_NIP dari .env setelah penelitian selesai.');
    }
}
