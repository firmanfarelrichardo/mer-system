# Monitoring & Observability — MER System Production

## Arsitektur 5-Layer Monitoring

| Layer | Komponen | Jenis | Fungsi |
|-------|----------|-------|--------|
| Layer 1 | Prometheus + cAdvisor | Self-hosted | Metrik infrastruktur container (CPU, RAM, network) |
| Layer 1 | Node Exporter | Self-hosted (host) | Metrik VPS/host OS (disk, load, CPU fisik) |
| Layer 2 | Loki + Promtail | Self-hosted | Log aggregation (Nginx + Laravel) |
| Layer 3 | Grafana | Self-hosted | Dashboard visualisasi semua data |
| Layer 4 | Sentry | SaaS | Error tracking + stack trace aplikasi |
| Layer 5 | CrowdSec | Self-hosted | Intrusion detection (ada di stack produksi) |

Stack monitoring berjalan di file terpisah (`docker-compose.monitoring.yml`) dan terisolasi dari stack produksi. Jika monitoring crash, aplikasi tetap berjalan.

---

## Cara Menjalankan Stack Monitoring

```bash
cd /var/www/mer-system/deployment/production

# Start semua service monitoring
docker compose -f docker-compose.monitoring.yml up -d

# Cek status
docker compose -f docker-compose.monitoring.yml ps

# Lihat log semua service monitoring
docker compose -f docker-compose.monitoring.yml logs -f

# Stop tanpa menghapus data (volume tetap ada)
docker compose -f docker-compose.monitoring.yml stop

# Hapus container (data di volume tetap aman)
docker compose -f docker-compose.monitoring.yml down
```

---

## Prometheus — Metrik Infrastruktur

### Cara Kerja (Pull-based)

Prometheus secara aktif mengambil (scrape) metrik dari target setiap 15 detik. Target tidak perlu mengirim data — cukup menyediakan endpoint HTTP `/metrics`.

```
Prometheus ──GET /metrics──> cAdvisor      → metrik per-container Docker
Prometheus ──GET /metrics──> Node Exporter → metrik VPS/host OS
```

### Target yang Di-scrape

| Job | Target | Metrik yang Dikumpulkan |
|-----|--------|------------------------|
| `prometheus` | `localhost:9090` | Self-monitoring (queue length, scrape duration) |
| `cadvisor` | `mer-cadvisor:8080` | CPU/RAM/network/disk per container |
| `node-exporter` | `172.17.0.1:9100` | CPU core, RAM, disk space, load average |

### Contoh Query PromQL Berguna

```promql
# CPU usage rata-rata semua container (5 menit terakhir)
rate(container_cpu_usage_seconds_total{image!=""}[5m]) * 100

# Container dengan memory usage tertinggi
topk(5, container_memory_usage_bytes{image!=""})

# Disk space tersisa di VPS
node_filesystem_avail_bytes{mountpoint="/"} / node_filesystem_size_bytes{mountpoint="/"} * 100

# Load average 5 menit VPS
node_load5

# Container yang sudah restart > 0 kali (deteksi crash loop)
increase(container_start_time_seconds{image!=""}[1h]) > 1
```

### Akses Prometheus (Debug)

Prometheus tidak expose ke internet. Akses via SSH tunnel:

```bash
# Di komputer lokal
ssh -L 9090:localhost:9090 username@<ip-vps>
# Buka: http://localhost:9090
```

### Reload Konfigurasi Tanpa Restart

```bash
curl -X POST http://localhost:9090/-/reload
# (Port 9090 accessible dari dalam Docker network atau via SSH tunnel)
```

---

## cAdvisor — Metrik Container Docker

### Cara Kerja

cAdvisor membaca metrik dari **cgroups** kernel Linux untuk setiap container yang berjalan. Data diekspos sebagai endpoint Prometheus `/metrics`.

```
Docker Engine
    └── cgroups ──dibaca oleh──> cAdvisor ──/metrics──> Prometheus
```

### Metrik Penting

| Metrik | Keterangan |
|--------|------------|
| `container_cpu_usage_seconds_total` | Total CPU seconds yang digunakan (kumulatif) |
| `container_memory_usage_bytes` | RAM yang digunakan saat ini |
| `container_memory_limit_bytes` | Limit RAM yang dikonfigurasi |
| `container_network_receive_bytes_total` | Total bytes diterima via network |
| `container_network_transmit_bytes_total` | Total bytes dikirim via network |
| `container_fs_usage_bytes` | Penggunaan disk container |

cAdvisor tidak expose port ke host. Hanya Prometheus yang mengaksesnya via Docker DNS (`mer-cadvisor:8080`).

---

## Node Exporter — Metrik Host OS

### Kenapa Diinstal di Host (Bukan Container)?

Container memiliki *isolated view* terhadap filesystem dan `/proc`. Node Exporter di dalam container hanya melihat resource yang dialokasikan ke container itu sendiri — bukan kondisi hardware VPS yang sebenarnya. Untuk mendapatkan data akurat:

