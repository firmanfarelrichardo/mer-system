# 07 — Analisis End-to-End: Rekonsiliasi & Master Workflow

> **Sistem**: Medication Error Reporting (MER) — Rumah Sakit  
> **Klasifikasi**: HIGH-RISK (Data Medis Sensitif / PHI)  
> **Tujuan Dokumen**: Analisis kesiapan 6 dokumen sebelumnya untuk eksekusi *Zero-to-Launch* tanpa interupsi, identifikasi celah (gaps), dan penyediaan *Master Workflow* yang mengikat semuanya.

---

## Daftar Isi

1. [Kesimpulan Analisis Eksekusi Nul-ke-Luncur (Zero-to-Launch)](#1-kesimpulan-analisis-eksekusi-nul-ke-luncur-zero-to-launch)
2. [Celah Operasional Tersembunyi (The Missing Links)](#2-celah-operasional-tersembunyi-the-missing-links)
3. [Solusi Tambalan (Patch) untuk Celah Teridentifikasi](#3-solusi-tambalan-patch-untuk-celah-teridentifikasi)
4. [Master Workflow: Urutan Eksekusi Definitif](#4-master-workflow-urutan-eksekusi-definitif)
5. [Skrip Inisialisasi Pra-Pipeline (Wajib Dijalankan Sekali)](#5-skrip-inisialisasi-pra-pipeline-wajib-dijalankan-sekali)

---

## 1. Kesimpulan Analisis Eksekusi Nul-ke-Luncur (Zero-to-Launch)

**Pertanyaan:** *Apakah keenam dokumen standar (01-06) sudah cukup untuk dijalankan dari nol hingga selesai deploy tanpa ada perubahan di tengah-tengah proses?*

**Jawaban:** **BELUM CUKUP (TIDAK BISA DILAKUKAN SECARA MEMBABI BUTA).**

Meskipun kualitas masing-masing dokumen sudah setara standar Enterprise/DevSecOps, ketika dirangkai menjadi satu alur waktu lurus (linear timeline), terdapat **3 (tiga) celah operasional / paradoks** yang akan menyebabkan kegagalan deployment (Error 500, Error 525/526 Cloudflare, atau Kegagalan CI/CD) jika eksekusi dilakukan tanpa langkah penengah.

---

## 2. Celah Operasional Tersembunyi (The Missing Links)

### Celah 1: Paradoks File `.env` dan `docker-compose up`

- **Dari Dokumen 03 (Docker)**: Menginstruksikan `docker-compose up -d`. Konfigurasi ini bergantung penuh pada variabel di file `.env` (seperti `DB_PASSWORD`, `APP_KEY`, dll).
- **Dari Dokumen 06 (CI/CD)**: Pipeline me-deploy kode menggunakan `scp-action`. Namun, file `.env` **TIDAK PERNAH** dikirim oleh repository (karena terdaftar di `.gitignore`).
- **Dampaknya**: Jika setelah VPS di-hardening kita langsung memicu CI/CD, deployment akan berhasil menyalin file, namun Docker akan **gagal start** atau PostgreSQL akan membuat database tanpa password karena file `.env` tidak ada di server.

### Celah 2: Resolusi Mode Cloudflare "Full (Strict)" vs "Nginx Port 80"

- **Dari Dokumen 02 (Cloudflare)**: Mewajibkan enkripsi mode **Full (Strict)** dan membuat Origin Certificate.
- **Dari Dokumen 03 & 04 (Nginx)**: Menginstruksikan container Nginx hanya untuk meng-expose **port 80:80** (HTTP) di default `docker-compose.yml`, dengan catatan bahwa Nginx block 443 adalah *opsional* (di Dokumen 04).
- **Dampaknya**: Jika Cloudflare diatur ke Full (Strict), Cloudflare **WAJIB** terhubung ke origin server melalui HTTPS (port 443) dengan memvalidasi Origin Certificate. Karena container Nginx ditutup di port 443, pengunjung akan disambut **Error 525 (SSL Handshake Failed)** atau **Error 521 (Web Server Is Down)**.

### Celah 3: Benturan "First-Time Initialization" dengan CI/CD

- **Dari Dokumen 04 (LaravelInit)**: Diinstruksikan eksekusi statis manual: `composer install` -> `key:generate` -> `migrate`.
- **Dari Dokumen 06 (CI/CD Action)**: Skrip deployment CI/CD mengeksekusi otomatis `migrate --force`.
- **Dampaknya**: Saat pipeline CI/CD pertama kali berjalan (First Deploy), jika `APP_KEY` belum di-generate secara manual (karena kita mengandalkan full-automation CI/CD), perintah `php artisan migrate` di *Step 3 Deploy SSH* akan **gagal/crash** ("No application encryption key has been specified").

---

## 3. Solusi Tambalan (Patch) untuk Celah Teridentifikasi

Agar sistem dapat di-deploy dari nol tanpa henti, lakukan penyesuaian/pre-kondisi berikut **SEBELUM** memicu Pipeline CI/CD (Dokumen 06):

### Patch untuk Celah 1 & 3: Pembuatan Konfigurasi Dasar (Skeleton)

Sebelum push kode pertama kali untuk memicu CI/CD, Administrator Server (DevOps) **WAJIB** membuat folder tujuan dan file `.env` production secara manual di VPS. File ini memuat `APP_KEY` awal dan password database rahasia.

### Patch untuk Celah 2: Aktivasi Port 443 di Docker & Nginx

Blok Server HTTPS (Port 443) di [Dokumen 04 Section 5](./04-nginx-waf-dan-laravel.md#5-konfigurasi-ssl-untuk-origin-certificate) **BUKAN OPSI**, melainkan **KEWAJIBAN**. 

Perubahan yang harus diterapkan dalam codebase repo sebelum push CI/CD:

1. **`deployment/production/docker-compose.yml`**: Nginx harus mem-publish `443:443` dan melakukan mounting direktori `/etc/ssl/cloudflare`.
2. **`deployment/production/nginx.conf`**: Harus memuat blok `server { listen 443 ssl; ... ssl_certificate ... }`.

---

## 4. Master Workflow: Urutan Eksekusi Definitif

Berikut adalah alur waktu (chronological order) mutlak dari poin 0 hingga sistem live. Ikuti tanpa melompat.

### Fase 1: Persiapan Infrastruktur (Hari ke-1)
1. Beli VPS Hostinger dan arahkan A Record DNS (Proxy Status: **DNS Only** sementara) ke IP VPS. (Dokumen 02)
2. Lakukan Hardening OS, pembuatan user `mer_ops`, Swap 4GB, dan UFW. (Dokumen 01)
3. Instal Docker Engine dan atur limit rotasi log daemon ke 50MB. (Dokumen 03)

### Fase 2: Keamanan Edge & SSL (Hari ke-1)
4. Masuk ke Cloudflare Dashboard. Ubah Proxy Status ke **Proxied (Awan Oranye)**.
5. Set SSL Mode ke **Full (Strict)**. Aktifkan WAF Managed Rules. (Dokumen 02)
6. Generate **Origin Certificate** di Cloudflare Dashboard.
7. Login ke VPS via SSH, buat folder `/etc/ssl/cloudflare`, dan simpan Origin Certificate + Private Key secara manual. Download CA Auth. (Dokumen 02 Section 5)

### Fase 3: Observabilitas (Hari ke-2)
8. Deploy stack Monitoring (Grafana, Loki, Promtail, Uptime Kuma) di VPS. (Dokumen 05)

### Fase 4: Persiapan CI/CD & State Awal (Hari ke-2)
9. Di Repository GitHub, masuk ke Settings > Secrets. Masukkan 4 secret (`VPS_SSH_HOST`, `PORT`, `USER`, `KEY`). Konfigurasi GitHub Environments. (Dokumen 06)
10. **(LANGKAH KRUSIAL BARU)**: Login ke VPS, jalankan skrip "Pre-Pipeline Initialization" (lihat Section 5 di bawah) untuk membuat kerangka direktori, mengatur `.env` definitif, dan pre-generate `APP_KEY`.

### Fase 5: Modifikasi Kode Repositori Lokal (Hari ke-2)
11. Update kode `docker-compose.yml` Anda untuk membuka port 443 dan mount `/etc/ssl/cloudflare`.
12. Update `nginx.conf` untuk menggunakan HTTPS 443 TLS.
13. Commit dan Push kode ke branch `main`.

### Fase 6: Otomatisasi & Peluncuran
14. GitHub Actions (Doc 06) terpicu otomatis.
15. CI menguji kode (lulus).
16. Reviewer meng-approve deployment.
17. CD mentransfer file via SCP (TIDAK MENIMPA `.env`).
18. CD via SSH memperbaiki permission UID 33:33.
19. CD mem-build Docker, up container, dan menjalankan migrasi database dengan aman.
20. Sistem LIVE.

---

## 5. Skrip Inisialisasi Pra-Pipeline (Wajib Dijalankan Sekali)

Sebelum mem-push kode ke GitHub yang memicu Pipeline CI/CD, jalankan blok perintah ini di VPS agar CD Pipeline tidak crash.

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# 1. Buat struktur direktori untuk production
mkdir -p /var/www/mer-system/production
mkdir -p /var/www/mer-system/shared/backups
cd /var/www/mer-system/production

# 2. Buat file .env secara manual yang tidak akan pernah di-timpa oleh GitHub Actions SCP
cat > .env << 'EOF'
APP_NAME="MER System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mers-rsryacudu.com

# APP_KEY akan diisi di langkah 3
APP_KEY=

# Database PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=mer_production
DB_USERNAME=mer_dbadmin
DB_PASSWORD=GANTI_DENGAN_PASSWORD_DB_SANGAT_KUAT_123!

# Cache & Session
CACHE_STORE=redis
CACHE_PREFIX=mer_
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

# Queue & Mail
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PASSWORD=GANTI_DENGAN_REDIS_PASSWORD_KUAT_123!
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=GANTI_DENGAN_SMTP_USER
MAIL_PASSWORD=GANTI_DENGAN_SMTP_PASS
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@mers-rsryacudu.com"
MAIL_FROM_NAME="MER System Alerts"
EOF

# 3. Amankan permissions .env
chmod 600 .env

# 4. Pre-Generate APP_KEY tanpa perlu composer install (menggunakan base64 acak)
# Laravel APP_KEY adalah string 'base64:' diikuti oleh 32 byte hash base64-encoded.
# Ini mencegah CI/CD gagal saat menjalankan php artisan migrate pertama kali.
RANDOM_KEY="base64:$(openssl rand -base64 32)"
sed -i "s|^APP_KEY=.*|APP_KEY=${RANDOM_KEY}|" .env

echo "Pre-Pipeline Initialization Selesai. APP_KEY Ter-generate."
echo "Anda sekarang BENAR-BENAR AMAN untuk mem-push kode ke GitHub dan memicu CI/CD."
```

## Penutup
Dengan ditutupnya ketiga celah logis ini dan diterapkannya *Master Workflow*, seluruh rangkaian dokumen 01 hingga 06 sekarang membentuk rantai operasi yang **Kalis Peluru (Bulletproof)**. Sistem kini dapat dibangun dari Server Kosong hingga Sistem Produksi Kelas-Rumah-Sakit secara lancar tanpa hambatan di tengah jalan.
