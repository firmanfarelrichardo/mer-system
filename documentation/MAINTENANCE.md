# 🛠️ MER System - Panduan Maintenance & Operasional

> Dokumen ini berisi panduan lengkap untuk melakukan development, deployment, monitoring, dan maintenance pada aplikasi **MER System (Medication Error Reporting)**.

---

## Daftar Isi

1. [Development Lokal](#1-development-lokal)
2. [Struktur Direktori Server (VPS)](#2-struktur-direktori-server-vps)
3. [Workflow Perubahan Kode & Deployment](#3-workflow-perubahan-kode--deployment)
4. [Akses Staging Server](#4-akses-staging-server)
5. [Script Maintenance Staging (`deploy.sh`)](#5-script-maintenance-staging-deploysh)
6. [Script Maintenance Production (`deploy.sh`)](#6-script-maintenance-production-deploysh)
7. [Sentry (Error & Performance Monitoring)](#7-sentry-error--performance-monitoring)
8. [Perintah Maintenance Manual](#8-perintah-maintenance-manual)
9. [Referensi Cepat](#9-referensi-cepat)

---

## 1. Development Lokal

### Prasyarat

- Docker Desktop (Windows/Mac) atau Docker Engine (Linux)
- Git, Git Bash (Windows) atau Terminal (Mac/Linux)
- SSH key yang sudah dikonfigurasi ke GitHub & VPS

### Menjalankan Aplikasi di Lokal

Gunakan Helper Script Interaktif (Sangat Direkomendasikan):

```bash
cd deployment/staging
chmod +x deploy.sh
./deploy.sh
```

Pilih menu **`1) Start Development`** — script akan otomatis:

- `docker compose up -d` (menjalankan semua container)
- `composer install` (jika vendor belum ada)
- `php artisan key:generate` (jika APP_KEY belum ada)
- `php artisan migrate` (menjalankan migrasi database)

### Menghentikan Development

Dari dalam menu `./deploy.sh`, pilih menu **`2) Stop Development`**.

---

## 2. Struktur Direktori Server (VPS)

Aplikasi MER System di VPS (`vps-hostinger`) diletakkan pada folder `/var/www/`. Staging dan Production dipisah sepenuhnya untuk menjaga isolasi lingkungan.

```text
/var/www/mer-system/
├── staging/                ← Environment Staging (Branch 'staging')
│   ├── deployment/staging/
│   │   ├── deploy.sh       ← Script interaktif untuk staging (lengkap, termasuk reset)
│   │   ├── .env            ← Environment variables untuk staging
│   │   └── docker-compose.yml
│   └── ... (source code staging)
│
└── production/             ← Environment Production (Branch 'production')
    ├── deployment/production/
    │   ├── deploy.sh       ← Script interaktif untuk production (aman, tanpa reset)
    │   ├── .env            ← Environment variables production
    │   └── docker-compose.yml
    └── ... (source code production)
```

> [!CAUTION]
> **JANGAN PERNAH** menjalankan `deploy.sh` milik staging di folder production, ataupun sebaliknya!
> Kedua script memiliki konfigurasi container, port, dan behavior yang berbeda.

---

## 3. Workflow Perubahan Kode & Deployment

### Alur Branching

```text
main (default)
  └── staging            ← Deploy ke VPS Staging
        └── production   ← Deploy ke VPS Production (Rilis resmi)
```

### Alur Perubahan Kode (Dari Awal Sampai Live)

Berikut langkah-langkah ketika Anda ingin melakukan perubahan kode (fitur baru, perbaikan bug, perubahan tampilan, dll):

#### Langkah 1 — Develop di Lokal

```bash
# Pastikan Anda berada di branch staging
git checkout staging
git pull origin staging

# Buat perubahan kode, lalu test di lokal
cd deployment/staging
./deploy.sh      # Pilih 1 → Start Development
```

Test perubahan di browser: **http://localhost:8080**

#### Langkah 2 — Push ke Staging

Setelah perubahan siap dan berjalan baik di lokal:

```bash
git add .
git commit -m "feat: deskripsi perubahan"
git push origin staging
```

#### Langkah 3 — Deploy ke VPS Staging

Ada dua cara:

**Cara A — Otomatis via CI/CD** (jika SSH key GitHub Actions aktif):
Cukup push saja. GitHub Actions akan otomatis deploy ke VPS staging. Pantau di tab **Actions** pada repository GitHub.

**Cara B — Manual via SSH** (jika CI/CD bermasalah):

```bash
# SSH ke VPS
ssh vps-hostinger

# Masuk ke folder staging
cd /var/www/mer-system/staging

# Ambil kode terbaru
git fetch origin
git reset --hard origin/staging

# Rebuild dan restart
cd deployment/staging
./deploy.sh
```

Di dalam menu `deploy.sh` staging:

1. Pilih **Opsi 2** → Stop Development
2. Pilih **Opsi 31** → NPM Run Build (compile asset CSS/JS)
3. Pilih **Opsi 4** → Quick Rebuild (rebuild container dengan kode terbaru)

Test di browser staging via SSH Tunnel (lihat [Bagian 4](#4-akses-staging-server)).

#### Langkah 4 — Deploy ke Production

Setelah staging sudah ditest dan siap rilis:

```bash
# Di lokal, merge staging ke production
git checkout production
git pull origin production
git merge staging
git push origin production
```

**Cara A — Otomatis via CI/CD**: Push saja, GitHub Actions akan deploy otomatis.

**Cara B — Manual via SSH**:

```bash
ssh vps-hostinger
cd /var/www/mer-system/production

# Ambil kode terbaru
git fetch origin
git reset --hard origin/production

# Rebuild dan restart
cd deployment/production
./deploy.sh
```

Di dalam menu `deploy.sh` production:

1. Pilih **Opsi 4** → Quick Rebuild (rebuild container, data aman)
2. Pilih **Opsi 22** → Clear All Cache (agar perubahan langsung aktif)

Atau lebih simpel, pilih **Opsi 80** → Full Deploy (otomatis menjalankan semua langkah deployment).

> [!IMPORTANT]
> **Kapan perlu rebuild (Opsi 4)?**
> Setiap ada perubahan kode PHP, Blade, atau konfigurasi — karena production menggunakan Docker dan kode di-copy ke dalam image saat build.
> Tanpa rebuild, container masih menjalankan kode lama meskipun `git pull` sudah dilakukan.

> [!TIP]
> **Kapan cukup clear cache saja (Opsi 22)?**
> Jika Anda hanya mengubah file `.env` atau konfigurasi Laravel tanpa mengubah kode PHP/Blade.

---

## 4. Akses Staging Server

Staging Server di VPS **tidak diekspos ke publik** demi keamanan. Aplikasi hanya di-bind ke `127.0.0.1:8080`. Untuk melihatnya dari browser laptop, gunakan **SSH Tunneling**.

### Cara Melakukan SSH Tunneling

Buka satu tab terminal baru di komputer lokal, lalu jalankan:

```bash
ssh -L 8081:127.0.0.1:8080 vps-hostinger
```

*(Biarkan terminal ini tetap terbuka selama mengetes)*

Setelah tunnel terhubung, buka browser dan akses:

> 🌐 **http://localhost:8081**

---

## 5. Script Maintenance Staging (`deploy.sh`)

Script staging menyediakan **semua** fitur termasuk reset database, karena data staging adalah data dummy yang boleh dihapus kapan saja.

Lokasi: `deployment/staging/deploy.sh`

### Daftar Menu Staging

| Menu | Nama | Keterangan |
|------|------|------------|
| **1** | Start Development | Nyalakan semua container |
| **2** | Stop Development | Matikan semua container |
| **3** | Clean Rebuild | Hapus semua container & rebuild dari nol |
| **4** | Quick Rebuild | Rebuild container tanpa hapus volume |
| **5** | Force Rebuild | Rebuild dari nol tanpa cache Docker |
| **10–14** | Status & Logs | Lihat status container, images, volumes, test endpoint |
| **15–18** | Logs | Lihat log App, DB, Vite, atau semua sekaligus |
| **20** | Run Migrations | Jalankan migration database yang belum dijalankan |
| **21** | Fresh Migration + Seed | ⚠️ Hapus semua tabel & isi ulang data dummy |
| **22** | Clear All Cache | Bersihkan cache Laravel (route, view, config) |
| **23** | Run Artisan Command | Jalankan perintah `php artisan` bebas |
| **24** | Access App Shell | Masuk ke shell container app |
| **25** | Re-initialize App | Install composer + generate key + migrate |
| **26** | Composer Install | Install/update dependensi PHP |
| **30–31** | Frontend (Vite) | NPM install & NPM run build |
| **40–42** | Database | Akses shell PostgreSQL, Redis, reset database |
| **50–51** | Cleanup | Hapus container stopped & dangling images |
| **60–61** | WSL Network | Konfigurasi IP gateway untuk WSL |
| **70–74** | Data Management | Reset database, hapus laporan/user |
| **0** | Exit | Keluar dari script |

---

## 6. Script Maintenance Production (`deploy.sh`)

Script production **hanya menyediakan operasi yang aman** — tidak ada opsi untuk reset database, fresh migration, atau hapus seluruh laporan/user. Semua operasi berbahaya memiliki konfirmasi ganda.

Lokasi: `deployment/production/deploy.sh`

### Daftar Menu Production

#### Start/Stop

| Menu | Nama | Keterangan |
|------|------|------------|
| **1** | Start Production | Nyalakan semua container (`docker compose up -d`) |
| **2** | Stop Production | Matikan semua container |

#### Rebuild

| Menu | Nama | Keterangan |
|------|------|------------|
| **3** | Quick Rebuild | Rebuild container app dengan kode terbaru. Volume database **tidak terhapus** sehingga data tetap aman. Wajib dijalankan setiap ada perubahan kode. |

#### Status & Info (Read-Only — Aman)

| Menu | Nama | Keterangan |
|------|------|------------|
| **10** | Container Status | Lihat container mana saja yang sedang running beserta port-nya |
| **11** | All Project Containers | Lihat semua container (termasuk yang stopped) |
| **12** | Project Images | Lihat daftar Docker image yang digunakan project |
| **13** | Volumes & Networks | Lihat volume (penyimpanan data) dan network Docker |
| **14** | Test Endpoint | Cek apakah aplikasi bisa diakses (health check via curl) |

#### Logs (Read-Only — Aman)

| Menu | Nama | Keterangan |
|------|------|------------|
| **15** | App Logs | Lihat log aplikasi Laravel (error, request, dll) |
| **16** | DB Logs | Lihat log database PostgreSQL |
| **17** | Web (Nginx) Logs | Lihat log web server Nginx (akses & error) |
| **18** | All Logs | Lihat log semua container sekaligus |

#### Laravel Commands

| Menu | Nama | Keterangan |
|------|------|------------|
| **20** | Run Migrations | Jalankan migration yang belum pernah dijalankan. **Aman** — hanya menambahkan tabel/kolom baru, tidak menghapus data apapun. |
| **22** | Clear All Cache | Bersihkan semua cache Laravel (route, view, config, event) lalu rebuild. Wajib dijalankan setelah ada perubahan kode atau konfigurasi `.env`. |
| **23** | Run Artisan Command | Jalankan perintah `php artisan` custom secara bebas |
| **24** | Access App Shell | Masuk ke shell container app untuk debugging manual |
| **26** | Composer Install | Install/update dependensi PHP dengan mode `--no-dev` (tanpa package development) |

#### Database (Akses Langsung)

| Menu | Nama | Keterangan |
|------|------|------------|
| **40** | Access PostgreSQL Shell | Masuk ke konsol database PostgreSQL untuk query manual |
| **41** | Access Redis CLI | Masuk ke konsol Redis untuk inspeksi cache/session |

#### Cleanup

| Menu | Nama | Keterangan |
|------|------|------------|
| **50** | Remove Stopped Containers | Hapus container yang sudah mati dan image yang tidak terpakai. **Tidak menghapus** container yang sedang running atau volume data. |

#### Data Management (Dengan Konfirmasi Ganda)

| Menu | Nama | Keterangan |
|------|------|------------|
| **73** | Hapus Laporan Spesifik | Hapus satu laporan berdasarkan nomor laporan. Memerlukan input nomor laporan + konfirmasi ketik "HAPUS" + konfirmasi y/n. |
| **74** | Hapus User Spesifik | Hapus satu user berdasarkan username. Memerlukan input username + konfirmasi ketik "HAPUS" + konfirmasi y/n. |

> [!WARNING]
> Menu 73 dan 74 memiliki **konfirmasi ganda** (3 langkah) sebelum data benar-benar dihapus. Namun tetap berhati-hati — data yang sudah dihapus **tidak dapat dikembalikan**.

#### Deployment

| Menu | Nama | Keterangan |
|------|------|------------|
| **80** | Full Deploy | Menjalankan seluruh alur deployment secara berurutan: `git pull` → `docker build` → `docker compose up` → `migrate` → `optimize` → `queue:restart`. Cocok untuk deployment manual yang ingin serba otomatis dalam satu langkah. |

### Perbedaan Menu Staging vs Production

| Fitur | Staging | Production |
|-------|:-------:|:----------:|
| Start/Stop | ✅ | ✅ |
| Quick Rebuild | ✅ | ✅ |
| Clean/Force Rebuild | ✅ | ❌ |
| Status, Logs, Info | ✅ | ✅ |
| Run Migrations | ✅ | ✅ |
| Fresh Migration + Seed | ✅ | ❌ |
| Clear Cache | ✅ | ✅ |
| Artisan Command & Shell | ✅ | ✅ |
| Composer Install | ✅ | ✅ (--no-dev) |
| NPM Install & Build (Vite) | ✅ | ❌ |
| Akses DB & Redis | ✅ | ✅ |
| Reset Database | ✅ | ❌ |
| Reset Seluruh Laporan/User | ✅ | ❌ |
| Hapus Laporan/User Spesifik | ✅ | ✅ (konfirmasi ganda) |
| WSL Network | ✅ | ❌ |
| Full Deploy | ❌ | ✅ |

---

## 7. Sentry (Error & Performance Monitoring)

Sentry digunakan untuk **menangkap error (exception)** dan **memantau performa** aplikasi secara real-time di production.

### Konfigurasi `.env` Production

```env
SENTRY_LARAVEL_DSN=https://xxxxx@o00000.ingest.sentry.io/00000
SENTRY_TRACES_SAMPLE_RATE=0.2
SENTRY_ENVIRONMENT=production
```

### Fitur Sentry yang Aktif

| Fitur | Status | Keterangan |
|-------|--------|------------|
| Error Tracking | ✅ Aktif | Otomatis menangkap semua exception |
| Performance Monitoring | ✅ Aktif | Trace request, SQL query, Redis, cache |
| Breadcrumbs | ✅ Aktif | Log, cache, SQL, queue, HTTP client |
| PII (Data Pribadi) | ❌ Dimatikan | Dimatikan demi kepatuhan kerahasiaan data medis |

---

## 8. Perintah Maintenance Manual

Gunakan opsi interaktif di `./deploy.sh` jika memungkinkan. Namun jika Anda perlu menggunakan perintah manual (dijalankan di VPS / Lokal):

### Container & Docker

```bash
# Lihat status semua container MER
docker ps --filter "name=mer-"

# Lihat logs aplikasi secara realtime
docker logs mer-app-staging -f       # Staging
docker logs production-app-1 -f      # Production

# Masuk ke dalam shell container aplikasi
docker exec -it mer-app-staging sh   # Staging
```

### Laravel Artisan (Manual di Dalam Container)

Jika sudah masuk ke shell container (via `deploy.sh` → Opsi 24):

```bash
php artisan optimize:clear  # Hapus semua cache
php artisan optimize        # Rebuild cache agar cepat
php artisan migrate         # Jalankan migration terbaru
php artisan route:list      # Lihat daftar URL
php artisan tinker          # Buka console interaktif PHP
```

### Troubleshooting Umum

| Masalah | Solusi |
|---------|--------|
| Perubahan kode tidak muncul di production | Jalankan `deploy.sh` → Opsi **4** (Rebuild) lalu Opsi **22** (Clear Cache) |
| Error 500 setelah update `.env` | Jalankan `deploy.sh` → Opsi **22** (Clear Cache) |
| Container tidak mau start | Cek log via Opsi **15–18**, pastikan port tidak bentrok |
| CI/CD GitHub Actions gagal (SSH error) | Deploy manual via SSH (lihat [Bagian 3](#langkah-4--deploy-ke-production)), lalu perbaiki SSH key di GitHub Secrets |
| Database migration error | Cek isi migration file, jalankan Opsi **24** (Shell) lalu `php artisan migrate --pretend` untuk preview |

---

## 9. Referensi Cepat

### Port Reference

| Service | Lokal (Laptop) | Staging (VPS) | Production (VPS) |
|---------|-------|---------------|-------------------|
| **App (Nginx)** | 8080 | 8080 (via Tunnel 8081) | 80/443 (Cloudflare) |
| **PostgreSQL** | 5433 | 5433 (via SSH) | 5432 (internal loopback) |
| **Redis** | 6379 | 6379 (via SSH) | internal |

### Kontak & Akses Penting

| Resource | URL / Command |
|----------|-----|
| GitHub Repository | https://github.com/firmanfarelrichardo/mer-system |
| Sentry Dashboard | https://sentry.io (login dengan akun tim) |
| SSH ke VPS | `ssh vps-hostinger` |
| Production URL | https://mers-rsryacudu.com |