- **Disk usage** VPS keseluruhan (bukan hanya overlay filesystem container)
- **CPU core count** fisik
- **Network interface** host (bukan veth container)
- **Load average** OS

Node Exporter harus berjalan sebagai proses host.

### Instalasi

```bash
sudo apt update && sudo apt install -y prometheus-node-exporter

# Batasi ke localhost saja (keamanan)
sudo systemctl edit prometheus-node-exporter
```

Tambahkan di editor:

```ini
[Service]
Environment="ARGS=--web.listen-address=127.0.0.1:9100"
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now prometheus-node-exporter

# Verifikasi
curl -s http://localhost:9100/metrics | grep node_load
```

Prometheus di dalam container mengakses Node Exporter via IP Docker bridge gateway ke host: `172.17.0.1:9100`.

---

## Loki — Log Aggregation

### Cara Kerja (Push-based)

Berbeda dari Prometheus yang pull, Loki menerima log yang di-push oleh Promtail. Loki menyimpan log dengan efisien menggunakan kompresi dan chunk storage.

```
Promtail ──HTTP POST──> Loki:3100 ──simpan──> chunk di disk (loki-data volume)
                                        │
                          Grafana ──LogQL query──> Loki ──hasil──> Grafana
```

### Konfigurasi Storage (loki-config.yml)

| Parameter | Nilai | Keterangan |
|-----------|-------|------------|
| Mode | Monolithic | Semua komponen dalam satu proses (cocok untuk VPS tunggal) |
| Schema | TSDB v13 | Storage engine terbaru, lebih efisien dari BoltDB |
| Retention | 720h (30 hari) | Log lebih dari 30 hari otomatis dihapus |
| WAL | Enabled | Write-Ahead Log mencegah data loss saat crash |
| Ingestion rate | 4 MB/s | Batas kecepatan log yang diterima (perlindungan DDoS log) |

### Contoh Query LogQL Berguna

```logql
# Semua log Nginx dengan status 500
{job="nginx-access"} |= "status=500"

# Log error Laravel dalam 1 jam terakhir
{job="laravel"} | logfmt | level = "error"

# IP address yang paling sering muncul di access log
{job="nginx-access"}
  | regexp `(?P<remote_addr>\d+\.\d+\.\d+\.\d+)`
  | unwrap remote_addr
  | count_over_time[1h]

# Laravel exception spesifik
{job="laravel"} |= "Illuminate\\Database\\QueryException"

# Log Nginx 404 dari IP spesifik
{job="nginx-access", remote_addr="1.2.3.4"} |= "404"
```

### Batas Resource (Perlindungan DDoS)

Loki memiliki hard limit 512 MB RAM. Saat DDoS menghasilkan jutaan baris log per menit, Loki akan ter-OOM-kill dan restart — **bukan** PostgreSQL atau PHP-FPM. Ini adalah perilaku yang disengaja (fail-safe).

---

## Promtail — Log Collection Agent

### Cara Kerja

Promtail memantau file log menggunakan `inotify` (Linux kernel file watcher). Setiap baris baru langsung dibaca, di-parse, diberi label, dan dikirim ke Loki.

```
Nginx access.log ──inotify──> Promtail
                                  ├── parse regex (IP, status, method)
                                  ├── tambah label: job="nginx-access"
                                  └── HTTP POST ──> Loki:3100
```

### Log yang Dipantau

| Job | File | Parse | Label yang Diekstrak |
|-----|------|-------|---------------------|
| `nginx-access` | `/var/log/nginx/access.log` | Regex (format `main` Nginx) | `remote_addr`, `status` |
| `nginx-error` | `/var/log/nginx/error.log` | Regex severity | `level` |
| `laravel` | `/var/log/laravel/laravel.log` | Multiline (stack trace) | `level` |

**Multiline handling untuk Laravel:** Satu entry log Laravel bisa terdiri dari 50+ baris (stack trace). Promtail menggunakan regex `^\[\d{4}-\d{2}-\d{2}` untuk mendeteksi awal entry baru — semua baris berikutnya sampai entry baru digabung menjadi satu log event di Loki.

### Posisi Baca (Positions File)

Promtail menyimpan offset baca terakhir di `/tmp/positions.yaml` (dipersistensikan via volume `promtail-positions`). Saat container restart, Promtail melanjutkan dari baris terakhir yang sudah dikirim — tidak membaca ulang seluruh file.

---

## Grafana — Dashboard Visualisasi

### Akses

Grafana bound ke `127.0.0.1:3000` — **tidak bisa diakses dari internet**. Gunakan SSH tunnel:

```bash
# Di komputer lokal operator
ssh -L 3000:localhost:3000 username@<ip-vps>
# Buka: http://localhost:3000
# Login: admin / <GRAFANA_ADMIN_PASSWORD dari .env>
```

### Datasource (Auto-Provisioned)

File `monitoring/grafana-datasources.yml` otomatis mendaftarkan datasource saat Grafana pertama kali start. Tidak perlu konfigurasi manual di UI.

