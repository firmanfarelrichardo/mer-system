<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahkan kolom jabatan dan tanggal_bergabung_unit ke akun.pengguna.
 *
 * jabatan              — jabatan / posisi pengguna di unit kerja (opsional).
 * tanggal_bergabung_unit — tanggal bergabung ke unit saat ini (opsional).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('akun.pengguna', function (Blueprint $table): void {
            $table->string('jabatan', 150)->nullable()->after('unit_id');
            $table->date('tanggal_bergabung_unit')->nullable()->after('jabatan');
        });
    }

    public function down(): void
    {
        Schema::table('akun.pengguna', function (Blueprint $table): void {
            $table->dropColumn(['jabatan', 'tanggal_bergabung_unit']);
        });
    }
};
