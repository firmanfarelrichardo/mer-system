<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom-kolom detail baru ke tabel pelaporan.insiden
 * dan pelaporan.detail_pasien untuk mendukung formulir multi-step.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── pelaporan.insiden ─────────────────────────────────────────

        // Buat unit_id nullable (saat unit diisi berdasarkan nama, bukan FK)
        DB::statement('ALTER TABLE "pelaporan"."insiden" ALTER COLUMN "unit_id" DROP NOT NULL');

        Schema::table('pelaporan.insiden', function (Blueprint $table) {
            $table->string('nama_unit_kerja', 255)->nullable()->after('unit_id');
            $table->string('fase_kesalahan', 50)->nullable()->after('tipe_insiden');
            $table->string('nama_pelapor', 255)->nullable()->after('tgl_lapor');
            $table->string('kontak_pelapor', 255)->nullable()->after('nama_pelapor');
            $table->boolean('is_anonim')->default(true)->after('kontak_pelapor');
            $table->boolean('sudah_dibaca')->default(false)->after('is_anonim');
        });

        // ── pelaporan.detail_pasien ──────────────────────────────────

        Schema::table('pelaporan.detail_pasien', function (Blueprint $table) {
            $table->jsonb('jenis_kesalahan')->nullable()->after('tindakan_awal');
            $table->jsonb('cedera')->nullable()->after('jenis_kesalahan');
            $table->jsonb('faktor_penyebab')->nullable()->after('cedera');
            $table->jsonb('intervensi_pasien')->nullable()->after('faktor_penyebab');
            $table->boolean('pernyataan_kronologi')->default(false)->after('intervensi_pasien');
        });
    }

    public function down(): void
    {
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

        DB::statement('ALTER TABLE "pelaporan"."insiden" ALTER COLUMN "unit_id" SET NOT NULL');
    }
};
