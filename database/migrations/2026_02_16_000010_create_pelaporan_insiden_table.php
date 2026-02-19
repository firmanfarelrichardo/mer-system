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
        DB::statement('DROP TABLE IF EXISTS "pelaporan"."insiden" CASCADE');
        Schema::create('pelaporan.insiden', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->default(DB::raw('gen_random_uuid()'))->unique();

            $table->foreignId('tenant_id')->index()->constrained('tenant.organisasi');

            $table->string('nomor_laporan', 100);

            $table->foreignId('pelapor_id')->nullable()->index()->constrained('akun.pengguna');
            $table->foreignId('unit_id')->index()->constrained('master.unit_kerja');

            $table->string('tipe_insiden', 100);
            $table->string('status_saat_ini', 50)->index();

            $table->dateTime('tgl_kejadian')->index();
            $table->dateTime('tgl_lapor')->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'nomor_laporan'], 'uq_nomor_laporan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS "pelaporan"."insiden" CASCADE');
    }
};
