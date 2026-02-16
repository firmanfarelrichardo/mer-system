<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create PostgreSQL extension for UUID generation
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto;');

        // Create schemas
        DB::statement('CREATE SCHEMA IF NOT EXISTS tenant;');
        DB::statement('CREATE SCHEMA IF NOT EXISTS akun;');
        DB::statement('CREATE SCHEMA IF NOT EXISTS master;');
        DB::statement('CREATE SCHEMA IF NOT EXISTS pelaporan;');
        DB::statement('CREATE SCHEMA IF NOT EXISTS audit;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop schemas (CASCADE will drop all tables in the schema)
        DB::statement('DROP SCHEMA IF EXISTS audit CASCADE;');
        DB::statement('DROP SCHEMA IF EXISTS pelaporan CASCADE;');
        DB::statement('DROP SCHEMA IF EXISTS master CASCADE;');
        DB::statement('DROP SCHEMA IF EXISTS akun CASCADE;');
        DB::statement('DROP SCHEMA IF EXISTS tenant CASCADE;');

        // Drop extension
        DB::statement('DROP EXTENSION IF EXISTS pgcrypto;');
    }
};
