# Arsitektur Sistem — MER System Production

## Gambaran Umum

MER System berjalan di VPS tunggal (Ubuntu 24.04 LTS, 8 GB RAM, 2 CPU Core) dengan dua Docker Compose stack yang terpisah namun terhubung: **stack produksi** dan **stack monitoring**. Keduanya berkomunikasi melalui external Docker network `mer-prod-network`.

---

## Diagram Traffic Flow

```
Internet
    │
    ▼
Cloudflare (Full Strict SSL — terminasi TLS di Cloudflare)
    │  Header: X-Forwarded-Proto: https
    │  Header: CF-Connecting-IP: <IP asli pengunjung>
    │
    ▼
VPS Ubuntu 24.04 LTS
    │
    ┌─────────────────────────────────────────────────────────┐
    │  Docker Stack: mer-prod-network                          │
    │                                                          │
    │  Internet ──:8888──> [ CrowdSec Bouncer ]               │
    │                              │                           │
    │                    IP di blacklist? ──YES──> 403         │
    │                              │ NO                        │
    │                              ▼                           │
    │                       [ Nginx :80 ]                      │
    │                              │                           │
    │              ┌───────────────┴───────────────┐          │
    │              │                               │           │
    │    Mode Lockdown aktif?              Request normal      │
    │              │                               │           │
    │             YES                              ▼           │
    │              │                   [ PHP-FPM :9000 ]       │
    │              ▼                        (Laravel)          │
    │   maintenance.html (503)                    │            │
    │                              ┌──────────────┤            │
    │                              │              │            │
    │                        [ PostgreSQL ]   [ Redis ]        │
    │                           :5432 internal  :6379 internal │
    │                                                          │
    │  [ CrowdSec Engine ] ──baca──> nginx-logs (volume)      │
    └─────────────────────────────────────────────────────────┘
```

---

## Stack Produksi — 6 Services

File: `docker-compose.yml`

| Service | Image | Port | Keterangan |
|---------|-------|------|------------|
| `app` | Custom (Dockerfile) | Internal | PHP-FPM + Queue Worker + Scheduler via Supervisord |
| `web` | nginx:1.27-alpine | :80 internal | Reverse proxy, security headers, static files |
| `db` | postgres:16-alpine | Internal only | Database utama, healthcheck aktif |
| `redis` | redis:7-alpine | Internal only | Cache, session, queue broker |
| `crowdsec` | crowdsecurity/crowdsec | Internal only | IDS engine, baca nginx-logs, kelola blacklist |
| `crowdsec-bouncer` | crowdsec-nginx-bouncer-standalone | **:8888 publik** | L7 Firewall, filter IP sebelum masuk Nginx |

**Catatan penting:** Port publik yang sebenarnya adalah `:8888` (CrowdSec Bouncer), bukan `:80` Nginx secara langsung. Semua traffic melewati bouncer terlebih dahulu.

### Dependency Graph

```
crowdsec-bouncer
    └── depends_on: web, crowdsec
          web
              └── depends_on: app
                    app
                        ├── depends_on: db (service_healthy)
                        └── depends_on: redis (service_healthy)
```

---

## Stack Monitoring — 5 Services

File: `docker-compose.monitoring.yml`

| Service | Image | Port | Keterangan |
|---------|-------|------|------------|
| `prometheus` | prom/prometheus | Internal only | Scrape metrik dari cAdvisor + Node Exporter |
| `cadvisor` | gcr.io/cadvisor/cadvisor | Internal only | Metrik per-container (CPU, RAM, network, disk) |
| `loki` | grafana/loki:3.0.0 | Internal only | Penerima + penyimpan log dari Promtail |
| `promtail` | grafana/promtail:3.0.0 | Internal only | Agent pembaca log Nginx dan Laravel |
| `grafana` | grafana/grafana | **127.0.0.1:3000** | Dashboard visualisasi (localhost only) |

**Prinsip isolasi:** Jika seluruh stack monitoring crash (OOM atau lainnya), stack produksi tetap berjalan. Monitoring adalah passive observer — tidak ada dependency dari stack produksi ke monitoring.

### Alur Data Monitoring

```
[ Nginx container ]
    └── menulis ke: nginx-logs (named volume)
                        │
                        └── dibaca oleh:
                                ├── [ CrowdSec Engine ] (real-time analisis)
                                └── [ Promtail ] ──HTTP POST──> [ Loki ]
                                                                       │
[ PHP-FPM container ]                                                  │
    └── menulis ke: app-logs (named volume)                            │
                        │                                              │
                        └── dibaca oleh: [ Promtail ]                  │
                                                                       │
[ Docker Engine ]                                                      │
    └── cgroups ──dibaca oleh── [ cAdvisor ]                          │
                                    │                                  │
                                    └──/metrics──> [ Prometheus ]      │
                                                                       │
[ Host OS /proc ]                                                      │
    └── dibaca oleh── [ Node Exporter :9100 ]                         │
                            │                                          │
                            └──/metrics──> [ Prometheus ]             │
                                                   │                   │
                                                   └──query──> [ Grafana ] <──query──┘
```

---

## Networking

