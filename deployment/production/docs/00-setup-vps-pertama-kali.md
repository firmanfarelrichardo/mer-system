# Setup VPS Production — Pertama Kali
### MER System | Ubuntu 24.04 LTS | 8 GB RAM | 2 CPU Core

> **Dokumen ini hanya dijalankan SEKALI** — saat pertama kali menyiapkan VPS
> untuk production. Deployment berikutnya cukup `git push` ke branch `production`.

---

## Gambaran Urutan Pekerjaan

```
[1] Siapkan VPS OS         → update, user, firewall, Docker
[2] Clone repository       → /var/www/mer-system
[3] Konfigurasi .env        → isi semua nilai wajib
[4] Konfigurasi Docker daemon → log rotation
[5] Jalankan setup-runner.sh → deploy.sh + setup-monitoring.sh
[6] Daftarkan GitHub runner  → self-hosted CI/CD
[7] Verifikasi akhir         → semua service running
```

---

## Bagian 1 — Persiapan OS VPS

### 1.1 — Update sistem

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl git nano openssl python3 ufw
```

### 1.2 — Buat user deploy (opsional tapi direkomendasikan)

Jalankan aplikasi bukan sebagai `root`:

```bash
sudo adduser meradmin
sudo usermod -aG sudo meradmin
sudo usermod -aG docker meradmin   # ditambahkan setelah Docker terinstal
```

> Seluruh panduan selanjutnya diasumsikan dijalankan sebagai user **meradmin**
> atau user yang memiliki akses `sudo` dan tergabung di group `docker`.

### 1.3 — Konfigurasi firewall (UFW)

```bash
# Reset ke default (deny all inbound, allow all outbound)
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Izinkan port yang diperlukan
sudo ufw allow 22/tcp     # SSH
sudo ufw allow 80/tcp     # HTTP (CrowdSec Bouncer — pintu masuk utama)
sudo ufw allow 443/tcp    # HTTPS (jika akan dikonfigurasi di Nginx langsung)
sudo ufw allow 8888/tcp   # CrowdSec Bouncer port (sesuai BOUNCER_PORT di .env)

# Aktifkan firewall
sudo ufw enable
sudo ufw status verbose
```

> **CATATAN PENTING**: Port `8888` adalah port CrowdSec Bouncer yang menjadi
> pintu masuk sesungguhnya ke aplikasi. Port `80` internal Nginx tidak
> perlu dibuka ke publik karena Nginx hanya mendengarkan di dalam Docker network.
> Pastikan port yang dibuka sesuai dengan nilai `BOUNCER_PORT` di `.env`.

---

## Bagian 2 — Install Docker Engine

### 2.1 — Install Docker dari repository resmi

```bash
# Tambah GPG key resmi Docker
curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
  | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg

# Tambah repository Docker
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io \
  docker-buildx-plugin docker-compose-plugin
```

### 2.2 — Tambahkan user ke group docker

```bash
sudo usermod -aG docker $USER
newgrp docker
```

### 2.3 — Verifikasi instalasi

```bash
docker --version
# Output yang diharapkan: Docker version 24.x.x atau lebih baru

docker compose version
# Output yang diharapkan: Docker Compose version v2.x.x
# BUKAN: docker-compose version 1.x (versi lama, tidak kompatibel)

# Test Docker berjalan tanpa sudo
docker run --rm hello-world
```

---

## Bagian 3 — Konfigurasi Log Rotation Docker Daemon

> Log rotation juga sudah dikonfigurasi per-service di `docker-compose.yml`.
> Konfigurasi daemon ini berfungsi sebagai **fallback global** untuk semua
> container yang tidak punya konfigurasi `logging:` eksplisit.

```bash
sudo nano /etc/docker/daemon.json
```

Isi dengan:

```json
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "5"
  }
}
```

Terapkan konfigurasi:

```bash
sudo systemctl reload docker

# Verifikasi Docker masih berjalan setelah reload
sudo systemctl status docker
```

---

## Bagian 4 — Clone Repository

### 4.1 — Buat direktori dan clone

```bash
sudo mkdir -p /var/www
sudo chown $USER:$USER /var/www

git clone https://github.com/firmanfarelrichardo/mer-system.git /var/www/mer-system
cd /var/www/mer-system
git checkout production
```

### 4.2 — Verifikasi branch dan file

```bash
git branch --show-current
# Output: production

