<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meningkatkan tabel audit.log_aktivitas untuk kebutuhan enterprise.
 *
 * PERUBAHAN:
 *   1. Index pada kolom `alamat_ip`
 *      → Diperlukan untuk query anomali (berapa banyak login dr IP ini?
 *        kapan terakhir IP ini aktif?). Tanpa index = full scan jutaan baris.
 *
 *   2. Kolom `user_agent` VARCHAR(512)
 *      → Menyimpan browser/OS/device. Bersama IP address, pasangan ini
 *        membentuk "fingerprint" sesi. Berguna untuk:
 *        - Mendeteksi session hijacking (IP sama, user_agent berbeda)
 *        - Mendeteksi bot/skrip otomatis
 *        - Forensik insiden keamanan
 *        - Compliance (OJK Sirkular POJK No.11/POJK.03/2022 untuk sistem keuangan)
 *
 *   3. Index pada kolom `id_pengguna`
 *      → Query "tampilkan semua log user X" adalah query paling sering.
 *        Tanpa index ini, paginasi log per user sangat lambat.
 *
 *   4. Composite index (tenant_id, created_at DESC)
 *      → Semua query dashboard difilter per tenant + diurutkan waktu terbaru.
 *        Ini adalah query pattern paling dominan di sistem multi-tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit.log_aktivitas', function (Blueprint $table): void {

            // Kolom user_agent - menyimpan identifikasi browser/OS client
            // VARCHAR(512): cukup untuk user agent paling panjang di dunia nyata
            // Nullable: log sistem (Observer) tidak memiliki HTTP context
            $table->string('user_agent', 512)->nullable()->after('alamat_ip');

            // Index: query "tampilkan log dari IP X"
            $table->index('alamat_ip', 'idx_log_alamat_ip');

            // Index: query "tampilkan semua log user X" (paginasi per user)
            $table->index('id_pengguna', 'idx_log_id_pengguna');

            // Composite index: query dashboard = filter tenant + sort waktu terbaru
            // Urutan kolom PENTING: kolom dengan cardinality rendah (tenant_id)
            // di depan untuk selective scan, lalu DESC untuk sort.
            $table->index(['tenant_id', 'created_at'], 'idx_log_tenant_created');
        });
    }

    public function down(): void
    {
        Schema::table('audit.log_aktivitas', function (Blueprint $table): void {
            $table->dropIndex('idx_log_alamat_ip');
            $table->dropIndex('idx_log_id_pengguna');
            $table->dropIndex('idx_log_tenant_created');
            $table->dropColumn('user_agent');
        });
    }
};
