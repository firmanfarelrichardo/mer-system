# Panduan Deployment — MER System Production

## Prasyarat

Sebelum menjalankan deployment pertama kali, pastikan kondisi berikut terpenuhi di VPS:

```bash
# Verifikasi Docker Engine terinstall
docker --version        # Docker Engine 24.x atau lebih baru
docker compose version  # Docker Compose v2.x (bukan docker-compose v1)

# Verifikasi Git branch yang benar
git branch --show-current  # Harus: production

# Verifikasi direktori kerja
pwd  # Harus: /var/www/mer-system/deployment/production
```

---

## Deploy Pertama Kali

### Langkah 1 — Clone Repository

```bash
git clone https://github.com/firmanfarelrichardo/mer-system.git /var/www/mer-system
cd /var/www/mer-system
git checkout production
```

### Langkah 2 — Buat External Network

Network ini harus dibuat **satu kali saja** dan bersifat persisten (tidak hilang saat stack di-restart).

```bash
docker network create mer-prod-network
```

Jika network sudah ada, perintah ini akan gagal dengan error. Itu normal — abaikan.

### Langkah 3 — Siapkan Environment File

```bash
cd deployment/production
cp .env.example .env
nano .env
```

Nilai yang **wajib diisi** sebelum deploy bisa berjalan:

| Variabel | Contoh | Keterangan |
|----------|--------|------------|
| `APP_URL` | `https://mers-rsryacudu.com` | URL production dengan `https://` |
| `DB_DATABASE` | `mer_system` | Nama database PostgreSQL |
| `DB_USERNAME` | `mer_user` | Username PostgreSQL |
| `DB_PASSWORD` | `P@ssw0rd_xyz` | Password kuat (min. 20 karakter) |
| `REDIS_PASSWORD` | `R3d1s_Secret_42` | Password Redis (min. 20 karakter) |
| `APP_KEY` | (kosongkan) | Otomatis digenerate oleh `deploy.sh` |

Nilai yang diisi **setelah stack pertama berjalan**:

| Variabel | Cara mendapatkan |
|----------|-----------------|
| `CROWDSEC_BOUNCER_API_KEY` | `docker exec mer-crowdsec cscli bouncers add nginx-bouncer` |
| `GRAFANA_ADMIN_PASSWORD` | Buat sendiri dengan `openssl rand -base64 24` |

### Langkah 4 — Jalankan Deploy Script

```bash
bash deploy.sh
```

Script ini menjalankan 7 langkah berurutan:

| Step | Perintah | Keterangan |
|------|----------|------------|
| 1 | `git pull --ff-only origin production` | Update kode, tolak divergent branches |
| 2 | `docker compose up -d --build --wait` | Build image, start container, tunggu healthcheck |
| 3 | Validasi `APP_KEY` | Generate key jika kosong, inject ke `.env`, restart container app |
| 4 | `php artisan migrate --force` | Jalankan semua migrasi database |
| 5 | `optimize:clear` → `optimize` | Rebuild cache config, route, view, event |
| 6 | `chown www-data storage` | Perbaiki permission storage |
| 7 | `queue:restart` | Graceful restart queue workers |

**Jika `deploy.sh` gagal di tengah jalan**, script berhenti karena `set -e`. Periksa output error, perbaiki masalahnya, lalu jalankan ulang — script idempoten dan aman dijalankan berulang.

### Langkah 5 — Konfigurasi CrowdSec Bouncer

```bash
# Generate API key untuk bouncer
docker exec mer-crowdsec cscli bouncers add nginx-bouncer

# Contoh output:
# Api key for 'nginx-bouncer':
#   abc123def456...
# Please keep this key since you will not be able to retrieve it.

# Salin key ke .env
nano .env
# → CROWDSEC_BOUNCER_API_KEY=abc123def456...

# Restart bouncer agar membaca key baru
docker compose restart crowdsec-bouncer
```

### Langkah 6 — Verifikasi

```bash
# Semua container harus berstatus "running" atau "healthy"
docker compose ps

# Test akses aplikasi
curl -I https://mers-rsryacudu.com

# Test bahwa DB tidak expose ke luar
nc -zv <ip-vps> 5432  # Harus gagal (connection refused)

# Cek log Nginx untuk request pertama
docker compose logs web --tail 20
```

---

## Update / Redeploy

Untuk setiap update kode (setelah commit di-push ke branch `production`):

```bash
cd /var/www/mer-system/deployment/production
bash deploy.sh
```

`deploy.sh` menangani seluruh siklus update: pull kode, rebuild image, migrate database, rebuild cache, restart queue worker.

### Kondisi Khusus: Perubahan `.env`

Jika ada perubahan environment variable (bukan kode), lakukan:

```bash
# Edit .env
nano .env

# Recreate service yang terpengaruh
# (compose membaca ulang .env saat recreate)
docker compose up -d --no-deps --force-recreate app
```

### Kondisi Khusus: Update Konfigurasi Nginx

```bash
# Edit nginx.conf
nano nginx.conf

# Test config valid
docker compose exec web nginx -t

# Reload tanpa downtime
docker compose exec web nginx -s reload
```

### Kondisi Khusus: Perubahan Schema Database

