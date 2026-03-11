# Kill Switch Level 3 — Prosedur Lockdown & Pemulihan

## Apa itu Kill Switch Level 3?

Kill Switch Level 3 adalah mekanisme pembekuan darurat seluruh backend sistem dalam hitungan detik. Digunakan ketika:

- Terdeteksi **intrusi aktif** atau eksploitasi zero-day yang sedang berlangsung
- **Kompromi database** atau data breach yang sedang terjadi
- **Defacement** atau manipulasi konten aplikasi
- Kondisi darurat lain yang memerlukan penghentian total aplikasi sambil mempertahankan akses investigasi

## Mekanisme Teknis

### Sebelum Lockdown (Kondisi Normal)

```
Browser → Cloudflare → CrowdSec Bouncer → Nginx → PHP-FPM → Laravel → PostgreSQL / Redis
```

### Setelah Lockdown Aktif

```
Browser → Cloudflare → CrowdSec Bouncer → Nginx → maintenance.html (503)

                        [PHP-FPM   = PAUSED — cgroups frozen]
                        [PostgreSQL = PAUSED — cgroups frozen]
                        [Redis     = PAUSED — cgroups frozen]
```

**Kunci perbedaan:** Container backend **di-pause** (bukan di-stop). `docker pause` menggunakan cgroups freezer subsystem — semua proses dibekukan di state saat ini tanpa kehilangan data di memori. Ini memungkinkan:
- Memory dump forensik jika diperlukan
- Resume cepat (unpause) tanpa cold start
- Database tidak kehilangan data in-flight

### Cara Nginx Mendeteksi Status Lockdown

Nginx memantau keberadaan file `lockdown/.lockdown` yang di-mount ke dalam container:

```nginx
# Di nginx.conf
set $lockdown_mode 0;
if (-f /etc/nginx/lockdown/.lockdown) {
    set $lockdown_mode 1;
}

# Semua request diarahkan ke maintenance.html saat lockdown aktif
if ($lockdown_mode = 1) {
    return 503;
}
error_page 503 /maintenance.html;
```

File `maintenance.html` adalah halaman HTML standalone — tanpa dependency eksternal (tanpa CDN, tanpa JavaScript, tanpa gambar eksternal). Halaman ini bisa dilayani oleh Nginx bahkan jika seluruh internet terputus.

---

## Prosedur Lockdown (Aktivasi Mode Darurat)

### Prasyarat

```bash
# Pastikan kamu sudah berada di direktori yang benar
cd /var/www/mer-system/deployment/production
pwd  # Harus: /var/www/mer-system/deployment/production
```

### Jalankan Lockdown

```bash
bash lockdown.sh
```

Script ini menjalankan langkah-langkah berikut secara otomatis:

1. **Buat file trigger** `lockdown/.lockdown` di host filesystem
   - File ini ter-bind-mount ke Nginx container di `/etc/nginx/lockdown/.lockdown`
   - Nginx membaca perubahan ini secara real-time tanpa perlu reload

2. **Kirim signal reload ke Nginx** (`nginx -s reload`)
   - Semua worker process baru mulai mengecek file trigger
   - Worker yang sedang menangani request aktif menyelesaikan request terlebih dahulu
   - Worker baru akan melayani `maintenance.html` untuk semua request

3. **Pause container backend** (urutan: app → redis → db)
   - `docker pause mer-app-prod` — membekukan PHP-FPM, queue worker, scheduler
   - `docker pause mer-redis-prod` — membekukan Redis
   - `docker pause mer-db-prod` — membekukan PostgreSQL

4. **Catat waktu lockdown** ke log untuk audit trail

### Output yang Diharapkan

```
============================================================
  LEVEL 3 KILL SWITCH — LOCKDOWN (DARURAT)
============================================================
  Waktu    : 2026-03-11 14:32:00 WIB
  Operator : root@mer-vps

  [INFO]  Membuat file trigger lockdown...
  [OK]    File trigger dibuat: /var/www/mer-system/deployment/production/lockdown/.lockdown
  [INFO]  Mengirim sinyal reload ke Nginx...
  [OK]    Nginx berhasil di-reload. Mode maintenance AKTIF.
  [INFO]  Mem-pause container: app...
  [OK]    Container app berhasil di-pause
  [INFO]  Mem-pause container: redis...
  [OK]    Container redis berhasil di-pause
  [INFO]  Mem-pause container: db...
  [OK]    Container db berhasil di-pause

============================================================
  LOCKDOWN AKTIF
  Semua pengunjung melihat halaman maintenance (503).
  Backend dibekukan — data aman untuk forensik.
============================================================
  Untuk memulihkan sistem: bash unlock.sh
```

### Verifikasi Lockdown Berhasil

```bash
# Verifikasi pengunjung melihat halaman maintenance
curl -I https://mers-rsryacudu.com
# Harus: HTTP/2 503

# Verifikasi container backend dalam status paused
docker compose ps
# app: paused
# db: paused
# redis: paused
# web: running (Nginx tetap melayani maintenance page)
# crowdsec: running
# crowdsec-bouncer: running
```

---

## Prosedur Unlock (Pemulihan Sistem)

### Urutan Pemulihan (Penting)

Urutan unpause **harus** dibalik dari urutan lockdown:

