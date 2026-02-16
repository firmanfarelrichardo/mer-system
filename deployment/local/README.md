# MER System - Local Development

Panduan untuk menjalankan MER System (Medication Error Reporting) di environment local menggunakan Docker.

## Tech Stack

| Technology | Version | Keterangan |
|-----------|---------|------------|
| PHP | 8.3 | FPM (Alpine) |
| Laravel | 12 | Framework |
| PostgreSQL | 16 | Multi-schema Database |
| Redis | 7 | Caching & Session |
| Nginx | 1.27 | Web Server |
| Node.js | Alpine | Vite + Tailwind CSS |

## Prerequisites

- Docker Desktop (Windows/Mac) atau Docker Engine (Linux)
- Git Bash (Windows) atau Terminal (Mac/Linux)

## Struktur File

```
deployment/local/
├── docker-compose.yml       # Konfigurasi Docker services (4 services)
├── Dockerfile               # Build image PHP-FPM + Node.js
├── docker-entrypoint.sh     # Script inisialisasi container
├── deploy.sh                # Helper script interaktif
├── .env.example             # Template environment variables
├── .env                     # Environment variables (dibuat dari .env.example)
├── nginx/
│   └── default.conf         # Konfigurasi Nginx (dibuat manual / auto-generate)
└── README.md                # Dokumentasi ini
```

## Quick Start

### 1. Setup Environment

```bash
cd deployment/local

# Copy environment file
cp .env.example .env

# Edit .env sesuai kebutuhan (database credentials, dll)
```

### 2. Jalankan Aplikasi

**Opsi A: Menggunakan Helper Script (Recommended)**

```bash
chmod +x deploy.sh
./deploy.sh
```

Pilih menu `1) Start Development`

**Opsi B: Menggunakan Docker Compose langsung**

```bash
docker compose up -d --build
```

### 3. Akses Aplikasi

| Service | URL / Endpoint |
|---------|---------------|
| Aplikasi (Nginx) | http://localhost:8000 |
| Vite HMR | http://localhost:5173 |
| PostgreSQL | localhost:5432 |
| Redis | localhost:6379 |

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      Docker Network                              │
│                  (mer-system-dev-network)                         │
│                                                                  │
│  ┌──────────────────┐       ┌──────────────────┐                │
│  │   mer-system-     │       │   mer-system-     │                │
│  │   web-dev         │       │   app-dev          │                │
│  │   (Nginx)         │       │   (PHP 8.3 FPM)    │                │
│  │   Port 80 ────────┤──────►│   Port 9000        │                │
│  │                   │       │   Vite :5173        │                │
│  └──────────────────┘       └────────┬───────────┘                │
│         │                            │                            │
│    Port 8000                         │                            │
│    (Host)                            ▼                            │
│                          ┌──────────────────┐                    │
│                          │   mer-system-     │                    │
│                          │   db-dev          │                    │
│                          │   (PostgreSQL 16) │                    │
│                          │   Port 5432       │                    │
│                          └──────────────────┘                    │
│                                                                  │
│                          ┌──────────────────┐                    │
│                          │   mer-system-     │                    │
│                          │   redis-dev       │                    │
│                          │   (Redis 7)       │                    │
│                          │   Port 6379       │                    │
│                          └──────────────────┘                    │
└─────────────────────────────────────────────────────────────────┘
```

## Database Schemas

PostgreSQL dikonfigurasi dengan multi-schema. DDL (`database/sql/mer_ddl.sql`) dijalankan otomatis saat pertama kali container database dibuat.

| Schema | Keterangan |
|--------|------------|
| `tenant` | Organisasi / Multi-tenant |
| `akun` | Pengguna, Peran, Sesi, RBAC |
| `master` | Unit Kerja, Kategori, Matriks |
| `pelaporan` | Insiden, Detail Pasien, RCA |
| `audit` | Log Aktivitas |

## Environment Variables

| Variable | Default | Keterangan |
|----------|---------|------------|
| `APP_PORT` | 8000 | Port aplikasi (Nginx) |
| `VITE_PORT` | 5173 | Port Vite HMR |
| `FORWARD_DB_PORT` | 5432 | Port PostgreSQL (host) |
| `FORWARD_REDIS_PORT` | 6379 | Port Redis (host) |
| `DB_DATABASE` | mer_system | Nama database |
| `DB_USERNAME` | postgres | Username database |
| `DB_PASSWORD` | secret | Password database |
| `REDIS_PASSWORD` | (kosong) | Password Redis |

## Helper Script (deploy.sh)

Script interaktif untuk memudahkan operasi development:

```bash
./deploy.sh
```

### Menu Tersedia

| No | Operasi | Keterangan |
|----|---------|------------|
| 1 | Start Development | Jalankan semua containers |
| 2 | Stop Development | Hentikan semua containers |
| 3 | Clean Rebuild | Hapus semua data & rebuild dari awal |
| 4 | Quick Rebuild | Rebuild tanpa hapus volume |
| 10 | Show Status | Lihat status containers |
| 11 | Show App Logs | Lihat logs aplikasi |
| 12 | Show DB Logs | Lihat logs database |
| 13 | Show All Logs | Lihat semua logs |
| 14 | Test Endpoint | Test apakah semua service berjalan |
| 20 | Run Migrations | Jalankan database migrations |
| 21 | Fresh Migration | Reset database + seed |
| 22 | Clear Cache | Bersihkan semua cache |
| 23 | Artisan Command | Jalankan perintah artisan |
| 24 | App Shell | Akses shell container |
| 30 | NPM Install | Install dependencies JS |
| 31 | NPM Build | Build assets |
| 40 | PostgreSQL Shell | Akses psql CLI |
| 41 | Redis CLI | Akses redis-cli |
| 42 | Reset Database | Drop & recreate database |
| 50 | Cleanup | Bersihkan Docker resources |
| 51 | Remove All | Hapus semua containers, images & volumes |

## Commands Manual

### Container Management

```bash
# Start
docker compose up -d