Jika migrasi memerlukan downtime (DROP COLUMN, ALTER TABLE pada tabel besar):

1. Aktifkan lockdown: `bash lockdown.sh`
2. Jalankan: `bash deploy.sh`
3. Verifikasi migrasi berhasil: `docker compose exec app php artisan migrate:status`
4. Nonaktifkan lockdown: `bash unlock.sh`

---

## Rollback

### Rollback Kode (Git)

```bash
cd /var/www/mer-system

# Lihat commit history
git log --oneline -10

# Rollback ke commit tertentu (ganti <hash> dengan commit yang diinginkan)
git checkout production
git reset --hard <commit-hash>

# Deploy ulang dari commit yang di-rollback
cd deployment/production
bash deploy.sh
```

**Perhatian:** Jika update yang di-rollback menyertakan migrasi database yang sudah berjalan, perlu jalankan `php artisan migrate:rollback` sebelum reset Git. Rollback migrasi destruktif (DROP COLUMN) tidak bisa dipulihkan tanpa backup.

### Rollback Docker Image

```bash
# Lihat image history
docker image ls | grep mer-system

# Jalankan container dari image sebelumnya
# (ganti :latest dengan tag commit sebelumnya jika image sudah di-tag)
docker compose up -d --no-build
```

---

## Backup & Restore

### Backup Database

Jalankan sebelum setiap update besar:

```bash
# Backup database ke file dengan timestamp
BACKUP_FILE="backup-$(date +%Y%m%d-%H%M%S).sql"
docker compose exec -T db pg_dump \
    -U "${DB_USERNAME}" \
    "${DB_DATABASE}" > "/var/backups/mer-system/${BACKUP_FILE}"

echo "Backup disimpan: /var/backups/mer-system/${BACKUP_FILE}"
```

Buat direktori backup jika belum ada:

```bash
mkdir -p /var/backups/mer-system
```

### Restore Database

```bash
# PERINGATAN: Ini menghapus semua data yang ada di database!
# Pastikan sudah ada backup terbaru sebelum restore.

BACKUP_FILE="/var/backups/mer-system/backup-20260311-120000.sql"

# Drop dan recreate database kosong
docker compose exec -T db psql -U "${DB_USERNAME}" -c "DROP DATABASE IF EXISTS ${DB_DATABASE};"
docker compose exec -T db psql -U "${DB_USERNAME}" -c "CREATE DATABASE ${DB_DATABASE};"

# Restore dari backup
cat "${BACKUP_FILE}" | docker compose exec -T db psql \
    -U "${DB_USERNAME}" \
    "${DB_DATABASE}"
```

### Backup Storage (File Upload)

```bash
# Backup semua file upload ke tarball
docker run --rm \
    -v production_app-storage:/data \
    -v /var/backups/mer-system:/backup \
    alpine tar czf "/backup/storage-$(date +%Y%m%d).tar.gz" /data

# Restore
docker run --rm \
    -v production_app-storage:/data \
    -v /var/backups/mer-system:/backup \
    alpine tar xzf "/backup/storage-20260311.tar.gz" -C /
```

---

## Deploy Stack Monitoring

Stack monitoring dijalankan secara independen dari stack produksi:

```bash
cd /var/www/mer-system/deployment/production

# Jalankan
docker compose -f docker-compose.monitoring.yml up -d

# Cek status
docker compose -f docker-compose.monitoring.yml ps

# Stop (tidak mengganggu stack produksi)
docker compose -f docker-compose.monitoring.yml down
```

### Install Node Exporter (Host Metrics)

Node Exporter diinstal di host OS (bukan container) untuk membaca metrik hardware asli:

```bash
# Install
sudo apt update && sudo apt install -y prometheus-node-exporter

# Konfigurasi hanya listen di localhost (keamanan)
sudo systemctl edit prometheus-node-exporter
```

Tambahkan konten berikut di editor yang terbuka:

```ini
[Service]
Environment="ARGS=--web.listen-address=127.0.0.1:9100"
```

```bash
# Terapkan dan aktifkan
sudo systemctl daemon-reload
sudo systemctl restart prometheus-node-exporter
sudo systemctl enable prometheus-node-exporter

# Verifikasi
curl -s http://localhost:9100/metrics | head -5
```

### Akses Grafana

Grafana hanya bisa diakses via SSH tunnel (tidak expose ke internet):

```bash
# Di komputer lokal operator
ssh -L 3000:localhost:3000 username@<ip-vps>

# Buka di browser lokal
# http://localhost:3000
# Login: admin / (nilai GRAFANA_ADMIN_PASSWORD di .env)
```

---

## Checklist Deploy Production

Gunakan checklist ini sebelum setiap deploy ke production:

- [ ] Backup database selesai dan file backup terverifikasi
- [ ] Kode sudah di-review dan di-merge ke branch `production`
- [ ] `.env` sudah diperbarui (jika ada variabel baru di `.env.example`)
- [ ] Migrasi database sudah ditest di environment staging/lokal
- [ ] Monitoring Grafana dipantau saat dan setelah deploy
- [ ] Jika migrasi destruktif: lockdown sudah diaktifkan sebelum deploy
