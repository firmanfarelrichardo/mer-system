# MER System - Dokumentasi Arsitektur Docker

## Daftar Isi

1. [Ringkasan Arsitektur](#1-ringkasan-arsitektur)
2. [Environment Local (Development)](#2-environment-local-development)
3. [Environment Production](#3-environment-production)
4. [Alur Kerja (Workflow)](#4-alur-kerja-workflow)
5. [Keputusan Arsitektural & Alasan](#5-keputusan-arsitektural--alasan)
6. [Perbedaan Local vs Production](#6-perbedaan-local-vs-production)
7. [Troubleshooting](#7-troubleshooting)

---

## 1. Ringkasan Arsitektur

MER System menggunakan arsitektur **containerized microservices** dengan Docker Compose untuk mengorkestrasi seluruh stack:

| Komponen     | Teknologi        | Fungsi                                    |
| ------------ | ---------------- | ----------------------------------------- |
| PHP-FPM      | PHP 8.3 (Alpine) | Application server (Laravel 12)           |
| Nginx        | Nginx 1.27       | Reverse proxy & static file server        |
| PostgreSQL   | PostgreSQL 16    | Database utama (multi-schema)             |
| Redis        | Redis 7          | Caching, session, queue                   |
| Vite/Node.js | Node.js 22       | Frontend build & HMR (khusus development) |

### Diagram Arsitektur

```
┌─────────────────────────────────────────────────────────────────┐
│                    DOCKER COMPOSE NETWORK                       │
│                                                                 │
│  ┌──────────┐    ┌──────────┐    ┌────────────┐                │
│  │  Browser  │───▶│  Nginx   │───▶│  PHP-FPM   │                │
│  │  :8000    │    │  (web)   │    │  (app)     │                │
│  └──────────┘    └──────────┘    └─────┬──────┘                │
│       │                                │                        │
│       │          ┌─────────────────────┼─────────┐             │
│       │          │                     │         │             │
│  ┌────▼─────┐   ┌▼──────────┐   ┌─────▼────┐                  │
│  │  Vite    │   │ PostgreSQL │   │  Redis   │   (local only)   │
│  │  :5173   │   │  (db)     │   │  (redis) │                  │
│  └──────────┘   └───────────┘   └──────────┘                  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Environment Local (Development)

### 2.1 Struktur File

```
deployment/local/
├── docker-compose.yml         # Orchestration 5 services
├── Dockerfile                 # PHP-FPM image dengan UID/GID alignment
├── docker-entrypoint.sh       # Inisialisasi ringan (tanpa wait-loop)
├── deploy.sh                  # Helper script interaktif
├── nginx/
│   └── default.conf           # Nginx server block config
└── .env                       # Environment variables
```

### 2.2 Dockerfile (`deployment/local/Dockerfile`)

#### Blok-blok Kode & Penjelasan

**1. Base Image: `php:8.3-fpm-alpine`**
- Alpine Linux dipilih karena ukurannya ~5MB vs ~30MB Debian, menghasilkan image yang jauh lebih kecil
- FPM variant dipilih karena akan berjalan di belakang Nginx (bukan Apache)

**2. System Dependencies & PHP Extensions**
```dockerfile
RUN apk add --no-cache ... && apk add --no-cache --virtual .build-deps ...
```
- Runtime dependencies (libpng, libpq, icu-libs, dll) diinstal secara permanen
- Build dependencies (dev headers) diinstal dalam virtual group `.build-deps` yang kemudian dihapus
- Teknik ini mengurangi ukuran final image (~100MB lebih kecil)
- `shadow` package: diperlukan untuk `usermod` dan `groupmod` (tidak tersedia di Alpine secara default)

**3. UID/GID Alignment (`usermod`/`groupmod`)**
```dockerfile
ARG UID=1000
ARG GID=1000
RUN groupmod -g ${GID} www-data && usermod -u ${UID} -g ${GID} www-data
```

**Masalah yang Dipecahkan:**
PHP-FPM berjalan sebagai user `www-data` (default UID=82 di Alpine). Ketika PHP menulis file (log, cache, upload) ke bind-mounted volume, file tersebut akan memiliki UID=82 di host filesystem. Developer (UID=1000) tidak bisa mengedit file tersebut tanpa `sudo`.

**Solusi:**
Mengubah UID/GID user `www-data` di dalam container agar sama dengan UID/GID developer di host (1000). Dengan ini:
- File yang dibuat oleh PHP-FPM di container → ownership UID=1000 di host
- Developer bisa membaca/menulis file tersebut tanpa konflik permission
- Tidak perlu membuat user custom (menghindari konflik dengan konfigurasi default PHP-FPM)

**Mengapa Tidak Membuat User Baru (`laravel`)?**
- PHP-FPM dikonfigurasi untuk berjalan sebagai `www-data` secara default
- Membuat user baru memerlukan perubahan konfigurasi PHP-FPM (`www.conf`)
- `chown` ke `www-data` di entrypoint tidak akan bekerja jika PHP-FPM berjalan sebagai user lain
- Best practice: selaraskan existing user, jangan membuat user baru

### 2.3 Docker Compose (`deployment/local/docker-compose.yml`)

#### Service: `app` (PHP-FPM)
```yaml
volumes:
  - ../..:/var/www/html    # Bind-mount seluruh project
```
- Bind-mount memungkinkan perubahan kode di host langsung terlihat di container
- Tidak ada anonymous volume `/var/www/html/vendor` — vendor dikelola manual via `composer install`

#### Service: `web` (Nginx)
```yaml
volumes:
  - ../..:/var/www/html:ro                                    # Read-only bind mount
  - ./nginx/default.conf:/etc/nginx/conf.d/default.conf:ro   # Config override
```
- `:ro` (read-only) mencegah Nginx menulis ke source code
- Nginx hanya perlu membaca file statis (CSS, JS, gambar) dan mem-proxy request PHP ke container `app`

#### Service: `vite` (Node.js 22 / HMR)
```yaml
vite:
  image: node:22-alpine
  working_dir: /var/www/html
  ports:
    - "${VITE_PORT:-5173}:5173"
  volumes:
    - ../..:/var/www/html
    - vite-node-modules:/var/www/html/node_modules
  command: sh -c "npm install && npm run dev"
```

**Mengapa Container Terpisah?**
1. **Single Responsibility Principle**: Satu container = satu proses utama
2. **Ukuran Image**: Menambahkan Node.js ke PHP image menambah ~300MB
3. **Independensi**: Restart Vite tidak perlu restart PHP-FPM
4. **Dev/Prod Parity**: Di production tidak ada Node.js (assets sudah di-build saat Docker build)

**Volume `vite-node-modules`:**
Named volume terpisah untuk `node_modules` mencegah konflik antara host `node_modules` (jika ada) dengan container. Ini juga meningkatkan performa I/O karena named volume lebih cepat dari bind-mount untuk operasi banyak file kecil.

**Alur HMR (Hot Module Replacement):**
1. Vite container menjalankan `npm run dev` yang memulai dev server di `0.0.0.0:5173`
2. Vite plugin Laravel membuat file `public/hot` (berisi URL dev server)
3. PHP membaca `public/hot` → tahu Vite dev server aktif
4. Blade directive `@vite()` menghasilkan `<script>` tag yang mengarah ke `localhost:5173`
5. Browser mengambil assets dari Vite dev server
6. WebSocket connection terbuka untuk HMR
7. Saat file `resources/` berubah → Vite mengirim update via WebSocket → browser auto-refresh

#### Service: `db` (PostgreSQL 16)
```yaml
ports:
  - "${FORWARD_DB_PORT:-5433}:5432"   # Port 5433 di host (menghindari konflik)
healthcheck:
  test: ["CMD", "pg_isready", "-q", "-d", "...", "-U", "..."]
  interval: 10s
  retries: 5
```
- Port di-forward ke 5433 (bukan 5432) untuk menghindari konflik dengan PostgreSQL lokal di host
- Healthcheck menggunakan `pg_isready` — command bawaan PostgreSQL yang ringan dan akurat
- Service `app` menggunakan `depends_on: db: condition: service_healthy` sehingga PHP-FPM baru start setelah PostgreSQL benar-benar ready menerima koneksi

#### Service: `redis` (Redis 7)
```yaml
healthcheck:
  test: ["CMD", "redis-cli", "ping"]
  interval: 10s
  retries: 3
```
- Healthcheck menggunakan `redis-cli ping` yang mengembalikan `PONG` jika Redis siap
- Named volume `mer-redis-data` memastikan data cache persist antar restart

### 2.4 Entrypoint (`deployment/local/docker-entrypoint.sh`)

**Prinsip Desain: Minimal & Cepat**

Entrypoint development hanya melakukan 4 hal:
1. **Setup `.env`**: Copy dari `.env.example` jika belum ada + generate `APP_KEY`
2. **Cek vendor**: Memberitahu developer jika belum menjalankan `composer install`
3. **Clear stale cache**: Membersihkan cache yang mungkin stale dari session sebelumnya
4. **Set permission**: Memastikan `storage/` dan `bootstrap/cache/` writable

**Mengapa Tidak Ada Wait-loop DB/Redis?**
```
# SEBELUM (dihapus):
until php -r "try { new PDO(...); } catch(...) { exit(1); }" || [ $counter ... ]; do
    sleep 3
done
```
Docker Compose `depends_on` dengan `condition: service_healthy` sudah menjamin:
- Container `app` TIDAK akan dimulai sampai `db` dan `redis` melewati healthcheck
- Healthcheck PostgreSQL: `pg_isready` (aktual koneksi check)
- Healthcheck Redis: `redis-cli ping` (aktual ping check)

Menghapus wait-loop menghemat **15-45 detik** waktu boot dan menghilangkan duplikasi logika.

**Mengapa Tidak Ada Logika NPM?**
```
# SEBELUM (dihapus):
if command -v npm &> /dev/null; then
    npm run dev -- --host 0.0.0.0 &
fi
```
Container PHP-FPM tidak memiliki Node.js, sehingga perintah ini selalu gagal. Vite dev server sekarang dijalankan oleh container `vite` yang terpisah.

### 2.5 Nginx Config (`deployment/local/nginx/default.conf`)

```nginx
location ~ \.php$ {
    fastcgi_pass app:9000;        # Docker DNS resolve ke container app
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
}
```

- `fastcgi_pass app:9000`: Docker internal DNS secara otomatis resolve service name `app` ke IP container PHP-FPM
- Tidak menggunakan `127.0.0.1:9000` karena Nginx dan PHP-FPM berjalan di container yang berbeda (berbeda network namespace)
- `$realpath_root`: Resolve symlink agar path benar mengarah ke file PHP aktual

### 2.6 Vite Config (`vite.config.js`)

```javascript
server: {
    host: '0.0.0.0',           // Listen di semua interface (wajib dalam Docker)
    port: 5173,
    hmr: {
        host: 'localhost',      // Browser connect HMR ke localhost (bukan container IP)
    },
    watch: {
        usePolling: true,       // Polling karena inotify tidak reliable di Docker
        interval: 1000,
    },
}
```

**Mengapa `host: '0.0.0.0'`?**
Secara default, Vite hanya listen di `127.0.0.1` (loopback). Dalam Docker, koneksi dari host machine masuk sebagai external IP. Tanpa `0.0.0.0`, request dari browser tidak akan sampai ke dev server.

**Mengapa `hmr.host: 'localhost'`?**
Vite menulis URL dev server ke file `public/hot`. Tanpa setting ini, URL bisa berisi `0.0.0.0` atau IP internal Docker yang tidak bisa diakses oleh browser di host machine.

**Mengapa `usePolling: true`?**
Docker bind-mount pada beberapa OS (terutama macOS dan Windows) tidak mendukung `inotify` filesystem events. Polling memastikan Vite mendeteksi perubahan file secara reliable, meskipun sedikit lebih lambat.

---

## 3. Environment Production

### 3.1 Struktur File

```
deployment/production/
├── docker-compose.yml         # Orchestration 4 services
├── Dockerfile                 # Multi-stage build (optimized)
├── docker-entrypoint.sh       # Migration, optimize, sync assets
├── nginx.conf                 # Full nginx config (production-grade)
├── php.ini                    # PHP production settings
├── supervisord.conf           # (opsional) Untuk single-container mode
└── .env                       # Environment variables (RAHASIA)
```

### 3.2 Docker Compose (`deployment/production/docker-compose.yml`)

#### Keamanan: Loopback Binding Database

```yaml
db:
  ports:
    - "127.0.0.1:5432:5432"    # HANYA bisa diakses dari localhost server
```

**Masalah yang Dipecahkan:**
Default Docker port binding (`0.0.0.0:5432:5432`) mengekspos database ke SEMUA network interface. Ini berarti siapapun di internet bisa mencoba koneksi ke PostgreSQL jika firewall tidak dikonfigurasi dengan benar.

**Solusi — Loopback Binding:**
- Port hanya di-bind ke `127.0.0.1` (loopback interface)
- Database TIDAK bisa diakses dari luar server
- Aplikasi (container `app`) tetap bisa mengakses via Docker internal network (service name `db`)

**Akses Database dari Workstation Lokal (pgAdmin):**
```bash
# 1. Buat SSH Tunnel dari workstation ke VPS
ssh -L 5432:127.0.0.1:5432 user@vps-ip-address

# 2. Buka pgAdmin, buat koneksi baru:
#    Host: localhost
#    Port: 5432
#    Username: (sesuai .env)
#    Password: (sesuai .env)
```
Traffic dienkripsi oleh SSH. Tidak ada port database yang terbuka ke internet.

#### Skalabilitas: Shared Volume Strategy

**Masalah Awal:**
```yaml
# SEBELUM (bermasalah):
app:
  volumes:
    - shared-public:/var/www/html/public    # ← Men-shadow file bawaan image!
```
Ketika named volume di-mount ke `/var/www/html/public`, volume MENGGANTIKAN isi direktori dari image. File public bawaan (termasuk Vite build output) menjadi tidak terlihat.

**Solusi — Staging Path:**
```yaml
# SESUDAH (benar):
app:
  volumes:
    - shared-public:/public-shared          # ← Mount ke path terpisah

web:
  volumes:
    - shared-public:/var/www/html/public:ro  # ← Nginx baca dari sini
```

**Alur:**
1. Image production memiliki public assets di `/var/www/html/public/` (baked-in)
2. Volume `shared-public` di-mount ke `/public-shared` di container app
3. Entrypoint copy `/var/www/html/public/` → `/public-shared/`
4. Nginx membaca dari volume `shared-public` yang sudah berisi assets terbaru

**Keamanan Multi-Replica (Race Condition Prevention):**
Jika menjalankan 3 replika `app` bersamaan, ketiga replica akan mencoba sync assets secara bersamaan. Entrypoint menggunakan `mkdir`-based locking:

```bash
# mkdir bersifat atomic di POSIX — hanya satu proses yang berhasil
while ! mkdir "$LOCKDIR" 2>/dev/null; do
    sleep 2    # Tunggu replica lain selesai
done
# ... sync assets ...
rm -rf "$LOCKDIR"
```

Untuk menjalankan multiple replicas:
```bash
# Opsi 1: Via --scale flag
docker compose -f deployment/production/docker-compose.yml up -d --scale app=3

# Opsi 2: Via deploy.replicas (tambahkan di service app)
# deploy:
#   replicas: 3
```

> **Catatan:** `container_name` sengaja TIDAK diset pada service `app` karena Docker membutuhkan nama unik untuk setiap replica.

#### Keamanan Redis: Internal Only

```yaml
redis:
  # TIDAK ada 'ports:' → tidak bisa diakses dari host/internet
  command: redis-server --appendonly yes --maxmemory 256mb --maxmemory-policy allkeys-lru
```
- Redis hanya bisa diakses oleh services dalam Docker network yang sama
- `--appendonly yes`: Persist data ke disk (AOF) untuk data safety
- `--maxmemory-policy allkeys-lru`: Otomatis hapus key yang paling jarang digunakan jika memory penuh

### 3.3 Entrypoint (`deployment/production/docker-entrypoint.sh`)

**4 Langkah Inisialisasi Production:**

| # | Langkah | Perintah | Alasan |
|---|---------|----------|--------|
| 1 | Database Migration | `php artisan migrate --force` | `--force` wajib di production mode |
| 2 | Laravel Optimization | `config:cache`, `route:cache`, `view:cache` | Menghilangkan parsing overhead per-request |
| 3 | Sync Public Assets | `cp -a ... /public-shared/` | Mengisi shared volume untuk Nginx |
| 4 | Set Permissions | `chown -R www-data:www-data /var/www/html/storage` | Memastikan PHP bisa menulis log/cache |

**Stale Lock Protection:**
Jika container crash saat sedang sync, lock directory tertinggal dan memblokir semua replica berikutnya. Entrypoint mendeteksi lock yang lebih tua dari 120 detik dan menghapusnya secara otomatis.

### 3.4 Nginx Config (`deployment/production/nginx.conf`)

**Perbedaan dengan Local:**
- Gzip compression aktif (mengurangi bandwidth)
- Static file caching 1 tahun (`expires 1y`, `Cache-Control: public, immutable`)
- Security headers (`X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`)
- `fastcgi_pass app:9000` — menggunakan Docker service name (bukan `127.0.0.1`)

**Penting:** File ini di-mount sebagai `/etc/nginx/nginx.conf` (main config), bukan sebagai conf.d file, karena berisi full config block termasuk `events {}` dan `http {}`.

---

## 4. Alur Kerja (Workflow)

### 4.1 Local Development — Dari Nol

```bash
# 1. Masuk ke direktori deployment
cd deployment/local

# 2. Buat file .env (copy dari .env.example dan sesuaikan)
cp .env.example .env

# 3. Build & start semua services
docker compose up -d --build

# 4. Install Composer dependencies
docker exec mer-app-dev composer install

# 5. Jalankan migration
docker exec mer-app-dev php artisan migrate

# 6. Akses aplikasi
# Browser: http://localhost:8000
# Vite HMR: otomatis aktif via port 5173
```

### 4.2 Local Development — Daily Workflow

```bash
# Start
docker compose -f deployment/local/docker-compose.yml up -d

# Cek status
docker compose -f deployment/local/docker-compose.yml ps

# Lihat log
docker compose -f deployment/local/docker-compose.yml logs -f app

# Artisan command
docker exec mer-app-dev php artisan <command>

# Masuk ke shell container
docker exec -it mer-app-dev bash

# Stop
docker compose -f deployment/local/docker-compose.yml down
```

### 4.3 Production Deployment

```bash
# 1. SSH ke VPS
ssh user@vps-ip

# 2. Clone/pull repository
git pull origin main

# 3. Buat .env production
cp deployment/production/.env.example deployment/production/.env
# Edit .env dengan kredensial production

# 4. Build & deploy
docker compose -f deployment/production/docker-compose.yml up -d --build

# 5. Verifikasi
docker compose -f deployment/production/docker-compose.yml ps
curl http://localhost/health

# 6. Scaling (opsional)
docker compose -f deployment/production/docker-compose.yml up -d --scale app=3
```

### 4.4 Production Update (Zero-Downtime)

```bash
# 1. Pull kode terbaru
git pull origin main

# 2. Rebuild hanya service app
docker compose -f deployment/production/docker-compose.yml build app

# 3. Rolling update
docker compose -f deployment/production/docker-compose.yml up -d --no-deps app

# Catatan: Nginx tetap melayani request selama update karena
# public assets sudah ada di shared volume dari deployment sebelumnya.
# Container baru akan sync assets terbaru saat start.
```

---

## 5. Keputusan Arsitektural & Alasan

### 5.1 Mengapa `depends_on` + Healthcheck, Bukan Wait-Loop?

| Aspek | Wait-Loop (Dihapus) | depends_on + Healthcheck (Dipilih) |
|-------|---------------------|-------------------------------------|
| Waktu boot | +15-45 detik | Tanpa overhead |
| Duplikasi logika | Ada (di entrypoint + compose) | Satu tempat (compose) |
| Reliability | PHP PDO check bisa false-positive | `pg_isready` native PostgreSQL |
| Maintenance | Harus update di 2 tempat | Cukup update healthcheck di compose |

### 5.2 Mengapa Tidak Menggunakan Laravel Sail?

| Aspek | Laravel Sail | Custom Setup (Dipilih) |
|-------|-------------|------------------------|
| Kontrol | Terbatas (opinionated) | Penuh |
| Image size | ~1.5GB (include Node, MySQL, dll) | ~200MB (hanya yang dibutuhkan) |
| Production parity | Berbeda drastis | Bisa diselaraskan |
| Multi-schema PostgreSQL | Tidak didukung out-of-box | Didukung penuh |
| Customization | Perlu publish & modify | Langsung di Dockerfile |

### 5.3 Mengapa Alpine Linux?

| Aspek | Debian (default) | Alpine (Dipilih) |
|-------|-------------------|-------------------|
| Base image size | ~120MB | ~5MB |
| Final image size | ~500MB+ | ~200MB |
| Package manager | apt (slower) | apk (faster) |
| Security surface | Lebih besar | Minimal |
| Trade-off | glibc (lebih compatible) | musl libc (minimal) |

### 5.4 Shared Volume vs Custom Nginx Image (Production)

| Aspek | Shared Volume (Dipilih) | Custom Nginx Image |
|-------|------------------------|---------------------|
| Kompleksitas | Sedang | Lebih tinggi (extra Dockerfile) |
| Scaling | Aman dengan lock mechanism | Tanpa lock (baked-in) |
| Update assets | Otomatis via entrypoint | Perlu rebuild nginx image |
| Disk usage | Satu copy di volume | Satu copy per container |
| **Rekomendasi** | **Cocok untuk <10 replicas** | **Cocok untuk >10 replicas / K8s** |

Untuk skala yang lebih besar (>10 replicas atau migrasi ke Kubernetes), disarankan beralih ke Custom Nginx Image dimana public assets di-embed langsung ke image Nginx melalui multi-stage build.

---

## 6. Perbedaan Local vs Production

| Aspek | Local | Production |
|-------|-------|------------|
| PHP-FPM Image | Build dari Dockerfile | Multi-stage build (optimized) |
| Source Code | Bind-mount (live edit) | COPY ke image (immutable) |
| Composer | Manual install (`--dev`) | Auto install (`--no-dev`, optimized autoloader) |
| Vite/Node.js | Container terpisah (HMR) | Build saat Docker build (no runtime Node.js) |
| OPcache | Tidak aktif | Aktif (`validate_timestamps=0`) |
| Error Display | `display_errors=On` | `display_errors=Off` |
| Database Port | `0.0.0.0:5433` (terbuka) | `127.0.0.1:5432` (loopback only) |
| Redis Port | Exposed ke host | Internal only |
| Nginx | Server block only | Full config (gzip, cache, security headers) |
| Container Restart | `unless-stopped` | `always` |
| Cache | Cleared on boot | Cached (`config:cache`, `route:cache`, `view:cache`) |

---

## 7. Troubleshooting

### 7.1 Permission Denied pada Storage

```bash
# Di local
docker exec mer-app-dev chown -R www-data:www-data storage bootstrap/cache
docker exec mer-app-dev chmod -R 775 storage bootstrap/cache

# Verifikasi UID alignment
docker exec mer-app-dev id www-data
# Expected: uid=1000(www-data) gid=1000(www-data)
```

### 7.2 Vite HMR Tidak Bekerja

1. Pastikan port 5173 tidak digunakan aplikasi lain: `lsof -i :5173`
2. Periksa file `public/hot` ada: `docker exec mer-app-dev cat public/hot`
3. Pastikan `vite.config.js` memiliki `hmr.host: 'localhost'`
4. Cek log Vite: `docker logs mer-vite-dev`

### 7.3 Database Connection Refused

```bash
# Cek healthcheck status
docker inspect mer-db-dev --format='{{.State.Health.Status}}'

# Cek log PostgreSQL
docker logs mer-db-dev

# Test koneksi dari container app
docker exec mer-app-dev php artisan db:show
```

### 7.4 Production: Nginx 502 Bad Gateway

```bash
# Pastikan container app berjalan
docker compose -f deployment/production/docker-compose.yml ps app

# Cek log PHP-FPM
docker compose -f deployment/production/docker-compose.yml logs app

# Pastikan nginx.conf menggunakan fastcgi_pass app:9000
# (BUKAN 127.0.0.1:9000 — karena mereka di container terpisah)
```

### 7.5 Production: Public Assets Tidak Muncul di Nginx

```bash
# Cek isi shared volume
docker compose -f deployment/production/docker-compose.yml exec web ls -la /var/www/html/public/

# Jika kosong, restart app untuk trigger sync
docker compose -f deployment/production/docker-compose.yml restart app

# Cek log sync
docker compose -f deployment/production/docker-compose.yml logs app | grep -i sync
```

### 7.6 Akses Database Production via pgAdmin

```bash
# Dari workstation lokal:
ssh -L 5432:127.0.0.1:5432 user@vps-ip

# Lalu di pgAdmin:
# Host: localhost
# Port: 5432
# Database: (sesuai POSTGRES_DB di .env)
# Username: (sesuai POSTGRES_USER di .env)
# Password: (sesuai POSTGRES_PASSWORD di .env)
```
