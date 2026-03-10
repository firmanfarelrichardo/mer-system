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
        DB::statement('DROP TABLE IF EXISTS "pelaporan"."penilaian_risiko" CASCADE');
        Schema::create('pelaporan.penilaian_risiko', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->default(DB::raw('gen_random_uuid()'))->unique();

            $table->foreignId('insiden_id')->index()->constrained('pelaporan.insiden');
            $table->foreignId('penilai_id')->index()->constrained('akun.pengguna');

            $table->foreignId('probabilitas_id')->index()->constrained('master.matriks_probabilitas');
            $table->foreignId('dampak_id')->index()->constrained('master.matriks_dampak');

            $table->string('warna_grading', 50);

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS "pelaporan"."penilaian_risiko" CASCADE');
    }
};
