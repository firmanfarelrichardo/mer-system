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
        Schema::create('akun.pengguna', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->default(DB::raw('gen_random_uuid()'))->unique();

            $table->foreignId('tenant_id')->index()->constrained('tenant.organisasi');

            $table->foreignId('unit_id')->nullable()->index()->constrained('master.unit_kerja');

            $table->string('nomor_induk', 100);
            $table->string('email', 255);
            $table->string('nomor_hp', 20);
            $table->string('alamat', 255);
            
            $table->string('nama_lengkap', 255);
            $table->string('kata_sandi', 255);

            $table->boolean('is_aktif')->default(true)->index();

            $table->dateTime('terakhir_login_pada')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email'], 'uq_pengguna_email');
            $table->unique(['tenant_id', 'nomor_induk'], 'uq_pengguna_nomor_induk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('akun.pengguna');
    }
};
