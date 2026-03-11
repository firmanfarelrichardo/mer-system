# Keamanan — MER System Production

## Gambaran Umum

Keamanan sistem diimplementasikan dalam beberapa lapisan (defense-in-depth):

```
Internet
    │
    ▼
[Layer 1] Cloudflare — WAF, DDoS protection, TLS termination
    │
    ▼
[Layer 2] CrowdSec Bouncer — IP blacklist, rate limiting L7
    │
    ▼
[Layer 3] Nginx — Security headers, request filtering, real IP extraction
    │
    ▼
[Layer 4] Laravel Middleware — SecurityHeaders (defense-in-depth), TrustProxies
    │
    ▼
[Layer 5] PHP-FPM — Aplikasi, validasi input, CSRF protection
```

---

## Layer 2 — CrowdSec (Intrusion Detection & Prevention)

### Cara Kerja

CrowdSec terdiri dari dua komponen terpisah yang bekerja bersama:

**CrowdSec Engine** (service `crowdsec`)
- Membaca `access.log` Nginx secara real-time dari shared volume `nginx-logs`
- Menganalisis pola serangan menggunakan *scenarios* dari komunitas global:
  - Brute force login
  - Path traversal (`../../../etc/passwd`)
  - SQL injection probing
  - HTTP scanning (Nikto, Nmap)
  - Bad user-agents (crawler berbahaya)
- Ketika IP terdeteksi berbahaya: menambahkannya ke **Local API decision list**

**CrowdSec Bouncer** (service `crowdsec-bouncer`)
- Berdiri di depan Nginx sebagai reverse proxy ringan
- Setiap request masuk: cek IP ke Local API CrowdSec Engine
- IP terlarang → langsung kembalikan `403 Forbidden` (request tidak pernah sampai PHP-FPM)
- IP bersih → teruskan ke Nginx

```
Internet ──:8888──> [ Bouncer ] ──cek IP──> [ CrowdSec Engine LAPI ]
                        │
                   IP berbahaya? ──YES──> 403 Forbidden
                        │ NO
                        ▼
                   [ Nginx :80 ]
```

### Setup Awal CrowdSec

**Generate API key untuk Bouncer:**

```bash
docker exec mer-crowdsec cscli bouncers add nginx-bouncer
# Salin key yang ditampilkan ke .env → CROWDSEC_BOUNCER_API_KEY
docker compose restart crowdsec-bouncer
```

**Perintah operasional CrowdSec:**

```bash
# Lihat IP yang saat ini di-ban
docker exec mer-crowdsec cscli decisions list

# Ban IP secara manual (misal: setelah investigasi)
docker exec mer-crowdsec cscli decisions add --ip 1.2.3.4 --duration 24h --reason "Manual ban"

# Hapus ban dari IP tertentu
docker exec mer-crowdsec cscli decisions delete --ip 1.2.3.4

# Lihat alert (deteksi serangan)
docker exec mer-crowdsec cscli alerts list

# Lihat status semua scenarios yang aktif
docker exec mer-crowdsec cscli scenarios list

# Update scenarios dari komunitas
docker exec mer-crowdsec cscli hub update
docker exec mer-crowdsec cscli hub upgrade
```

### Integrasi dengan CrowdSec Central API (Opsional)

Mendaftarkan instance ke CrowdSec Central API memungkinkan berbagi informasi threat intelligence dengan komunitas global:

```bash
docker exec mer-crowdsec cscli capi register
docker compose restart crowdsec
```

---

## Layer 3 — Nginx Security Headers

Semua header berikut dikonfigurasi di `nginx.conf` dalam blok `http {}` sehingga berlaku untuk **semua response** dari server.

### HSTS (HTTP Strict Transport Security)

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
```

| Parameter | Nilai | Efek |
|-----------|-------|------|
| `max-age` | 31536000 (1 tahun) | Browser ingat bahwa domain ini HANYA boleh diakses via HTTPS selama 1 tahun |
| `includeSubDomains` | - | Berlaku juga untuk semua subdomain |
| `preload` | - | Domain bisa didaftarkan ke HSTS Preload List browser |

**Konsekuensi:** Setelah header ini diterima browser, seluruh request ke domain (termasuk port 80/HTTP) akan otomatis di-redirect ke HTTPS oleh browser — bahkan sebelum packet keluar ke jaringan. Ini mencegah SSL stripping attack.

### Content Security Policy (CSP)

```nginx
add_header Content-Security-Policy "
  default-src 'self';
  script-src 'self' 'unsafe-inline';
  style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;
  font-src 'self' https://fonts.gstatic.com data:;
  img-src 'self' data: blob:;
  connect-src 'self';
  frame-ancestors 'none';
  base-uri 'self';
  form-action 'self';
  object-src 'none'
