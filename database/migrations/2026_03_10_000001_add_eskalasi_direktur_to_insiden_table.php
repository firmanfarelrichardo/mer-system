<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pelaporan.insiden', function (Blueprint $table) {
            $table->boolean('is_eskalasi_direktur')->default(false)->after('sudah_dibaca');
            $table->text('solusi_direktur')->nullable()->after('is_eskalasi_direktur');
            $table->timestamp('waktu_solusi_direktur')->nullable()->after('solusi_direktur');
        });
    }

    public function down(): void
    {
        Schema::table('pelaporan.insiden', function (Blueprint $table) {
            $table->dropColumn(['is_eskalasi_direktur', 'solusi_direktur', 'waktu_solusi_direktur']);
        });
    }
};
