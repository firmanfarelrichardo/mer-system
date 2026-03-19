<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom `wajib_ganti_sandi` ke tabel akun.pengguna.
     *
     * Kolom ini digunakan oleh fitur "Emergency Password Reset":
     * ketika Admin mereset sandi darurat, kolom ini diset true.
     * Middleware CheckForcePasswordChange akan memaksa pengguna
     * mengganti sandi sementara sebelum mengakses sistem.
     */
    public function up(): void
    {
        Schema::table('akun.pengguna', function (Blueprint $table): void {
            $table->boolean('wajib_ganti_sandi')
                ->default(false)
                ->after('is_aktif')
                ->comment('Flag: pengguna wajib mengganti kata sandi sebelum akses sistem');
        });
    }

    public function down(): void
    {
        Schema::table('akun.pengguna', function (Blueprint $table): void {
            $table->dropColumn('wajib_ganti_sandi');
        });
    }
};
