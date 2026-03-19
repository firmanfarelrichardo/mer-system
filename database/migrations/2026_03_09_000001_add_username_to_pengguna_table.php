<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `username` ke tabel `akun.pengguna`.
 *
 * - nullable  : pengguna lama tidak wajib memiliki username.
 * - unique    : unik secara global (bukan per-tenant) agar dapat
 *               dipakai sebagai identifier login tanpa ambiguitas.
 * - after     : ditempatkan setelah `nomor_induk`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('akun.pengguna', function (Blueprint $table) {
            $table->string('username', 50)
                ->nullable()
                ->unique('uq_pengguna_username')
                ->after('nomor_induk');
        });
    }

    public function down(): void
    {
        Schema::table('akun.pengguna', function (Blueprint $table) {
            $table->dropUnique('uq_pengguna_username');
            $table->dropColumn('username');
        });
    }
};