ls deployment/production/
# Harus ada: deploy.sh, setup-monitoring.sh, setup-runner.sh,
#            docker-compose.yml, docker-compose.monitoring.yml,
#            .env.example, Dockerfile, nginx.conf
```

---

## Bagian 5 — Konfigurasi File `.env`

### 5.1 — Salin dari template

```bash
cd /var/www/mer-system/deployment/production
cp .env.example .env
nano .env
```

### 5.2 — Nilai yang wajib diisi sebelum deploy

Isi setiap variabel di bawah dengan nilai sesungguhnya. **Jangan tinggalkan satu pun yang kosong** — `deploy.sh` akan berhenti dan menampilkan error jika ada variabel wajib yang kosong.

```bash
# ── APLIKASI ──────────────────────────────────────────────────────────────────
APP_NAME="MER System"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta

# URL production. Wajib https:// agar Laravel generate URL yang benar.
APP_URL=https://mers-rsryacudu.com

# Port yang diekspos ke host untuk Nginx.
# Ini adalah port INTERNAL Nginx — bukan port Bouncer.
APP_PORT=80

# APP_KEY: DIKOSONGKAN — akan digenerate otomatis oleh deploy.sh
# menggunakan: openssl rand -base64 32
APP_KEY=

# ── DATABASE ──────────────────────────────────────────────────────────────────
DB_HOST=db          # Nama service Docker, jangan ganti
DB_PORT=5432
DB_DATABASE=mer_system
DB_USERNAME=mer_user

# Generate password kuat:
# openssl rand -base64 32
DB_PASSWORD=<isi_password_kuat>

# ── REDIS ─────────────────────────────────────────────────────────────────────
REDIS_HOST=redis    # Nama service Docker, jangan ganti
REDIS_PORT=6379

# Generate password kuat:
# openssl rand -base64 32
REDIS_PASSWORD=<isi_password_kuat>

# ── SESSION ───────────────────────────────────────────────────────────────────
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

# ── CROWDSEC ──────────────────────────────────────────────────────────────────
# DIKOSONGKAN — akan digenerate otomatis oleh setup-monitoring.sh
CROWDSEC_BOUNCER_API_KEY=

# Port CrowdSec Bouncer (pintu masuk sesungguhnya ke aplikasi).
# Sesuaikan dengan rule UFW yang sudah dibuat di Bagian 1.3.
BOUNCER_PORT=8888

# ── MONITORING / GRAFANA ──────────────────────────────────────────────────────
GRAFANA_ADMIN_USER=admin

# Generate password kuat:
# openssl rand -base64 24
GRAFANA_ADMIN_PASSWORD=<isi_password_kuat>

# Telegram alert (opsional — kosongkan jika tidak digunakan).
# BOT_TOKEN : dari @BotFather di Telegram
# CHAT_ID   : ID grup/channel tujuan (bisa negatif, contoh: -1001234567890)
GRAFANA_TELEGRAM_BOT_TOKEN=
GRAFANA_TELEGRAM_CHAT_ID=
```

> **Ringkasan variabel yang dikosongkan secara sengaja:**
>
> | Variabel | Dikosongkan karena |
> |---|---|
> | `APP_KEY` | Digenerate oleh `deploy.sh` via `openssl rand -base64 32` (Step 3/7) |
> | `CROWDSEC_BOUNCER_API_KEY` | Digenerate oleh `setup-monitoring.sh` via `cscli bouncers add` (Step 3/4) |

### 5.3 — Amankan permission file `.env`

File `.env` berisi password database dan secret key. Batasi aksesnya:

```bash
chmod 600 /var/www/mer-system/deployment/production/.env

# Verifikasi
ls -la /var/www/mer-system/deployment/production/.env
# Output: -rw------- 1 meradmin meradmin ...
```

---

## Bagian 6 — Jalankan Deployment Pertama

### 6.1 — Pastikan permission skrip sudah executable

```bash
cd /var/www/mer-system/deployment/production
chmod +x deploy.sh setup-monitoring.sh setup-runner.sh
```

### 6.2 — Jalankan orchestrator

`setup-runner.sh` adalah entry point utama yang mengeksekusi dua fase secara berurutan:

```bash
cd /var/www/mer-system/deployment/production
bash setup-runner.sh
```

**Apa yang terjadi saat skrip ini berjalan:**

```
FASE 1 — deploy.sh (7 langkah berurutan):
  Step 1/7  Strict environment validation
            → Cek APP_NAME, APP_URL, DB_DATABASE, DB_USERNAME,
              DB_PASSWORD, REDIS_PASSWORD tidak kosong
  Step 2/7  Git synchronization
            → git fetch origin production
            → git reset --hard origin/production
  Step 3/7  APP_KEY check & generation
            → Jika APP_KEY kosong: generate via openssl rand -base64 32
            → Inject ke .env (format: base64:<key>)
            → Reload .env ke environment
  Step 4/7  Atomic build & healthcheck
            → docker network create mer-prod-network (idempoten)
            → docker compose up -d --build --remove-orphans --wait
            → Blok hingga SEMUA healthcheck PASS
  Step 5/7  Database & Redis readiness probe
            → Test koneksi PHP PDO ke PostgreSQL dari dalam container app
            → Test koneksi PHP Redis ke Redis dari dalam container app
            → php artisan migrate --force
  Step 6/7  Application cache optimization
            → php artisan optimize:clear
            → php artisan optimize
            → php artisan view:cache
            → php artisan event:cache
  Step 7/7  Permission enforcement & queue worker restart
            → chown -R www-data:www-data storage bootstrap/cache
            → php artisan queue:restart

