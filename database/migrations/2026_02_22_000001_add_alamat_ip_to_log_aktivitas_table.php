<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom `alamat_ip` ke tabel audit.log_aktivitas.
 *
 * Kolom ini menyimpan IP address pengguna saat melakukan aksi,
 * diperlukan untuk audit trail yang lengkap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit.log_aktivitas', function (Blueprint $table) {
            $table->string('alamat_ip', 45)->nullable()->after('id_pengguna');
        });
    }

    public function down(): void
    {
        Schema::table('audit.log_aktivitas', function (Blueprint $table) {
            $table->dropColumn('alamat_ip');
        });
    }
};
