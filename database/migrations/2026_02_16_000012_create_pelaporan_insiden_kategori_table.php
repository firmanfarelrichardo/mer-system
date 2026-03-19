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
        DB::statement('DROP TABLE IF EXISTS "pelaporan"."insiden_kategori" CASCADE');
        Schema::create('pelaporan.insiden_kategori', function (Blueprint $table) {
            $table->foreignId('insiden_id')->index()->constrained('pelaporan.insiden')->cascadeOnDelete();
            $table->foreignId('kategori_id')->index()->constrained('master.kategori_kesalahan');

            $table->primary(['insiden_id', 'kategori_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS "pelaporan"."insiden_kategori" CASCADE');
    }
};
