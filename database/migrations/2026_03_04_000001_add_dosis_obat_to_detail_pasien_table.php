<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom `dosis_obat` ke tabel `pelaporan.detail_pasien`.
     *
     * Kolom ini menyimpan dosis obat yang terlibat dalam insiden,
     * diletakkan tepat setelah kolom `obat_terkait`.
     */
    public function up(): void
    {
        Schema::table('pelaporan.detail_pasien', function (Blueprint $table) {
            $table->string('dosis_obat', 255)->nullable()->after('obat_terkait');
        });
    }

    /**
     * Batalkan penambahan kolom.
     */
    public function down(): void
    {
        Schema::table('pelaporan.detail_pasien', function (Blueprint $table) {
            $table->dropColumn('dosis_obat');
        });
    }
};
