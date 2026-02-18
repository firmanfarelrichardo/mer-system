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
        Schema::create('akun.peran', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->default(DB::raw('gen_random_uuid()'))->unique();

            $table->foreignId('tenant_id')->index()->constrained('tenant.organisasi');

            $table->string('nama_peran', 100);

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'nama_peran'], 'uq_peran');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('akun.peran');
    }
};
