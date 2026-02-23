<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: akun.notifikasi
 *
 * Tabel notifikasi database (berbasis Laravel Notifications).
 * Ditempatkan di skema `akun` karena secara logis terkait
 * dengan entitas pengguna (`akun.pengguna`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akun.notifikasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akun.notifikasi');
    }
};
