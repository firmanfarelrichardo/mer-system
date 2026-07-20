<?php

/**
 * Konfigurasi sistem Audit Log.
 *
 * File ini mengontrol perilaku AuditLogService.
 * Semua nilai bisa di-override via environment variable.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Mode Asynchronous
    |--------------------------------------------------------------------------
    | false (default): INSERT synchronous - mudah di-debug di local.
    | true           : Dispatch ke queue 'audit' - wajib untuk production
    |                  agar respons user tidak tertahan oleh operasi DB.
    |
    | Prasyarat async: Redis aktif, queue worker berjalan di container app.
    | Jalankan: php artisan queue:work --queue=audit,default
    */
    'async' => env('AUDIT_LOG_ASYNC', false),

    /*
    |--------------------------------------------------------------------------
    | Retention Log (dalam hari)
    |--------------------------------------------------------------------------
    | Berapa lama log disimpan sebelum dihapus oleh command pruning.
    | Best practice industri:
    |   - Sistem keuangan (OJK): minimal 5 tahun (1825 hari)
    |   - Sistem rumah sakit (Kemenkes): minimal 5 tahun rekam medis
    |   - Sistem umum: 1-2 tahun
    |
    | Nilai 0 = tidak pernah dihapus otomatis (manual pruning).
    */
    'retention_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 1825), // 5 tahun

    /*
    |--------------------------------------------------------------------------
    | Role yang boleh melihat IP Address & User Agent
    |--------------------------------------------------------------------------
    | Demi privasi pengguna (UU PDP No.27/2022), kolom IP address dan
    | user_agent hanya boleh ditampilkan kepada role tertentu.
    |
    | Role yang TIDAK ada di sini akan melihat IP disamarkan (masking):
    | "125.160.x.x" bukan "125.160.123.45"
    */
    'roles_boleh_lihat_ip' => ['admin'],

];
