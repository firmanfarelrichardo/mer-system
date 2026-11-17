# Stack Teknologi

> Daftar lengkap teknologi, dependencies, dan mapping direktori source code MER System.

## Runtime dan Framework

| Teknologi | Versi | Fungsi |
|-----------|-------|--------|
| PHP | ≥ 8.2 | Runtime backend |
| Laravel | 12.x | Framework web (monolit MVC + service layer) |
| PostgreSQL | 16 | Database relasional multi-schema |
| Redis | 7 | Cache, session, queue |
| Nginx | latest | Reverse proxy / web server |
| Node.js / NPM | latest | Build tool frontend |

## Dependencies Production (Composer)

| Package | Versi | Fungsi |
|---------|-------|--------|
| `laravel/framework` | ^12.0 | Core framework |
| `laravel/tinker` | ^2.10.1 | REPL interaktif |
| `barryvdh/laravel-dompdf` | ^3.1 | Generasi PDF laporan dan rekapitulasi |
| `mews/captcha` | ^3.4 | CAPTCHA (jika diaktifkan) |
| `sentry/sentry-laravel` | ^4.22 | Error tracking dan monitoring |

## Dependencies Development (Composer)

| Package | Versi | Fungsi |
|---------|-------|--------|
| `phpunit/phpunit` | ^11.5.3 | Unit dan feature testing |
| `larastan/larastan` | ^3.9 | Analisis statis PHP (PHPStan untuk Laravel) |
| `laravel/pint` | ^1.24 | Code style fixer (PSR-12 / Laravel preset) |
| `laravel/pail` | ^1.2.2 | Real-time log viewer |
| `laravel/sail` | ^1.41 | Docker dev environment (opsional) |
| `fakerphp/faker` | ^1.23 | Data sintetis untuk testing/seeding |
| `mockery/mockery` | ^1.6 | Mocking framework untuk testing |
| `nunomaduro/collision` | ^8.6 | Error handler CLI yang informatif |

## Frontend

| Teknologi | Fungsi |
|-----------|--------|
| Vite | Build tool dan HMR untuk assets |
| Tailwind CSS | Utility-first CSS framework |
| Alpine.js | Reactive UI tanpa build step besar |
| Blade | Template engine Laravel (server-side rendering) |

## Infrastruktur dan DevOps

| Teknologi | Fungsi |
|-----------|--------|
| Docker + Docker Compose | Containerization untuk staging dan production |
| Cloudflare | CDN, DNS, TLS (Full Strict) untuk production |
| Grafana | Dashboard monitoring |
| Loki + Promtail | Log aggregation |
| Uptime Kuma | Uptime monitoring |
| Supervisor | Process manager untuk queue worker di container |

## Mapping Direktori Source Code

| Direktori | Tanggung Jawab |
|-----------|----------------|
| `app/Http/Controllers/` | Orkestrasi request HTTP dan response — termasuk `Admin/`, `Auth/`, `Peneliti/` |
| `app/Http/Requests/` | Validasi input server-side dan pembatasan (rate limit login) |
| `app/Http/Middleware/` | Middleware autentikasi, force password change, dll. |
| `app/Services/` | Logika bisnis: `LaporanService`, `AuditLogService`, `NotifikasiService`, `PenggunaService`, dll. |
| `app/Repositories/` | Query data terfokus agar controller/service tidak duplikasi query |
| `app/DataTransferObjects/` | DTO untuk transfer data tervalidasi/terstruktur antar lapisan |
| `app/Models/` | Eloquent model: mapping tabel PostgreSQL ber-skema, relasi, scope, accessor, cast |
| `app/Policies/` | Otorisasi aksi per model (contoh: `InsidenPolicy`) |
| `app/Events/` | Domain event (contoh: `InsidenStatusBerubah`) |
| `app/Listeners/` | Handler event (contoh: `KirimNotifikasiInsiden`) |
| `app/Notifications/` | Notifikasi in-app berbasis Laravel notification |
| `app/Observers/` | Observer model untuk audit otomatis pada mutasi |
| `app/Jobs/` | Background job untuk queue processing |
| `app/Support/` | Helper dan utility classes |
| `app/Providers/` | Service provider Laravel |
| `resources/views/` | Template Blade per halaman dan komponen |
| `routes/web.php` | Definisi semua HTTP route aplikasi |
| `database/migrations/` | Migration skema database (sumber kebenaran perubahan) |
| `database/sql/` | DDL referensi awal PostgreSQL |
| `config/` | Konfigurasi Laravel dan aplikasi (termasuk `audit.php`) |
| `tests/` | Unit test dan feature test (PHPUnit) |
| `deployment/staging/` | Docker Compose dan konfigurasi staging |
| `deployment/production/` | Docker Compose dan konfigurasi production |
| `documentation/` | Dokumentasi operasional deployment (01–07) |
| `public/` | Entry point web dan aset publik |
| `storage/` | File upload, cache, log, session |

## Perintah Development

```bash
# Jalankan semua service development secara paralel (server, queue, logs, vite)
composer dev

# Setup awal project
composer setup

# Unit/feature test
composer test

# Analisis statis
./vendor/bin/phpstan analyse

# Build aset frontend
npm run build

# Validasi konfigurasi Docker
docker compose -f deployment/staging/docker-compose.yml config
docker compose -f deployment/production/docker-compose.yml config
```
