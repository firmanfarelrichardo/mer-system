<?php

use App\Http\Middleware\CheckForcePasswordChange;
use App\Http\Middleware\EnsurePeranAktif;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // -----------------------------------------------------------------
        // TrustProxies — Wajib dikonfigurasi agar Request::ip() mengembalikan
        // IP asli pengguna, bukan IP container Nginx/Docker.
        //
        // $proxies = '*'  : percayai semua proxy (aman untuk internal Docker network
        //                   karena Nginx sudah jadi pintu masuk pertama).
        //                   Untuk production di VPS, ganti dengan IP Nginx yang spesifik,
        //                   misalnya: ['10.0.0.5'] atau ['172.20.0.0/16'] (subnet Docker).
        //
        // $headers        : header mana yang dibaca untuk mendapatkan IP asli.
        //                   HEADER_X_FORWARDED_FOR adalah standar industri yang paling umum.
        // -----------------------------------------------------------------
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*'),
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_HOST |
                     Request::HEADER_X_FORWARDED_PORT |
                     Request::HEADER_X_FORWARDED_PROTO,
        );

        // -----------------------------------------------------------------
        // Global Middleware — dijalankan pada SETIAP request HTTP.
        // SecurityHeaders: defense-in-depth, menambahkan X-Frame-Options,
        // X-Content-Type-Options, dll. pada response dari PHP-FPM.
        // -----------------------------------------------------------------
        $middleware->append(SecurityHeaders::class);

        // -----------------------------------------------------------------
        // Alias Middleware — deklarasi alias agar dapat dipakai di route
        // tanpa menulis FQCN penuh.
        // -----------------------------------------------------------------
        $middleware->alias([
            'force.password.change' => CheckForcePasswordChange::class,
            'peran.aktif'           => EnsurePeranAktif::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        \Sentry\Laravel\Integration::handles($exceptions);
    })->create();
