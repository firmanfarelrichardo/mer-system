<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('DROP TABLE IF EXISTS "pelaporan"."detail_pasien" CASCADE');
        Schema::create('pelaporan.detail_pasien', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->default(DB::raw('gen_random_uuid()'))->unique();

            $table->foreignId('insiden_id')->unique()->index()->constrained('pelaporan.insiden')->cascadeOnDelete();

            $table->string('nama_pasien', 255);
            $table->string('nomor_rekam_medis', 100);

            $table->string('obat_terkait', 255)->nullable();
            $table->string('dokter_penulis_resep', 255)->nullable();

            $table->text('kronologi');
            $table->text('tindakan_awal')->nullable();
            $table->jsonb('jenis_kesalahan')->nullable();
            $table->jsonb('cedera')->nullable();
            $table->jsonb('faktor_penyebab')->nullable();
            $table->jsonb('intervensi_pasien')->nullable();
            $table->boolean('pernyataan_kronologi')->default(false);

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS "pelaporan"."detail_pasien" CASCADE');
    }
};
