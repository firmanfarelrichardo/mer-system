# 04 — Nginx Reverse Proxy, Security Headers & Laravel Optimization

> **Sistem**: Medication Error Reporting (MER) — Rumah Sakit  
> **Klasifikasi**: HIGH-RISK (Data Medis Sensitif / PHI)  
> **Prasyarat**: Dokumen [01](./01-arsitektur-dan-hardening-os.md), [02](./02-cloudflare-dan-edge-security.md), dan [03](./03-docker-production-staging.md) sudah dilaksanakan

---

## Daftar Isi

1. [Peran Nginx dalam Arsitektur](#1-peran-nginx-dalam-arsitektur)
2. [Konfigurasi Nginx Production (Full)](#2-konfigurasi-nginx-production-full)
3. [Penjelasan Security Headers](#3-penjelasan-security-headers)
4. [Rate Limiting di Nginx](#4-rate-limiting-di-nginx)
5. [Konfigurasi SSL untuk Origin Certificate](#5-konfigurasi-ssl-untuk-origin-certificate)
6. [Optimasi PHP-FPM](#6-optimasi-php-fpm)
7. [Konfigurasi PHP.ini Production](#7-konfigurasi-phpini-production)
8. [Supervisord — Multi-Process Container](#8-supervisord--multi-process-container)
9. [Resolusi Konflik Izin Host-Container (UID/GID)](#9-resolusi-konflik-izin-host-container-uidgid)
10. [First-Time Initialization (Inisialisasi Pertama)](#10-first-time-initialization-inisialisasi-pertama)
11. [Startup Script Laravel (Entrypoint)](#11-startup-script-laravel-entrypoint)
12. [Verifikasi](#12-verifikasi)

---

## 1. Peran Nginx dalam Arsitektur

### Versi Sederhana (Analogi Pemula)

Nginx berperan sebagai **resepsionis rumah sakit**:

| Tugas Resepsionis | Equivalen Nginx |
|-------------------|-----------------|
| Menerima tamu di pintu depan | Menerima HTTP request dari Cloudflare |
| Mengarahkan tamu ke dokter yang tepat | Meneruskan request PHP ke PHP-FPM (FastCGI) |
| Memberikan brosur tanpa perlu dokter | Menyajikan static files (CSS, JS, gambar) langsung tanpa PHP |
| Menolak tamu yang mencurigakan | Memblokir request ke file sensitif (.env, .git) |
| Memberi tanda pengenal ke setiap tamu | Menambahkan Security Headers ke setiap response |
| Membatasi antrean agar tidak membludak | Rate limiting permintaan per IP |

### Versi Formal (Standar Industri)

Nginx berfungsi sebagai **Layer 7 reverse proxy** yang menangani:

1. **TLS Termination** — Mendekripsi HTTPS dari Cloudflare menggunakan Origin Certificate
2. **Static Asset Serving** — Menyajikan file statis dengan zero-copy sendfile() tanpa melibatkan PHP
3. **Request Routing** — Meneruskan request dinamis ke PHP-FPM upstream via FastCGI protocol
4. **Security Enforcement** — Menambahkan HTTP security headers dan memblokir akses ke path sensitif
5. **Rate Limiting** — Membatasi request rate per IP menggunakan leaky bucket algorithm
6. **Connection Management** — Mengelola keep-alive connections dan mengoptimasi TCP parameters

---

## 2. Konfigurasi Nginx Production (Full)

### Mengapa Konfigurasi Ini Penting?

File `nginx.conf` dibawah ini adalah konfigurasi **lengkap** yang menggantikan default Nginx config. Setiap directive disertai penjelasan mengapa dipilih dan apa konsekuensinya.

> **Lokasi file:** `deployment/production/nginx.conf`

```nginx
# ===========================================
# MER System - Production Nginx Configuration
# ===========================================
# File ini di-mount ke container Nginx sebagai /etc/nginx/nginx.conf
# Mount mode: read-only (:ro) untuk mencegah modifikasi dari dalam container.
# ===========================================

user nginx;

# auto = mengikuti jumlah CPU core yang tersedia.
# Pada VPS 2 Core, ini menghasilkan 2 worker processes.
# Setiap worker mampu menangani ribuan koneksi bersamaan berkat epoll.
worker_processes auto;

error_log /var/log/nginx/error.log warn;
pid /run/nginx.pid;

events {
    # Jumlah koneksi simultan per worker process.
    # 1024 x 2 workers = 2048 koneksi simultan maksimum.
    # Cukup untuk traffic rumah sakit (biasanya < 500 concurrent users).
    worker_connections 1024;

    # epoll adalah event notification mechanism paling efisien di Linux.
    # Kompleksitas O(1) vs select()/poll() yang O(n).
    use epoll;

    # Worker menerima semua koneksi baru sekaligus (bukan satu per satu).
    # Mengurangi context switching antara kernel dan userspace.
    multi_accept on;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    # -----------------------------------------------
    # Logging Format
    # -----------------------------------------------
    # Format log yang menyertakan $http_x_forwarded_for karena
    # IP asli client ada di header ini (Cloudflare sebagai reverse proxy).
    # $remote_addr akan berisi IP Cloudflare, bukan IP client.
    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    # Log format JSON untuk integrasi dengan Grafana Loki.
    # JSON memudahkan parsing dan query di LogQL.
    log_format json_combined escape=json
        '{'
            '"time_local":"$time_local",'
            '"remote_addr":"$remote_addr",'
            '"x_forwarded_for":"$http_x_forwarded_for",'
            '"request":"$request",'
            '"status":$status,'
            '"body_bytes_sent":$body_bytes_sent,'
            '"request_time":$request_time,'
            '"http_referrer":"$http_referer",'
            '"http_user_agent":"$http_user_agent",'
            '"upstream_response_time":"$upstream_response_time"'
        '}';

    access_log /var/log/nginx/access.log json_combined;

    # -----------------------------------------------
    # Performance Tuning
    # -----------------------------------------------
    # Sembunyikan versi Nginx dari response header Server.
    # Mencegah attacker mencocokkan versi dengan database CVE.
    server_tokens off;

    # sendfile: mengirim file langsung dari disk ke network socket
    # tanpa melalui userspace buffer. Mengurangi CPU usage untuk static files.
    sendfile on;

    # tcp_nopush: menggabungkan header dan data dalam satu TCP packet.
    # Mengurangi jumlah packet yang dikirim untuk file kecil (CSS, JS).
    tcp_nopush on;

    # tcp_nodelay: menonaktifkan Nagle's algorithm agar data kecil
    # dikirim segera tanpa menunggu buffer penuh. Penting untuk interaktifitas.
    tcp_nodelay on;

    # Durasi keep-alive connection. 65 detik cukup untuk menghandle
    # browsing session yang berkelanjutan tanpa reconnect.
    keepalive_timeout 65;

    types_hash_max_size 2048;

    # Limit ukuran request body (upload file).
    # 50M cukup untuk dokumen medis (laporan insiden, foto bukti).
    # Lebih besar meningkatkan risiko DoS melalui upload besar.
    client_max_body_size 50M;

    # -----------------------------------------------
    # Gzip Compression
    # -----------------------------------------------
    # Mengompresi response sebelum dikirim ke client.
    # Mengurangi bandwidth 60-80% untuk teks (HTML, CSS, JS, JSON).
    gzip on;
    gzip_vary on;
    gzip_proxied any;

    # Level 6 adalah sweet spot antara rasio kompresi dan CPU usage.
    # Level 1-3: cepat tapi kompresi rendah.
    # Level 7-9: kompresi tinggi tapi CPU intensif (tidak worth it).
    gzip_comp_level 6;

    # Minimum size file sebelum di-gzip.
    # File < 256 bytes tidak efisien di-gzip (overhead header > savings).
    gzip_min_length 256;

    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/json
        application/javascript
        application/xml
        application/xml+rss
        application/x-font-ttf
        font/opentype
        image/svg+xml;

    # -----------------------------------------------
    # Rate Limiting Zones
    # -----------------------------------------------
    # Definisi zone rate limiting menggunakan leaky bucket algorithm.
    # $binary_remote_addr = IP client dalam format binary (hemat memori).
    # zone=login:10m = alokasi 10MB shared memory (~160,000 IP addresses).

    # Zone untuk halaman login: 5 request per detik per IP.
    limit_req_zone $binary_remote_addr zone=login:10m rate=5r/s;

    # Zone untuk API: 10 request per detik per IP.
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;

    # Zone untuk request umum: 20 request per detik per IP.
    limit_req_zone $binary_remote_addr zone=general:10m rate=20r/s;

    # -----------------------------------------------
    # Security Headers (Global)
    # -----------------------------------------------
    # X-Frame-Options: mencegah halaman dimuat dalam iframe (clickjacking).
    # SAMEORIGIN = hanya iframe dari domain yang sama yang diizinkan.
    add_header X-Frame-Options "SAMEORIGIN" always;

    # X-Content-Type-Options: mencegah browser menebak MIME type.
    # Menghindari serangan dimana browser mengeksekusi file non-JS sebagai JS.
    add_header X-Content-Type-Options "nosniff" always;

    # Referrer-Policy: mengontrol informasi referrer yang dikirim ke situs lain.
    # strict-origin-when-cross-origin = kirim origin saja (tanpa path) ke situs lain.
    # Mencegah kebocoran URL internal (yang mungkin mengandung ID pasien).
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Permissions-Policy: menonaktifkan API browser yang tidak diperlukan.
    # Sistem medis tidak perlu kamera, mikrofon, geolokasi, dll.
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()" always;

    # Content-Security-Policy: membatasi sumber resource yang boleh dimuat.
    # 'self' = hanya dari domain sendiri. 'unsafe-inline' diperlukan untuk
    # Blade/Vue inline styles dan scripts. Idealnya diganti dengan nonce.
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'" always;

    # HSTS: memaksa browser selalu gunakan HTTPS.
    # max-age=31536000 = 1 tahun. includeSubDomains = berlaku untuk subdomain.
    # CATATAN: hanya aktifkan setelah HTTPS sudah 100% berfungsi.
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # -----------------------------------------------
    # Server Block
    # -----------------------------------------------
    server {
        listen 80;
        server_name _;
        root /var/www/html/public;
        index index.php;

        charset utf-8;

        # Health check endpoint untuk monitoring (Uptime Kuma, Docker healthcheck).
        # access_log off agar tidak membanjiri log dengan request health check.
        location /health {
            access_log off;
            return 200 "OK";
            add_header Content-Type text/plain;
        }

        # -----------------------------------------------
        # Rate Limiting: Login Endpoint
        # -----------------------------------------------
        # Membatasi brute-force attack pada halaman login.
        # burst=10: mengizinkan 10 request melebihi rate sebelum reject.
        # nodelay: request dalam burst dilayani langsung (tidak di-queue).
        location = /login {
            limit_req zone=login burst=10 nodelay;
            limit_req_status 429;
            try_files $uri $uri/ /index.php?$query_string;
        }

        # Rate Limiting: API Endpoints
        location /api/ {
            limit_req zone=api burst=20 nodelay;
            limit_req_status 429;
            try_files $uri $uri/ /index.php?$query_string;
        }

        # -----------------------------------------------
        # Main Location (Catch-All)
        # -----------------------------------------------
        # try_files mencoba: 1) file statis, 2) direktori, 3) fallback ke index.php.
        # Ini adalah pola standar Laravel untuk pretty URLs.
        location / {
            limit_req zone=general burst=30 nodelay;
            try_files $uri $uri/ /index.php?$query_string;
        }

        # -----------------------------------------------
        # Static Files Caching
        # -----------------------------------------------
        # Static files langsung disajikan oleh Nginx tanpa PHP.
        # expires 1y + immutable = browser tidak perlu revalidate selama 1 tahun.
        # Vite menambahkan hash ke filename, jadi saat file berubah, URL-nya berubah.
        location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|svg|eot|webp)$ {
            expires 1y;
            add_header Cache-Control "public, immutable";
            access_log off;
        }

        # -----------------------------------------------
        # PHP-FPM Processing
        # -----------------------------------------------
        location ~ \.php$ {
            fastcgi_pass app:9000;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            include fastcgi_params;

            # Sembunyikan header X-Powered-By (berisi versi PHP).
            # Information disclosure yang bisa digunakan attacker.
            fastcgi_hide_header X-Powered-By;

            # --- Timeout Configuration ---
            # fastcgi_read_timeout dinaikkan ke 300s untuk DOMPDF.
            # Rendering dokumen PDF medis yang kompleks (banyak tabel, gambar)
            # bisa memakan waktu > 60 detik.
            fastcgi_connect_timeout 60s;
            fastcgi_send_timeout 60s;
            fastcgi_read_timeout 300s;

            # --- Buffer Configuration ---
            # Buffer cukup besar untuk menampung response PHP tanpa
            # perlu menulis ke temporary file (lebih cepat).
            fastcgi_buffer_size 32k;
            fastcgi_buffers 16 16k;
        }

        # -----------------------------------------------
        # Deny Access to Hidden Files
        # -----------------------------------------------
        # Blokir akses ke file/folder yang dimulai dengan titik (.env, .git, .htaccess).
        # File ini TIDAK BOLEH bisa diakses dari web.
        location ~ /\. {
            deny all;
            access_log off;
            log_not_found off;
        }

        # -----------------------------------------------
        # Deny Access to Sensitive File Extensions
        # -----------------------------------------------
        location ~* \.(env|log|sql|bak|conf|ini|sh|yml|yaml|toml|lock)$ {
            deny all;
            access_log off;
            log_not_found off;
        }

        # Deny akses ke direktori storage Laravel.
        location ~* ^/storage/ {
            deny all;
            access_log off;
        }
    }
}
```

---

## 3. Penjelasan Security Headers

| Header | Nilai | Serangan yang Dicegah | Dampak Jika Tidak Ada |
|--------|-------|----------------------|----------------------|
| `X-Frame-Options` | `SAMEORIGIN` | **Clickjacking** — Attacker menyembunyikan halaman MER di dalam iframe transparan di situs lain | User bisa diklik-jebak untuk submit form tanpa sadar |
| `X-Content-Type-Options` | `nosniff` | **MIME Sniffing** — Browser menebak tipe file dan mengeksekusi file upload sebagai JavaScript | File upload berbahaya bisa dieksekusi |
| `Strict-Transport-Security` | `max-age=31536000` | **SSL Stripping** — Attacker downgrade HTTPS ke HTTP | Data medis terkirim tanpa enkripsi |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | **Information Leakage** — URL yang mengandung ID pasien bocor lewat header Referer | ID pasien terekspos ke situs eksternal |
| `Permissions-Policy` | `camera=(), microphone=()...` | **Unauthorized Hardware Access** — Skrip jahat mengaktifkan kamera/mikrofon | Privasi user dikompromikan |
| `Content-Security-Policy` | `default-src 'self'...` | **XSS, Code Injection** — Skrip dari domain asing dieksekusi di browser | Attacker bisa mencuri session/data |

---

## 4. Rate Limiting di Nginx

### Mengapa Rate Limiting Di Nginx Selain di Cloudflare?

**Versi Formal:** Defense in Depth mengharuskan setiap lapisan memiliki kontrol keamanannya sendiri. Jika Cloudflare rate limiting terlewat (misconfiguration, bypass), Nginx rate limiting menjadi lapisan kedua. Selain itu, Nginx rate limiting melindungi dari traffic internal (antar container) yang tidak melewati Cloudflare.

### Cara Kerja Leaky Bucket Algorithm

```
Request masuk: ████████████████ (banyak sekaligus)
                    |
                    v
            +---------------+
            | Leaky Bucket  |  Kapasitas: burst=10
            |               |  Rate: 5r/s (1 request per 200ms)
            | ████████████  |
            | ████████      |  <-- request ditampung di bucket
            | ████          |
            +-------+-------+
                    |
                    v (keluar dengan rate konstan)
            Request dilayani: ██ ██ ██ ██ ██

            Request melebihi burst: DITOLAK (429 Too Many Requests)
```

---

## 5. Konfigurasi SSL untuk Origin Certificate

### Nginx SSL Server Block (HTTPS)

Jika Anda ingin Nginx menangani TLS termination langsung (bukan hanya HTTP dari Cloudflare), tambahkan server block HTTPS berikut. Ini diperlukan jika Cloudflare dikonfigurasi dalam mode **Full (Strict)** dan mengirim traffic HTTPS ke origin.

> [!NOTE]
> Pada setup default MER System, Cloudflare mengirim traffic ke origin via HTTP (port 80) karena container Nginx hanya listen di port 80. Cloudflare menangani TLS termination di edge. Konfigurasi SSL di bawah ini untuk skenario dimana Anda ingin **end-to-end encryption** dari Cloudflare ke origin server.

Untuk mengaktifkan SSL di Nginx, modifikasi `docker-compose.yml` production agar Nginx listen di port 443 juga, dan mount sertifikat:

```yaml
# Tambahan pada service web di docker-compose.yml
web:
  ports:
    - "80:80"
    - "443:443"
  volumes:
    - shared-public:/var/www/html/public:ro
    - ./nginx.conf:/etc/nginx/nginx.conf:ro
    - /etc/ssl/cloudflare:/etc/ssl/cloudflare:ro
```

Tambahkan server block HTTPS di dalam `nginx.conf`:

```nginx
    # Server block HTTP - redirect ke HTTPS
    server {
        listen 80;
        server_name _;
        return 301 https://$host$request_uri;
    }

    # Server block HTTPS
    server {
        listen 443 ssl;
        server_name _;

        # --- Origin Certificate dari Cloudflare ---
        ssl_certificate /etc/ssl/cloudflare/origin-cert.pem;
        ssl_certificate_key /etc/ssl/cloudflare/origin-key.pem;

        # --- Authenticated Origin Pull ---
        # Memverifikasi bahwa koneksi benar-benar dari Cloudflare.
        # Jika attacker mencoba koneksi langsung ke port 443,
        # Nginx akan menolak karena tidak ada client certificate Cloudflare.
        ssl_client_certificate /etc/ssl/cloudflare/authenticated-origin-pull-ca.pem;
        ssl_verify_client on;

        # --- TLS Security ---
        ssl_protocols TLSv1.2 TLSv1.3;
        ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384';
        ssl_prefer_server_ciphers on;
        ssl_session_cache shared:SSL:10m;
        ssl_session_timeout 10m;

        root /var/www/html/public;
        index index.php;
        charset utf-8;

        # ... (location blocks sama seperti server block HTTP di atas)
    }
```

---

## 6. Optimasi PHP-FPM

### Mengapa PHP-FPM Perlu Dituning?

**Versi Formal:** PHP-FPM (FastCGI Process Manager) mengelola pool worker processes. Konfigurasi default (`pm = dynamic`) tidak optimal untuk server dengan RAM terbatas (8GB) yang juga menjalankan database dan Redis. Tuning yang tepat mencegah out-of-memory (OOM) sambil memaksimalkan throughput.

**Versi Sederhana:** PHP-FPM seperti meja dokter. Default-nya, rumah sakit menyiapkan terlalu banyak meja (worker) yang semuanya makan listrik (RAM). Kita atur agar meja buka secara dinamis sesuai jumlah pasien (request).

### Konfigurasi PHP-FPM Pool

> **Lokasi:** File ini bisa ditambahkan ke Dockerfile sebagai custom pool config.
> **CATATAN PENTING:** Konfigurasi INI PHP-FPM (`www.conf`) **TUDAK BOLEH** menggunakan tanda pagar (`#`) untuk komentar. Selalu gunakan titik koma (`;`). Jika menggunakan `#`, aplikasi akan mengalami *crash* berulang dengan error `ZEND_INI_PARSER_ENTRY`. Jika Anda melakukan perubahan pada file ini, selalu terapkan dengan membangun ulang image: `docker compose up -d --build app`.

```ini
; ===========================================
; MER System - PHP-FPM Pool Configuration
; File: www.conf (override default pool)
; ===========================================

[www]
user = www-data
group = www-data

; Listen pada port 9000 di semua interface container.
; Nginx (container terpisah) mengakses via Docker DNS: app:9000
listen = 0.0.0.0:9000

; --- Process Manager: dynamic ---
; dynamic dipilih karena traffic rumah sakit bersifat bursty:
; - Pagi & siang: banyak request (jam kerja staf medis)
; - Malam & weekend: sedikit request
; Mode dynamic menskalakan worker sesuai load aktual.
pm = dynamic

; Jumlah MAKSIMUM worker processes.
; Formula: (Total RAM untuk PHP) / (Rata-rata RAM per worker)
; = 1536MB / 50MB per worker = ~30 workers max
; Dibatasi ke 20 untuk menyisakan headroom.
pm.max_children = 20

; Jumlah worker yang langsung disiapkan saat PHP-FPM start.
pm.start_servers = 5

; Minimum worker idle (siap menerima request tanpa delay spawn).
pm.min_spare_servers = 3

; Maksimum worker idle (worker berlebih di-kill untuk hemat RAM).
pm.max_spare_servers = 10

; Setelah 500 request, worker di-recycle (restart).
; Mencegah memory leak pada long-running processes.
pm.max_requests = 500

; Timeout untuk slow request logging (10 detik).
; Request yang lebih lambat dari ini akan di-log untuk investigasi.
request_slowlog_timeout = 10s
slowlog = /var/www/html/storage/logs/php-fpm-slow.log

; Status page (hanya untuk monitoring internal via Docker network).
pm.status_path = /fpm-status
```

---

## 7. Konfigurasi PHP.ini Production

File `php.ini` sudah ada di repository: [`deployment/production/php.ini`](../deployment/production/php.ini).

Penjelasan setiap parameter kunci:

| Parameter | Nilai | Mengapa |
|-----------|-------|---------|
| `opcache.enable` | `1` | Meng-cache bytecode PHP di shared memory. Menghilangkan parsing PHP files setiap request |
| `opcache.validate_timestamps` | `0` | **Parameter paling krusial.** PHP tidak mengecek perubahan file di setiap request. Meningkatkan throughput ~40%. Aman karena di production kode tidak berubah tanpa deploy |
| `opcache.memory_consumption` | `256` | 256MB shared memory untuk cache. Cukup untuk ~10,000 file PHP (Laravel + vendor) |
| `memory_limit` | `512M` | Per-request memory limit. DOMPDF membutuhkan RAM besar saat render PDF kompleks |
| `max_execution_time` | `300` | 5 menit timeout. PDF generation dokumen medis tebal bisa memakan waktu > 60 detik |
| `display_errors` | `Off` | **Wajib Off di production.** Error message mengandung path, query, dan info sensitif |
| `expose_php` | `Off` | Menghilangkan header `X-Powered-By: PHP/8.2`. Information disclosure |
| `allow_url_fopen` | `Off` | Mencegah PHP membuka URL sebagai file. Mengurangi risiko SSRF attack |
| `session.cookie_httponly` | `1` | Session cookie tidak bisa diakses oleh JavaScript. Mencegah XSS session hijacking |
| `session.cookie_secure` | `1` | Session cookie hanya dikirim melalui HTTPS. Mencegah sniffing di HTTP |

---

## 8. Supervisord — Multi-Process Container

### Mengapa Supervisord?

**Versi Formal:** Prinsip "satu proses per container" adalah ideal, tapi untuk deployment VPS tunggal dengan resource terbatas, menjalankan PHP-FPM + queue worker + scheduler dalam container terpisah menghasilkan overhead yang signifikan (3x image size, 3x memory overhead per container). Supervisord mengorkestrasi multiple processes dalam satu container sebagai kompromi pragmatis.

**Versi Sederhana:** Daripada menyewa 3 ruangan terpisah (3 container) untuk 1 dokter + 1 asisten + 1 penjadwal, lebih efisien satu ruangan besar (1 container) dengan manajer ruangan (Supervisord) yang mengawasi ketiganya.

### Konfigurasi Supervisord (File Lengkap)

> **Lokasi file:** `deployment/production/supervisord.conf`

```ini
; ===========================================
; MER System - Supervisord Configuration
; ===========================================
; File ini menjalankan 3 proses dalam satu container:
; 1. PHP-FPM   : menerima HTTP request dari Nginx
; 2. Queue     : memproses background jobs (email, PDF)
; 3. Scheduler : menjalankan cron-like tasks Laravel
;
; Supervisord dipilih karena VPS hanya 8GB RAM.
; Menjalankan masing-masing sebagai container terpisah
; memakan ~3x overhead memory.
; ===========================================

[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
logfile_maxbytes=5MB
logfile_backups=3
pidfile=/var/run/supervisord.pid

; ===========================================
; Program 1: PHP-FPM
; ===========================================
; Proses utama yang melayani HTTP request dari Nginx
; via FastCGI protocol pada port 9000.
;
; priority=1 memastikan PHP-FPM start PERTAMA sebelum
; queue worker dan scheduler. Jika PHP-FPM belum siap,
; queue worker yang memanggil kode Laravel bisa crash.
;
; -F (--nodaemonize) memaksa PHP-FPM berjalan di foreground
; agar Supervisord bisa mendeteksi jika proses mati.
[program:php-fpm]
command=php-fpm -F
autostart=true
autorestart=true
priority=1
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

; ===========================================
; Program 2: Queue Worker
; ===========================================
; Memproses background jobs dari Redis queue:
; - Pengiriman email notifikasi insiden medis
; - Generasi PDF laporan (DOMPDF)
; - Proses data berat yang tidak boleh blocking HTTP request
;
; --sleep=3      : polling interval (detik) jika queue kosong
; --tries=3      : jumlah percobaan sebelum job dianggap gagal
; --max-time=3600: worker di-restart setiap 1 jam untuk mencegah
;                  memory leak yang umum pada long-running PHP process
; --max-jobs=1000: worker di-restart setelah memproses 1000 job
;                  (safety limit tambahan untuk memory leak)
;
; stopwaitsecs=30: memberikan waktu 30 detik untuk menyelesaikan
;                  job yang sedang diproses sebelum di-kill.
;                  Mencegah job terpotong di tengah-tengah.
[program:queue-worker]
command=php /var/www/html/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --max-jobs=1000
autostart=true
autorestart=true
priority=5
numprocs=1
stopwaitsecs=30
stdout_logfile=/var/www/html/storage/logs/queue-worker.log
stdout_logfile_maxbytes=5MB
stdout_logfile_backups=3
stderr_logfile=/var/www/html/storage/logs/queue-worker-error.log
stderr_logfile_maxbytes=5MB
stderr_logfile_backups=3

; ===========================================
; Program 3: Task Scheduler
; ===========================================
; Menjalankan Laravel Task Scheduler setiap 60 detik,
; menggantikan kebutuhan cronjob di dalam container.
;
; schedule:run memeriksa jadwal yang didefinisikan di
; app/Console/Kernel.php dan mengeksekusi task yang
; sudah waktunya (backup reminder, report generation,
; pembersihan session expired, dsb).
;
; Menggunakan loop sh agar berjalan terus-menerus:
; - PENTING: Karena image berbasis Alpine Linux, gunakan 'sh' BUKAN 'bash'
;   (penggunaan bash akan membuat scheduler gagal karena not found)
; - Jalankan schedule:run
; - Tunggu 60 detik
; - Ulangi
[program:scheduler]
command=sh -c "while true; do php /var/www/html/artisan schedule:run --no-interaction >> /var/www/html/storage/logs/scheduler.log 2>&1; sleep 60; done"
autostart=true
autorestart=true
priority=10
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

### Ringkasan Proses Supervisord

| Program | Perintah | Priority | Fungsi |
|---------|----------|----------|--------|
| `php-fpm` | `php-fpm -F` | 1 (start pertama) | Melayani HTTP request dari Nginx |
| `queue-worker` | `php artisan queue:work --max-time=3600` | 5 | Memproses background jobs (email notifikasi, PDF generation) |
| `scheduler` | `php artisan schedule:run` (loop 60s) | 10 | Menjalankan scheduled tasks (backup reminder, report generation) |

### Mengapa `numprocs=1` untuk Queue Worker?

Pada VPS 2 Core dengan memory limit 1536MB untuk container app, satu queue worker sudah cukup untuk throughput rumah sakit (< 100 jobs/jam). Menambah worker berarti menambah konsumsi RAM (~50MB per worker) yang merenggut dari pool PHP-FPM. Jika traffic meningkat signifikan, naikkan `numprocs` ke 2-3 sambil memperhatikan memory usage.

> [!IMPORTANT]
> **Queue Worker dan Scheduler WAJIB berjalan di production.** Tanpa queue worker, semua background jobs (notifikasi email insiden medis, PDF generation) tidak akan terproses — tertumpuk di Redis tanpa disentu. Tanpa scheduler, tugas terjadwal (cleanup session, reminder) tidak akan tereksekusi.

---

## 9. Resolusi Konflik Izin Host-Container (UID/GID)

### Mengapa Ini Kritis?

**Versi Formal:** Saat Docker container menjalankan PHP-FPM sebagai user `www-data` (UID 33), dan volume yang di-mount dari host dimiliki oleh user `mer_ops` (UID 1000), terjadi UID/GID mismatch. PHP-FPM tidak bisa menulis ke direktori `storage/logs`, `storage/framework/cache`, `storage/framework/sessions`, dan `bootstrap/cache` karena filesystem permission menolak akses dari UID 33. Ini menghasilkan **Error 500 (Permission Denied)** yang terlihat di browser tanpa informasi diagnostik apapun (karena log file pun tidak bisa ditulis).

**Versi Sederhana:** Bayangkan dokter (PHP-FPM/www-data) diminta menulis catatan di ruang arsip (storage/), tapi ruangan itu hanya bisa dibuka oleh petugas IT (mer_ops). Dokter berdiri di depan pintu terkunci — tidak bisa menulis, tidak bisa melaporkan masalah (log tidak bisa ditulis), sistem hanya menunjukkan "Error 500" di layar tanpa penjelasan.

### Peta Konflik UID/GID

| Konteks | User | UID | GID |
|---------|------|-----|-----|
| Host OS (Ubuntu) | `mer_ops` | 1000 | 1000 |
| Docker Container | `www-data` | 33 | 33 |
| Docker Container | `root` | 0 | 0 |

**Direktori yang terdampak:**

| Direktori | Siapa yang menulis | Mengapa |
|-----------|-------------------|----------|
| `storage/logs/` | PHP-FPM (www-data) | Log aplikasi Laravel |
| `storage/framework/cache/` | PHP-FPM (www-data) | Cache data konfigurasi dan route |
| `storage/framework/sessions/` | PHP-FPM (www-data) | Session file pengguna |
| `storage/framework/views/` | PHP-FPM (www-data) | Compiled Blade templates |
| `storage/app/` | PHP-FPM (www-data) | File upload (dokumen insiden medis) |
| `bootstrap/cache/` | PHP-FPM (www-data) | Cache autoloader dan konfigurasi |

### Eksekusi — Sinkronisasi Permission dari Host

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# Masuk ke direktori project production.
cd /var/www/mer-system/production

# Set ownership untuk direktori yang membutuhkan akses tulis oleh PHP-FPM.
# Menggunakan UID:GID numerik (33:33) agar perintah ini bekerja
# meskipun user www-data tidak ada di host OS.
#
# Mengapa 33:33?
# Di dalam container Debian/Ubuntu, user www-data memiliki UID=33.
# Dengan men-chown file ke UID 33 dari host, saat container melihat
# file tersebut melalui volume mount, file tersebut dimiliki oleh www-data
# sesuai mapping UID di dalam container.
sudo chown -R 33:33 storage/
sudo chown -R 33:33 bootstrap/cache/

# Set permission yang optimal:
# Direktori: 775 = owner+group bisa baca/tulis/eksekusi, others bisa baca+eksekusi
# File:      664 = owner+group bisa baca/tulis, others bisa baca
#
# Mengapa 775/664 (bukan 777)?
# 777 memberikan write access ke SEMUA user di sistem — kerentanan keamanan.
# 775/664 membatasi write hanya ke owner (www-data) dan group,
# sementara user lain hanya bisa membaca.
sudo find storage/ -type d -exec chmod 775 {} \;
sudo find storage/ -type f -exec chmod 664 {} \;
sudo find bootstrap/cache/ -type d -exec chmod 775 {} \;
sudo find bootstrap/cache/ -type f -exec chmod 664 {} \;

# Verifikasi ownership dan permission
ls -la storage/
ls -la bootstrap/cache/
# Setiap entri harus menunjukkan:
# drwxrwxr-x  ... 33 33 ... (untuk direktori)
# -rw-rw-r--  ... 33 33 ... (untuk file)
```

### Integrasi di Docker Entrypoint

Perintah chown juga dijalankan otomatis di `docker-entrypoint.sh` setiap kali container start, sebagai jaring pengaman jika permission berubah (misalnya setelah `git pull` yang membuat file baru):

```bash
# Cuplikan dari docker-entrypoint.sh
# Dijalankan sebagai root saat container start, sebelum Supervisord
# menurunkan privilege ke www-data.
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache
```

> [!CAUTION]
> **Error 500 tanpa log adalah tanda klasik permission denied.** Jika setelah deployment pertama Anda melihat halaman kosong dengan HTTP 500, langkah pertama yang HARUS dilakukan adalah memeriksa ownership `storage/` di dalam container:
> ```bash
> docker exec production-app-1 ls -la /var/www/html/storage/
> # Jika ownership BUKAN www-data:www-data, jalankan:
> docker exec production-app-1 chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
> ```

---

## 10. First-Time Initialization (Inisialisasi Pertama)

### Mengapa Perlu Urutan Eksekusi Statis?

**Versi Formal:** Laravel membutuhkan serangkaian perintah inisialisasi yang harus dijalankan dalam urutan spesifik saat pertama kali di-deploy. Masing-masing perintah memiliki dependensi terhadap output perintah sebelumnya. Melewatkan satu langkah atau menjalankan dalam urutan yang salah menghasilkan error yang sulit didiagnosis — mulai dari "No application encryption key has been specified" hingga "SQLSTATE connection refused".

**Versi Sederhana:** Ini seperti prosedur membuka rumah sakit baru: pertama pasang alat medis (composer install), lalu buat kunci brankas (key:generate), siapkan ruang arsip (migrate), dan pasang petunjuk arah (storage:link). Jika langkah-langkah ini dilakukan tanpa urutan, rumah sakit tidak bisa beroperasi.

### Urutan Eksekusi (Wajib Diikuti)

> **Konteks Eksekusi:** `User: mer_ops @ VPS` — jalankan setelah `docker compose up -d` pertama

```bash
# ================================================================
# FIRST-TIME INITIALIZATION - MER System
# ================================================================
# Jalankan perintah ini SATU KALI saja saat deployment pertama.
# Urutan TIDAK BOLEH diubah karena setiap langkah depends on
# output langkah sebelumnya.
# ================================================================

# Pastikan semua container sudah running
cd /var/www/mer-system/production
docker compose -f deployment/production/docker-compose.yml ps

# ================================================================
# STEP 1: composer install
# ================================================================
# Menginstall seluruh dependency PHP dari composer.lock
#
# --optimize-autoloader : menghasilkan classmap yang dioptimasi
#     sehingga PHP tidak perlu melakukan filesystem scan setiap kali
#     memanggil class. Meningkatkan autoloading speed ~60%.
#
# --no-dev : TIDAK menginstall development dependencies
#     (PHPUnit, Faker, Debugbar, dll). Mengurangi attack surface
#     dan ukuran vendor/ hingga ~30%.
#
# HARUS dijalankan PERTAMA karena semua artisan command
# berikutnya membutuhkan vendor/ directory.
docker exec production-app-1 \
    composer install --optimize-autoloader --no-dev --no-interaction

# ================================================================
# STEP 2: php artisan key:generate
# ================================================================
# Menggenerate APP_KEY (AES-256-CBC encryption key) dan menyimpannya
# ke file .env. Key ini digunakan untuk:
# - Enkripsi session cookie
# - Enkripsi data sensitif di database (jika menggunakan encrypt())
# - Signing CSRF token
#
# HARUS dijalankan SEBELUM migrate karena beberapa migration
# mungkin menggunakan encryption helper.
#
# PERHATIAN TENTANG .ENV:
# Dalam arsitektur kita, file .env berada di *host* VPS (bukan di dalam container).
# Nilai-nilai ini diinjeksi lewat `--env-file` pada saat container berjalan.
# Jika `APP_KEY` sudah terisi di file `.env` di host, LEWATI proses generate key,
# dan cukup jalankan reset cache configurasi sebagai gantinya:
# docker exec production-app-1 php artisan config:clear
# docker exec production-app-1 php artisan config:cache
#
docker exec production-app-1 \
    php artisan key:generate --force --no-interaction

# ================================================================
# STEP 3: php artisan migrate
# ================================================================
# Membuat semua tabel database dari migration files.
#
# --force : WAJIB di production. Tanpa flag ini, Laravel menolak
#     menjalankan migration di environment production sebagai
#     mekanisme keamanan (mencegah migration tidak sengaja).
#
# HARUS dijalankan SETELAH key:generate karena beberapa seeder
# atau migration callback mungkin membutuhkan APP_KEY.
docker exec production-app-1 \
    php artisan migrate --force --no-interaction

# ================================================================
# STEP 4: php artisan storage:link
# ================================================================
# Membuat symbolic link: public/storage -> storage/app/public
#
# Ini memungkinkan file yang diupload oleh user (foto bukti
# insiden medis, dokumen pendukung) dapat diakses via URL publik
# (https://mers-rsryacudu.com/storage/foto-insiden.jpg).
#
# Tanpa symlink ini, semua file upload tidak bisa ditampilkan
# di browser — user melihat gambar rusak (broken image).
docker exec production-app-1 \
    php artisan storage:link --no-interaction

# ================================================================
# STEP 5: Cache Optimization (Opsional tapi Sangat Direkomendasikan)
# ================================================================
# Meng-compile konfigurasi, routes, dan views ke file PHP statis
# sehingga tidak perlu parsing ulang setiap request.
# Meningkatkan throughput ~30%.
docker exec production-app-1 php artisan config:cache
docker exec production-app-1 php artisan route:cache
docker exec production-app-1 php artisan view:cache

# ================================================================
# VERIFIKASI
# ================================================================
echo "=== First-Time Initialization Complete ==="
echo ""
echo "Checklist:"

# Verifikasi vendor/ terinstall
docker exec production-app-1 test -d vendor && echo "[OK] vendor/ directory exists" || echo "[FAIL] vendor/ missing"

# Verifikasi APP_KEY terisi (Mengecek langsung ke .env)
docker exec production-app-1 grep -q "^APP_KEY=" .env && echo "[OK] APP_KEY is set" || echo "[FAIL] APP_KEY not set"

# Verifikasi migration berhasil
docker exec production-app-1 php artisan migrate:status | tail -5

# Verifikasi log direktori supervisor ( memastikan fix sebelumnya berhasil)
docker exec production-app-1 test -d /var/log/supervisor && echo "[OK] Supervisor log dir exists" || echo "[FAIL] Supervisor log dir missing"

# Verifikasi storage link
docker exec production-app-1 test -d public/storage && echo "[OK] Storage link exists" || echo "[FAIL] Storage link missing"

echo ""
echo "Cek Browser: Buka URL/IP Anda untuk melihat halaman MER System."
```

### Ringkasan Urutan Dependensi

```
STEP 1: composer install
   |
   +-- Menghasilkan: vendor/ directory (semua dependency PHP)
   |
   v
STEP 2: key:generate
   |
   +-- Menghasilkan: APP_KEY di .env (encryption key)
   +-- Depend on: vendor/ (artisan command membutuhkan autoloader)
   |
   v
STEP 3: migrate --force
   |
   +-- Menghasilkan: tabel database di PostgreSQL
   +-- Depend on: APP_KEY (beberapa migration menggunakan encryption)
   +-- Depend on: database container healthy (depends_on di compose)
   |
   v
STEP 4: storage:link
   |
   +-- Menghasilkan: symlink public/storage -> storage/app/public
   +-- Depend on: vendor/ (artisan command)
   |
   v
STEP 5: config/route/view cache
   |
   +-- Menghasilkan: cached config/routes/views di bootstrap/cache/
   +-- Depend on: ALL previous steps completed
```

> [!WARNING]
> **JANGAN jalankan `key:generate` di production yang sudah berjalan** kecuali Anda yakin belum ada data terenkripsi. Mengganti APP_KEY akan membuat semua data yang di-encrypt dengan key lama tidak bisa di-decrypt — termasuk session aktif pengguna, password reset tokens, dan data medis terenkripsi.

---

## 11. Startup Script Laravel (Entrypoint)

### Alur Startup Container Production

```
Container Start
      |
      v
[docker-entrypoint.sh]
      |
      +---> 1. php artisan migrate --force
      |         (Update schema database)
      |
      +---> 2. php artisan config:cache
      |     2. php artisan route:cache
      |     2. php artisan view:cache
      |         (Compile config/routes/views ke file PHP statis)
      |
      +---> 3. Sync public assets ke shared volume
      |         (Lock-based untuk keamanan multi-replica)
      |
      +---> 4. chown www-data storage/
      |         (Set permission)
      |
      +---> exec "$@" (Jalankan CMD = supervisord)
      |
      v
[supervisord]
      |
      +---> [php-fpm]        (priority 1 - start pertama)
      +---> [queue-worker]   (priority 5)
      +---> [scheduler]      (priority 10)
```

File referensi: [`deployment/production/docker-entrypoint.sh`](../deployment/production/docker-entrypoint.sh)

### Mengapa `migrate --force` di Entrypoint?

| Alternatif | Pro | Kontra |
|-----------|-----|--------|
| **Manual via SSH** | Kontrol penuh | Human error, lupa migrate, tidak scalable |
| **CI/CD pipeline** | Otomatis, auditable | Membutuhkan infrastruktur CI/CD |
| **Entrypoint (dipilih)** | Otomatis, self-contained | Migration berjalan setiap container start (idempoten karena Laravel migration tracking) |

Laravel migration bersifat **idempoten** — menjalankan `migrate` berulang kali tidak mengubah apapun jika semua migration sudah dijalankan. Ini aman untuk diletakkan di entrypoint.

---

## 12. Verifikasi

### Test Security Headers

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# Test semua security headers dari response Nginx
curl -sI http://localhost | grep -iE \
    '(x-frame|x-content|strict-transport|referrer-policy|permissions-policy|content-security|server|x-powered)'

# Output yang diharapkan:
# x-frame-options: SAMEORIGIN
# x-content-type-options: nosniff
# strict-transport-security: max-age=31536000; includeSubDomains
# referrer-policy: strict-origin-when-cross-origin
# permissions-policy: camera=(), microphone=(), geolocation=(), payment=()
# content-security-policy: default-src 'self'; ...
# TIDAK ADA: server: nginx/x.x.x (tersembunyi oleh server_tokens off)
# TIDAK ADA: x-powered-by: PHP/8.x (tersembunyi oleh fastcgi_hide_header)
```

### Test Rate Limiting

```bash
# Kirim 20 request cepat ke /login untuk trigger rate limit.
for i in $(seq 1 20); do
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/login)
    echo "Request $i: HTTP $STATUS"
done

# Output yang diharapkan:
# Request 1-15: HTTP 200 (atau 302 redirect)
# Request 16-20: HTTP 429 (Too Many Requests)
```

### Test Blocked Paths

```bash
# Test akses ke file sensitif
echo "=== Testing Blocked Paths ==="
for path in "/.env" "/.git/config" "/storage/logs/laravel.log" "/artisan"; do
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost$path")
    echo "  $path => HTTP $STATUS"
done

# Output yang diharapkan: semua 403 Forbidden
```

### Test PHP-FPM Health

```bash
# Cek PHP-FPM processes
docker exec production-app-1 ps aux | grep php-fpm

# Cek PHP-FPM status (jika pm.status_path dikonfigurasi)
docker exec production-app-1 curl -s http://localhost/fpm-status 2>/dev/null || echo "FPM status not configured"

# Cek supervisord processes
docker exec production-app-1 supervisorctl status
```

### Checklist Verifikasi

| # | Item | Cara Test | Status Diharapkan |
|---|------|-----------|-------------------|
| 1 | Security Headers | `curl -sI` | 6 headers present |
| 2 | Server Version Hidden | `curl -sI` | Tidak ada `nginx/x.x` |
| 3 | PHP Version Hidden | `curl -sI` | Tidak ada `X-Powered-By` |
| 4 | Rate Limiting | 20 rapid requests | Request ke-16+ = 429 |
| 5 | .env Blocked | `curl /.env` | 403 Forbidden |
| 6 | .git Blocked | `curl /.git/config` | 403 Forbidden |
| 7 | Storage Blocked | `curl /storage/logs/...` | 403 Forbidden |
| 8 | PHP-FPM Running | `supervisorctl status` | RUNNING |
| 9 | Queue Worker Running | `supervisorctl status` | RUNNING |
| 10 | Gzip Active | `curl -H 'Accept-Encoding: gzip'` | Content-Encoding: gzip |

---

> **Dokumen selanjutnya:** [05-monitoring-dan-backup-strategy.md](./05-monitoring-dan-backup-strategy.md) — Setup Grafana Loki untuk log audit medis, metrik sistem ringan, dan backup PostgreSQL terenkripsi harian.
