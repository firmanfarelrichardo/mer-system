<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buat tabel pelaporan.tindak_lanjut untuk menyimpan histori umpan balik
 * dari Kepala Ruangan atau Komite.
 *
 * Kolom-kolom baru pada pelaporan.insiden dan pelaporan.detail_pasien
 * telah digabungkan langsung ke migration pembuatan tabel masing-masing
 * (2026_02_16_000010 dan 2026_02_16_000011) agar setiap fresh migration
 * selalu berhasil tanpa ALTER TABLE.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP TABLE IF EXISTS "pelaporan"."tindak_lanjut" CASCADE');
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
        DB::statement('DROP TABLE IF EXISTS "pelaporan"."tindak_lanjut" CASCADE');
    }
};
