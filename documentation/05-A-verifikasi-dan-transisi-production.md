# 05-A - Verifikasi Staging & Transisi ke Production

> **Sistem**: Medication Error Reporting (MER) - Rumah Sakit  
> **Konteks**: Eksekusi Tahap Akhir dari Infrastruktur Cloud & Monitoring (Berdasarkan Dokumen 05)  
> **Tujuan**: Validasi hasil Staging dan proses rilis sistem *observability* (Sentry & Logging) ke Production VPS dengan Arsitektur *Branch-Based Deployment*.

---

## 1. Arsitektur Host: Kenapa Staging Tidak "Dibersihkan"?

Penting untuk dipahami bahwa arsitektur VPS Anda (`srv1474250`) meng-host lingkungan `staging` dan `production` di **satu mesin fisik yang sama**, namun berjalan pada folder dan container Docker yang **terisolasi total**.

1. **Komponen Tingkat Host (TIDAK PERLU dibersihkan):**
   * Infrastruktur Monitoring (Grafana, Loki, Promtail, Netdata, Uptime Kuma) serta **Skrip Backup Harian (Cronjob)** berjalan di luar aplikasi, pada level *root* VPS. Komponen ini **sudah Production-Ready** dan memonitor seluruh VPS Anda secara global.
2. **Komponen Tingkat Aplikasi (PERLU di-merge ke Production):**
   * Konfigurasi Sentry di Laravel (`config/sentry.php`), Channel Log Audit Medis di (`config/logging.php`), dan *Medical Audit Observer* adalah pembaruan kode kustom. Ini harus dipindahkan dari branch `staging` atau pengerjaan ke branch `production`.

---

## 2. Uji Coba Kinerja di Staging (Verifikasi)

Sebelum me-merger kode ke *production*, pastikan semua berjalan lancar di *staging*:

### A. Uji Coba Audit Log Medis
1. Buka website **Staging** Anda.
2. Lakukan manipulasi data (Tambah/Ubah data Insiden).
3. Cek di terminal VPS (sebagai `mer_ops`):
   ```bash
   cat /var/www/mer-system/staging/storage/logs/medical-audit.log
   ```
   *Ekspektasi: Muncul format JSON aktivitas yang menyertakan NIK pelaku dan perubahan data terkait rekam medis.*

### B. Uji Coba Sentry (Sensor Layanan Publik)
1. Paksa error pada web staging Anda (e.g. mengakses *route* asal).
2. Lihat detail payload tersebut di **Sentry Dashboard** (sentry.io).
3. Pastikan tidak ada data sensitif rumah sakit (PHI) yang sengaja terlempar, karena fitur pii telah kita blokir (`'send_default_pii' => false` pada `config/sentry.php`).

---

## 3. Langkah Implementasi ke Lingkungan Production

Aplikasi *Production* memiliki standar Docker Image yang lebih ketat, di mana *Composer* tidak lagi disertakan ke dalam *runtime container* *live* PHP demi keamanan dan minimalisasi ukuran (*Multi-stage Build*). Eksekusi perubahan harus dilakukan dengan teknik **Re-build**.

### Langkah 1: Sinkronisasi Git Branch (Dilakukan di Lokal / GitHub)
Gunakan metode *pull request* atau eksekusi *merge* via terminal lokal Anda untuk menyesuaikan tata letak folder Anda (di mana branch `staging` khusus untuk folder staging, dan `production` untuk folder production).

```bash
# 1. Merge fitur ke staging, lalu upload (Catatan: beri kutip ganda jika branch memiliki karakter &)
git checkout staging
git pull origin staging
git merge "prod/monitoring&backup"
git push origin staging

# 2. Merge staging ke production, lalu upload
git checkout production
git pull origin production
git merge staging
git push origin production
```

> **🔥 PENTING (Catatan Sentry Terkait `scrub_fields` Error):**
> Pada `config/sentry.php`, parameter array eksplisit `scrub_fields` telah dihapus karena *package* versi terbaru `sentry/sentry-laravel` tidak lagi mendukungnya di *root tag* sentry config, yang dapat memicu *fatal error*: `syntax error unexpected token ","` atau komplain _discover loader_ saat proses dump-autoload. Standar privasi murni di-handle melalui *flag* `'send_default_pii' => false;`.

### Langkah 2: Tarik Kode & Build Ulang di VPS Production
Jalankan langkah-langkah *copy-paste* presisi tinggi ini di dalam Terminal VPS Anda (*user* `mer_ops`):

```bash
# 1. Pindah ke root directory production
cd /var/www/mer-system/production

# 2. Pastikan file .env sudah dipasangi variabel DSN Sentry
# Jalankan ini HANYA JIKA Anda belum pernah memasukkannya
echo 'SENTRY_LARAVEL_DSN="https://xxxx@o12345.ingest.sentry.io/xxx"' >> /var/www/mer-system/production/.env

# 3. Ambil kode terbaru yang baru saja di-merge
git checkout production
git pull origin production

# 4. Hentikan container production sejenak (untuk flush jaringan & port)
# CATATAN: Wajib menggunakan argumen `--env-file .env` karena posisi docker-compose.yml 
# berada di sub-folder, namun konteks build dan env membutuhkan variabel dari root direktori!
docker compose -f deployment/production/docker-compose.yml --env-file .env down

# 5. RE-BUILD image aplikasi. 
# Tahap ini akan secara otomatis men-download package Sentry (composer install)
# di dalam kontainer re-build tanpa error karena mengisolir kode terkini & syntax yang benar.
docker compose -f deployment/production/docker-compose.yml --env-file .env build --no-cache app

# 6. Nyalakan kembali seluruh resource aplikasi production
docker compose -f deployment/production/docker-compose.yml --env-file .env up -d

# 7. Berikan waktu toleransi beberapa detik agar PostgreSQL & Redis (dependencies) terhubung penuh
sleep 5

# 8. Reset serta Refresh state konfigurasi Laravel!
docker compose -f deployment/production/docker-compose.yml --env-file .env exec app php artisan config:clear
docker compose -f deployment/production/docker-compose.yml --env-file .env exec app php artisan config:cache
docker compose -f deployment/production/docker-compose.yml --env-file .env exec app php artisan queue:restart
```

### Langkah 3: Verifikasi Tahap Akhir (The Sentry Test)

Terakhir, lakukan uji koneksi Sentry di dalam production *container* secara langsung:

```bash
docker compose -f deployment/production/docker-compose.yml --env-file .env exec app php artisan sentry:test
```

*Ekspektasi: Muncul teks `[Sentry] Event sent with ID: <random-hash>`. Ini menandakan Sistem Informasi Rumah Sakit Anda sudah beroperasi penuh di lini terdepan dengan observabilitas standar.*

---

## 4. Opsional: Pembersihan Data Uji Coba Staging

Sebagai *best practice*, jika Anda telah mensimulasikan pemulihan *database* dengan mendongkrak masuk data tiruan (seperti praktik tes instruksi *Restore* dari backup pada Dokumen 05), Anda bisa menormalkan kembali ruang lingkup *staging* ini.

```bash
# Masuk ke folder aplikasi Staging
cd /var/www/mer-system/staging

# Wipe clean database dan inject ulang dummy seeder standar
docker compose exec app php artisan migrate:fresh --seed

# Buang isi logs agar rapih 
truncate -s 0 /var/www/mer-system/staging/storage/logs/laravel.log
truncate -s 0 /var/www/mer-system/staging/storage/logs/medical-audit.log
```