```
Lockdown   : app → redis → db   (pause: dependency dimatikan duluan)
Unlock     : db → redis → app   (unpause: dependency dihidupkan duluan)
```

Jika urutan salah (app di-unpause sebelum db), PHP-FPM akan mencoba koneksi database yang belum siap dan menghasilkan 502 Bad Gateway error.

### Jalankan Unlock

```bash
bash unlock.sh
```

Script ini menjalankan langkah-langkah berikut:

1. **Unpause container backend** (urutan: db → redis → app)
   - PostgreSQL hidup terlebih dahulu, siap menerima koneksi
   - Redis hidup, session dan queue broker kembali aktif
   - PHP-FPM resume, queue worker dan scheduler kembali berjalan

2. **Hapus file trigger** `lockdown/.lockdown`
   - Nginx mendeteksi ketiadaan file ini pada request berikutnya
   - Traffic kembali diteruskan ke PHP-FPM secara normal

3. **Catat waktu unlock** ke log untuk audit trail

### Verifikasi Unlock Berhasil

```bash
# Cek status container
docker compose ps
# Semua: running (healthy)

# Test akses aplikasi
curl -I https://mers-rsryacudu.com
# Harus: HTTP/2 302 atau 200 (bukan 503)

# Test login
curl -s https://mers-rsryacudu.com/login | grep -i "<title>"
```

---

## Halaman Maintenance

File: `lockdown/maintenance.html`

Halaman ini dirancang untuk berfungsi dalam kondisi ekstrem:

| Karakteristik | Detail |
|---------------|--------|
| Zero external dependency | Tidak ada CDN, tidak ada Google Fonts, tidak ada gambar eksternal |
| Zero JavaScript | Murni HTML + CSS inline — tidak bisa di-inject XSS |
| Self-contained | Seluruh styling ada di dalam file `<style>` tag |
| Ukuran < 10 KB | Dilayani langsung oleh Nginx dari disk, tanpa I/O ke backend |
| Status HTTP 503 | Memberitahu crawler/bot bahwa ini kondisi sementara (bukan 404) |

---

## Skenario Penggunaan

### Skenario 1: Intrusi Terdeteksi, Investigasi Diperlukan

```bash
# 1. Aktifkan lockdown SEGERA
bash lockdown.sh

# 2. Buat snapshot memory PostgreSQL (forensik)
# Container paused, state memori masih utuh
docker checkpoint create mer-db-prod db-snapshot-$(date +%Y%m%d)

# 3. Backup database sebelum investigasi lebih jauh
docker compose start db   # unpause sementara untuk backup
docker compose exec -T db pg_dump -U postgres mer_system > /var/forensics/db-dump-$(date +%Y%m%d).sql
docker compose pause mer-db-prod   # pause ulang

# 4. Analisis log
docker compose logs web --since 4h > /var/forensics/nginx-logs-$(date +%Y%m%d).txt
docker exec mer-crowdsec cscli alerts list --since 24h

# 5. Setelah investigasi selesai dan sistem dinyatakan aman
bash unlock.sh
```

### Skenario 2: Update Emergency (Hotfix)

```bash
# 1. Lockdown untuk mencegah race condition selama deploy darurat
bash lockdown.sh

# 2. Deploy hotfix
bash deploy.sh

# 3. Verifikasi fix berhasil
docker compose exec app php artisan tinker --execute="echo 'OK';"

# 4. Buka kembali
bash unlock.sh
```

### Skenario 3: Maintenance Database (Migrasi Destruktif)

```bash
# 1. Lockdown agar tidak ada request ke DB selama migrasi
bash lockdown.sh

# 2. Unpause hanya container app dan db (redis tidak perlu)
docker compose unpause db
docker compose unpause app

# 3. Jalankan migrasi
docker compose exec app php artisan migrate --force

# 4. Verifikasi dan buka kembali
docker compose exec app php artisan migrate:status
bash unlock.sh
```

---

## Catatan Penting

### Apa yang TETAP BERJALAN saat Lockdown

| Service | Status | Keterangan |
|---------|--------|------------|
| `web` (Nginx) | Running | Melayani maintenance.html |
| `crowdsec` | Running | Log tetap dianalisis |
| `crowdsec-bouncer` | Running | Blokir IP berbahaya tetap aktif |
| Stack monitoring | Running | Prometheus, Grafana, Loki tetap berjalan |
| Node Exporter | Running | Service host, tidak terpengaruh |

### Apa yang DIBEKUKAN saat Lockdown

| Service | Status | Data |
|---------|--------|------|
| `app` (PHP-FPM) | Paused | In-memory state dibekukan |
| `db` (PostgreSQL) | Paused | In-memory state dibekukan, WAL aman |
| `redis` | Paused | In-memory state + AOF file aman |

### Durasi Lockdown

`docker pause` menggunakan cgroups freezer — tidak ada timeout. Container tetap paused tanpa batas waktu sampai `docker unpause` atau `docker stop/start` dijalankan secara eksplisit.

Jika VPS di-reboot saat lockdown aktif, container yang di-pause akan **restart** (karena `restart: unless-stopped`) dan kembali ke kondisi running. File trigger `lockdown/.lockdown` tetap ada di host filesystem sehingga Nginx masih menampilkan maintenance page — tetapi container backend sudah running. Jalankan `bash unlock.sh` untuk menghapus file trigger dan kembali normal.
