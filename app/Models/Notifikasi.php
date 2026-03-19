<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Model Notifikasi — extends DatabaseNotification Laravel.
 *
 * Mengarahkan ke tabel `akun.notifikasi` (PostgreSQL schema)
 * alih-alih tabel default `notifications`.
 */
class Notifikasi extends DatabaseNotification
{
    protected $table = 'akun.notifikasi';
}
