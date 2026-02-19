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
        DB::statement('DROP TABLE IF EXISTS "akun"."reset_kata_sandi" CASCADE');
        Schema::create('akun.reset_kata_sandi', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pengguna_id')->index()->constrained('akun.pengguna')->cascadeOnDelete();

            $table->string('email', 255)->index();
            $table->string('token_hash', 255)->index();

            $table->string('ip_request', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->dateTime('expired_at')->index();
            $table->dateTime('used_at')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS "akun"."reset_kata_sandi" CASCADE');
    }
};
