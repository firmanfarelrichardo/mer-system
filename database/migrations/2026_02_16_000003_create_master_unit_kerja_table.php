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
        Schema::create('master.unit_kerja', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->default(DB::raw('gen_random_uuid()'))->unique();

            $table->foreignId('tenant_id')->index()->constrained('tenant.organisasi');

            $table->string('kode_unit', 50);
            $table->string('nama_unit', 255);
            $table->text('keterangan')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'kode_unit'], 'uq_unit_kode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master.unit_kerja');
    }
};