FASE 2 — setup-monitoring.sh (4 langkah):
  Step 1/4  Network validation
            → Verifikasi mer-prod-network sudah ada
  Step 2/4  Monitoring stack orchestration
            → docker compose -f docker-compose.monitoring.yml up -d --remove-orphans
            → Verifikasi 5 container monitoring (prometheus, loki, grafana,
              cadvisor, node-exporter) dalam status running
  Step 3/4  CrowdSec security integration
            → Verifikasi parser crowdsecurity/nginx terpasang
            → Update hub signatures ke versi terbaru
            → Generate CROWDSEC_BOUNCER_API_KEY jika kosong
            → Inject key ke .env + restart crowdsec-bouncer
  Step 4/4  Docker log rotation verification
            → Baca /etc/docker/daemon.json dan laporkan status
```

> **Jika FASE 1 gagal**: skrip berhenti, FASE 2 tidak dijalankan.
> Cek error dan jalankan ulang `bash setup-runner.sh`.
>
> **Jika FASE 2 gagal**: aplikasi tetap berjalan normal (PARTIAL).
> Monitoring adalah concern terpisah — kegagalannya tidak mematikan produksi.

### 6.3 — Akses remote database (jika perlu debug awal)

Database PostgreSQL **tidak** expose port ke host. Akses via SSH tunnel:

```bash
# Dari mesin lokal Anda (bukan VPS)
ssh -L 5432:localhost:5432 meradmin@<IP_VPS> -N
# Kemudian connect ke localhost:5432 dari local database client
```

---

## Bagian 7 — Setup GitHub Actions Self-Hosted Runner

Runner ini memungkinkan GitHub Actions berjalan **langsung di VPS** tanpa memerlukan koneksi SSH inbound dari GitHub. VPS polling GitHub secara outbound — tidak ada port tambahan yang perlu dibuka di firewall.

### 7.1 — Buka halaman pendaftaran runner di GitHub

Buka: `https://github.com/firmanfarelrichardo/mer-system/settings/actions/runners`

Klik **"New self-hosted runner"** → pilih **Linux** → pilih **x64**.

### 7.2 — Download dan ekstrak runner di VPS

Gunakan perintah **persis** dari halaman GitHub (URL dan token selalu berbeda tiap sesi):

```bash
# Buat direktori runner
mkdir -p /home/$USER/actions-runner
cd /home/$USER/actions-runner

# Download runner (salin URL dari halaman GitHub — jangan hardcode)
curl -o actions-runner-linux-x64.tar.gz -L <URL_DARI_HALAMAN_GITHUB>

# Verifikasi checksum (salin hash dari halaman GitHub)
echo "<HASH_DARI_HALAMAN_GITHUB>  actions-runner-linux-x64.tar.gz" | shasum -a 256 -c

# Ekstrak
tar xzf actions-runner-linux-x64.tar.gz
```

### 7.3 — Konfigurasi runner

```bash
./config.sh \
  --url https://github.com/firmanfarelrichardo/mer-system \
  --token <TOKEN_DARI_HALAMAN_GITHUB> \
  --labels "self-hosted,production-vps" \
  --name "mer-production-vps" \
  --unattended
```

> **Token bersifat sementara** (berlaku ~1 jam). Jika expired, generate ulang
> dari halaman GitHub yang sama.
>
> **Labels `self-hosted,production-vps` wajib persis**. Workflow
> `production-deploy.yml` menggunakan `runs-on: [self-hosted, production-vps]`
> untuk memilih runner ini.

### 7.4 — Install sebagai systemd service (auto-start saat reboot)

```bash
sudo ./svc.sh install
sudo ./svc.sh start
```

### 7.5 — Verifikasi runner aktif

