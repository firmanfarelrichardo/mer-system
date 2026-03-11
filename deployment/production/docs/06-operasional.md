# Runbook Operasional — MER System Production

Panduan operasi sehari-hari untuk operator yang mengelola sistem. Berisi perintah rutin, prosedur troubleshooting, dan panduan pemulihan dari berbagai kondisi error.

---

## Perintah Sehari-hari

### Cek Status Sistem

```bash
cd /var/www/mer-system/deployment/production

# Status semua container produksi
docker compose ps

# Status container monitoring
docker compose -f docker-compose.monitoring.yml ps

# Penggunaan resource real-time semua container
docker stats --no-stream

# Penggunaan disk volume
docker system df -v
```

### Lihat Log

```bash
# Log Nginx (access + error)
docker compose logs web --tail 50 --follow

# Log aplikasi PHP-FPM
docker compose logs app --tail 50 --follow

# Log queue worker saja (dari dalam supervisord)
docker compose exec app tail -f storage/logs/queue-worker.log

# Log Laravel (exception, error, warning)
docker compose exec app tail -f storage/logs/laravel.log

# Log semua container sekaligus (bercampur)
docker compose logs --tail 20 --follow

# Log CrowdSec (alert intrusi)
docker compose logs crowdsec --tail 30
```

### Masuk ke Shell Container

```bash
# Shell container app (PHP)
docker compose exec app sh

# Shell container database (PostgreSQL)
docker compose exec db psql -U postgres mer_system

# Shell container Redis
docker compose exec redis redis-cli -a "${REDIS_PASSWORD}"

# Shell container Nginx
docker compose exec web sh
```

---

## Deploy & Update

### Deploy Update Kode

```bash
cd /var/www/mer-system/deployment/production
bash deploy.sh
```

### Restart Service Tertentu

```bash
# Restart PHP-FPM saja (tanpa rebuild image)
docker compose restart app

# Restart Nginx
docker compose restart web

# Restart queue worker (graceful via artisan)
docker compose exec app php artisan queue:restart

# Restart supervisord process tertentu
docker compose exec app supervisorctl restart queue-worker:queue-worker_00
docker compose exec app supervisorctl restart queue-worker:queue-worker_01
docker compose exec app supervisorctl restart scheduler
```

### Rebuild Image Tanpa Update Kode

```bash
# Rebuild image dari kode yang sudah ada (misal: perubahan Dockerfile)
docker compose build app
docker compose up -d --no-deps app
```

---

## Database Operations

### Koneksi ke Database

```bash
# Via Docker exec (dari dalam container)
docker compose exec db psql -U postgres mer_system

# Via SSH tunnel dari komputer lokal (akses remote aman)
ssh -L 5432:localhost:5432 username@<ip-vps>
# Lalu di komputer lokal:
psql -h localhost -U postgres mer_system
```

### Backup Database

```bash
# Backup manual (ganti nama file sesuai kebutuhan)
mkdir -p /var/backups/mer-system
docker compose exec -T db pg_dump -U postgres mer_system \
    > "/var/backups/mer-system/backup-$(date +%Y%m%d-%H%M%S).sql"
```

### Artisan Commands

```bash
# Status migrasi
docker compose exec app php artisan migrate:status

# Jalankan migrasi
docker compose exec app php artisan migrate --force

# Rollback 1 batch terakhir
docker compose exec app php artisan migrate:rollback --force

# Bersihkan seluruh cache
docker compose exec app php artisan optimize:clear

# Rebuild cache
docker compose exec app php artisan optimize
docker compose exec app php artisan view:cache
docker compose exec app php artisan event:cache

# Tinker (REPL interaktif)
docker compose exec app php artisan tinker

# Cek status queue
docker compose exec app php artisan queue:monitor redis:default

# Retry failed jobs
docker compose exec app php artisan queue:retry all

# Hapus failed jobs lama
docker compose exec app php artisan queue:flush
```

---

## Troubleshooting