| Datasource | URL | Bahasa Query | Konten |
|------------|-----|-------------|--------|
| Prometheus | `http://mer-prometheus:9090` | PromQL | Metrik infrastruktur |
| Loki | `http://mer-loki:3100` | LogQL | Log Nginx + Laravel |

### Dashboard yang Direkomendasikan (Import dari Grafana.com)

| ID | Nama | Keterangan |
|----|------|------------|
| `193` | Docker Host & Container Overview | Metrik cAdvisor — CPU, RAM, network per container |
| `1860` | Node Exporter Full | Metrik host OS lengkap |
| `13639` | Loki & Promtail | Overview log aggregation |
| `14055` | Nginx Log Analysis | Analisis traffic Nginx |

Cara import: Grafana → Dashboard → Import → Masukkan ID → Load

### Ganti Password Admin

```bash
# Via CLI (jika terlupa password)
docker compose -f docker-compose.monitoring.yml exec grafana \
    grafana cli admin reset-admin-password <PasswordBaru>

# Atau via UI: klik avatar (pojok kiri bawah) → Profile → Change Password
```

---

## Sentry — Error Tracking Aplikasi (Layer 4, SaaS)

### Instalasi Laravel Integration

```bash
# Di dalam container app (atau tambahkan ke Dockerfile jika ingin permanen)
docker compose exec app composer require sentry/sentry-laravel

# Publish konfigurasi
docker compose exec app php artisan vendor:publish --provider="Sentry\Laravel\ServiceProvider"
```

### Konfigurasi `.env`

```env
SENTRY_LARAVEL_DSN=https://<public-key>@o<org-id>.ingest.sentry.io/<project-id>
SENTRY_TRACES_SAMPLE_RATE=0.1   # Capture 10% request untuk performance monitoring
```

DSN didapat dari: https://sentry.io → Project → Settings → Client Keys (DSN)

### Apa yang Di-capture Sentry

- Semua **unhandled exception** (error 500) dengan stack trace lengkap
- **User context** (ID, email jika dikonfigurasi) saat terjadi error
- **Request context** (URL, method, headers, POST data)
- **Breadcrumbs** (urutan kejadian sebelum error terjadi)
- **Slow queries** (jika performance monitoring diaktifkan)
- **Queue job failures**

### Perbedaan Sentry vs Loki

| | Sentry | Loki |
|---|--------|------|
| Fokus | Exception & error aplikasi | Semua baris log (access, error, queue) |
| Interface | Web UI sentry.io | Grafana LogQL query |
| Grouping | Otomatis deduplikasi error yang sama | Raw log entries |
| Alert | Email/Slack/PagerDuty per error | Harus buat alert rule manual di Grafana |
| Biaya | Gratis 10K events/bulan | Self-hosted, gratis |

---

## Alokasi Resource Monitoring

| Service | RAM Limit | CPU Limit | Alasan |
|---------|-----------|-----------|--------|
| Prometheus | 512 MB | 0.5 core | Time-series DB, ~5K active series untuk MER System |
| Loki | 512 MB | 0.5 core | Batasan ketat agar DDoS tidak crash VPS |
| Grafana | 256 MB | 0.25 core | Caching query result, stabil di 256 MB |
| cAdvisor | 256 MB | 0.25 core | Ringan untuk 6-10 container |
| Promtail | 256 MB | 0.25 core | Agent sederhana, buffer log minimal |
| **Total** | **~1.5 GB** | **~1.75 core** | |

---

## Troubleshooting Monitoring

### Prometheus tidak bisa scrape cAdvisor

```bash
# Cek DNS resolution
docker compose -f docker-compose.monitoring.yml exec prometheus \
    wget -O- http://mer-cadvisor:8080/metrics 2>&1 | head -5

# Jika gagal: pastikan keduanya berada di mer-prod-network
docker network inspect mer-prod-network | grep -A 3 "cadvisor\|prometheus"
```

### Loki tidak menerima log dari Promtail

```bash
# Cek log Promtail
docker compose -f docker-compose.monitoring.yml logs promtail --tail 30

# Cek status pengiriman
docker compose -f docker-compose.monitoring.yml exec promtail \
    wget -O- http://localhost:9080/ready 2>&1
```

### Grafana tidak bisa connect ke datasource

```bash
# Cek bahwa container Prometheus dan Loki running
docker compose -f docker-compose.monitoring.yml ps

# Tes koneksi dari container Grafana
docker compose -f docker-compose.monitoring.yml exec grafana \
    wget -O- http://mer-prometheus:9090/-/healthy
docker compose -f docker-compose.monitoring.yml exec grafana \
    wget -O- http://mer-loki:3100/ready
```

### Disk monitoring penuh

```bash
# Cek ukuran volume monitoring
docker system df -v | grep -E "prometheus-data|loki-data|grafana-data"

# Hapus data Prometheus lama secara manual (hati-hati)
# Loki otomatis hapus data >30 hari berdasarkan konfigurasi retention
```
