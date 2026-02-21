<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perbarui skema pelaporan untuk mendukung formulir multi-step
 * dan alur tindak lanjut oleh Kepala Ruangan / Komite.
 *
 * Perubahan:
 *   1. pelaporan.insiden      — tambah kolom detail pelapor & status baca
 *   2. pelaporan.detail_pasien — tambah kolom klasifikasi (JSONB)
 *   3. pelaporan.tindak_lanjut — buat tabel histori umpan balik
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. pelaporan.insiden ──────────────────────────────────────

        // Jadikan unit_id nullable; nama unit disimpan sebagai plain string
        // agar laporan tetap dapat dibuat tanpa FK ke master.unit_kerja.
        DB::statement('ALTER TABLE "pelaporan"."insiden" ALTER COLUMN "unit_id" DROP NOT NULL');

        Schema::table('pelaporan.insiden', function (Blueprint $table) {
            $table->string('nama_unit_kerja', 255)->nullable()->after('unit_id');
            $table->string('fase_kesalahan', 50)->nullable()->after('tipe_insiden');
            $table->string('nama_pelapor', 255)->nullable()->after('tgl_lapor');
            $table->string('kontak_pelapor', 255)->nullable()->after('nama_pelapor');
            $table->boolean('is_anonim')->default(true)->after('kontak_pelapor');
            $table->boolean('sudah_dibaca')->default(false)->after('is_anonim');
        });

        // ── 2. pelaporan.detail_pasien ───────────────────────────────

        Schema::table('pelaporan.detail_pasien', function (Blueprint $table) {
            $table->jsonb('jenis_kesalahan')->nullable()->after('tindakan_awal');
            $table->jsonb('cedera')->nullable()->after('jenis_kesalahan');
            $table->jsonb('faktor_penyebab')->nullable()->after('cedera');
            $table->jsonb('intervensi_pasien')->nullable()->after('faktor_penyebab');
            $table->boolean('pernyataan_kronologi')->default(false)->after('intervensi_pasien');
        });

        // ── 3. pelaporan.tindak_lanjut ───────────────────────────────
        // Menyimpan catatan tindak lanjut (feedback) yang diberikan oleh
        // Kepala Ruangan atau Komite. Satu insiden bisa memiliki banyak
        // catatan (append-only — tidak ada update/delete).

        Schema::create('pelaporan.tindak_lanjut', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')
                ->default(DB::raw('gen_random_uuid()'))
                ->unique();

            $table->foreignId('insiden_id')
                ->index()
                ->constrained('pelaporan.insiden')
                ->cascadeOnDelete();

            $table->foreignId('pengguna_id')
                ->index()
                ->constrained('akun.pengguna');

            // Status baru yang ditetapkan saat tindak lanjut ini dibuat.
            $table->string('status_baru', 50);

            // Catatan umpan balik dari pejabat terkait.
            $table->text('catatan');

            $table->timestamp('created_at')->useCurrent();
            // Tidak ada updated_at — tabel ini bersifat append-only.
        });
    }

    public function down(): void
    {
        // Hapus tabel tindak_lanjut terlebih dahulu (ada FK ke insiden).
        DB::statement('DROP TABLE IF EXISTS "pelaporan"."tindak_lanjut" CASCADE');

        Schema::table('pelaporan.detail_pasien', function (Blueprint $table) {
            $table->dropColumn([
                'jenis_kesalahan',
                'cedera',
                'faktor_penyebab',
                'intervensi_pasien',
                'pernyataan_kronologi',
            ]);
        });

        Schema::table('pelaporan.insiden', function (Blueprint $table) {
            $table->dropColumn([
                'nama_unit_kerja',
                'fase_kesalahan',
                'nama_pelapor',
                'kontak_pelapor',
                'is_anonim',
                'sudah_dibaca',
            ]);
        });

        // Catatan: tidak mengembalikan unit_id ke NOT NULL karena
        // data laporan yang sudah ada menggunakan nama_unit_kerja (nullable).
    }
};
