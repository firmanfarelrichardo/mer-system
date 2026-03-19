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
        DB::statement('DROP TABLE IF EXISTS "pelaporan"."investigasi_rca" CASCADE');
        Schema::create('pelaporan.investigasi_rca', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->default(DB::raw('gen_random_uuid()'))->unique();

            $table->foreignId('insiden_id')->index()->constrained('pelaporan.insiden');
            $table->foreignId('investigator_id')->index()->constrained('akun.pengguna');

            $table->text('akar_masalah');
            $table->text('rekomendasi_sistem')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS "pelaporan"."investigasi_rca" CASCADE');
    }
};
