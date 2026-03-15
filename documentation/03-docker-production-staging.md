# 03 — Docker Production & Staging (Network Isolation)

> **Sistem**: Medication Error Reporting (MER) — Rumah Sakit  
> **Klasifikasi**: HIGH-RISK (Data Medis Sensitif / PHI)  
> **Spesifikasi VPS**: 2 Core CPU, 8GB RAM, 100GB SSD  
> **Prasyarat**: Dokumen [01](./01-arsitektur-dan-hardening-os.md) dan [02](./02-cloudflare-dan-edge-security.md) sudah dilaksanakan

---

## Daftar Isi

1. [Konsep Isolasi Environment](#1-konsep-isolasi-environment)
2. [Struktur Folder Server](#2-struktur-folder-server)
3. [SSH Deploy Key (Distribusi Kode Git yang Aman)](#3-ssh-deploy-key-distribusi-kode-git-yang-aman)
4. [Instalasi Docker Engine](#4-instalasi-docker-engine)
5. [Jebakan Docker & UFW — Solusi Teknis](#5-jebakan-docker--ufw--solusi-teknis)
6. [Docker Compose Production](#6-docker-compose-production)
7. [Docker Compose Staging](#7-docker-compose-staging)
8. [Dockerfile Production (Multi-Stage Build)](#8-dockerfile-production-multi-stage-build)
9. [Entrypoint Production](#9-entrypoint-production)
10. [Kalkulasi Resource Budget (RAM 8GB)](#10-kalkulasi-resource-budget-ram-8gb)
11. [Deployment Workflow](#11-deployment-workflow)
12. [Verifikasi](#12-verifikasi)

---

## 1. Konsep Isolasi Environment

### Versi Sederhana (Analogi Pemula)

Bayangkan VPS sebagai **satu gedung rumah sakit** yang dibagi menjadi dua lantai:

| Lantai | Environment | Fungsi |
|--------|-------------|--------|
| **Lantai 1** | **Production** | Ruang operasi yang sesungguhnya. Data pasien asli. Tidak boleh ada eksperimen. |
| **Lantai 2** | **Staging** | Ruang simulasi. Untuk uji coba prosedur baru sebelum diterapkan di lantai 1. |

Kedua lantai **tidak boleh terhubung** — pintu antaranya dikunci permanen. Listrik dan air terpisah (database, cache, network masing-masing). Jika lantai 2 kebakaran (bug di staging), lantai 1 tetap beroperasi normal.

### Versi Formal (Standar Industri)

Isolasi environment diterapkan menggunakan **Docker bridge network** yang terpisah:

- `mer-prod-network` — Semua container production berkomunikasi di jaringan ini.
- `mer-staging-network` — Semua container staging berkomunikasi di jaringan ini.

Karena bridge network bersifat *namespace-isolated*, container di `mer-prod-network` **tidak bisa** mengirim paket ke container di `mer-staging-network`, meskipun keduanya berjalan di host yang sama. Ini setara dengan VLAN separation di level OS.

---

## 2. Struktur Folder Server

### Eksekusi

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# Buat struktur folder utama di /var/www/mer-system.
# /var/www dipilih karena merupakan konvensi standar Linux untuk web application root.
sudo mkdir -p /var/www/mer-system/{production,staging}
sudo mkdir -p /var/www/mer-system/shared/{ssl,scripts,backups}
sudo mkdir -p /var/log/mer-system

# Set ownership ke user mer_ops agar tidak perlu sudo untuk operasi sehari-hari.
sudo chown -R mer_ops:mer_ops /var/www/mer-system
sudo chown -R mer_ops:mer_ops /var/log/mer-system
```

### Peta Direktori Akhir

```
/var/www/mer-system/
|-- production/                 # Clone repository untuk production
|   |-- .env                    # Environment variables production (RAHASIA)
|   |-- deployment/
|   |   `-- production/
|   |       |-- docker-compose.yml
|   |       |-- Dockerfile
|   |       |-- docker-entrypoint.sh
|   |       |-- nginx.conf
|   |       |-- php.ini
|   |       `-- supervisord.conf
|   `-- ... (kode Laravel)
|
|-- staging/                    # Clone repository untuk staging
|   |-- .env                    # Environment variables staging
|   |-- deployment/
|   |   `-- staging/
|   |       |-- docker-compose.yml
|   |       |-- Dockerfile
|   |       `-- ... (mirip production)
|   `-- ... (kode Laravel)
|
`-- shared/
    |-- ssl/                    # Sertifikat Cloudflare Origin (symlink)
    |-- scripts/                # Skrip operasional (backup, update, dll)
    `-- backups/                # Backup database harian
```

---

## 3. SSH Deploy Key (Distribusi Kode Git yang Aman)

### Mengapa Bukan FTP/SFTP?

**Versi Formal:** FTP (File Transfer Protocol) mengirimkan kredensial dan data dalam bentuk *plaintext* — artinya siapapun yang melakukan network sniffing di jalur antara komputer Anda dan server dapat melihat username, password, dan seluruh kode sumber yang ditransfer. SFTP lebih baik (terenkripsi), tapi tetap memiliki kelemahan operasional: tidak ada version tracking, tidak ada rollback capability, dan rawan human error (upload file yang salah, menimpa file konfigurasi). SSH Deploy Key dengan Git memberikan distribusi kode yang terenkripsi, auditable (setiap perubahan tercatat di commit history), dan reproducible (bisa rollback ke commit manapun).

**Versi Sederhana:** FTP seperti mengirim dokumen rahasia rumah sakit lewat pos terbuka — siapapun bisa membaca isinya. SFTP seperti pos tercatat — lebih aman tapi tidak ada catatan apa yang dikirim kapan. Git + Deploy Key seperti sistem kurir rumah sakit yang terenkripsi, tercatat setiap pengiriman, dan bisa menarik kembali dokumen jika salah kirim.

### Apa Itu SSH Deploy Key?

**Versi Formal:** SSH Deploy Key adalah pasangan kunci kriptografis Ed25519 yang di-generate khusus di server (bukan di komputer lokal) dan didaftarkan ke platform Git (GitHub/GitLab) sebagai **read-only key**. Berbeda dengan SSH key personal yang memberikan akses ke semua repository akun, Deploy Key hanya memberikan akses ke **satu repository spesifik** dengan hak baca saja. Jika server dikompromikan, attacker hanya bisa membaca kode sumber dari satu repository — tidak bisa mengubah kode atau mengakses repository lain.

**Versi Sederhana:** Deploy Key seperti kartu akses ruang arsip yang hanya bisa "baca" — staf bisa masuk dan menyalin dokumen (git pull), tapi tidak bisa mengubah atau menghapus arsip asli (read-only). Dan kartu ini hanya berlaku untuk satu ruang arsip (satu repository), bukan seluruh gedung.

### Eksekusi — Generate Deploy Key di VPS

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# Generate SSH key pair Ed25519 khusus untuk deploy.
# -t ed25519    : algoritma kriptografi modern (kecil, cepat, aman)
# -C "..."      : label identifikasi agar mudah dikenali
# -f ~/.ssh/... : lokasi penyimpanan — TERPISAH dari key personal
# -N ""         : tanpa passphrase (diperlukan untuk operasi otomatis
#                 seperti cronjob atau CI/CD yang tidak bisa input passphrase)
ssh-keygen -t ed25519 -C "deploy@mer-system-vps" -f ~/.ssh/mer_deploy_ed25519 -N ""
```

**Mengapa tanpa passphrase?**

Deploy key tanpa passphrase diperbolehkan karena:
1. Key ini bersifat **read-only** — jika dicuri, attacker hanya bisa membaca kode (yang mungkin sudah open-source atau bisa di-revoke segera).
2. Key ini digunakan oleh **proses otomatis** (git pull via script/cron) yang tidak bisa memasukkan passphrase secara interaktif.
3. Keamanan dijaga melalui **permission file yang ketat** dan **hak akses read-only di platform Git**.

```bash
# Set permission ketat pada private key.
# 600 = hanya pemilik (mer_ops) yang bisa baca/tulis.
chmod 600 ~/.ssh/mer_deploy_ed25519
chmod 644 ~/.ssh/mer_deploy_ed25519.pub

# Tampilkan public key — salin output ini untuk langkah berikutnya.
cat ~/.ssh/mer_deploy_ed25519.pub
# Output contoh:
# ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAA... deploy@mer-system-vps
```

### Eksekusi — Daftarkan Deploy Key di GitHub

```
Navigasi: github.com > Repository mer-system > Settings > Deploy keys > Add deploy key
```

| Field | Nilai |
|-------|-------|
| **Title** | `VPS Production - Read Only (mer_ops)` |
| **Key** | *(paste isi dari `cat ~/.ssh/mer_deploy_ed25519.pub`)* |
| **Allow write access** | **JANGAN dicentang** (biarkan unchecked = read-only) |

Klik **Add key**.

> [!IMPORTANT]
> **JANGAN centang "Allow write access"** kecuali Anda memerlukan server untuk melakukan `git push` (biasanya tidak diperlukan untuk deployment). Read-only key menerapkan prinsip *Least Privilege* — server hanya perlu *membaca* kode, bukan *menulis*.

### Eksekusi — Daftarkan Deploy Key di GitLab (Alternatif)

Jika menggunakan GitLab sebagai platform Git:

```
Navigasi: gitlab.com > Project mer-system > Settings > Repository > Deploy keys > Add new key
```

| Field | Nilai |
|-------|-------|
| **Title** | `VPS Production - Read Only (mer_ops)` |
| **Key** | *(paste isi dari `cat ~/.ssh/mer_deploy_ed25519.pub`)* |
| **Grant write permissions** | **Tidak dicentang** (read-only) |
| **Expiry date** | *(opsional, kosongkan untuk tanpa batas waktu)* |

### Eksekusi — Konfigurasi SSH Client di VPS

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# Buat/edit SSH config agar git secara otomatis menggunakan deploy key
# saat berkomunikasi dengan GitHub.
# IdentitiesOnly=yes mencegah SSH mencoba key lain (menghindari
# "Too many authentication failures" jika ada banyak key).

mkdir -p ~/.ssh

cat >> ~/.ssh/config << 'SSH_GIT_CONFIG'

# --- MER System Deploy Key (Git Operations) ---
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/mer_deploy_ed25519
    IdentitiesOnly yes
SSH_GIT_CONFIG

chmod 600 ~/.ssh/config
```

**Untuk GitLab**, ganti blok di atas dengan:

```bash
cat >> ~/.ssh/config << 'SSH_GIT_CONFIG'

# --- MER System Deploy Key (Git Operations) ---
Host gitlab.com
    HostName gitlab.com
    User git
    IdentityFile ~/.ssh/mer_deploy_ed25519
    IdentitiesOnly yes
SSH_GIT_CONFIG
```

### Eksekusi — Verifikasi Koneksi

```bash
# Test koneksi SSH ke GitHub menggunakan deploy key.
ssh -T git@github.com
# Output yang diharapkan:
# Hi <username>/<repository>! You've successfully authenticated,
# but GitHub does not provide shell access.

# Untuk GitLab:
# ssh -T git@gitlab.com
# Output: Welcome to GitLab, @<username>!
```

### Eksekusi — Clone Repository dengan Deploy Key

```bash
# Clone repository ke folder production.
# GUNAKAN URL SSH (git@github.com:...) — BUKAN HTTPS.
# URL HTTPS memerlukan personal access token, sedangkan
# URL SSH akan otomatis menggunakan deploy key yang sudah dikonfigurasi.
cd /var/www/mer-system
git clone git@github.com:<ORGANISASI>/mer-system.git production

# Clone ke folder staging (branch berbeda)
git clone -b staging git@github.com:<ORGANISASI>/mer-system.git staging

# Verifikasi remote URL menggunakan SSH
cd /var/www/mer-system/production
git remote -v
# Output seharusnya:
# origin  git@github.com:<ORGANISASI>/mer-system.git (fetch)
# origin  git@github.com:<ORGANISASI>/mer-system.git (push)
```

### Eksekusi — Pull Kode Terbaru (Update Deployment)

```bash
# Setiap kali ada update kode, cukup jalankan:
cd /var/www/mer-system/production
git pull origin main

# Untuk staging:
cd /var/www/mer-system/staging
git pull origin staging
```

> [!NOTE]
> Jika Anda perlu mengganti deploy key (rotasi key, key compromised), cukup:
> 1. Generate key baru: `ssh-keygen -t ed25519 -C "deploy@mer-system-vps" -f ~/.ssh/mer_deploy_ed25519 -N ""`
> 2. Hapus key lama di GitHub/GitLab Settings > Deploy keys
> 3. Tambahkan key baru (public key) ke GitHub/GitLab
> 4. Test: `ssh -T git@github.com`

---

## 4. Instalasi Docker Engine

### Mengapa Docker CE Official dan Bukan apt default?

**Versi Formal:** Paket `docker.io` dari repository Ubuntu sering tertinggal 6-12 bulan dari rilis upstream. Docker CE dari repository resmi menyediakan patch keamanan lebih cepat, fitur terbaru (Compose v2, BuildKit), dan konsistensi versi yang terjamin.

### Eksekusi

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# Hapus instalasi Docker lama (jika ada) agar tidak konflik.
sudo apt remove -y docker docker-engine docker.io containerd runc 2>/dev/null || true

# Tambahkan repository resmi Docker.
# Langkah ini mengimpor GPG key Docker dan mendaftarkan repositorynya ke apt.
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker Engine, CLI, dan Compose plugin.
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Tambahkan user mer_ops ke grup docker agar bisa menjalankan
# perintah docker tanpa sudo.
sudo usermod -aG docker mer_ops

# PENTING: Logout dan login ulang agar grup berlaku.
# Atau gunakan: newgrp docker
newgrp docker

# Verifikasi instalasi
docker --version
docker compose version
docker run --rm hello-world
```

### Konfigurasi Docker Daemon

```bash
# Tulis konfigurasi daemon Docker yang dioptimasi untuk production.
sudo tee /etc/docker/daemon.json > /dev/null << 'DOCKER_DAEMON'
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "50m",
    "max-file": "3"
  },
  "storage-driver": "overlay2",
  "live-restore": true,
  "default-address-pools": [
    {"base": "172.20.0.0/16", "size": 24},
    {"base": "172.21.0.0/16", "size": 24}
  ]
}
DOCKER_DAEMON

# Restart Docker daemon untuk menerapkan konfigurasi
sudo systemctl restart docker
sudo systemctl enable docker
```

**Penjelasan setiap parameter:**

| Parameter | Nilai | Alasan |
|-----------|-------|--------|
| `log-driver` | `json-file` | Format log yang kompatibel dengan Promtail/Loki |
| `max-size` | `50m` | Batas ukuran per file log container. Nilai 50MB dipilih karena sistem medis production menghasilkan log yang lebih verbose (audit trail, error tracking DOMPDF, query logging). Nilai terlalu kecil (10MB) menyebabkan log penting ter-rotasi sebelum sempat dianalisis. Nilai terlalu besar (>100MB) berisiko menghabiskan disk |
| `max-file` | `3` | Rotasi 3 file = maksimum **150MB log per container**. Dengan ~8 container aktif (production + staging), total worst-case pemakaian disk untuk log = 8 x 150MB = **1.2GB** — aman untuk SSD 100GB |
| `storage-driver` | `overlay2` | Driver storage paling efisien untuk Linux modern |
| `live-restore` | `true` | Container tetap berjalan saat Docker daemon restart (zero downtime untuk maintenance daemon) |
| `default-address-pools` | `172.20-21.x.x` | Mencegah konflik IP dengan subnet lokal dan VPN |

> [!IMPORTANT]
> **Mengapa `max-size` ditingkatkan ke 50MB?** Konfigurasi log rotation ini adalah pencegahan terhadap error **"No space left on device"** yang merupakan penyebab downtime paling umum di server Docker production. Tanpa rotasi, satu container yang mengalami *error loop* (misalnya koneksi database gagal terus-menerus) bisa menghasilkan log bergigabyte dalam hitungan jam, menghabiskan seluruh disk, dan menyebabkan **semua container** gagal — termasuk database. Konfigurasi `max-size: 50m` dengan `max-file: 3` menjamin setiap container tidak pernah menggunakan lebih dari 150MB disk untuk log.

---

## 5. Jebakan Docker & UFW — Solusi Teknis

### Masalah

> [!CAUTION]
> **Docker secara default mem-bypass UFW sepenuhnya.** Ini adalah jebakan keamanan paling berbahaya yang sering diabaikan.

Saat Anda mendefinisikan `ports: "5432:5432"` di docker-compose, Docker menulis aturan langsung ke iptables chain `DOCKER-USER` dan `DOCKER`, melewati chain `ufw-user-input`. Hasilnya: port 5432 terekspos ke seluruh internet meskipun UFW Anda tidak mengizinkan port tersebut.

```
UFW Chain:     INPUT → ufw-user-input → DROP (port 5432 ditolak)
Docker Chain:  INPUT → DOCKER-USER → DOCKER → ACCEPT (port 5432 TERBUKA!)
                        ↑ Docker bypass UFW di sini
```

### Solusi yang Diterapkan (3 Lapis)

#### Lapis 1: Loopback Binding

Semua port sensitif (database, Redis) di-bind ke `127.0.0.1` sehingga hanya bisa diakses dari localhost server:

```yaml
# SALAH (terekspos ke internet):
ports:
  - "5432:5432"          # Sama dengan 0.0.0.0:5432:5432

# BENAR (hanya localhost):
ports:
  - "127.0.0.1:5432:5432"
```

#### Lapis 2: Tidak Expose Port Sama Sekali

Untuk service yang hanya perlu diakses oleh container lain (Redis), **jangan definisikan `ports:` sama sekali**:

```yaml
redis:
  # Tidak ada 'ports:' = tidak ada port yang di-bind ke host
  # Container lain tetap bisa akses via Docker internal DNS (redis:6379)
```

#### Lapis 3: Restrict Docker iptables

```bash
# Tambahkan aturan di DOCKER-USER chain untuk membatasi akses
# dari luar ke port-port container.
sudo iptables -I DOCKER-USER -i eth0 -j DROP
sudo iptables -I DOCKER-USER -i eth0 -p tcp -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
sudo iptables -I DOCKER-USER -i eth0 -p tcp --dport 80 -j ACCEPT
sudo iptables -I DOCKER-USER -i eth0 -p tcp --dport 443 -j ACCEPT

# Persist aturan iptables agar bertahan setelah reboot
sudo apt install -y iptables-persistent
sudo netfilter-persistent save
```

**Penjelasan aturan (dibaca dari bawah ke atas karena urutan insert):**

1. `--dport 443 -j ACCEPT` — Izinkan HTTPS
2. `--dport 80 -j ACCEPT` — Izinkan HTTP
3. `ESTABLISHED,RELATED -j ACCEPT` — Izinkan response dari koneksi yang sudah ada
4. `-i eth0 -j DROP` — Tolak semua traffic lain dari interface publik

---

## 6. Docker Compose Production

### File Konfigurasi

> **Lokasi file:** `/var/www/mer-system/production/deployment/production/docker-compose.yml`

```yaml
# ===========================================
# MER System - Production Docker Compose
# VPS: Ubuntu 24.04 LTS | RAM 8GB | CPU 2 Core
# ===========================================
# PRINSIP KEAMANAN:
# - Hanya port 80 Nginx yang terexpose ke host (melalui Cloudflare)
# - PostgreSQL di-bind ke 127.0.0.1 (loopback only)
# - Redis tidak expose port sama sekali (internal only)
# - Setiap container memiliki memory limit untuk mencegah OOM
# ===========================================

services:
  # -------------------------------------------
  # PHP-FPM Application Server
  # -------------------------------------------
  app:
    build:
      context: ../..
      dockerfile: deployment/production/Dockerfile
    restart: unless-stopped
    environment:
      APP_NAME: ${APP_NAME:-MER System}
      APP_ENV: production
      APP_DEBUG: "false"
      APP_KEY: ${APP_KEY}
      APP_URL: ${APP_URL}
      APP_TIMEZONE: Asia/Jakarta
      TZ: Asia/Jakarta
      DB_CONNECTION: pgsql
      DB_HOST: db
      DB_PORT: 5432
      DB_DATABASE: ${DB_DATABASE}
      DB_USERNAME: ${DB_USERNAME}
      DB_PASSWORD: ${DB_PASSWORD}
      REDIS_HOST: redis
      REDIS_PORT: 6379
      REDIS_PASSWORD: ${REDIS_PASSWORD}
      CACHE_STORE: redis
      SESSION_DRIVER: redis
      QUEUE_CONNECTION: ${QUEUE_CONNECTION:-redis}
      SENTRY_LARAVEL_DSN: ${SENTRY_LARAVEL_DSN}
      SENTRY_TRACES_SAMPLE_RATE: ${SENTRY_TRACES_SAMPLE_RATE}
    volumes:
      - shared-public:/public-shared
      - app-storage:/var/www/html/storage/app
      - app-logs:/var/www/html/storage/logs
    networks:
      - mer-prod-network
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_healthy
    deploy:
      resources:
        limits:
          memory: 1536M
        reservations:
          memory: 512M

  # -------------------------------------------
  # Nginx Reverse Proxy
  # Satu-satunya container yang expose port ke host.
  # -------------------------------------------
  web:
    image: nginx:1.27-alpine
    container_name: mer-web-prod
    restart: unless-stopped
    ports:
      - "80:80"
    volumes:
      - shared-public:/var/www/html/public:ro
      - ./nginx.conf:/etc/nginx/nginx.conf:ro
    depends_on:
      - app
    networks:
      - mer-prod-network
    deploy:
      resources:
        limits:
          memory: 256M
        reservations:
          memory: 64M

  # -------------------------------------------
  # PostgreSQL 16 Database
  # Port di-bind ke 127.0.0.1 (loopback) — BUKAN 0.0.0.0.
  # Akses remote via SSH Tunnel saja.
  # -------------------------------------------
  db:
    image: postgres:16-alpine
    container_name: mer-db-prod
    restart: unless-stopped
    ports:
      - "127.0.0.1:5432:5432"
    environment:
      POSTGRES_DB: ${DB_DATABASE}
      POSTGRES_USER: ${DB_USERNAME}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
      TZ: Asia/Jakarta
    volumes:
      - mer-db-data:/var/lib/postgresql/data
    networks:
      - mer-prod-network
    healthcheck:
      test: ["CMD", "pg_isready", "-q", "-d", "${DB_DATABASE}", "-U", "${DB_USERNAME}"]
      interval: 10s
      timeout: 5s
      retries: 5
    deploy:
      resources:
        limits:
          memory: 2048M
        reservations:
          memory: 512M
    command: >
      postgres
      -c shared_buffers=512MB
      -c effective_cache_size=1536MB
      -c work_mem=8MB
      -c maintenance_work_mem=128MB
      -c max_connections=100
      -c log_min_duration_statement=1000
      -c log_statement=ddl

  # -------------------------------------------
  # Redis 7 (Cache, Session, Queue)
  # Tidak expose port - hanya akses internal docker network.
  # -------------------------------------------
  redis:
    image: redis:7-alpine
    container_name: mer-redis-prod
    restart: unless-stopped
    command: >
      redis-server
      --appendonly yes
      --maxmemory 512mb
      --maxmemory-policy allkeys-lru
      --requirepass ${REDIS_PASSWORD}
    volumes:
      - mer-redis-data:/data
    networks:
      - mer-prod-network
    healthcheck:
      test: ["CMD", "redis-cli", "-a", "${REDIS_PASSWORD}", "ping"]
      interval: 10s
      timeout: 5s
      retries: 3
    deploy:
      resources:
        limits:
          memory: 640M
        reservations:
          memory: 128M

networks:
  mer-prod-network:
    driver: bridge

volumes:
  shared-public:
    driver: local
  app-storage:
    driver: local
  app-logs:
    driver: local
  mer-db-data:
    driver: local
  mer-redis-data:
    driver: local
```

### Environment File Production

> **Lokasi file:** `/var/www/mer-system/production/.env`

```env
# ===========================================
# MER System - Production Environment
# ===========================================
# PERHATIAN: File ini berisi kredensial sensitif.
# JANGAN commit ke version control.
# Pastikan file ini ada di .gitignore.
# ===========================================

APP_NAME="MER System"
APP_KEY=base64:GENERATE_DENGAN_php_artisan_key_generate
APP_URL=https://mers-rsryacudu.com

DB_DATABASE=mer_production
DB_USERNAME=mer_dbadmin
DB_PASSWORD=GENERATE_PASSWORD_MINIMAL_32_KARAKTER_CAMPURAN
# Contoh generate: openssl rand -base64 32

REDIS_PASSWORD=GENERATE_PASSWORD_REDIS_MINIMAL_32_KARAKTER
# Contoh generate: openssl rand -base64 32

QUEUE_CONNECTION=redis
APP_PORT=80

SENTRY_LARAVEL_DSN="https://public@sentry.example.com/1"
SENTRY_TRACES_SAMPLE_RATE="1.0"
```

---

## 7. Docker Compose Staging

### Prinsip Isolasi Staging

| Aspek | Production | Staging |
|-------|-----------|---------|
| Network | `mer-prod-network` | `mer-staging-network` |
| Port Nginx | `80` | `127.0.0.1:8080` |
| Port DB | `127.0.0.1:5432` | `127.0.0.1:5433` |
| Database name | `mer_production` | `mer_staging` |
| Container prefix | `mer-*-prod` | `mer-*-staging` |
| Memory budget | ~4.5GB | ~2GB |

### File Konfigurasi

> **Lokasi file:** `/var/www/mer-system/staging/deployment/staging/docker-compose.yml`

```yaml
# ===========================================
# MER System - Staging Docker Compose
# ===========================================
# ISOLASI: Network, port, database, dan volume semuanya
# terpisah dari production. Container staging berjalan
# di mer-staging-network yang tidak bisa berkomunikasi
# dengan mer-prod-network.
#
# AKSES: Nginx staging di-bind ke 127.0.0.1:8080.
# Untuk mengakses staging dari browser lokal, gunakan SSH Tunnel:
#   ssh -L 8080:127.0.0.1:8080 mer_ops@<IP_VPS>
#   Lalu buka: http://localhost:8080
# ===========================================

services:
  app:
    build:
      context: ../..
      dockerfile: deployment/staging/Dockerfile
    container_name: mer-app-staging
    restart: unless-stopped
    environment:
      APP_NAME: "MER System (Staging)"
      APP_ENV: staging
      APP_DEBUG: "true"
      APP_KEY: ${APP_KEY}
      APP_URL: ${APP_URL:-http://localhost:8080}
      APP_TIMEZONE: Asia/Jakarta
      TZ: Asia/Jakarta
      DB_CONNECTION: pgsql
      DB_HOST: db
      DB_PORT: 5432
      DB_DATABASE: ${DB_DATABASE:-mer_staging}
      DB_USERNAME: ${DB_USERNAME:-mer_staging_user}
      DB_PASSWORD: ${DB_PASSWORD}
      REDIS_HOST: redis
      REDIS_PORT: 6379
      REDIS_PASSWORD: ${REDIS_PASSWORD}
      CACHE_STORE: redis
      SESSION_DRIVER: redis
      QUEUE_CONNECTION: redis
    volumes:
      - staging-shared-public:/public-shared
      - staging-app-storage:/var/www/html/storage/app
      - staging-app-logs:/var/www/html/storage/logs
    networks:
      - mer-staging-network
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_healthy
    deploy:
      resources:
        limits:
          memory: 768M
        reservations:
          memory: 256M

  web:
    image: nginx:1.27-alpine
    container_name: mer-web-staging
    restart: unless-stopped
    ports:
      - "127.0.0.1:8080:80"
    volumes:
      - staging-shared-public:/var/www/html/public:ro
      - ./nginx.conf:/etc/nginx/nginx.conf:ro
    depends_on:
      - app
    networks:
      - mer-staging-network
    deploy:
      resources:
        limits:
          memory: 128M
        reservations:
          memory: 32M

  db:
    image: postgres:16-alpine
    container_name: mer-db-staging
    restart: unless-stopped
    ports:
      - "127.0.0.1:5433:5432"
    environment:
      POSTGRES_DB: ${DB_DATABASE:-mer_staging}
      POSTGRES_USER: ${DB_USERNAME:-mer_staging_user}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
      TZ: Asia/Jakarta
    volumes:
      - staging-db-data:/var/lib/postgresql/data
    networks:
      - mer-staging-network
    healthcheck:
      test: ["CMD", "pg_isready", "-q", "-d", "${DB_DATABASE:-mer_staging}", "-U", "${DB_USERNAME:-mer_staging_user}"]
      interval: 10s
      timeout: 5s
      retries: 5
    deploy:
      resources:
        limits:
          memory: 768M
        reservations:
          memory: 256M
    command: >
      postgres
      -c shared_buffers=128MB
      -c effective_cache_size=512MB
      -c work_mem=4MB
      -c maintenance_work_mem=64MB
      -c max_connections=30

  redis:
    image: redis:7-alpine
    container_name: mer-redis-staging
    restart: unless-stopped
    command: >
      redis-server
      --appendonly yes
      --maxmemory 128mb
      --maxmemory-policy allkeys-lru
      --requirepass ${REDIS_PASSWORD}
    volumes:
      - staging-redis-data:/data
    networks:
      - mer-staging-network
    healthcheck:
      test: ["CMD", "redis-cli", "-a", "${REDIS_PASSWORD}", "ping"]
      interval: 10s
      timeout: 5s
      retries: 3
    deploy:
      resources:
        limits:
          memory: 192M
        reservations:
          memory: 64M

networks:
  mer-staging-network:
    driver: bridge

volumes:
  staging-shared-public:
    driver: local
  staging-app-storage:
    driver: local
  staging-app-logs:
    driver: local
  staging-db-data:
    driver: local
  staging-redis-data:
    driver: local
```

---

## 8. Dockerfile Production (Multi-Stage Build)

### Mengapa Multi-Stage Build?

**Versi Formal:** Multi-stage build memisahkan environment yang diperlukan untuk *build* (Node.js, NPM, Composer, build-deps) dari environment *runtime* (PHP-FPM saja). Hasilnya: image final berukuran ~200MB dibanding ~800MB jika semua tools di-include. Attack surface berkurang drastis karena compiler, header files, dan package manager yang tidak dibutuhkan di production tidak ada di image final.

**Versi Sederhana:** Ibarat memasak di dapur (build stage) dan menyajikan di meja makan (runtime stage). Tamu tidak perlu melihat dapur yang berantakan — mereka hanya perlu hidangan yang sudah jadi. Image final hanya berisi "hidangan jadi" tanpa "peralatan masak."

> [!NOTE]
> File Dockerfile production sudah ada di repository: `deployment/production/Dockerfile`. Pastikan Anda menggunakan file yang sudah ada. Referensikan dokumentasi ini untuk memahami setiap keputusan arsitektural di dalamnya.

Referensi file: [`deployment/production/Dockerfile`](../deployment/production/Dockerfile) — lihat dokumen [docker-architecture.md](./docker-architecture.md) bagian Production untuk penjelasan detail setiap layer.

---

## 9. Entrypoint Production

File `docker-entrypoint.sh` menjalankan 4 langkah inisialisasi setiap kali container production dimulai:

| # | Langkah | Perintah | Mengapa |
|---|---------|----------|---------|
| 1 | Database Migration | `php artisan migrate --force` | Flag `--force` wajib karena Laravel menolak migration di production tanpa flag ini (safety mechanism) |
| 2 | Laravel Optimization | `config:cache`, `route:cache`, `view:cache` | Meng-compile konfigurasi, routes, dan views ke file PHP statis sehingga tidak perlu parsing ulang setiap request. Meningkatkan throughput ~30% |
| 3 | Sync Public Assets | `cp -a ... /public-shared/` | Menyalin static assets (CSS, JS, images dari Vite build) ke shared volume yang dibaca oleh Nginx |
| 4 | Set Permissions | `chown -R www-data:www-data storage` | Memastikan PHP-FPM worker (running as www-data) bisa menulis ke log, cache, dan upload |

Referensi file: [`deployment/production/docker-entrypoint.sh`](../deployment/production/docker-entrypoint.sh)

---

## 10. Kalkulasi Resource Budget (RAM 8GB)

### Mengapa Memory Limit Penting?

**Versi Formal:** Tanpa memory limit, satu container yang mengalami memory leak bisa mengkonsumsi seluruh RAM server (OOM condition), menyebabkan Linux OOM Killer membunuh proses secara acak — termasuk database yang mungkin sedang menulis data. Dengan limit, kernel hanya membunuh container yang melebihi batas, menjaga layanan lain tetap stabil.

### Alokasi RAM (Total 8GB)

```
+-------------------------------------------------------------------+
|                    RAM 8GB = 8192 MB                               |
+-------------------------------------------------------------------+
| OS + Docker Daemon     : ~800 MB  (reserved, tidak bisa dibatasi) |
| Monitoring (Loki, dll) : ~512 MB  (lihat doc 05)                  |
+-------------------------------------------------------------------+
|                   Sisa untuk container: ~6880 MB                   |
+-------------------------------------------------------------------+

PRODUCTION (~4480 MB):
  +-- PostgreSQL          : 2048 MB (limit) / 512 MB (reservation)
  +-- PHP-FPM + Workers   : 1536 MB (limit) / 512 MB (reservation)
  +-- Redis               :  640 MB (limit) / 128 MB (reservation)
  +-- Nginx               :  256 MB (limit) /  64 MB (reservation)

STAGING (~1856 MB):
  +-- PostgreSQL           :  768 MB (limit) / 256 MB (reservation)
  +-- PHP-FPM + Workers    :  768 MB (limit) / 256 MB (reservation)
  +-- Redis                :  192 MB (limit) /  64 MB (reservation)
  +-- Nginx                :  128 MB (limit) /  32 MB (reservation)

BUFFER: ~544 MB (headroom untuk spike traffic)
```

**Penjelasan `limits` vs `reservations`:**

| Parameter | Fungsi |
|-----------|--------|
| `limits` | Batas MAKSIMUM. Jika container mencoba melebihi, kernel langsung membunuh proses di dalamnya (OOM Kill). |
| `reservations` | Batas MINIMUM yang dijamin tersedia. Docker scheduler memastikan host memiliki cukup RAM untuk reservation sebelum memulai container. |

---

## 11. Deployment Workflow

### Initial Deployment (Pertama Kali)

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# 1. Clone repository ke folder production
cd /var/www/mer-system
git clone git@github.com:<REPO_ANDA>/mer-system.git production

# 2. Masuk ke folder production
cd production

# 3. Generate credentials (SIMPAN BAIK-BAIK)
echo "DB_PASSWORD: $(openssl rand -base64 32)"
echo "REDIS_PASSWORD: $(openssl rand -base64 32)"

# 4. Buat file .env production
cp .env.example .env
# Edit .env dengan credentials yang di-generate di step 3
nano .env

# 5. Generate APP_KEY
# Jalankan sementara container PHP untuk generate key
docker run --rm -v $(pwd):/app -w /app php:8.2-cli php artisan key:generate

# 6. Build dan deploy
docker compose --env-file .env -f deployment/production/docker-compose.yml up -d --build

# 7. Verifikasi semua container berjalan
docker compose --env-file .env -f deployment/production/docker-compose.yml ps

# 8. Test health endpoint
curl http://localhost/health
```

### Update Deployment (Subsequent)

```bash
# 1. Masuk ke folder production
cd /var/www/mer-system/production

# 2. Tarik kode terbaru
git pull origin production

# 3. Rebuild dan deploy (hanya app yang perlu rebuild)
docker compose --env-file .env -f deployment/production/docker-compose.yml build app
docker compose --env-file .env -f deployment/production/docker-compose.yml up -d --no-deps app

# 4. Verifikasi
docker compose --env-file .env -f deployment/production/docker-compose.yml ps
docker compose --env-file .env -f deployment/production/docker-compose.yml logs --tail 50 app
```

### Staging Deployment

```bash
# Clone ke folder staging (branch yang berbeda)
cd /var/www/mer-system
git clone -b staging git@github.com:<REPO_ANDA>/mer-system.git staging

cd staging
cp .env.example .env
nano .env  # Sesuaikan untuk staging

# --- TROUBLESHOOTING & CATATAN PENTING STAGING ---
# 1. Port Collision: Pastikan nilai APP_PORT tidak bentrok dengan production (misal gunakan APP_PORT=8080).
#    Jika .env stagging menggunakan port 80, web container akan gagal (bind: port is already allocated).
# 2. Resolusi Nginx DNS: Pada file konfigurasi Nginx staging (deployment/staging/nginx/default.conf),
#    pastikan 'fastcgi_pass' mengarah ke _container_name_ eksak (contoh: 'mer-app-dev:9000' atau 'mer-app-staging:9000'),
#    Bukan nama service ('app:9000'). Jika tidak, Nginx akan terkena crash loop "host not found in upstream".
# -------------------------------------------------

docker compose --env-file .env -f deployment/staging/docker-compose.yml up -d --build
```

### Akses Staging dari Komputer Lokal

Karena keamanan Nginx Staging membatasinya hanya pada `127.0.0.1:8080` di internal VPS, Anda perlu membuka jalur terenkripsi (SSH Tunnel) dari komputer lokal ke VPS tersebut.

```bash
# Buka terminal DI KOMPUTER LOKAL Anda (jangan jalankan di dalam VPS).
# Jika Anda sebelumnya sudah mengatur alias ~/.ssh/config ('mer-vps'):
ssh -L 8080:127.0.0.1:8080 mer-vps

# Atau jika menggunakan perintah manual (pastikan path SSH key dan IP benar):
ssh -L 8080:127.0.0.1:8080 -p 49152 mer_ops@<IP_VPS_ANDA>

# JANGAN tutup terminal ini (biarkan tetap menyala untuk menjaga tunnel).
# Buka browser di komputer lokal dan kunjungi (disarankan bukan localhost tapi IP balik langsung):
# http://127.0.0.1:8080
# Anda akan melihat instance staging MER System tersambung secara otomatis.
```

---

## 12. Verifikasi

### Verifikasi Isolasi Network

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
echo "========================================="
echo "  MER System - Docker Verification"
echo "========================================="

echo ""
echo "[1] Container Status (Production)"
docker compose --env-file .env -f /var/www/mer-system/production/deployment/production/docker-compose.yml ps

echo ""
echo "[2] Container Status (Staging)"
docker compose --env-file .env -f /var/www/mer-system/staging/deployment/staging/docker-compose.yml ps 2>/dev/null || echo "  Staging belum di-deploy"

echo ""
echo "[3] Network Isolation"
echo "  Production networks:"
docker network ls | grep mer-prod
echo "  Staging networks:"
docker network ls | grep mer-staging

echo ""
echo "[4] Port Bindings (KEAMANAN)"
echo "  Hanya port berikut yang boleh terekspos ke 0.0.0.0:"
docker ps --format "table {{.Names}}\t{{.Ports}}" | grep -v "127.0.0.1"

echo ""
echo "[5] Memory Usage"
docker stats --no-stream --format "table {{.Name}}\t{{.MemUsage}}\t{{.MemPerc}}"

echo ""
echo "[6] Disk Usage"
docker system df

echo ""
echo "========================================="
```

### Test Keamanan Port

```bash
# Dari komputer lokal, verifikasi port database TIDAK bisa diakses langsung
nc -zv <IP_VPS_ANDA> 5432    # Harus: Connection refused/timeout
nc -zv <IP_VPS_ANDA> 5433    # Harus: Connection refused/timeout
nc -zv <IP_VPS_ANDA> 6379    # Harus: Connection refused/timeout

# Hanya port ini yang boleh terbuka:
nc -zv <IP_VPS_ANDA> 80      # Harus: Connection succeeded (Nginx)
nc -zv <IP_VPS_ANDA> 49152   # Harus: Connection succeeded (SSH)
```

---

> **Dokumen selanjutnya:** [04-nginx-waf-dan-laravel.md](./04-nginx-waf-dan-laravel.md) — Konfigurasi Nginx Reverse Proxy, Security Headers, Rate Limiting, PHP-FPM Optimization, dan startup script Laravel.
