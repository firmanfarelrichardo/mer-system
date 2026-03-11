# MER System — Production Infrastructure

Direktori ini berisi seluruh konfigurasi infrastruktur production untuk MER System (Manajemen Event & Risiko). Dibangun di atas Docker dengan prinsip isolasi, immutable infrastructure, dan defense-in-depth.

**Spesifikasi VPS:** Ubuntu 24.04 LTS | 8 GB RAM | 2 CPU Core  
**Domain:** mers-rsryacudu.com via Cloudflare (Full Strict SSL)

---

## Struktur Direktori

```
deployment/production/
├── README.md                       # File ini — indeks dokumentasi
│
├── docker-compose.yml              # Stack produksi (6 services)
├── docker-compose.monitoring.yml   # Stack monitoring (5 services, terpisah)
├── Dockerfile                      # Multi-stage build (composer→node→php-fpm)
├── docker-entrypoint.sh            # Inisialisasi container saat startup
├── nginx.conf                      # Reverse proxy + security headers
├── supervisord.conf                # Process manager (FPM + worker + scheduler)
├── php.ini                         # Konfigurasi PHP production
│
├── deploy.sh                       # Script deploy otomatis (7 langkah)
├── lockdown.sh                     # Aktivasi mode darurat (freeze backend)
├── unlock.sh                       # Pemulihan dari mode darurat
│
├── .env.example                    # Template environment variables
├── .env                            # Nilai aktual (TIDAK di-commit ke Git)
│
├── lockdown/
│   └── maintenance.html            # Halaman 503 statis (zero-dependency)
│
└── monitoring/
    ├── prometheus.yml              # Konfigurasi scrape targets Prometheus
    ├── loki-config.yml             # Konfigurasi log aggregation Loki
    ├── promtail-config.yml         # Konfigurasi log collection agent
    └── grafana-datasources.yml     # Auto-provisioning datasource Grafana
```

---

## Indeks Dokumentasi

| Dokumen | Konteks |
|---------|---------|
| [docs/01-arsitektur.md](docs/01-arsitektur.md) | Arsitektur sistem, diagram traffic flow, relasi antar service, alokasi resource |
| [docs/02-deployment.md](docs/02-deployment.md) | Panduan deploy pertama kali, update via deploy.sh, rollback, backup database |
| [docs/03-keamanan.md](docs/03-keamanan.md) | Nginx security headers, HTTPS Cloudflare, CrowdSec IDS, Laravel middleware |
| [docs/04-monitoring.md](docs/04-monitoring.md) | Stack observability: Prometheus, Grafana, Loki, Promtail, cAdvisor, Sentry |
| [docs/05-kill-switch.md](docs/05-kill-switch.md) | Level 3 Kill Switch: prosedur lockdown dan pemulihan sistem darurat |
| [docs/06-operasional.md](docs/06-operasional.md) | Runbook operasi sehari-hari, troubleshooting, perintah penting |

---

## Quick Start (Deploy Pertama Kali)

```bash
# 1. Masuk ke direktori ini
cd /var/www/mer-system/deployment/production

# 2. Buat external network (satu kali saja)
docker network create mer-prod-network

# 3. Salin dan isi environment file
cp .env.example .env
nano .env   # Isi semua nilai yang wajib diisi

# 4. Jalankan deploy script
bash deploy.sh

# 5. (Opsional) Jalankan stack monitoring
docker compose -f docker-compose.monitoring.yml up -d
```

Panduan lengkap: [docs/02-deployment.md](docs/02-deployment.md)

---

## Perintah Darurat

```bash
# Bekukan seluruh backend (tampilkan halaman maintenance)
bash lockdown.sh

# Pulihkan sistem ke operasi normal
bash unlock.sh
```

Prosedur lengkap: [docs/05-kill-switch.md](docs/05-kill-switch.md)