# Stop
docker compose down

# Rebuild
docker compose up -d --build

# Logs
docker logs mer-system-app-dev -f

# Status
docker ps --filter "name=mer-system"
```

### Laravel Commands

```bash
# Artisan
docker exec mer-system-app-dev php artisan migrate
docker exec mer-system-app-dev php artisan db:seed
docker exec mer-system-app-dev php artisan optimize:clear

# Composer
docker exec mer-system-app-dev composer install
docker exec mer-system-app-dev composer update

# NPM
docker exec mer-system-app-dev npm install
docker exec mer-system-app-dev npm run build
```

### Database Access

```bash
# PostgreSQL shell
docker exec -it mer-system-db-dev psql -U postgres -d mer_system

# List schemas
docker exec mer-system-db-dev psql -U postgres -d mer_system -c "\dn"

# List tables in schema
docker exec mer-system-db-dev psql -U postgres -d mer_system -c "\dt pelaporan.*"

# Redis CLI
docker exec -it mer-system-redis-dev redis-cli
```

### Shell Access

```bash
docker exec -it mer-system-app-dev sh
```

## Troubleshooting

### Port Already in Use

Jika port sudah digunakan:

1. Edit `.env` dan ubah port yang konflik
2. Restart: `docker compose down && docker compose up -d`

### Database Connection Failed

1. Cek status container: `docker ps --filter "name=mer-system-db"`
2. Cek logs: `docker logs mer-system-db-dev`
3. Pastikan credentials di `.env` sudah benar
4. Coba restart: `docker compose restart db`

### DDL Not Applied

DDL hanya dijalankan pada saat pertama kali volume database dibuat. Jika perlu re-apply:

```bash
# Reset database (HAPUS SEMUA DATA)
docker compose down
docker volume rm $(docker volume ls -q --filter name=mer-db-data)
docker compose up -d
```

### Vite/HMR Not Working

1. Pastikan port 5173 tidak diblokir firewall
2. Hard refresh browser: `Ctrl+Shift+R`
3. Cek logs: `docker logs mer-system-app-dev`

### Permission Denied

```bash
docker exec mer-system-app-dev chmod -R 777 storage bootstrap/cache
```

### Redis Connection Refused

1. Cek status: `docker ps --filter "name=mer-system-redis"`
2. Test ping: `docker exec mer-system-redis-dev redis-cli ping`
3. Restart: `docker compose restart redis`
