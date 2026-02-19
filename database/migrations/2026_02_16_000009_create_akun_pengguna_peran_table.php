<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('DROP TABLE IF EXISTS "akun"."pengguna_peran" CASCADE');
        Schema::create('akun.pengguna_peran', function (Blueprint $table) {
            $table->foreignId('pengguna_id')->index()->constrained('akun.pengguna')->cascadeOnDelete();
            $table->foreignId('peran_id')->index()->constrained('akun.peran')->cascadeOnDelete();

            $table->primary(['pengguna_id', 'peran_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS "akun"."pengguna_peran" CASCADE');
    }
};
