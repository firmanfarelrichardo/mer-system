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
        DB::statement('DROP TABLE IF EXISTS "akun"."sesi" CASCADE');
        Schema::create('akun.sesi', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->foreignId('pengguna_id')->index()->constrained('akun.pengguna')->cascadeOnDelete();

            $table->string('refresh_token_hash', 255)->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->dateTime('last_activity');
            $table->dateTime('expired_at')->index();

            $table->boolean('is_revoked')->default(false)->index();
            $table->dateTime('revoked_at')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS "akun"."sesi" CASCADE');
    }
};