### 502 Bad Gateway

**Penyebab:** Nginx tidak bisa terhubung ke PHP-FPM.

```bash
# 1. Cek apakah container app berjalan
docker compose ps app

# 2. Cek log PHP-FPM
docker compose logs app --tail 30

# 3. Test koneksi FPM dari dalam container Nginx
docker compose exec web sh -c "curl -s http://app:9000/ping"

# 4. Cek status supervisord
docker compose exec app supervisorctl status

# 5. Restart PHP-FPM via supervisord
docker compose exec app supervisorctl restart php-fpm
```

### 503 Service Unavailable (Bukan Lockdown)

```bash
# Cek apakah lockdown aktif
ls -la lockdown/.lockdown
# Ada file .lockdown → jalankan: bash unlock.sh

# Jika tidak ada .lockdown, cek Nginx
docker compose exec web nginx -t
docker compose logs web --tail 20
```

### Database Connection Error

```bash
# 1. Cek container DB berjalan dan healthy
docker compose ps db

# 2. Cek log PostgreSQL
docker compose logs db --tail 20

# 3. Test koneksi dari container app
docker compose exec app php artisan tinker --execute="DB::connection()->getPdo(); echo 'OK';"

# 4. Verifikasi kredensial di .env
grep DB_ .env

# 5. Cek apakah .env sudah dimuat ke container (env var di runtime)
docker compose exec app printenv | grep DB_
```

### Redis Connection Error

```bash
# 1. Cek container Redis
docker compose ps redis

# 2. Test koneksi
docker compose exec redis redis-cli -a "${REDIS_PASSWORD}" ping
# Harus menjawab: PONG

# 3. Cek dari container app
docker compose exec app php artisan tinker --execute="Cache::store('redis')->put('test', 1, 10); echo Cache::store('redis')->get('test');"

# 4. Cek log Redis
docker compose logs redis --tail 20
```

### Queue Worker Tidak Memproses Job

```bash
# 1. Cek status worker
docker compose exec app supervisorctl status queue-worker:*

# 2. Cek failed jobs
docker compose exec app php artisan queue:failed

# 3. Restart workers
docker compose exec app php artisan queue:restart
# Supervisord akan respawn worker otomatis

# 4. Jika masih bermasalah, restart container app
docker compose restart app
```

### Container Terus-menerus Restart (Crash Loop)

```bash
# Lihat log sebelum crash
docker compose logs app --tail 50

# Cek exit code terakhir
docker inspect mer-app-prod | grep ExitCode

# Cek healthcheck log
docker inspect mer-app-prod | grep -A 10 Health
```

### Disk Space Penuh

```bash
# Cek penggunaan disk
df -h
du -sh /var/lib/docker/volumes/*

# Hapus image dan container yang tidak terpakai
docker system prune -a --volumes
# PERINGATAN: Ini menghapus volume yang tidak digunakan!
# Lebih aman:
docker system prune  # Hapus hanya container, network, image dangling

# Bersihkan log Docker yang besar
find /var/lib/docker/containers -name "*.log" -size +100M \
    -exec truncate -s 0 {} \;

# Rotasi log Nginx secara manual
docker compose exec web sh -c "rm /var/log/nginx/*.log && nginx -s reopen"
```

### Memory / OOM Kill

```bash
# Cek apakah ada container yang pernah di-OOM-kill
docker inspect mer-app-prod | grep OOMKilled
docker inspect mer-db-prod | grep OOMKilled

# Lihat penggunaan memory real-time
docker stats --no-stream --format "table {{.Name}}\t{{.MemUsage}}\t{{.MemPerc}}"

# Jika PostgreSQL OOM, turunkan shared_buffers di konfigurasi
# (tambahkan ke command PostgreSQL di docker-compose.yml)
```

---

## Monitoring Operations

### Cek Kesehatan Stack Monitoring