" always;
```

| Direktif | Nilai | Keterangan |
|----------|-------|------------|
| `default-src 'self'` | - | Semua resource harus dari origin yang sama |
| `script-src 'unsafe-inline'` | - | Diperlukan untuk Blade inline scripts dan Alpine.js |
| `frame-ancestors 'none'` | - | Mencegah aplikasi di-embed di iframe (anti-clickjacking) |
| `form-action 'self'` | - | Form hanya boleh submit ke origin yang sama (anti-CSRF via CSP) |
| `object-src 'none'` | - | Blokir plugin lama (Flash, applet Java) |

**Efek praktis:** Browser akan otomatis memblokir iklan pop-up, external script injection, dan iframe dari situs pihak ketiga.

### Header Lain

| Header | Nilai | Fungsi |
|--------|-------|--------|
| `X-Frame-Options` | `DENY` | Blokir semua iframe — MER tidak perlu di-embed di manapun |
| `X-Content-Type-Options` | `nosniff` | Cegah browser menebak MIME type dari konten |
| `X-XSS-Protection` | `1; mode=block` | XSS filter legacy (browser IE/Edge lama) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Kirim referrer URL hanya untuk request same-origin |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` | Matikan sensor API yang tidak diperlukan |

### Filtering Request Berbahaya

```nginx
# Nginx hanya meneruskan request ke PHP-FPM untuk file index.php
# Semua file .php lainnya langsung dikembalikan 404
location ~ \.php$ {
    try_files $uri =404;
    # → hanya index.php yang ada di public/, sehingga request
    #   ke /storage/logs/laravel.php → 404 (tidak di-execute)
}

# Upload file dengan ekstensi eksekutabel diblokir di level Nginx
# (dikonfigurasi via CSP dan PHP upload validation di level aplikasi)
```

### Cloudflare Real IP

Tanpa konfigurasi ini, `$remote_addr` menampilkan IP Cloudflare CDN — bukan IP pengunjung asli. CrowdSec tidak bisa memblokir IP yang benar.

```nginx
set_real_ip_from 173.245.48.0/20;   # Cloudflare IP ranges
set_real_ip_from 103.21.244.0/22;
# ... (13 range total)
real_ip_header CF-Connecting-IP;
```

Perbarui daftar range ini jika Cloudflare menambah IP baru: https://www.cloudflare.com/ips/

---

## Layer 3 — HTTPS via Cloudflare

### Mode: Full (Strict)

Konfigurasi Cloudflare menggunakan **Full (Strict)** mode:

```
Browser ──TLS──> Cloudflare ──HTTP──> VPS
```

- TLS di-terminasi di Cloudflare (bukan di VPS)
- Traffic Cloudflare → VPS berjalan via HTTP plaintext (di dalam jaringan terlindungi Cloudflare)
- Laravel tetap menghasilkan URL `https://` karena membaca header `X-Forwarded-Proto: https` dari Cloudflare

### Konfigurasi Laravel untuk TrustProxies

Di `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_AWS_ELB
    );
})
```

Di `app/Providers/AppServiceProvider.php`:

```php
// Paksa semua URL yang digenerate Laravel menggunakan https://
if (app()->environment('production')) {
    URL::forceScheme('https');
}
```

Di `.env`:

```env
SESSION_SECURE_COOKIE=true   # Cookie session hanya dikirim via HTTPS
SESSION_SAME_SITE=lax        # Cegah CSRF dari situs external
```

---

## Layer 4 — Laravel SecurityHeaders Middleware

File: `app/Http/Middleware/SecurityHeaders.php`

Middleware ini menambahkan header keamanan tambahan dari sisi PHP sebagai lapisan kedua (defense-in-depth). Jika Nginx sekalipun bypass, header tetap dikirim dari aplikasi.

