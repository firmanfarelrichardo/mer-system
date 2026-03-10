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
        DB::statement('DROP TABLE IF EXISTS "audit"."log_aktivitas" CASCADE');
        Schema::create('audit.log_aktivitas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->default(DB::raw('gen_random_uuid()'))->unique();

            $table->foreignId('tenant_id')->index()->constrained('tenant.organisasi');

            $table->string('nama_tabel', 100);
            $table->string('aksi', 20);

            $table->bigInteger('id_data')->nullable();

            $table->bigInteger('id_pengguna')->nullable()->index();

            $table->jsonb('data_lama')->nullable();
            $table->jsonb('data_baru')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS "audit"."log_aktivitas" CASCADE');
    }
};