### External Network: `mer-prod-network`

Satu network Docker yang di-share antara kedua compose stack:

```bash
# Dibuat manual sebelum stack pertama dijalankan
docker network create mer-prod-network
```

**Kenapa external (bukan internal)?**
Container di compose file yang berbeda tidak bisa saling mengakses dengan service DNS name kecuali berada di network yang sama. Network external memungkinkan:
- Promtail (monitoring stack) membaca log dari shared volume yang ditulis Nginx (produksi stack)
- cAdvisor melihat container dari produksi stack
- Grafana mengakses Prometheus dan Loki (sesama monitoring stack)

### DNS Resolution Lintas Compose File

Untuk container yang berkomunikasi lintas compose file, gunakan `container_name` sebagai hostname (bukan service name):

| Container | Hostname DNS |
|-----------|--------------|
| `mer-prometheus` | `mer-prometheus` |
| `mer-loki` | `mer-loki` |
| `mer-cadvisor` | `mer-cadvisor` |
| `mer-web-prod` | `mer-web-prod` |

Service name (`prometheus`, `loki`, dst.) hanya bisa di-resolve oleh container dalam compose file yang **sama**.

---

## Shared Volumes

| Volume | Ditulis oleh | Dibaca oleh | Isi |
|--------|-------------|-------------|-----|
| `nginx-logs` | `web` (Nginx) | `crowdsec`, `promtail` | access.log, error.log |
| `app-logs` | `app` (PHP) | `promtail` | laravel.log, queue-worker.log |
| `shared-public` | `app` (entrypoint sync) | `web` (Nginx) | Compiled Vite assets, public/ |
| `app-storage` | `app` | `app` | File upload, cache framework |
| `mer-db-data` | `db` (PostgreSQL) | `db` | Data database |
| `mer-redis-data` | `redis` | `redis` | Data AOF persistence |

---

## Alokasi Resource (VPS 8 GB RAM)

```
Stack Produksi
├── app (PHP-FPM + 2 queue workers + scheduler)    ~2.0 GB
├── db  (PostgreSQL 16)                            ~1.5 GB
├── redis (Redis 7 + AOF, max 512 MB configured)   ~0.5 GB
├── web (Nginx, very lightweight)                  ~0.1 GB
└── crowdsec + bouncer                             ~0.4 GB
                                            Total: ~4.5 GB

Stack Monitoring
├── prometheus (time-series DB, 30d retention)     ~0.5 GB (limit: 512 MB)
├── loki (log aggregation)                         ~0.5 GB (limit: 512 MB)
├── cadvisor (container metrics)                   ~0.2 GB (limit: 256 MB)
├── promtail (log agent)                           ~0.2 GB (limit: 256 MB)
└── grafana (dashboard)                            ~0.2 GB (limit: 256 MB)
                                            Total: ~1.5 GB (hard limit)

OS + buffer                                        ~2.0 GB
                                       Grand Total: ~8.0 GB
```

**Prinsip resource limits:** Semua container monitoring diberi hard limits via `deploy.resources.limits`. Jika terjadi serangan DDoS yang membanjiri log, Loki/Promtail akan ter-OOM-kill dan restart — bukan PostgreSQL atau PHP-FPM.

---

## Arsitektur Multi-Stage Docker Build

```
Stage 1: composer:2
    └── composer install --no-dev --optimize-autoloader
            │
            ▼
Stage 2: node:22-alpine
    └── npm ci && npm run build (Vite)
            │
            ▼
Stage 3: php:8.2-fpm-alpine (Final Image)
    ├── Copy vendor/ dari Stage 1
    ├── Copy public/build/ dari Stage 2
    ├── Install PHP extensions (pgsql, redis, gd, zip, bcmath, pcntl)
    ├── Copy php.ini, supervisord.conf
    └── ENTRYPOINT: docker-entrypoint.sh → supervisord
```

Komponen yang berjalan di dalam satu container `app`:
- `php-fpm` — melayani request dari Nginx via FastCGI
- `queue-worker` (2 proses) — memproses background jobs
- `scheduler` (loop 60s) — menjalankan `artisan schedule:run`

Ketiga proses dikelola oleh `supervisord` sebagai PID 1.

---

## Arsitektur HTTPS

```
Browser ──HTTPS :443──> Cloudflare (terminasi TLS)
                │
                └── Cloudflare ──HTTP──> VPS :8888 (CrowdSec Bouncer)
                                                     │
                                    Header X-Forwarded-Proto: https
                                    Header CF-Connecting-IP: <ip asli>
                                                     │
                                              Nginx (set_real_ip_from)
                                              Mengekstrak IP asli dari
                                              header Cloudflare ranges
```

TLS di-terminasi oleh Cloudflare (Full Strict mode). Traffic antara Cloudflare dan VPS berjalan via HTTP internal, namun Laravel dipaksa menggunakan HTTPS scheme via:
- `URL::forceScheme('https')` di `AppServiceProvider`
- `TrustProxies` middleware mengizinkan semua proxy (`*`) dengan header `X-Forwarded-Proto`
- `SESSION_SECURE_COOKIE=true` di `.env`
