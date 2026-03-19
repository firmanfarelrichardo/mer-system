# 05 — Monitoring, Log Audit & Backup Strategy

> **Sistem**: Medication Error Reporting (MER) — Rumah Sakit  
> **Klasifikasi**: HIGH-RISK (Data Medis Sensitif / PHI)  
> **Spesifikasi VPS**: 2 Core CPU, 8GB RAM, 100GB SSD  
> **Prasyarat**: Dokumen [01](./01-arsitektur-dan-hardening-os.md) s/d [04](./04-nginx-waf-dan-laravel.md) sudah dilaksanakan

---

## Daftar Isi

- [⚠️ SOP Implementasi (Wajib Dibaca)](#️-sop-implementasi-wajib-dibaca)
1. [Strategi Monitoring untuk VPS Terbatas](#1-strategi-monitoring-untuk-vps-terbatas)
2. [Grafana Loki + Promtail (Centralized Logging)](#2-grafana-loki--promtail-centralized-logging)
3. [Uptime Kuma (Uptime & Health Monitoring)](#3-uptime-kuma-uptime--health-monitoring)
4. [Netdata (Lightweight System Metrics)](#4-netdata-lightweight-system-metrics)
5. [Log Audit Medis di Laravel](#5-log-audit-medis-di-laravel)
6. [Sentry — Exception & Error Tracking](#6-sentry--exception--error-tracking)
7. [Backup Strategy — PostgreSQL Terenkripsi](#7-backup-strategy--postgresql-terenkripsi)
8. [Skrip Backup Otomatis Harian](#8-skrip-backup-otomatis-harian)
9. [Restore Prosedur](#9-restore-prosedur)
10. [Verifikasi](#10-verifikasi)

---

## ⚠️ SOP Implementasi (Wajib Dibaca)

Mengingat aplikasi `production` berstatus *live* dan menangani data medis (*High-Risk*), **SANGAT TIDAK DISARANKAN** untuk mengeksekusi langsung dokumen ini ke *environment production*. Spesifikasi VPS yang terbatas (8GB RAM) berisiko mengalami *Out of Memory* (OOM) yang dapat menyebabkan gangguan *downtime* berantai pada aplikasi jika set *monitoring* tidak diuji terlebih dahulu.

**Alur Kerja (Workflow) Zero-Downtime yang Wajib Dilakukan:**

1. **Kerjakan di Branch Khusus (`feature/monitoring`):**
   * Di komputer lokal Anda, buat *branch* khusus dari `main` atau `development`: `git checkout -b feature/monitoring`.
   * Pada *branch* ini, tambahkan file `docker-compose.monitoring.yml`, konfigurasi Loki/Promtail, rancangan skrip *backup*, dan edit konfigurasi Laravel (`logging.php`, `sentry.php`).
   * *Push* *branch* `feature/monitoring` ke repositori Git pusat.
2. **Deploy dan Uji Coba di `staging` (VPS):**
   * Masuk ke direktori *staging* di VPS: `cd /var/www/mer-system/staging`
   * Tarik (*pull*) dan ganti aktifkan *branch* tersebut: `git fetch origin && git checkout feature/monitoring`
   * Terapkan konfigurasi *monitoring* (*docker compose up*). Pastikan Anda **menyesuaikan semua nilai direktori dan nama container di dalam script yang ada di dokumen ini** menjadi versi *staging* (contoh: ubah *path* `/production/` menjadi `/staging/`, dan container `mer-db-prod` menjadi `mer-db-staging`).
   * Pantau penggunaan RAM dan CPU *server* menggunakan perintah `docker stats`. Pastikan OOM Killer tidak aktif.
   * Uji coba simulasi pembuatan log medis, verifikasi sensor (*scrubbing*) PHP di Sentry, dan jalankan simulasi Skrip Backup serta Restore secara manual.
3. **Deploy ke `production` (Hanya jika Staging Sukses):**
   * Jika semua komponen sudah diverifikasi berjalan mulus di *staging* VPS tanpa membebani sistem pembatasan *resource*, gabungkan (*merge*) *branch* `feature/monitoring` ke `main`/`production`.
   * Pindah ke direktori *production*: `cd /var/www/mer-system/production`
   * Lakukan integrasi versi terbaru: `git pull origin main` (atau *branch production* yang relevan).
   * Jalankan instruksi *monitoring* dan *backup* secara nyata di *production*.

---

## 1. Strategi Monitoring untuk VPS Terbatas

### Mengapa BUKAN ELK Stack?

**Versi Formal:** ELK Stack (Elasticsearch, Logstash, Kibana) membutuhkan minimal 4GB RAM hanya untuk Elasticsearch saja. Pada VPS dengan 8GB RAM yang sudah menjalankan PostgreSQL, Redis, PHP-FPM, dan Nginx, menggunakan ELK akan menyebabkan resource contention dan OOM (Out of Memory) kills.

**Versi Sederhana:** ELK seperti mobil mewah — fitur lengkap tapi boros bensin. VPS kita hanya punya tangki bensin kecil (8GB RAM). Kita pilih motor irit (Loki + Promtail) yang tetap bisa mengantarkan kita ke tujuan yang sama: melihat log terpusat.

### Stack Monitoring yang Dipilih

| Komponen | RAM Estimasi | Fungsi |
|----------|-------------|--------|
| **Grafana Loki** | ~128MB | Log aggregation & query engine (pengganti Elasticsearch) |
| **Promtail** | ~64MB | Log collector/shipper (pengganti Logstash/Filebeat) |
| **Grafana** | ~128MB | Dashboard visualisasi (pengganti Kibana) |
| **Uptime Kuma** | ~128MB | Uptime monitoring, health check, alerting |
| **Netdata** | ~64MB | Real-time system metrics (CPU, RAM, disk, network) |
| **Total** | **~512MB** | **8x lebih hemat dari ELK (~4GB)** |

---

## 2. Grafana Loki + Promtail (Centralized Logging)

### Apa Itu Loki?

**Versi Formal:** Grafana Loki adalah sistem log aggregation yang terinspirasi oleh Prometheus. Berbeda dengan Elasticsearch yang melakukan full-text indexing (mahal), Loki hanya mengindeks metadata/label (murah). Log content disimpan dalam compressed chunks dan hanya di-decompress saat query. Ini menghasilkan footprint storage dan memory yang jauh lebih kecil.

**Versi Sederhana:** Loki seperti sistem arsip rumah sakit. Bukan menyimpan fotokopi setiap halaman rekam medis (full-text index), tapi hanya katalog kartu (label index). Saat butuh rekam medis tertentu, baru buka lemari yang tepat berdasarkan katalog.

### Docker Compose — Monitoring Stack

> **Lokasi file:** `/var/www/mer-system/production/deployment/production/docker-compose.monitoring.yml`

```yaml
# ===========================================
# MER System - Monitoring Stack
# ===========================================
# Dijalankan terpisah dari stack utama agar bisa di-restart
# tanpa mengganggu production application.
# Jalankan: docker compose -f docker-compose.monitoring.yml up -d
# ===========================================

services:
  # -------------------------------------------
  # Grafana Loki — Log Aggregation Engine
  # -------------------------------------------
  loki:
    image: grafana/loki:2.9.4
    container_name: mer-loki
    restart: unless-stopped
    ports:
      - "127.0.0.1:3100:3100"
    volumes:
      - loki-data:/loki
      - ./loki-config.yml:/etc/loki/local-config.yaml:ro
    command: -config.file=/etc/loki/local-config.yaml
    networks:
      - mer-monitoring-network
    deploy:
      resources:
        limits:
          memory: 192M
        reservations:
          memory: 64M

  # -------------------------------------------
  # Promtail — Log Collector
  # Membaca log dari Docker containers dan file sistem,
  # lalu mengirimnya ke Loki.
  # -------------------------------------------
  promtail:
    image: grafana/promtail:2.9.4
    container_name: mer-promtail
    restart: unless-stopped
    volumes:
      - /var/log:/var/log:ro
      - /var/lib/docker/containers:/var/lib/docker/containers:ro
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./promtail-config.yml:/etc/promtail/config.yml:ro
    command: -config.file=/etc/promtail/config.yml
    networks:
      - mer-monitoring-network
    depends_on:
      - loki
    deploy:
      resources:
        limits:
          memory: 96M
        reservations:
          memory: 32M

  # -------------------------------------------
  # Grafana — Dashboard & Visualization
  # -------------------------------------------
  grafana:
    image: grafana/grafana:10.3.1
    container_name: mer-grafana
    restart: unless-stopped
    ports:
      - "127.0.0.1:3000:3000"
    environment:
      GF_SECURITY_ADMIN_USER: ${GRAFANA_ADMIN_USER:-admin}
      GF_SECURITY_ADMIN_PASSWORD: ${GRAFANA_ADMIN_PASSWORD}
      GF_LOG_LEVEL: warn
    volumes:
      - grafana-data:/var/lib/grafana
    networks:
      - mer-monitoring-network
    depends_on:
      - loki
    deploy:
      resources:
        limits:
          memory: 192M
        reservations:
          memory: 64M

  # -------------------------------------------
  # Uptime Kuma — Health & Uptime Monitoring
  # -------------------------------------------
  uptime-kuma:
    image: louislam/uptime-kuma:1
    container_name: mer-uptime-kuma
    restart: unless-stopped
    # Menggunakan network_mode host agar Kuma bisa langsung mem-ping localhost (127.0.0.1) DB/Redis di VPS
    network_mode: "host"
    environment:
      - UPTIME_KUMA_HOST=127.0.0.1
      - UPTIME_KUMA_PORT=3001
    volumes:
      - uptime-kuma-data:/app/data
    deploy:
      resources:
        limits:
          memory: 192M
        reservations:
          memory: 64M

networks:
  mer-monitoring-network:
    driver: bridge

volumes:
  loki-data:
    driver: local
  grafana-data:
    driver: local
  uptime-kuma-data:
    driver: local
```

### Konfigurasi Loki

> **Lokasi file:** `deployment/production/loki-config.yml`

```yaml
# ===========================================
# MER System - Loki Configuration
# Dioptimalkan untuk VPS 8GB RAM
# ===========================================

auth_enabled: false

server:
  http_listen_port: 3100

common:
  path_prefix: /loki
  storage:
    filesystem:
      chunks_directory: /loki/chunks
      rules_directory: /loki/rules
  replication_factor: 1
  ring:
    kvstore:
      store: inmemory

schema_config:
  configs:
    - from: 2024-01-01
      store: tsdb
      object_store: filesystem
      schema: v13
      index:
        prefix: index_
        period: 24h

limits_config:
  # Retensi log 30 hari — cukup untuk audit trail medis bulanan.
  # Log lebih lama diarsip melalui backup script.
  retention_period: 720h
  ingestion_rate_mb: 4
  ingestion_burst_size_mb: 6
  max_query_series: 500
  max_query_parallelism: 2

compactor:
  working_directory: /loki/compactor
  compaction_interval: 10m
  retention_enabled: true
  retention_delete_delay: 2h
  retention_delete_worker_count: 150

# Membatasi penggunaan memori untuk query
query_range:
  parallelise_shardable_queries: false

# Chunk dan ingester config dioptimalkan untuk throughput rendah
ingester:
  chunk_idle_period: 1h
  max_chunk_age: 2h
  chunk_retain_period: 30s
  wal:
    dir: /loki/wal
```

### Konfigurasi Promtail

> **Lokasi file:** `deployment/production/promtail-config.yml`

```yaml
# ===========================================
# MER System - Promtail Configuration
# ===========================================

server:
  http_listen_port: 9080
  grpc_listen_port: 0

positions:
  filename: /tmp/positions.yaml

clients:
  - url: http://loki:3100/loki/api/v1/push

scrape_configs:
  # -------------------------------------------
  # Job 1: Docker Container Logs
  # Mengumpulkan stdout/stderr dari semua container Docker.
  # -------------------------------------------
  - job_name: docker
    docker_sd_configs:
      - host: unix:///var/run/docker.sock
        refresh_interval: 5s
    relabel_configs:
      - source_labels: ['__meta_docker_container_name']
        regex: '/(.*)'
        target_label: 'container'
      - source_labels: ['__meta_docker_container_log_stream']
        target_label: 'logstream'
      - source_labels: ['__meta_docker_container_label_com_docker_compose_service']
        target_label: 'service'

  # -------------------------------------------
  # Job 2: System Auth Log
  # Mengumpulkan log autentikasi SSH untuk audit keamanan.
  # -------------------------------------------
  - job_name: auth
    static_configs:
      - targets:
          - localhost
        labels:
          job: auth
          __path__: /var/log/auth.log

  # -------------------------------------------
  # Job 3: Nginx Access Log (JSON format)
  # -------------------------------------------
  - job_name: nginx
    static_configs:
      - targets:
          - localhost
        labels:
          job: nginx
          __path__: /var/lib/docker/containers/*/*.log
    pipeline_stages:
      - docker: {}
      - match:
          selector: '{service="web"}'
          stages:
            - json:
                expressions:
                  status: status
                  request: request
                  request_time: request_time
            - labels:
                status:

  # -------------------------------------------
  # Job 4: UFW Firewall Log
  # -------------------------------------------
  - job_name: ufw
    static_configs:
      - targets:
          - localhost
        labels:
          job: ufw
          __path__: /var/log/ufw.log

  # -------------------------------------------
  # Job 5: Cloudflare UFW Update Log
  # -------------------------------------------
  - job_name: cloudflare-update
    static_configs:
      - targets:
          - localhost
        labels:
          job: cloudflare-update
          __path__: /var/log/mer-system/cloudflare-ufw-update.log
```

### Deploy Monitoring Stack

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
cd /var/www/mer-system/production/deployment/production

# Deploy monitoring stack
docker compose -f docker-compose.monitoring.yml up -d

# Verifikasi semua container berjalan
docker compose -f docker-compose.monitoring.yml ps

# Test Loki ready
curl -s http://127.0.0.1:3100/ready
# Output: ready

# Test Grafana login (menggunakan -L agar mengikuti redirect 301)
curl -sL -o /dev/null -w "%{http_code}" http://127.0.0.1:3000/login
# Output: 200
```

### Akses Grafana dari Komputer Lokal

```bash
# Buat SSH tunnel
ssh -L 3000:127.0.0.1:3000 -L 3001:127.0.0.1:3001 -p 49152 mer_ops@<IP_VPS_ANDA>

# Buka browser:
#   Grafana: http://localhost:3000
#   Uptime Kuma: http://localhost:3001
```

### Setup Grafana — Tambahkan Loki sebagai Data Source

1. Login Grafana (`admin` / password dari env var)
2. Navigasi: **Connections > Data sources > Add data source**
3. Pilih **Loki**
4. URL: `http://loki:3100`
5. Klik **Save & Test**

### Contoh Query LogQL untuk Audit Medis

```logql
# Lihat semua log dari container PHP-FPM (aplikasi Laravel)
{service="app"}

# Filter log dengan status HTTP 500 (internal server error)
{service="web"} | json | status=500

# Cari log yang mengandung kata "medication" (kasus audit medis)
{service="app"} |= "medication"

# Log autentikasi SSH yang gagal (deteksi brute force)
{job="auth"} |= "Failed password"

# Log UFW yang di-blokir (deteksi scanning)
{job="ufw"} |= "[UFW BLOCK]"
```

---

## 3. Uptime Kuma (Uptime & Health Monitoring)

### Setup Monitors di Uptime Kuma

Setelah akses Uptime Kuma via SSH tunnel (`http://localhost:3001`):

1. Buat akun admin saat pertama kali akses
2. Navigasi ke tombol **+ Tambah Monitor** di kiri atas untuk setiap entri di bawah ini:

| Nama Monitor (Ramah) | Tipe Monitor | URL / Host | Port | Interval (Detik) | Alasan |
|-----------------------|---------------|-------------|------|-------------------|--------|
| **MER Production** | `HTTP(s)` | `https://mers-rsryacudu.com/login` | - | 60 | Tambahkan **Header** (Advanced): `User-Agent` dengan isi `Mozilla/5.0` agar tidak diblokir (403) oleh Cloudflare/WAF. |
| **MER Staging** | `HTTP(s)` | `http://127.0.0.1:8080/login` | - | 60 | Health check staging langsung via port lokal VPS yang di-forward. |
| **PostgreSQL Live** | `TCP Port` | `127.0.0.1` | `5432` | 30 | Cek langsung binding port localhost PostgreSQL dari internal VPS. |
| **Redis Server** | `Redis` | `redis://:secret_redis_staging@127.0.0.1:6379`| `6379` | 30 | Cek konektivitas node caching di environment lokal VPS. |
| **SSH VPS Access**| `TCP Port` | `127.0.0.1` | `49152` | 60 | Cek service custom SSH VPS berjalan melalui loopback VPS itu sendiri. |

*Catatan Penting:* 
1. Pastikan mengubah Nilai "Kode Status yang Diterima" pada bagian HTTP/S menjadi `200-299` atau kosongkan.
2. Karena Uptime Kuma kini diatur sebagai `network_mode: "host"`, ia berbagi jaringan persis seperti OS VPS aslinya. Oleh karena itu, kita **hanya perlu menggunakan IP `127.0.0.1`** tanpa terhalang isolasi network Docker (menghindari error _ECONNREFUSED_).

### Setup Notifikasi Alarm (Email Gmail)

Uptime Kuma dapat mengirim peringatan seketika (alert) bila "Monitor" di atas berstatus **DOWN**:

1. Klik tombol akun admin di sudut kanan atas > Pilih **Pengaturan** > Pilih **Notifikasi**
2. Klik tombol **Setel Notifikasi**
3. Isi parameter ini untuk Email via Gmail:
   - **Tipe Notifikasi**: `Email (SMTP)`
   - **Nama yang Ramah**: `Peringatan IT MER Server`
   - **Nama Inang SMTP**: `smtp.gmail.com`
   - **Port**: `465` (Secara SSL) atau `587`
   - **Keamanan TLS**: Aktifkan (centang)
   - **Pengguna Akun Email**: `[alamat.email.anda]@gmail.com`
   - **Kata Sandi**: *(Gunakan Sandi Aplikasi / App Password Google, JANGAN sandi email asli!)*
   - **Dari Surel (From)**: `[alamat.email.anda]@gmail.com`
   - **Beralih Kepada (To)**: Email IT Manager/Penerima Alert
4. Klik tombol **Uji Coba**, bila ada notifikasi sukses, simpan pengaturannya.
5. Kaitkan notifikasi ini di tab *Umum* setiap Monitor yang telah dibuat.

---

## 4. Netdata (Lightweight System Metrics)

### Instalasi

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# Jalankan Netdata sebagai Docker container.
# Port 19999 hanya di-bind ke localhost untuk keamanan.
docker run -d \
    --name mer-netdata \
    --restart unless-stopped \
    --pid=host \
    --network host \
    -v netdata-config:/etc/netdata \
    -v netdata-lib:/var/lib/netdata \
    -v netdata-cache:/var/cache/netdata \
    -v /etc/passwd:/host/etc/passwd:ro \
    -v /etc/group:/host/etc/group:ro \
    -v /etc/localtime:/etc/localtime:ro \
    -v /proc:/host/proc:ro \
    -v /sys:/host/sys:ro \
    -v /etc/os-release:/host/etc/os-release:ro \
    -v /var/log:/host/var/log:ro \
    -v /var/run/docker.sock:/var/run/docker.sock:ro \
    -e NETDATA_CLAIM_TOKEN="" \
    -e DO_NOT_TRACK=1 \
    --memory=128m \
    --cap-add SYS_PTRACE \
    --security-opt apparmor=unconfined \
    netdata/netdata:stable

# Verifikasi
curl -s http://127.0.0.1:19999/api/v1/info | jq '.version'
```

### Akses Netdata

```bash
# Via SSH tunnel
ssh -L 19999:127.0.0.1:19999 -p 49152 mer_ops@<IP_VPS_ANDA>
# Buka: http://localhost:19999
```

Netdata menyediakan dashboard real-time untuk:
- **CPU**: utilization per core, load average
- **Memory**: RAM usage, swap, cache
- **Disk**: I/O throughput, latency, space usage
- **Network**: bandwidth in/out, errors, drops
- **Docker**: per-container resource usage

---

## 5. Log Audit Medis di Laravel

### Rekomendasi Logging untuk Sistem Medis

Untuk sistem yang menangani data medis sensitif, setiap operasi CRUD pada data pasien **wajib** memiliki audit trail. Konfigurasikan Laravel logging di `config/logging.php`:

```php
// Tambahkan channel khusus audit medis
'channels' => [
    // ... channel existing ...

    'medical_audit' => [
        'driver' => 'daily',
        'path' => storage_path('logs/medical-audit.log'),
        'level' => 'info',
        'days' => 90,  // Retensi 90 hari di disk
        'permission' => 0640,
    ],
],
```

### Contoh Penggunaan di Controller

```php
use Illuminate\Support\Facades\Log;

// Setiap operasi pada data insiden medis harus di-log
Log::channel('medical_audit')->info('Insiden medication error dibuat', [
    'action' => 'create',
    'user_id' => auth()->id(),
    'user_name' => auth()->user()->name,
    'ip_address' => request()->ip(),
    'incident_id' => $incident->id,
    'timestamp' => now()->toIso8601String(),
]);
```

Log ini otomatis dikumpulkan oleh Promtail (via Docker container log atau file mount) dan bisa di-query di Grafana:

```logql
{service="app"} |= "medical_audit" | json
```

---

## 6. Sentry — Exception & Error Tracking

### Mengapa Sentry Berbeda dari Loki?

**Versi Sederhana:** Loki adalah CCTV yang merekam aktivitas orang keluar-masuk (log server/infrastruktur). Sentry adalah dokter spesialis yang menganalisis penyebab dokter pingsan saat operasi, lengkap dengan gejalanya (error aplikasi/exception). Keduanya bekerja saling melengkapi.

**Versi Formal:** 
- **Loki** mencatat *audit trail*, *access logs* Nginx, dan *stdout/stderr* container. Fokusnya pada telemetri infrastruktur dan rekam jejak operasional sistem.
- **Sentry** (via SDK Laravel) menangkap *unhandled exceptions*, mencakup *stack trace* kode PHP, merekam state aplikasi (variabel), dan menghitung impact pengguna. Fokusnya pada penyelesaian bug (error tracking).

### Peringatan Kebocoran Data / PHI

> [!CAUTION]
> **POTENSI KEBOCORAN PROTECTED HEALTH INFORMATION (PHI) DI SENTRY** 
> Sentry secara default mengambil *context* (isi variabel, HTTP payload, request header) saat exception terjadi. Jika payload request berisi data pasien, data klinis tersebut **akan terkirim dan tersimpan di server Sentry** saat terjadi crash. Hal ini merupakan pelanggaran berat terhadap standar kerahasiaan medis.

### Konfigurasi Laravel Sentry (Sensor Data Otomatis)

Untuk mencegah kebocoran PHI, `send_default_pii` harus dinonaktifkan dan field medis yang rentan terekspos dimasukkan ke dalam opsi `scrub_fields` agar digantikan oleh label `[Filtered]` secara otomatis oleh SDK Sentry.

> **Lokasi file:** `config/sentry.php`

```php
<?php

return [

    // DSN dari .env Production/Staging
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),
    
    // SANGAT PENTING: Matikan pengiriman Personally Identifiable Information
    // (PII) secara default seperti alamat IP pasien, cookie sesi, dan user ID.
    'send_default_pii' => false,

    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 1.0),

    // Sensor parameter berisiko otomatis saat crash
    'scrub_fields' => [
        // Keamanan dasar
        'password',
        'password_confirmation',
        'token',
        
        // Data PHI (Protected Health Information) Rumah Sakit
        'nik',
        'nama_pasien',
        'rekam_medis',
        'no_rm',
        'diagnosis',
        'tanggal_lahir',
        'alamat_pasien',
        'no_telp',
        'hasil_lab',
        'tindakan_medis',
        'obat_diresepkan',
        'catatan_klinis'
    ],

];
```

---

## 7. Backup Strategy — PostgreSQL Terenkripsi

### Prinsip Backup 3-2-1

**Versi Formal:** Strategi backup 3-2-1 mengharuskan: 3 salinan data, 2 media penyimpanan berbeda, 1 salinan offsite. Ini memberikan redundansi maksimum terhadap berbagai skenario kegagalan (hardware failure, ransomware, bencana alam).

**Versi Sederhana:** Simpan 3 salinan rekam medis: 1 di brankas rumah sakit (server), 1 di flashdisk manajer (local backup), 1 di brankas bank (offsite/cloud). Jika satu hilang, masih ada dua cadangan.

### Implementasi

| Salinan | Lokasi | Metode |
|---------|--------|--------|
| **Salinan 1** | PostgreSQL live | Data aktif di database |
| **Salinan 2** | `/var/www/mer-system/shared/backups/` | `pg_dump` terenkripsi, rotasi 7 hari |
| **Salinan 3** | Cloud storage (offsite) | Dikirim via `rclone` ke S3/GCS/Backblaze |

---

## 8. Skrip Backup Otomatis Harian

### Buat Skrip Backup

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
sudo tee /opt/mer-system/scripts/backup-database.sh > /dev/null << 'BACKUP_SCRIPT'
#!/usr/bin/env bash
# ===========================================
# MER System - Encrypted PostgreSQL Backup
# ===========================================
# Skrip ini dijalankan via cronjob setiap hari pukul 02:00 WIB.
#
# Alur kerja:
# 1. pg_dump dari container PostgreSQL
# 2. Kompresi dengan gzip (mengecilkan ukuran ~80%)
# 3. Enkripsi dengan OpenSSL AES-256-CBC (military-grade)
# 4. Simpan di lokal dengan rotasi 7 hari
# 5. Upload ke offsite storage via rclone (opsional)
# 6. Log hasilnya untuk audit trail
#
# KEAMANAN:
# - Backup terenkripsi sehingga jika file dicuri, data tetap aman
# - Key enkripsi disimpan terpisah dari file backup
# - Rotasi otomatis mencegah disk penuh
# ===========================================

set -euo pipefail

# --- Konfigurasi ---
BACKUP_DIR="/var/www/mer-system/shared/backups"
LOG_FILE="/var/log/mer-system/backup.log"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7

# Kredensial database (dibaca dari file .env production)
ENV_FILE="/var/www/mer-system/production/.env"
DB_DATABASE=$(grep "^DB_DATABASE=" "$ENV_FILE" | cut -d= -f2 | tr -d '"' | tr -d "'")
DB_USERNAME=$(grep "^DB_USERNAME=" "$ENV_FILE" | cut -d= -f2 | tr -d '"' | tr -d "'")
DB_PASSWORD=$(grep "^DB_PASSWORD=" "$ENV_FILE" | cut -d= -f2 | tr -d '"' | tr -d "'")

# Key enkripsi — disimpan di file terpisah dengan permission ketat.
# File ini harus dibuat manual saat setup awal:
#   openssl rand -base64 32 > /opt/mer-system/.backup-encryption-key
#   chmod 600 /opt/mer-system/.backup-encryption-key
ENCRYPTION_KEY_FILE="/opt/mer-system/.backup-encryption-key"

# Nama file backup
BACKUP_FILENAME="mer_db_${TIMESTAMP}.sql.gz.enc"
BACKUP_PATH="${BACKUP_DIR}/${BACKUP_FILENAME}"

# --- Fungsi ---
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

cleanup() {
    # Hapus file temporary jika ada error
    rm -f "${BACKUP_DIR}/mer_db_${TIMESTAMP}.sql.gz" 2>/dev/null || true
}
trap cleanup EXIT

# --- Validasi ---
mkdir -p "$BACKUP_DIR"
mkdir -p "$(dirname "$LOG_FILE")"

if [ ! -f "$ENCRYPTION_KEY_FILE" ]; then
    log "ERROR: Encryption key file tidak ditemukan: $ENCRYPTION_KEY_FILE"
    log "Buat dengan: openssl rand -base64 32 > $ENCRYPTION_KEY_FILE && chmod 600 $ENCRYPTION_KEY_FILE"
    exit 1
fi

# --- Eksekusi Backup ---
log "=== Memulai backup database: $DB_DATABASE ==="

# Step 1: pg_dump dari container Docker
# Menggunakan docker exec untuk menjalankan pg_dump di dalam container database.
# PGPASSWORD di-set sebagai environment variable agar pg_dump tidak meminta password.
log "Step 1/4: Menjalankan pg_dump..."
docker exec -e PGPASSWORD="$DB_PASSWORD" mer-db-prod \
    pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" \
    --no-owner \
    --no-privileges \
    --clean \
    --if-exists \
    --format=plain | gzip > "${BACKUP_DIR}/mer_db_${TIMESTAMP}.sql.gz"

DUMP_SIZE=$(du -h "${BACKUP_DIR}/mer_db_${TIMESTAMP}.sql.gz" | cut -f1)
log "Step 1/4: pg_dump selesai. Ukuran terkompresi: $DUMP_SIZE"

# Step 2: Enkripsi dengan AES-256-CBC
# -pbkdf2: menggunakan Password-Based Key Derivation Function v2 (modern, aman)
# -iter 100000: 100,000 iterasi PBKDF2 (memperlambat brute-force)
log "Step 2/4: Mengenkripsi backup..."
openssl enc -aes-256-cbc -pbkdf2 -iter 100000 \
    -salt \
    -in "${BACKUP_DIR}/mer_db_${TIMESTAMP}.sql.gz" \
    -out "$BACKUP_PATH" \
    -pass file:"$ENCRYPTION_KEY_FILE"

# Hapus file unencrypted
rm -f "${BACKUP_DIR}/mer_db_${TIMESTAMP}.sql.gz"

ENCRYPTED_SIZE=$(du -h "$BACKUP_PATH" | cut -f1)
log "Step 2/4: Enkripsi selesai. Ukuran final: $ENCRYPTED_SIZE"

# Step 3: Rotasi — hapus backup lebih lama dari RETENTION_DAYS hari
log "Step 3/4: Rotasi backup (retensi ${RETENTION_DAYS} hari)..."
DELETED_COUNT=$(find "$BACKUP_DIR" -name "mer_db_*.sql.gz.enc" -mtime +${RETENTION_DAYS} -delete -printf '%f\n' | wc -l)
log "Step 3/4: $DELETED_COUNT backup lama dihapus."

# Step 4: Upload ke offsite storage (opsional — perlu setup rclone)
# Jika rclone sudah dikonfigurasi, uncomment blok berikut:
# log "Step 4/4: Mengupload ke offsite storage..."
# rclone copy "$BACKUP_PATH" remote:mer-backups/database/ --log-file="$LOG_FILE" --log-level INFO
# log "Step 4/4: Upload offsite selesai."
log "Step 4/4: Offsite upload dilewati (rclone belum dikonfigurasi)."

# --- Ringkasan ---
TOTAL_BACKUPS=$(find "$BACKUP_DIR" -name "mer_db_*.sql.gz.enc" | wc -l)
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)
log "=== Backup selesai ==="
log "  File    : $BACKUP_FILENAME"
log "  Ukuran  : $ENCRYPTED_SIZE"
log "  Total   : $TOTAL_BACKUPS backup ($TOTAL_SIZE)"
log ""
BACKUP_SCRIPT

# Set permission
sudo chmod 700 /opt/mer-system/scripts/backup-database.sh
```

### Setup Encryption Key

```bash
# Generate key enkripsi yang kuat (32 byte random = 256-bit)
sudo openssl rand -base64 32 > /opt/mer-system/.backup-encryption-key
sudo chmod 600 /opt/mer-system/.backup-encryption-key
sudo chown root:root /opt/mer-system/.backup-encryption-key

# PENTING: Simpan salinan key ini di tempat yang aman (password manager, safe).
# Jika key hilang, SEMUA backup tidak bisa didekripsi.
cat /opt/mer-system/.backup-encryption-key
```

### Daftarkan Cronjob

```bash
# Jalankan backup setiap hari pukul 02:00 WIB.
# Dipilih dini hari karena traffic paling rendah dan tidak mengganggu operasional.
(sudo crontab -l 2>/dev/null; echo "0 2 * * * /opt/mer-system/scripts/backup-database.sh") | sudo crontab -

# Verifikasi
sudo crontab -l
```

### Test Manual

```bash
# Jalankan backup secara manual untuk memastikan berjalan
sudo /opt/mer-system/scripts/backup-database.sh

# Periksa hasil
ls -lh /var/www/mer-system/shared/backups/
cat /var/log/mer-system/backup.log
```

---

## 9. Restore Prosedur

### Restore dari Backup Terenkripsi

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# === Prosedur Restore Database ===

# Variabel — sesuaikan dengan backup yang ingin di-restore
BACKUP_FILE="/var/www/mer-system/shared/backups/mer_db_20260314_020000.sql.gz.enc"
ENCRYPTION_KEY_FILE="/opt/mer-system/.backup-encryption-key"

# Step 1: Dekripsi backup
openssl enc -aes-256-cbc -pbkdf2 -iter 100000 \
    -d \
    -in "$BACKUP_FILE" \
    -out /tmp/mer_restore.sql.gz \
    -pass file:"$ENCRYPTION_KEY_FILE"

# Step 2: Dekompresi
gunzip /tmp/mer_restore.sql.gz

# Step 3: Restore ke database
# PERHATIAN: Ini akan MENGGANTI seluruh isi database.
# Pastikan Anda yakin sebelum menjalankan perintah ini.
docker exec -i mer-db-prod psql -U mer_dbadmin -d mer_production < /tmp/mer_restore.sql

# Step 4: Bersihkan file temporary
rm -f /tmp/mer_restore.sql

# Step 5: Verifikasi
docker exec mer-db-prod psql -U mer_dbadmin -d mer_production \
    -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public';"

echo "Restore selesai. Verifikasi aplikasi di browser."
```

### Restore Staging dari Backup Production

Untuk mengisi staging dengan data production (agar testing dengan data realistis):

```bash
# Dekripsi dan dekompresi seperti di atas, lalu restore ke staging
docker exec -i mer-db-staging psql -U mer_staging_user -d mer_staging < /tmp/mer_restore.sql
```

---

## 10. Verifikasi

### Checklist Monitoring & Backup

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
echo "========================================="
echo "  MER System - Monitoring & Backup Check"
echo "========================================="

echo ""
echo "[1] Loki"
LOKI_STATUS=$(curl -sf http://127.0.0.1:3100/ready 2>/dev/null || echo "NOT RUNNING")
echo "  Status: $LOKI_STATUS"

echo ""
echo "[2] Grafana"
GRAFANA_STATUS=$(curl -sfL -o /dev/null -w "%{http_code}" http://127.0.0.1:3000/login 2>/dev/null || echo "NOT RUNNING")
echo "  HTTP Status: $GRAFANA_STATUS"

echo ""
echo "[3] Uptime Kuma"
KUMA_STATUS=$(curl -sfL -o /dev/null -w "%{http_code}" http://127.0.0.1:3001 2>/dev/null || echo "NOT RUNNING")
echo "  HTTP Status: $KUMA_STATUS"

echo ""
echo "[4] Netdata"
NETDATA_STATUS=$(curl -sfL -o /dev/null -w "%{http_code}" http://127.0.0.1:19999 2>/dev/null || echo "NOT RUNNING")
echo "  HTTP Status: $NETDATA_STATUS"

echo ""
echo "[5] Promtail"
docker ps --filter "name=mer-promtail" --format "  Container: {{.Status}}"

echo ""
echo "[6] Backup"
echo "  Encryption key exists: $(test -f /opt/mer-system/.backup-encryption-key && echo 'YES' || echo 'NO')"
echo "  Backup script exists : $(test -f /opt/mer-system/scripts/backup-database.sh && echo 'YES' || echo 'NO')"
echo "  Backup cronjob       : $(sudo crontab -l 2>/dev/null | grep -c 'backup-database' && echo 'ACTIVE' || echo 'NOT FOUND')"
echo "  Latest backup        : $(ls -t /var/www/mer-system/shared/backups/mer_db_*.sql.gz.enc 2>/dev/null | head -1 || echo 'NONE')"
echo "  Total backups        : $(find /var/www/mer-system/shared/backups -name 'mer_db_*.sql.gz.enc' 2>/dev/null | wc -l)"

echo ""
echo "[7] Memory Usage (All Containers)"
docker stats --no-stream --format "  {{.Name}}: {{.MemUsage}} ({{.MemPerc}})" 2>/dev/null | sort

echo ""
echo "========================================="
```

### Tabel Ringkasan Verifikasi

| # | Item | Cara Verifikasi | Status Diharapkan |
|---|------|----------------|-------------------|
| 1 | Loki ready | `curl localhost:3100/ready` | `ready` |
| 2 | Grafana accessible | `curl localhost:3000` | HTTP 200 |
| 3 | Loki data source | Grafana UI > Data Sources | Connected |
| 4 | Promtail running | `docker ps` | Up |
| 5 | Container logs di Loki | Grafana > Explore > `{service="app"}` | Log entries muncul |
| 6 | Auth logs di Loki | Grafana > Explore > `{job="auth"}` | Log entries muncul |
| 7 | Uptime Kuma running | `curl localhost:3001` | HTTP 200 |
| 8 | Health monitors active | Uptime Kuma UI | All monitors green |
| 9 | Netdata running | `curl localhost:19999` | HTTP 200 |
| 10 | Backup script works | Manual run | File `.enc` created |
| 11 | Backup cronjob active | `sudo crontab -l` | Entry exists |
| 12 | Encryption key exists | `ls /opt/mer-system/.backup-encryption-key` | File exists |
| 13 | Restore tested | Restore to staging | Data intact |
| 14 | Total RAM usage | `docker stats` | < 7GB |

---

### Setup Offsite Backup dengan Rclone (Opsional)

Untuk mengirim backup ke cloud storage (Backblaze B2 sebagai contoh karena paling murah):

```bash
# Install rclone
curl https://rclone.org/install.sh | sudo bash

# Konfigurasi remote storage
rclone config
# Ikuti wizard:
# - name: mer-offsite
# - type: b2 (atau s3, gcs, sesuai provider)
# - account: <account_id>
# - key: <application_key>

# Test upload
rclone copy /var/www/mer-system/shared/backups/ mer-offsite:mer-backups/database/ --dry-run

# Uncomment baris rclone di backup-database.sh setelah berhasil
```

---

> **Dokumentasi selesai.** Kelima file dokumentasi mencakup seluruh siklus deployment dari hardening OS hingga monitoring dan backup:
>
> 1. [01-arsitektur-dan-hardening-os.md](./01-arsitektur-dan-hardening-os.md) — Fondasi keamanan server
> 2. [02-cloudflare-dan-edge-security.md](./02-cloudflare-dan-edge-security.md) — Perlindungan tepi
> 3. [03-docker-production-staging.md](./03-docker-production-staging.md) — Containerization & isolasi
> 4. [04-nginx-waf-dan-laravel.md](./04-nginx-waf-dan-laravel.md) — Web server & aplikasi
> 5. [05-monitoring-dan-backup-strategy.md](./05-monitoring-dan-backup-strategy.md) — Observability & disaster recovery
