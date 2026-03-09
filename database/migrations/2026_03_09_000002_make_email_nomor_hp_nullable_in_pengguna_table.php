<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('akun.pengguna', function (Blueprint $table): void {
            $table->string('email', 255)->nullable()->change();
            $table->string('nomor_hp', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('akun.pengguna', function (Blueprint $table): void {
            $table->string('email', 255)->nullable(false)->change();
            $table->string('nomor_hp', 20)->nullable(false)->change();
        });
    }
};