Middleware ini di-register sebagai **global middleware** di `bootstrap/app.php` sehingga berlaku untuk semua request.

---

## Manajemen Secret

### Prinsip: Secret Tidak Boleh di Git

File `.env` di-`gitignore` dan **tidak pernah di-commit** ke repository. Hanya `.env.example` (berisi nama variabel tanpa nilai) yang ada di Git.

### Lokasi Secret di VPS

| Secret | Lokasi | Cara generate |
|--------|--------|---------------|
| `APP_KEY` | `/var/www/mer-system/deployment/production/.env` | `php artisan key:generate --show` (otomatis via deploy.sh) |
| `DB_PASSWORD` | `.env` | `openssl rand -base64 32` |
| `REDIS_PASSWORD` | `.env` | `openssl rand -base64 32` |
| `CROWDSEC_BOUNCER_API_KEY` | `.env` | `docker exec mer-crowdsec cscli bouncers add nginx-bouncer` |
| `GRAFANA_ADMIN_PASSWORD` | `.env` | `openssl rand -base64 24` |

### Rotasi Secret

Untuk merotasi secret (misal: setelah insiden keamanan):

```bash
# Rotasi DB_PASSWORD
# 1. Ubah password di PostgreSQL
docker compose exec db psql -U postgres -c "ALTER USER mer_user PASSWORD 'PasswordBaruXyz';"
# 2. Update .env
nano .env  # → DB_PASSWORD=PasswordBaruXyz
# 3. Recreate container app
docker compose up -d --no-deps --force-recreate app

# Rotasi REDIS_PASSWORD
# Redis memerlukan restart karena password dikonfigurasi via command flag
nano .env  # → REDIS_PASSWORD=RedisBaruXyz
docker compose up -d --no-deps --force-recreate redis app

# Rotasi APP_KEY (BERBAHAYA: semua session dan encrypted data tidak bisa didekripsi)
# HANYA lakukan jika key sudah terkompromis. Semua user akan logout paksa.
nano .env  # → APP_KEY= (kosongkan)
bash deploy.sh  # Step 3 akan generate key baru otomatis
```

---

## Port Exposure Summary

| Port | Bisa diakses dari | Service | Keterangan |
|------|-------------------|---------|------------|
| `:8888` | Internet | CrowdSec Bouncer | Pintu masuk publik (bukan Nginx langsung) |
| `:80` | Internal Docker | Nginx | Tidak bisa diakses dari luar Docker network |
| `:9000` | Internal Docker | PHP-FPM | Hanya Nginx yang terhubung |
| `:5432` | Internal Docker | PostgreSQL | Tidak pernah expose ke host |
| `:6379` | Internal Docker | Redis | Tidak pernah expose ke host |
| `:8080` | Internal Docker | CrowdSec LAPI | Hanya Bouncer yang terhubung |
| `:3000` | `127.0.0.1` saja | Grafana | Akses via SSH tunnel |
| `:9090` | Internal Docker | Prometheus | Akses via SSH tunnel jika perlu debug |
| `:9100` | `127.0.0.1` saja | Node Exporter | Akses via Docker bridge (172.17.0.1) |

---

## Checklist Audit Keamanan

Jalankan pemeriksaan ini secara berkala (bulanan):

```bash
# 1. Cek apakah ada port yang tidak seharusnya terbuka
ss -tlnp | grep -E ':80|:443|:5432|:6379|:9090|:9100'

# 2. Cek status CrowdSec (apakah ada serangan aktif)
docker exec mer-crowdsec cscli alerts list --since 24h

# 3. Cek IP yang sedang di-ban
docker exec mer-crowdsec cscli decisions list

# 4. Update scenarios CrowdSec dari komunitas
docker exec mer-crowdsec cscli hub update && docker exec mer-crowdsec cscli hub upgrade

# 5. Cek keberadaan file berbahaya di web root
docker compose exec app find /var/www/html/public -name "*.php" ! -name "index.php"
# Hasilnya harus kosong — hanya index.php yang boleh ada

# 6. Verifikasi session cookie aman
curl -I https://mers-rsryacudu.com | grep -i "set-cookie"
# Harus ada: HttpOnly; Secure; SameSite=Lax

# 7. Tes security headers
curl -I https://mers-rsryacudu.com | grep -E "Strict-Transport|Content-Security|X-Frame"
```