```bash
# Di terminal VPS
sudo ./svc.sh status
# Output: active (running)

# Atau via systemd langsung
systemctl status "actions.runner.*"
```

Kembali ke halaman GitHub → **Settings → Actions → Runners** → runner harus terlihat dengan status **Idle** (hijau). Jika masih **Offline**, tunggu 30 detik dan refresh halaman.

---

## Bagian 8 — Verifikasi Akhir

Jalankan semua perintah ini setelah setup selesai untuk memastikan semuanya berjalan benar.

### 8.1 — Status seluruh container

```bash
cd /var/www/mer-system/deployment/production

# Stack produksi (6 container)
docker compose ps
# Semua harus STATUS: running, HEALTH: healthy (kecuali service tanpa healthcheck)

# Stack monitoring (6 container)
docker compose -f docker-compose.monitoring.yml ps
# Semua harus STATUS: running
```

Daftar container yang harus running:

| Container | Stack | Keterangan |
|---|---|---|
| `mer-web-prod` | Produksi | Nginx reverse proxy |
| `mer-app` (atau nama dari Dockerfile) | Produksi | PHP-FPM + Queue |
| `mer-db-prod` | Produksi | PostgreSQL 16 |
| `mer-redis-prod` | Produksi | Redis 7 |
| `mer-crowdsec` | Produksi | IDS engine |
| `mer-crowdsec-bouncer` | Produksi | Nginx firewall bouncer |
| `mer-prometheus` | Monitoring | Scrape metrics |
| `mer-loki` | Monitoring | Log aggregation |
| `mer-promtail` | Monitoring | Log collection agent |
| `mer-cadvisor` | Monitoring | Container metrics |
| `mer-node-exporter` | Monitoring | Host OS metrics |
| `mer-grafana` | Monitoring | Dashboard + alerts |

### 8.2 — Verifikasi aplikasi merespons

```bash
# Test via CrowdSec Bouncer (jalur traffic sesungguhnya)
curl -I http://localhost:8888/
# HTTP/1.1 200 OK (atau 301/302 jika redirect ke HTTPS)

# Alternatif jika APP_PORT=80 (Nginx langsung, melalui Docker)
curl -I http://localhost:80/
```

Status code yang valid: `200`, `301`, `302`, `401`, `403`.

### 8.3 — Verifikasi APP_KEY sudah terisi

```bash
grep "^APP_KEY=" /var/www/mer-system/deployment/production/.env
# Output harus: APP_KEY=base64:<string_panjang>
# BUKAN APP_KEY= (kosong)
```

### 8.4 — Verifikasi CROWDSEC_BOUNCER_API_KEY sudah terisi

```bash
grep "^CROWDSEC_BOUNCER_API_KEY=" /var/www/mer-system/deployment/production/.env
# Output harus: CROWDSEC_BOUNCER_API_KEY=<key_value>
# BUKAN CROWDSEC_BOUNCER_API_KEY= (kosong)
```

### 8.5 — Verifikasi CrowdSec parser aktif

```bash
docker exec mer-crowdsec cscli collections list
# Harus ada baris: crowdsecurity/nginx   (status: enabled atau downloaded)
```

### 8.6 — Verifikasi database migration berhasil

```bash
docker compose exec app php artisan migrate:status
# Semua migration harus status: Ran
```

### 8.7 — Akses Grafana

Grafana terikat ke `127.0.0.1:3000` — tidak bisa diakses langsung dari internet.
Gunakan SSH tunnel dari mesin lokal:

```bash
# Dari mesin lokal Anda
ssh -L 3000:127.0.0.1:3000 meradmin@<IP_VPS> -N
```

Buka browser: `http://localhost:3000`

Login dengan:
- **Username**: `admin`
- **Password**: nilai `GRAFANA_ADMIN_PASSWORD` dari `.env`

### 8.8 — Cek audit log orchestrator

```bash
cat /var/log/mer-deploy/orchestrator.log
# Baris terakhir harus: status=SUCCESS
```

---

## Bagian 9 — Setelah Setup: Cara Kerja CI/CD

Setelah setup selesai, **workflow CI/CD berjalan otomatis**:

```
Developer push ke branch production
            │
            ▼
   GitHub Actions (Job 1: validate)
   Berjalan di GitHub-hosted runner:
   - Cek semua key wajib ada di .env.example
   - Cek file deploy kritis ada di repository
            │
            ▼ (jika Job 1 PASS)
   GitHub Actions (Job 2: deploy)
   Berjalan di VPS via self-hosted runner:
   - Catat ROLLBACK_COMMIT (baseline)
   - git fetch + git reset --hard origin/production
   - bash deploy.sh (7 langkah)
   - Jika gagal → rollback otomatis ke ROLLBACK_COMMIT
   - Gate kesehatan: docker compose ps + curl HTTP check
   - Tulis audit log
```