```bash
# Status semua service monitoring
docker compose -f docker-compose.monitoring.yml ps

# Prometheus: apakah semua target di-scrape dengan sukses?
# Akses via SSH tunnel: http://localhost:9090/targets
ssh -L 9090:localhost:9090 username@<ip-vps>

# Loki: apakah siap menerima push?
docker compose -f docker-compose.monitoring.yml exec loki \
    wget -O- http://localhost:3100/ready

# Promtail: apakah sudah terhubung ke Loki?
docker compose -f docker-compose.monitoring.yml logs promtail --tail 20
```

### Reload Konfigurasi Prometheus

```bash
# Setelah edit monitoring/prometheus.yml, reload tanpa restart:
curl -X POST http://localhost:9090/-/reload
# (Jalankan dari dalam VM atau via SSH tunnel)
```

---

## Jadwal Pemeliharaan Periodik

### Harian (Otomatis via Scheduler Laravel)

| Waktu | Task | Command |
|-------|------|---------|
| 01:00 | Bersihkan log aktivitas lama | `artisan log:prune` |
| 03:00 | Optimize database | `artisan db:optimize` |

### Mingguan (Manual oleh Operator)

```bash
# Setiap Minggu pagi
# 1. Update scenarios CrowdSec
docker exec mer-crowdsec cscli hub update && docker exec mer-crowdsec cscli hub upgrade

# 2. Cek dan hapus failed jobs
docker compose exec app php artisan queue:retry all
docker compose exec app php artisan queue:failed

# 3. Backup database
mkdir -p /var/backups/mer-system
docker compose exec -T db pg_dump -U postgres mer_system \
    > "/var/backups/mer-system/weekly-$(date +%Y%m%d).sql"
```

### Bulanan (Manual oleh Operator)

```bash
# 1. Update docker images
docker compose pull
docker compose -f docker-compose.monitoring.yml pull

# 2. Audit keamanan (lihat docs/03-keamanan.md)
# 3. Rotasi password (jika diperlukan)
# 4. Review alert di Grafana/Sentry
# 5. Test prosedur lockdown/unlock
bash lockdown.sh && sleep 5 && bash unlock.sh
```

---

## Environment Variables Reference

File: `.env` (di direktori ini)

| Variabel | Wajib | Default | Keterangan |
|----------|-------|---------|------------|
| `APP_KEY` | Ya | — | Digenerate otomatis oleh deploy.sh |
| `APP_URL` | Ya | — | `https://mers-rsryacudu.com` |
| `APP_PORT` | Tidak | `80` | Port internal Nginx |
| `DB_DATABASE` | Ya | — | Nama database PostgreSQL |
| `DB_USERNAME` | Ya | — | Username PostgreSQL |
| `DB_PASSWORD` | Ya | — | Password PostgreSQL, min. 20 karakter |
| `REDIS_PASSWORD` | Ya | — | Password Redis, min. 20 karakter |
| `SESSION_SECURE_COOKIE` | Tidak | `true` | True di production (HTTPS) |
| `SESSION_SAME_SITE` | Tidak | `lax` | `lax` atau `strict` |
| `CROWDSEC_BOUNCER_API_KEY` | Ya | — | Generate via `cscli bouncers add` |
| `BOUNCER_PORT` | Tidak | `8888` | Port publik CrowdSec Bouncer |
| `GRAFANA_ADMIN_USER` | Tidak | `admin` | Username Grafana |
| `GRAFANA_ADMIN_PASSWORD` | Ya | — | Password Grafana, min. 20 karakter |

---

## Kontak & Eskalasi

Ketika menghadapi insiden yang tidak bisa diselesaikan sendiri:

1. **Aktifkan lockdown** terlebih dahulu: `bash lockdown.sh`
2. **Preservasi log** sebelum tindakan apapun: `docker compose logs > incident-$(date +%Y%m%d).log`
3. **Jangan hapus atau overwrite** data apapun sebelum investigasi selesai
4. **Dokumentasikan** semua tindakan yang diambil beserta timestamp
