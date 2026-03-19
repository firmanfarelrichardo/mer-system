# 06 — Verifikasi Staging & Transisi ke Production

> **Sistem**: Medication Error Reporting (MER) — Rumah Sakit  
> **Konteks**: Eksekusi Tahap Akhir dari Infrastruktur Cloud & Monitoring  
> **Tujuan**: Validasi hasil Staging dan strategi rilis (deployment) fitur observabilitas ke Production VPS.

---

## 1. Konsep Penting: Apakah Staging Perlu "Dibersihkan"?

Sebelum kita memindahkan semua yang sudah dikerjakan di Tahap 05 ke *Production*, penting untuk memahami arsitektur VPS Anda saat ini: **VPS Anda (srv1474250) meng-host baik Staging maupun Production di satu mesin yang sama.**

Oleh karena itu:
1. **Bagian yang TIDAK PERLU dihapus/dibersihkan:**
   * **Infrastruktur Monitoring** (Grafana, Loki, Promtail, Netdata, Uptime Kuma) berjalan di level **Server Host**, bukan level aplikasi. Mereka sudah terinstal secara global dan saat ini sudah memonitor seluruh aktivitas CPU, RAM, serta container yang ada di VPS (termasuk Production).
   * **Skrip Backup Database** (`/opt/mer-system/scripts/backup-database.sh`) berjalan di level root VPS dan (berdasarkan pengujian kita sebelumnya) sudah menargetkan container `mer-db-prod`. Ini sudah dalam status *Production Ready*.
2. **Bagian yang HANYA PERLU digabungkan (Code Merge):**
   * Konfigurasi Sentry (`config/sentry.php`), Log Audit Medis (`config/logging.php`), dan *Medical Audit Observer* adalah perubahan tingkat *kode aplikasi Laravel*. Ini harus dipindahkan dari branch staging ke *production*.
3. **Bagian yang BOLEH dibersihkan (Opsional - Pembersihan Data Staging):**
   * Jika Anda ingin database staging kembali bersih setelah di-restore dengan data testing production tadi, Anda bisa menjalankan *migrate fresh* di folder staging.

---

## 2. Uji Coba Kinerja di Staging (Verifikasi Dokumen 05)

Sebelum merilis kodingan ke production, mari kita validasi fungsionalitas aplikasinya di environment staging. 

### A. Uji Coba Audit Log Medis
1. Buka website **Staging** Anda di browser.
2. Login sebagai pengguna/admin, dan lakukan aksi **Tambah Data / Ubah Data Insiden (Medication Error)**.
3. Cek di terminal VPS apakah log terbuat dengan benar.
   ```bash
   cat /var/www/mer-system/staging/storage/logs/medical-audit.log
   ```
   *Ekspektasi: Terdapat baris JSON yang mencatat aksi, NIK pengguna, dan ID insiden.*

### B. Uji Coba Sentry (Error Tracking & Sensor PHI)
1. Coba salahkan sebuah URL di web staging atau paksa sebuah exception (misal mengakses rute yang belum dibuat secara internal server error).
2. Buka **Sentry Dashboard** (sentry.io).
3. Pastikan *Error* tertangkap.
4. Buka detail error tersebut, dan periksa bagian `Payload` atau `Request Data`. Pastikan field-field medis seperti `nik`, `no_rm`, `diagnosis` statusnya berubah menjadi keterangan `[Filtered]` (ini membuktikan sensor jalan).

### C. Uji Coba Uptime Kuma & Loki
1. Buka Grafana via SSH tunnel (`http://localhost:3000`). Buka tab *Explore*, masukkan query `{service="app"}` untuk memastikan log Laravel staging juga masuk.
2. Buka Uptime Kuma (`http://localhost:3001`). Pastikan panel hijau memonitor `MER Production` dan `MER Staging`.

---

## 3. Langkah Implementasi Kode ke Production (The Migration)

Setelah pengujian poin 2 berhasil dengan mulus, saatnya kita mengaktifkan sistem keamanan dan observability tersebut ke **Aplikasi Production**.

### Langkah 1: Push & Merge Kode
Pastikan semua konfigurasi (Sentry handler, Medical Audit service) di branch pengerjaan saat ini (`prod/monitoring&backup`) sudah di-*commit*.

**Lakukan di Komputer Lokal / VPS:**
```bash
cd /var/www/mer-system/staging
git add .
git commit -m "feat: setup sentry, medical audit logging, and observability"
git push origin prod/monitoring&backup

# (Opsional) Melalui GitHub UI atau via Git:
# Lakukan Pull Request & Merge branch prod/monitoring&backup ke branch 'main'
```

### Langkah 2: Tarik Pembaruan ke Direktori Production
Sekarang, kita perbarui kode di folder production yang aktif (live).

**Lakukan di Terminal VPS:**
```bash
cd /var/www/mer-system/production
git checkout main
git pull origin main
```

### Langkah 3: Install Package Sentry di Production
Karena kita menambahkan pustaka Sentry (`sentry/sentry-laravel`) pada `composer.json`, kita harus menginstalnya di production.

```bash
docker compose exec app composer install --no-dev --optimize-autoloader
```

### Langkah 4: Sesuaikan `.env` Production
Tambahkan DSN (Data Source Name) milik Sentry ke dalam konfigurasi `.env` production.

```bash
# Buka file .env
sudo nano /var/www/mer-system/production/.env
```
Tambahkan baris berikut di bagian mana saja (idealnya dekat bagian logging):
```ini
# (Sesuaikan URL DSN ini dengan DSN Sentry Project Production Anda jika dibedakan)
SENTRY_LARAVEL_DSN="https://contoh-hash@sentry.io/project-id"

# Pastikan aplikasi berjalan di mode Production
APP_ENV=production
APP_DEBUG=false
```

### Langkah 5: Bersihkan Cache dan Restart Worker
Agar Laravel membaca perubahan obsever, logger, dan environment secara bersih:

```bash
# Membersihkan semua cache Laravel
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache

# Restart worker queue (penting jika ada Job terkait log/sentry yang mengantre)
docker compose exec app php artisan queue:restart
```

### Langkah 6: Test Koneksi Sentry Production
Verifikasi bahwa server production mampu mengirim status crash ke Sentry.

```bash
docker compose exec app php artisan sentry:test
```
*Ekspektasi: Muncul log "[Sentry] Event sent with ID: ...." dan error masuk ke Dashboard Sentry Anda.*

---

## 4. Pembersihan Staging (Optional Cleanup)

Jika Anda ingin merapikan folder staging setelah dipakai "rusak-rusakan" selama fase simulasi (misalnya database berantakan karena restore uji coba tadi), lakukan langkah ini:

```bash
cd /var/www/mer-system/staging

# Reset database staging agar kosong dan isi dengan seeder dummy bawaan aplikasi
docker compose exec app php artisan migrate:fresh --seed

# Kosongkan file log simulasi trial
truncate -s 0 /var/www/mer-system/staging/storage/logs/laravel.log
truncate -s 0 /var/www/mer-system/staging/storage/logs/medical-audit.log
```

---
> **Kesimpulan:** Arsitektur Infrastruktur (Grafana, Netdata, Backup DB) **tidak perlu digeser letaknya**, ia sudah berstatus final dan melindungi Host secara umum. Anda hanya murni melakukan migrasi pembaruan kodingan Laravel ke folder production Anda.