Untuk **deploy manual** atau langkah darurat:

```bash
cd /var/www/mer-system/deployment/production

# Deploy + restart monitoring (full)
bash setup-runner.sh

# Deploy saja, skip monitoring (untuk hotfix cepat)
bash setup-runner.sh --skip-monitoring

# Deploy saja langsung (tanpa orchestrator)
bash deploy.sh

# Monitoring saja (jika stack monitoring mati)
bash setup-monitoring.sh
```

---

## Bagian 10 — Pemecahan Masalah Umum

### Container tidak mau start (healthcheck gagal)

```bash
# Lihat log container spesifik
docker compose logs app --tail 50
docker compose logs db --tail 50
docker compose logs redis --tail 50

# Lihat detail healthcheck status
docker inspect mer-db-prod | python3 -m json.tool | grep -A5 "Health"
```

### `APP_KEY` masih kosong setelah deploy

Deploy.sh seharusnya mengisi ini otomatis. Jika tidak:

```bash
NEW_KEY="base64:$(openssl rand -base64 32)"
sed -i "s|^APP_KEY=.*|APP_KEY=${NEW_KEY}|" \
  /var/www/mer-system/deployment/production/.env
docker compose restart app
```

### `CROWDSEC_BOUNCER_API_KEY` masih kosong

```bash
# Generate manual
docker exec mer-crowdsec cscli bouncers add nginx-bouncer

# Salin key yang ditampilkan, lalu:
nano /var/www/mer-system/deployment/production/.env
# → Isi: CROWDSEC_BOUNCER_API_KEY=<key_yang_dicopy>

docker compose restart crowdsec-bouncer
```

### Network `mer-prod-network` tidak ditemukan

Terjadi jika deploy.sh belum pernah dijalankan atau network dihapus manual:

```bash
docker network create mer-prod-network
```

### GitHub runner status Offline

```bash
cd /home/$USER/actions-runner
sudo ./svc.sh status

# Jika stopped, start ulang:
sudo ./svc.sh start

# Lihat log
journalctl -u "actions.runner.*" -n 50
```

### Monitoring stack OOM (ter-kill oleh kernel)

Ini perilaku yang didesain (resource limits sengaja ketat). Stack monitoring
akan restart otomatis (`restart: unless-stopped`). Cek mengapa OOM terjadi:

```bash
# Lihat OOM events
dmesg | grep -i "oom\|killed" | tail -20

# Restart monitoring manual jika perlu
docker compose -f /var/www/mer-system/deployment/production/docker-compose.monitoring.yml \
  restart
```

---

## Referensi: Struktur File Relevan

```
deployment/production/
├── .env                          # Environment production (TIDAK di-commit ke Git)
├── .env.example                  # Template — template ini yang ada di Git
├── deploy.sh                     # FASE 1: core app deployment (7 langkah)
├── setup-monitoring.sh           # FASE 2: monitoring & security (4 langkah)
├── setup-runner.sh               # Orchestrator: FASE 1 → FASE 2
├── docker-compose.yml            # Stack produksi (6 services)
├── docker-compose.monitoring.yml # Stack monitoring (6 services)
├── Dockerfile                    # Image PHP-FPM + Laravel
├── nginx.conf                    # Konfigurasi Nginx
├── lockdown/                     # File trigger lockdown darurat
├── lockdown.sh                   # Aktifkan mode maintenance (503)
├── unlock.sh                     # Nonaktifkan mode maintenance
├── monitoring/
│   ├── prometheus.yml            # Konfigurasi scrape targets
│   ├── loki-config.yml           # Konfigurasi Loki storage
│   ├── promtail-config.yml       # Pipeline parsing log Nginx + Laravel
│   ├── grafana-datasources.yml   # Auto-provisioning Prometheus + Loki
│   ├── grafana-alerts.yml        # Alert rules (CPU, RAM, restart, disk)
│   └── grafana-contact-points.yml# Notifikasi Telegram
└── docs/
    ├── 00-setup-vps-pertama-kali.md  # ← Dokumen ini
    ├── 01-arsitektur.md
    ├── 02-deployment.md
    ├── 03-keamanan.md
    ├── 04-monitoring.md
    ├── 05-kill-switch.md
    └── 06-operasional.md
```

---

*Dokumen ini mencerminkan keadaan repository pada branch `production`.*
*Update dokumen ini jika ada perubahan infrastruktur yang signifikan.*
