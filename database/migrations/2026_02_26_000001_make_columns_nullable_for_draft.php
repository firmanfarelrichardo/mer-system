<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Membuat kolom-kolom transaksional nullable untuk mendukung fitur Simpan Draf.
 *
 * Pendekatan draf menggunakan tabel utama `pelaporan.insiden` dan `pelaporan.detail_pasien`
 * (bukan tabel terpisah). Saat Nakes menyimpan draf (data belum lengkap),
 * kolom-kolom yang belum diisi harus nullable agar database tidak melempar Not Null Violation.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Tabel pelaporan.insiden ──────────────────────────────────
        // Kolom berikut sudah nullable di migration awal:
        //   fase_kesalahan, nama_pelapor, kontak_pelapor, unit_id, nama_unit_kerja
        // Yang perlu dinullable-kan: tipe_insiden, tgl_kejadian, tgl_lapor, nomor_laporan
        Schema::table('pelaporan.insiden', function (Blueprint $table): void {
            $table->string('tipe_insiden', 100)->nullable()->change();
            $table->dateTime('tgl_kejadian')->nullable()->change();
            $table->dateTime('tgl_lapor')->nullable()->change();
        });

        // ── Tabel pelaporan.detail_pasien ────────────────────────────
        // Kolom-kolom pasien & kronologi harus nullable agar draf bisa disimpan
        // walaupun data belum lengkap.
        Schema::table('pelaporan.detail_pasien', function (Blueprint $table): void {
            $table->string('nama_pasien', 255)->nullable()->change();
            $table->string('nomor_rekam_medis', 100)->nullable()->change();
            $table->text('kronologi')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pelaporan.insiden', function (Blueprint $table): void {
            $table->string('tipe_insiden', 100)->nullable(false)->change();
            $table->dateTime('tgl_kejadian')->nullable(false)->change();
            $table->dateTime('tgl_lapor')->nullable(false)->change();
        });

        Schema::table('pelaporan.detail_pasien', function (Blueprint $table): void {
            $table->string('nama_pasien', 255)->nullable(false)->change();
            $table->string('nomor_rekam_medis', 100)->nullable(false)->change();
            $table->text('kronologi')->nullable(false)->change();
        });
    }
};
