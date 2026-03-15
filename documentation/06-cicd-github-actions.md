# 06 — CI/CD Pipeline dengan GitHub Actions (Zero-Trust Deployment)

> **Sistem**: Medication Error Reporting (MER) — Rumah Sakit  
> **Klasifikasi**: HIGH-RISK (Data Medis Sensitif / PHI)  
> **Spesifikasi VPS**: Ubuntu 24.04 LTS — 2 Core CPU, 8GB RAM, 100GB SSD (Hostinger)  
> **Stack**: Laravel 12, PostgreSQL 16, Redis 7, Docker, Nginx, Cloudflare  
> **Prasyarat**: Dokumen [01](./01-arsitektur-dan-hardening-os.md) s/d [05](./05-monitoring-dan-backup-strategy.md) sudah dilaksanakan  
> **Tanggal**: Maret 2026

---

## Daftar Isi

1. [Arsitektur CI/CD Zero-Trust](#1-arsitektur-cicd-zero-trust)
2. [Perbedaan Deployment Manual vs CI/CD Pipeline](#2-perbedaan-deployment-manual-vs-cicd-pipeline)
3. [Diagram Alur Pipeline](#3-diagram-alur-pipeline)
4. [Persiapan — Konfigurasi GitHub Secrets](#4-persiapan--konfigurasi-github-secrets)
5. [Persiapan — Konfigurasi GitHub Environments](#5-persiapan--konfigurasi-github-environments)
6. [Persiapan — Struktur Direktori Workflow](#6-persiapan--struktur-direktori-workflow)
7. [Pipeline Staging (staging.yml)](#7-pipeline-staging-stagingyml)
8. [Pipeline Production (production.yml)](#8-pipeline-production-productionyml)
9. [Penjelasan Setiap Tahap Pipeline](#9-penjelasan-setiap-tahap-pipeline)
10. [Skrip Remote Deployment (deploy.sh)](#10-skrip-remote-deployment-deploysh)
11. [Rollback Strategy](#11-rollback-strategy)
12. [Verifikasi](#12-verifikasi)

---

## 1. Arsitektur CI/CD Zero-Trust

### Versi Sederhana (Analogi Pemula)

Bayangkan proses deployment seperti **mengirim obat dari pabrik farmasi ke rumah sakit**:

| Metode Lama (Manual) | Metode Baru (CI/CD) |
|-----------------------|---------------------|
| Kurir (developer) membawa obat sendiri dari pabrik, naik motor ke rumah sakit, dan meletakkan sendiri ke rak | Pabrik memiliki **conveyor belt otomatis** yang memeriksa kualitas obat (CI: testing), mengemas dengan aman (build), lalu mengirim melalui kurir terpercaya (CD: SCP) ke rumah sakit |
| Jika obat salah, kurir harus balik ke pabrik | Jika obat gagal uji kualitas, conveyor belt otomatis berhenti sebelum dikirim |
| Kurir punya kunci semua gudang pabrik | Kurir hanya diberi **surat jalan satu paket** — tidak punya akses ke gudang pabrik (Zero-Trust) |

### Versi Formal (Standar Industri)

Arsitektur CI/CD ini menerapkan model **Zero-Trust Deployment** dengan prinsip-prinsip:

1. **VPS sebagai Receiving-Only Endpoint** — VPS tidak memiliki kredensial Git (deploy key, PAT, atau SSH key ke GitHub). VPS hanya *menerima* file dari GitHub Runner melalui SCP dan *mengeksekusi* perintah Docker yang dikirim melalui SSH. Jika VPS dikompromikan, attacker tidak bisa mengakses source code repository manapun.

2. **GitHub Runner sebagai Build Environment** — Semua operasi berat (dependency installation, testing, vulnerability scanning, asset compilation) dijalankan di GitHub-hosted runner (`ubuntu-latest`) yang memiliki resource jauh lebih besar dari VPS. Setelah selesai, hanya *hasil jadi* yang dikirim ke VPS.

3. **Secrets Injection at Runtime** — Kredensial SSH (private key, host, port) disimpan di GitHub Secrets (encrypted at rest dengan libsodium sealed box) dan hanya di-inject ke runner environment saat pipeline berjalan. Tidak pernah tersimpan di kode, log, atau artifact.

4. **Manual Approval Gate** — Deployment ke production memerlukan persetujuan eksplisit dari reviewer melalui GitHub Environments protection rules, mencegah push langsung ke production tanpa human verification.

---

## 2. Perbedaan Deployment Manual vs CI/CD Pipeline

### Mengapa Meninggalkan Deployment Manual?

| Aspek | Manual (SSH + git pull) | CI/CD Pipeline |
|-------|------------------------|----------------|
| **Kecepatan** | 5-15 menit per deployment | 3-5 menit (otomatis) |
| **Konsistensi** | Rawan human error (lupa migrate, lupa cache:clear) | Setiap langkah terdefinisi dan dieksekusi identik |
| **Keamanan** | VPS menyimpan deploy key (read-only ke repo) | VPS TIDAK menyimpan kredensial Git apa pun (Zero-Trust) |
| **Audit Trail** | Tidak ada — siapa deploy kapan tidak tercatat | Setiap deployment tercatat di GitHub Actions log dengan SHA commit, waktu, dan status |
| **Rollback** | Manual: `git checkout <commit>` | Otomatis: re-run workflow pada commit sebelumnya |
| **Resource VPS** | `composer install` dan `npm run build` dijalankan di VPS (CPU spike) | Build dijalankan di GitHub Runner — VPS hanya restart Docker |
| **Testing** | Tidak ada testing otomatis sebelum deploy | Automated test suite wajib lulus sebelum deploy |
| **Approval** | Tidak ada mekanisme approval | Approval wajib dari reviewer sebelum production deploy |

### Implikasi terhadap Dokumen Sebelumnya

> [!NOTE]
> Dengan mengadopsi CI/CD Pipeline, **Section 3 (SSH Deploy Key)** di [Dokumen 03](./03-docker-production-staging.md) menjadi **opsional**. Deploy Key hanya diperlukan jika Anda ingin mempertahankan kemampuan `git pull` manual sebagai *fallback* darurat. Pipeline CI/CD menggunakan mekanisme SCP untuk mentransfer kode, bukan Git.

---

## 3. Diagram Alur Pipeline

```
DEVELOPER
    |
    | git push (staging branch)
    v
+------------------------------------------------------------------+
| GITHUB ACTIONS RUNNER (ubuntu-latest)                             |
| Resource: 4 vCPU, 16GB RAM, 14GB SSD                             |
|                                                                   |
|  +--- CI Stage (Continuous Integration) ----------------------+  |
|  |                                                             |  |
|  |  [1] Checkout Code                                          |  |
|  |  [2] Setup PHP 8.2 + Extensions                             |  |
|  |  [3] Composer Install (--no-dev --optimize-autoloader)       |  |
|  |  [4] Setup Node.js 20 + npm ci                              |  |
|  |  [5] npm run build (Vite asset compilation)                  |  |
|  |  [6] php artisan test (PHPUnit/Pest test suite)              |  |
|  |  [7] Composer Audit (vulnerability scan dependencies)        |  |
|  |                                                             |  |
|  |  Jika GAGAL -> Pipeline BERHENTI, deployment DIBATALKAN      |  |
|  +-------------------------------------------------------------+  |
|                          |                                        |
|                          | CI LULUS                                |
|                          v                                        |
|  +--- CD Stage (Continuous Deployment) -----------------------+  |
|  |                                                             |  |
|  |  [8] SCP: Transfer kode ke VPS (/var/www/mer-system/...)    |  |
|  |  [9] SSH: Eksekusi deploy.sh di VPS                         |  |
|  |      - chown/chmod storage/ & bootstrap/cache/              |  |
|  |      - docker compose build app                             |  |
|  |      - docker compose up -d                                 |  |
|  |      - docker exec: migrate, cache, optimize                |  |
|  |      - Health check verification                            |  |
|  |                                                             |  |
|  +-------------------------------------------------------------+  |
+------------------------------------------------------------------+
                           |
                           | SCP (Encrypted SSH Transfer)
                           v
+------------------------------------------------------------------+
| VPS HOSTINGER (Ubuntu 24.04)                                      |
| Port SSH: 49152 | User: mer_ops                                  |
|                                                                   |
|  /var/www/mer-system/staging/    <- Staging deployment target     |
|  /var/www/mer-system/production/ <- Production deployment target  |
|                                                                   |
|  [Docker] docker compose up -d --build                            |
|  [Docker] Nginx -> PHP-FPM -> PostgreSQL -> Redis                 |
+------------------------------------------------------------------+
```

### Alur Production (dengan Approval Gate)

```
DEVELOPER
    |
    | Pull Request: staging -> main
    v
+------------------------------------------------------------------+
| GITHUB                                                            |
|                                                                   |
|  [1] CI Stage (sama seperti staging)                              |
|  [2] Menunggu Approval dari Reviewer                              |
|      +----------------------------------------------------+      |
|      | GitHub Environment: "production"                    |      |
|      | Required Reviewers: devops-lead, tech-lead          |      |
|      | Reviewer menerima notifikasi email/GitHub            |      |
|      | Reviewer memeriksa:                                  |      |
|      |   - Test results                                    |      |
|      |   - Code changes (diff)                             |      |
|      |   - Staging verification                            |      |
|      | Reviewer klik "Approve" atau "Reject"                |      |
|      +----------------------------------------------------+      |
|  [3] Jika APPROVED -> CD Stage (deploy ke production)             |
|  [4] Jika REJECTED -> Pipeline BERHENTI                           |
+------------------------------------------------------------------+
```

---

## 4. Persiapan — Konfigurasi GitHub Secrets

### Mengapa GitHub Secrets?

**Versi Formal:** GitHub Secrets menggunakan enkripsi *libsodium sealed box* (Curve25519 + XSalsa20-Poly1305) untuk menyimpan nilai sensitif. Secret hanya didekripsi saat runtime di dalam GitHub Runner yang terisolasi. Nilai secret tidak pernah muncul di log (otomatis di-mask dengan `***`), tidak bisa dibaca melalui API setelah di-set, dan tidak bisa diakses oleh fork repositories. Ini memenuhi prinsip *secrets-never-at-rest-in-plaintext*.

**Versi Sederhana:** GitHub Secrets seperti brankas digital. Anda memasukkan kunci rumah sakit (SSH key) ke dalam brankas. Saat kurir (GitHub Runner) perlu mengirim obat, brankas terbuka otomatis hanya untuk kurir itu di saat itu saja. Setelah selesai, brankas tertutup lagi. Tidak ada yang bisa membuka brankas dari luar — termasuk developer yang memasukkan kuncinya.

### Eksekusi — Tambahkan Secrets di GitHub

```
Navigasi: github.com > Repository mer-system > Settings > Secrets and variables > Actions > New repository secret
```

Tambahkan 4 secret berikut satu per satu:

#### Secret 1: `VPS_SSH_HOST`

| Field | Nilai |
|-------|-------|
| **Name** | `VPS_SSH_HOST` |
| **Secret** | `<IP_VPS_ANDA>` *(contoh: `154.41.xx.xx`)* |

**Alasan:** IP address VPS Hostinger. Dipisahkan menjadi secret agar IP server tidak terekspos di file workflow YAML yang tersimpan di repository (bisa dibaca oleh siapapun dengan akses baca ke repo).

#### Secret 2: `VPS_SSH_PORT`

| Field | Nilai |
|-------|-------|
| **Name** | `VPS_SSH_PORT` |
| **Secret** | `49152` |

**Alasan:** Port SSH custom yang dikonfigurasi di [Dokumen 01 Section 7](./01-arsitektur-dan-hardening-os.md#7-hardening-daemon-ssh). Menyimpan port di secret mencegah attacker mengetahui port SSH dari membaca file workflow.

#### Secret 3: `VPS_SSH_USER`

| Field | Nilai |
|-------|-------|
| **Name** | `VPS_SSH_USER` |
| **Secret** | `mer_ops` |

**Alasan:** Username operasional non-root yang dibuat di [Dokumen 01 Section 5](./01-arsitektur-dan-hardening-os.md#5-pembuatan-user-operasional-non-root). Hanya user ini yang memiliki akses SSH.

#### Secret 4: `VPS_SSH_KEY`

| Field | Nilai |
|-------|-------|
| **Name** | `VPS_SSH_KEY` |
| **Secret** | *(isi lengkap private key Ed25519)* |

**Ini adalah secret paling kritis.** Nilai yang dimasukkan adalah **seluruh isi** file private key Ed25519, termasuk header dan footer.

### Eksekusi — Mendapatkan Isi Private Key

> **Konteks Eksekusi:** `Terminal Komputer Lokal`

```bash
# Tampilkan isi LENGKAP private key.
# SALIN SELURUH output — dari -----BEGIN hingga -----END termasuk baris kosong.
cat ~/.ssh/mer_ops_ed25519
```

Output yang harus di-copy **SELURUHNYA** ke GitHub Secret:

```
-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAA... (banyak baris)
...
-----END OPENSSH PRIVATE KEY-----
```

> [!CAUTION]
> **Paste SELURUH output** termasuk baris `-----BEGIN OPENSSH PRIVATE KEY-----` dan `-----END OPENSSH PRIVATE KEY-----`. Jika ada baris yang terpotong atau spasi yang hilang, SSH authentication akan gagal dengan error `Load key: invalid format`.

### Verifikasi Secrets

Setelah keempat secret ditambahkan, halaman Secrets di GitHub harus menampilkan:

| Name | Updated |
|------|---------|
| `VPS_SSH_HOST` | *just now* |
| `VPS_SSH_KEY` | *just now* |
| `VPS_SSH_PORT` | *just now* |
| `VPS_SSH_USER` | *just now* |

> [!IMPORTANT]
> **Anda tidak bisa melihat kembali nilai secret** setelah disimpan. Jika perlu memperbarui, klik "Update" dan paste nilai baru. Tidak ada opsi "Show" atau "Copy".

---

## 5. Persiapan — Konfigurasi GitHub Environments

### Apa Itu GitHub Environments?

**Versi Formal:** GitHub Environments adalah mekanisme *deployment protection rules* yang memungkinkan organisasi menerapkan *gate* pada proses deployment. Environment bisa dikonfigurasi dengan: required reviewers (approval manual), wait timer (delay sebelum deploy), dan branch restrictions (hanya branch tertentu yang boleh deploy). Ini menerapkan prinsip *Separation of Duties* — developer yang menulis kode tidak bisa langsung mendeploy ke production tanpa persetujuan pihak lain.

**Versi Sederhana:** Environment seperti **pintu keamanan berlapis** di rumah sakit. Untuk memasukkan obat baru ke ruang farmasi utama (production), perlu tanda tangan persetujuan dari kepala farmasi (reviewer). Tanpa tanda tangan, pintu tidak bisa dibuka — tidak peduli semodern apapun troli pengantarnya (pipeline).

### Eksekusi — Buat Environment "staging"

```
Navigasi: github.com > Repository mer-system > Settings > Environments > New environment
```

| Field | Nilai |
|-------|-------|
| **Environment name** | `staging` |

Klik **Configure environment**, lalu atur:

| Setting | Nilai | Alasan |
|---------|-------|--------|
| **Required reviewers** | *(tidak dicentang)* | Staging tidak perlu approval — tujuannya adalah testing cepat |
| **Deployment branches** | **Selected branches** > tambahkan `staging` | Hanya branch `staging` yang bisa deploy ke environment ini |

### Eksekusi — Buat Environment "production"

```
Navigasi: github.com > Repository mer-system > Settings > Environments > New environment
```

| Field | Nilai |
|-------|-------|
| **Environment name** | `production` |

Klik **Configure environment**, lalu atur:

| Setting | Nilai | Alasan |
|---------|-------|--------|
| **Required reviewers** | **Centang**, tambahkan 1-2 reviewer (misal: `devops-lead`, `tech-lead`) | Setiap deployment production WAJIB disetujui oleh minimal 1 reviewer |
| **Prevent self-review** | **Centang** | Developer yang memicu pipeline tidak bisa meng-approve sendiri |
| **Wait timer** | `0` (minit) | Tidak ada delay tambahan setelah approval (langsung deploy) |
| **Deployment branches** | **Selected branches** > tambahkan `main` | Hanya branch `main` yang bisa deploy ke environment production |

### Bagaimana Approval Bekerja?

```
1. Developer push ke staging -> staging pipeline berjalan otomatis
2. Developer buka Pull Request: staging -> main
3. PR di-review dan di-merge ke main
4. Production pipeline MULAI berjalan...
5. CI Stage selesai (testing lulus)
6. Pipeline BERHENTI di CD Stage (menunggu approval)
7. Reviewer menerima notifikasi (email + GitHub notification)
8. Reviewer membuka GitHub Actions > klik "Review deployments"
9. Reviewer memeriksa:
   - Test results (semua lulus?)
   - Code diff (perubahan masuk akal?)
   - Staging sudah di-test manual?
10. Reviewer klik "Approve" atau "Reject"
11. Jika Approved -> CD Stage lanjut (deploy ke production)
12. Jika Rejected -> Pipeline berhenti, developer diperi notifikasi
```

---

## 6. Persiapan — Struktur Direktori Workflow

### Eksekusi — Buat Struktur File

> **Konteks Eksekusi:** `Terminal Komputer Lokal` (di dalam repository mer-system)

```bash
# Buat direktori untuk workflow GitHub Actions.
# Direktori .github/workflows/ adalah konvensi wajib GitHub —
# file YAML di luar direktori ini tidak akan dikenali.
mkdir -p .github/workflows

# Buat file workflow
touch .github/workflows/staging.yml
touch .github/workflows/production.yml
```

### Peta Direktori

```
mer-system/                        (repository root)
|-- .github/
|   `-- workflows/
|       |-- staging.yml            # Pipeline untuk branch staging
|       `-- production.yml         # Pipeline untuk branch main (production)
|
|-- deployment/
|   |-- production/
|   |   |-- docker-compose.yml
|   |   |-- Dockerfile
|   |   |-- docker-entrypoint.sh
|   |   |-- nginx.conf
|   |   |-- php.ini
|   |   `-- supervisord.conf
|   `-- staging/
|       |-- docker-compose.yml
|       `-- ...
|
|-- app/                           # Kode Laravel
|-- .env.example
|-- composer.json
|-- package.json
`-- ...
```

---

## 7. Pipeline Staging (staging.yml)

### File Konfigurasi Lengkap

> **Lokasi file:** `.github/workflows/staging.yml`

```yaml
# ===========================================
# MER System - Staging CI/CD Pipeline
# ===========================================
# Pipeline ini dipicu saat ada push ke branch 'staging'.
#
# ALUR:
# 1. CI: Checkout -> PHP Setup -> Composer Install -> Build Assets -> Test -> Audit
# 2. CD: SCP kode ke VPS -> SSH eksekusi deploy di VPS
#
# PRINSIP ZERO-TRUST:
# - Semua kredensial disimpan di GitHub Secrets
# - VPS tidak menyimpan kredensial Git
# - Build dan testing dijalankan di GitHub Runner (bukan VPS)
# - VPS hanya menerima kode jadi dan restart Docker
# ===========================================

name: Staging Pipeline

on:
  push:
    branches:
      - staging

# Membatalkan pipeline sebelumnya yang masih berjalan untuk branch yang sama.
# Mencegah race condition jika developer push berkali-kali secara cepat.
concurrency:
  group: staging-${{ github.ref }}
  cancel-in-progress: true

jobs:
  # ===========================================
  # JOB 1: CI (Continuous Integration)
  # ===========================================
  # Menjalankan semua testing dan scanning di GitHub Runner.
  # Jika job ini gagal, CD tidak akan dieksekusi.
  # ===========================================
  ci:
    name: CI - Test & Security Scan
    runs-on: ubuntu-latest
    environment: staging

    steps:
      # -----------------------------------------
      # Step 1: Checkout source code
      # -----------------------------------------
      # Mengunduh kode dari branch yang memicu pipeline.
      # fetch-depth: 0 mengunduh SELURUH history (dibutuhkan
      # jika ada proses yang memerlukan git log/history).
      - name: Checkout Repository
        uses: actions/checkout@v4
        with:
          fetch-depth: 0

      # -----------------------------------------
      # Step 2: Setup PHP 8.2
      # -----------------------------------------
      # Menginstal PHP 8.2 dengan ekstensi yang dibutuhkan Laravel.
      # Runner default hanya memiliki PHP tanpa ekstensi tambahan.
      - name: Setup PHP 8.2
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: >-
            pdo_pgsql,
            pgsql,
            mbstring,
            xml,
            ctype,
            json,
            bcmath,
            tokenizer,
            fileinfo,
            gd,
            zip,
            redis
          coverage: none
          tools: composer:v2

      # -----------------------------------------
      # Step 3: Cache Composer dependencies
      # -----------------------------------------
      # Menyimpan folder vendor/ di cache GitHub agar tidak perlu
      # download ulang semua dependency saat pipeline berikutnya.
      # Menghemat ~30-60 detik per pipeline run.
      - name: Cache Composer Dependencies
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}
          restore-keys: |
            composer-

      # -----------------------------------------
      # Step 4: Install PHP dependencies
      # -----------------------------------------
      # --no-dev         : tidak instal dev dependencies (PHPUnit
      #                    diinstal via require-dev tapi dijalankan
      #                    via --dev di step terpisah jika perlu)
      # --optimize-autoloader : classmap yang dioptimasi
      # --no-interaction : tidak ada prompt interaktif
      #
      # CATATAN: Untuk menjalankan test, kita PERLU dev dependencies.
      # Oleh karena itu kita instal DENGAN dev terlebih dahulu untuk testing,
      # lalu nanti di CD stage kita transfer kode TANPA vendor/ dan
      # biarkan Docker build yang menjalankan composer install --no-dev.
      - name: Install Composer Dependencies
        run: composer install --prefer-dist --no-interaction --no-progress

      # -----------------------------------------
      # Step 5: Setup Node.js 20
      # -----------------------------------------
      - name: Setup Node.js 20
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'

      # -----------------------------------------
      # Step 6: Install & Build Frontend Assets
      # -----------------------------------------
      # npm ci lebih cepat dan deterministic dibanding npm install.
      # Menggunakan lockfile (package-lock.json) untuk exact versions.
      # npm run build mengkompilasi aset Vite (CSS, JS) ke folder
      # public/build/ yang akan disertakan dalam transfer SCP.
      - name: Install Node Dependencies
        run: npm ci --no-audit --no-fund

      - name: Build Frontend Assets (Vite)
        run: npm run build

      # -----------------------------------------
      # Step 7: Prepare Environment for Testing
      # -----------------------------------------
      # Membuat file .env dari .env.example dengan nilai-nilai
      # yang cocok untuk testing di GitHub Runner.
      # SQLite in-memory digunakan sebagai database testing
      # agar tidak perlu provisioning PostgreSQL di runner.
      - name: Prepare Test Environment
        run: |
          cp .env.example .env
          sed -i 's/DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
          sed -i 's|DB_DATABASE=.*|DB_DATABASE=:memory:|' .env
          php artisan key:generate --no-interaction

      # -----------------------------------------
      # Step 8: Run Test Suite
      # -----------------------------------------
      # Menjalankan seluruh test suite (PHPUnit/Pest).
      # Jika ada test yang gagal, step ini mengembalikan
      # exit code non-zero dan pipeline BERHENTI di sini.
      - name: Run Test Suite
        run: php artisan test --no-interaction

      # -----------------------------------------
      # Step 9: Composer Security Audit
      # -----------------------------------------
      # Memeriksa apakah ada dependency PHP yang memiliki
      # CVE (known vulnerability) yang belum di-patch.
      # composer audit mengembalikan exit code non-zero
      # jika ditemukan vulnerability, menghentikan pipeline.
      - name: Composer Security Audit
        run: composer audit --no-interaction

  # ===========================================
  # JOB 2: CD (Continuous Deployment) - Staging
  # ===========================================
  # Hanya berjalan JIKA job CI berhasil (needs: ci).
  # Mentransfer kode ke VPS dan me-restart Docker.
  # ===========================================
  cd:
    name: CD - Deploy to Staging
    runs-on: ubuntu-latest
    needs: ci
    environment: staging

    steps:
      - name: Checkout Repository
        uses: actions/checkout@v4

      # -----------------------------------------
      # Step 1: Build assets di runner (bukan VPS)
      # -----------------------------------------
      # Kita perlu build ulang karena job CD terpisah dari CI
      # (tidak share filesystem). Alternatifnya: gunakan artifacts,
      # tapi build ulang lebih sederhana untuk project ini.
      - name: Setup PHP 8.2
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: >-
            pdo_pgsql,
            pgsql,
            mbstring,
            xml,
            ctype,
            json,
            bcmath,
            tokenizer,
            fileinfo,
            gd,
            zip,
            redis
          coverage: none
          tools: composer:v2

      - name: Setup Node.js 20
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'

      - name: Install Node Dependencies
        run: npm ci --no-audit --no-fund

      - name: Build Frontend Assets (Vite)
        run: npm run build

      # -----------------------------------------
      # Step 2: Transfer kode ke VPS via SCP
      # -----------------------------------------
      # appleboy/scp-action menggunakan SSH untuk mentransfer
      # file secara terenkripsi dari GitHub Runner ke VPS.
      #
      # source: "." = seluruh repository (kecuali yang di-exclude)
      # target: direktori staging di VPS
      # overwrite: true = menimpa file yang sudah ada
      # rm: false = TIDAK menghapus file lama yang tidak ada di source
      #     (mencegah penghapusan .env, storage/app/ uploads, dll)
      #
      # KEAMANAN:
      # - Private key diambil dari GitHub Secrets (terenkripsi)
      # - Koneksi SCP melalui port SSH custom (49152)
      # - File ditransfer melalui tunnel SSH terenkripsi (Ed25519)
      - name: Transfer Code to VPS via SCP
        uses: appleboy/scp-action@v0.1.7
        with:
          host: ${{ secrets.VPS_SSH_HOST }}
          username: ${{ secrets.VPS_SSH_USER }}
          key: ${{ secrets.VPS_SSH_KEY }}
          port: ${{ secrets.VPS_SSH_PORT }}
          source: "."
          target: "/var/www/mer-system/staging"
          overwrite: true
          rm: false

      # -----------------------------------------
      # Step 3: Deploy di VPS via SSH
      # -----------------------------------------
      # appleboy/ssh-action menjalankan perintah shell di VPS
      # melalui koneksi SSH terenkripsi.
      #
      # Tahapan eksekusi (semuanya dijalankan di VPS):
      # 1. Fix permission storage/ dan bootstrap/cache/
      # 2. Rebuild dan restart Docker containers
      # 3. Jalankan migration dan optimasi Laravel
      # 4. Health check untuk verifikasi deployment
      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1.2.0
        with:
          host: ${{ secrets.VPS_SSH_HOST }}
          username: ${{ secrets.VPS_SSH_USER }}
          key: ${{ secrets.VPS_SSH_KEY }}
          port: ${{ secrets.VPS_SSH_PORT }}
          command_timeout: 15m
          script: |
            set -e

            echo "=== MER System: Staging Deployment ==="
            echo "Commit: ${{ github.sha }}"
            echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S %Z')"
            echo ""

            DEPLOY_DIR="/var/www/mer-system/staging"
            COMPOSE_FILE="$DEPLOY_DIR/deployment/staging/docker-compose.yml"

            # --- Step 1: Fix Permissions ---
            # SCP mentransfer file dengan ownership user SSH (mer_ops, UID 1000).
            # PHP-FPM di dalam container berjalan sebagai www-data (UID 33).
            # Tanpa chown ini, PHP-FPM tidak bisa menulis ke storage/ dan
            # bootstrap/cache/, menyebabkan Error 500 (Permission Denied).
            echo "[1/5] Fixing file permissions..."
            sudo chown -R 33:33 "$DEPLOY_DIR/storage/"
            sudo chown -R 33:33 "$DEPLOY_DIR/bootstrap/cache/"
            sudo find "$DEPLOY_DIR/storage/" -type d -exec chmod 775 {} \;
            sudo find "$DEPLOY_DIR/storage/" -type f -exec chmod 664 {} \;
            sudo find "$DEPLOY_DIR/bootstrap/cache/" -type d -exec chmod 775 {} \;
            sudo find "$DEPLOY_DIR/bootstrap/cache/" -type f -exec chmod 664 {} \;
            echo "    Permissions fixed."

            # --- Step 2: Build Docker Image ---
            # Hanya rebuild container app (PHP-FPM + Supervisord).
            # Container db, redis, web (nginx) menggunakan image resmi
            # yang tidak perlu rebuild.
            echo "[2/5] Building Docker image..."
            docker compose -f "$COMPOSE_FILE" build --no-cache app
            echo "    Build complete."

            # --- Step 3: Restart Containers ---
            # --force-recreate memaksa container dibuat ulang dari image baru.
            # --no-deps hanya restart container app, tidak mengganggu db/redis.
            # -d menjalankan di background.
            echo "[3/5] Restarting containers..."
            docker compose -f "$COMPOSE_FILE" up -d --force-recreate --no-deps app
            echo "    Containers restarted."

            # --- Step 4: Run Migrations & Optimize ---
            # Menunggu 10 detik agar container siap sebelum eksekusi artisan.
            echo "[4/5] Running migrations and optimization..."
            sleep 10
            docker compose -f "$COMPOSE_FILE" exec -T app php artisan migrate --force --no-interaction
            docker compose -f "$COMPOSE_FILE" exec -T app php artisan config:cache
            docker compose -f "$COMPOSE_FILE" exec -T app php artisan route:cache
            docker compose -f "$COMPOSE_FILE" exec -T app php artisan view:cache
            echo "    Migrations and optimization complete."

            # --- Step 5: Health Check ---
            echo "[5/5] Running health check..."
            sleep 5
            HTTP_STATUS=$(curl -sf -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/health 2>/dev/null || echo "000")

            if [ "$HTTP_STATUS" = "200" ]; then
                echo "    Health check PASSED (HTTP $HTTP_STATUS)"
                echo ""
                echo "=== Staging Deployment SUCCESS ==="
            else
                echo "    Health check FAILED (HTTP $HTTP_STATUS)"
                echo "    Checking container logs..."
                docker compose -f "$COMPOSE_FILE" logs --tail 30 app
                echo ""
                echo "=== Staging Deployment FAILED ==="
                exit 1
            fi
```

---

## 8. Pipeline Production (production.yml)

### File Konfigurasi Lengkap

> **Lokasi file:** `.github/workflows/production.yml`

```yaml
# ===========================================
# MER System - Production CI/CD Pipeline
# ===========================================
# Pipeline ini dipicu saat ada push ke branch 'main'.
#
# ALUR:
# 1. CI: Testing & Security Scanning (sama seperti staging)
# 2. APPROVAL: Menunggu persetujuan manual dari reviewer
# 3. CD: Deploy ke production HANYA setelah approval diterima
#
# KEAMANAN:
# - Manual approval wajib sebelum deploy ke production
# - Semua kredensial dari GitHub Secrets (Zero-Trust)
# - VPS tidak menyimpan kredensial Git
# ===========================================

name: Production Pipeline

on:
  push:
    branches:
      - main

concurrency:
  group: production-${{ github.ref }}
  cancel-in-progress: false  # JANGAN batalkan deployment production yang sedang berjalan

jobs:
  # ===========================================
  # JOB 1: CI (Continuous Integration)
  # ===========================================
  ci:
    name: CI - Test & Security Scan
    runs-on: ubuntu-latest

    steps:
      - name: Checkout Repository
        uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Setup PHP 8.2
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: >-
            pdo_pgsql,
            pgsql,
            mbstring,
            xml,
            ctype,
            json,
            bcmath,
            tokenizer,
            fileinfo,
            gd,
            zip,
            redis
          coverage: none
          tools: composer:v2

      - name: Cache Composer Dependencies
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}
          restore-keys: |
            composer-

      - name: Install Composer Dependencies
        run: composer install --prefer-dist --no-interaction --no-progress

      - name: Setup Node.js 20
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'

      - name: Install Node Dependencies
        run: npm ci --no-audit --no-fund

      - name: Build Frontend Assets (Vite)
        run: npm run build

      - name: Prepare Test Environment
        run: |
          cp .env.example .env
          sed -i 's/DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
          sed -i 's|DB_DATABASE=.*|DB_DATABASE=:memory:|' .env
          php artisan key:generate --no-interaction

      - name: Run Test Suite
        run: php artisan test --no-interaction

      - name: Composer Security Audit
        run: composer audit --no-interaction

  # ===========================================
  # JOB 2: CD (Continuous Deployment) - Production
  # ===========================================
  # PERBEDAAN KUNCI dari staging pipeline:
  # 1. environment: production -> memicu approval gate
  # 2. cancel-in-progress: false -> deployment tidak boleh dibatalkan
  # 3. Target direktori: /var/www/mer-system/production
  # 4. Port health check: 80 (bukan 8080)
  # 5. docker-compose.yml production (bukan staging)
  # ===========================================
  cd:
    name: CD - Deploy to Production
    runs-on: ubuntu-latest
    needs: ci
    environment: production  # <-- MEMICU APPROVAL GATE

    steps:
      - name: Checkout Repository
        uses: actions/checkout@v4

      - name: Setup PHP 8.2
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: >-
            pdo_pgsql,
            pgsql,
            mbstring,
            xml,
            ctype,
            json,
            bcmath,
            tokenizer,
            fileinfo,
            gd,
            zip,
            redis
          coverage: none
          tools: composer:v2

      - name: Setup Node.js 20
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'

      - name: Install Node Dependencies
        run: npm ci --no-audit --no-fund

      - name: Build Frontend Assets (Vite)
        run: npm run build

      - name: Transfer Code to VPS via SCP
        uses: appleboy/scp-action@v0.1.7
        with:
          host: ${{ secrets.VPS_SSH_HOST }}
          username: ${{ secrets.VPS_SSH_USER }}
          key: ${{ secrets.VPS_SSH_KEY }}
          port: ${{ secrets.VPS_SSH_PORT }}
          source: "."
          target: "/var/www/mer-system/production"
          overwrite: true
          rm: false

      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1.2.0
        with:
          host: ${{ secrets.VPS_SSH_HOST }}
          username: ${{ secrets.VPS_SSH_USER }}
          key: ${{ secrets.VPS_SSH_KEY }}
          port: ${{ secrets.VPS_SSH_PORT }}
          command_timeout: 15m
          script: |
            set -e

            echo "=== MER System: PRODUCTION Deployment ==="
            echo "Commit: ${{ github.sha }}"
            echo "Triggered by: ${{ github.actor }}"
            echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S %Z')"
            echo ""

            DEPLOY_DIR="/var/www/mer-system/production"
            COMPOSE_FILE="$DEPLOY_DIR/deployment/production/docker-compose.yml"

            # --- Step 1: Pre-Deployment Backup ---
            # Membuat snapshot database sebelum deployment sebagai safety net.
            # Jika deployment gagal, database bisa di-restore dari backup ini.
            echo "[1/6] Creating pre-deployment database backup..."
            BACKUP_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
            docker exec mer-db-prod pg_dump \
                -U mer_dbadmin \
                -d mer_production \
                --no-owner \
                --no-privileges \
                --clean \
                --if-exists \
                --format=plain \
                | gzip > "/var/www/mer-system/shared/backups/pre_deploy_${BACKUP_TIMESTAMP}.sql.gz" 2>/dev/null || echo "    WARNING: Backup skipped (database mungkin belum ada)"
            echo "    Pre-deployment backup complete."

            # --- Step 2: Fix Permissions ---
            echo "[2/6] Fixing file permissions..."
            sudo chown -R 33:33 "$DEPLOY_DIR/storage/"
            sudo chown -R 33:33 "$DEPLOY_DIR/bootstrap/cache/"
            sudo find "$DEPLOY_DIR/storage/" -type d -exec chmod 775 {} \;
            sudo find "$DEPLOY_DIR/storage/" -type f -exec chmod 664 {} \;
            sudo find "$DEPLOY_DIR/bootstrap/cache/" -type d -exec chmod 775 {} \;
            sudo find "$DEPLOY_DIR/bootstrap/cache/" -type f -exec chmod 664 {} \;
            echo "    Permissions fixed."

            # --- Step 3: Build Docker Image ---
            echo "[3/6] Building Docker image..."
            docker compose -f "$COMPOSE_FILE" build --no-cache app
            echo "    Build complete."

            # --- Step 4: Rolling Restart (Minimal Downtime) ---
            # Strategi: rebuild app container saja, database dan Redis tetap berjalan.
            # Downtime hanya terjadi selama Nginx mendeteksi perubahan upstream (~2-5 detik).
            echo "[4/6] Performing rolling restart..."
            docker compose -f "$COMPOSE_FILE" up -d --force-recreate --no-deps app
            echo "    Rolling restart complete."

            # --- Step 5: Run Migrations & Optimize ---
            echo "[5/6] Running migrations and optimization..."
            sleep 10
            docker compose -f "$COMPOSE_FILE" exec -T app php artisan migrate --force --no-interaction
            docker compose -f "$COMPOSE_FILE" exec -T app php artisan config:cache
            docker compose -f "$COMPOSE_FILE" exec -T app php artisan route:cache
            docker compose -f "$COMPOSE_FILE" exec -T app php artisan view:cache
            docker compose -f "$COMPOSE_FILE" exec -T app php artisan storage:link 2>/dev/null || true
            echo "    Migrations and optimization complete."

            # --- Step 6: Health Check ---
            echo "[6/6] Running health check..."
            sleep 5
            RETRY_COUNT=0
            MAX_RETRIES=6

            while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
                HTTP_STATUS=$(curl -sf -o /dev/null -w "%{http_code}" http://127.0.0.1:80/health 2>/dev/null || echo "000")

                if [ "$HTTP_STATUS" = "200" ]; then
                    echo "    Health check PASSED (HTTP $HTTP_STATUS)"
                    echo ""
                    echo "=== Production Deployment SUCCESS ==="
                    echo "  Commit : ${{ github.sha }}"
                    echo "  Time   : $(date '+%Y-%m-%d %H:%M:%S %Z')"
                    echo "  Actor  : ${{ github.actor }}"
                    echo "======================================"
                    exit 0
                fi

                RETRY_COUNT=$((RETRY_COUNT + 1))
                echo "    Health check attempt $RETRY_COUNT/$MAX_RETRIES: HTTP $HTTP_STATUS (waiting 10s...)"
                sleep 10
            done

            echo "    Health check FAILED after $MAX_RETRIES attempts"
            echo "    Last HTTP status: $HTTP_STATUS"
            echo ""
            echo "    Container status:"
            docker compose -f "$COMPOSE_FILE" ps
            echo ""
            echo "    Recent app logs:"
            docker compose -f "$COMPOSE_FILE" logs --tail 50 app
            echo ""
            echo "=== Production Deployment FAILED ==="
            echo "ROLLBACK: Restore database dari backup pre_deploy_${BACKUP_TIMESTAMP}.sql.gz"
            exit 1
```

---

## 9. Penjelasan Setiap Tahap Pipeline

### CI Stage — Mengapa Setiap Step Diperlukan?

| # | Step | Apa yang Dilakukan | Jika Dilewatkan |
|---|------|--------------------|-----------------|
| 1 | Checkout | Clone repository ke runner | Tidak ada kode untuk diproses |
| 2 | Setup PHP | Instal PHP 8.2 + ekstensi | `composer install` dan `artisan test` gagal |
| 3 | Cache Composer | Simpan vendor/ di cache GitHub | Pipeline ~60 detik lebih lambat |
| 4 | Composer Install | Download dependency PHP | Semua kode Laravel gagal (autoloader missing) |
| 5 | Setup Node.js | Instal Node 20 + npm | `npm run build` gagal |
| 6 | npm ci + Build | Kompilasi aset CSS/JS (Vite) | Frontend rusak (unstyled, no JS) |
| 7 | Prepare .env | Buat .env untuk testing | `artisan test` gagal (no APP_KEY) |
| 8 | Test Suite | Jalankan PHPUnit/Pest | Bug lolos ke server |
| 9 | Composer Audit | Scan vulnerability dependency | Dependency dengan CVE lolos ke production |

### CD Stage — Mengapa Setiap Step Diperlukan?

| # | Step | Apa yang Dilakukan | Jika Dilewatkan |
|---|------|--------------------|-----------------|
| 1 | SCP Transfer | Kirim kode terenkripsi ke VPS | Kode baru tidak sampai ke VPS |
| 2 | Fix Permissions | `chown 33:33` + `chmod 775` | Error 500 (Permission Denied) |
| 3 | Docker Build | Rebuild image PHP-FPM | Container pakai kode lama |
| 4 | Docker Restart | Restart container dengan image baru | Perubahan kode tidak aktif |
| 5 | Migrate + Cache | Update database + compile config | Schema outdated, konfigurasi stale |
| 6 | Health Check | Verifikasi `/health` return 200 | Deployment gagal tanpa deteksi |

### Mengapa Build Assets di GitHub Runner dan Bukan di VPS?

| Operasi | CPU Runner (4 vCPU) | CPU VPS (2 Core) | Keputusan |
|---------|---------------------|-------------------|-----------|
| `composer install` (200+ packages) | ~15 detik | ~45 detik | Runner |
| `npm ci` (500+ packages) | ~20 detik | ~60 detik | Runner |
| `npm run build` (Vite compilation) | ~10 detik | ~30 detik + risk OOM | Runner |
| `php artisan test` (100+ tests) | ~20 detik | ~60 detik + CPU spike | Runner |
| `docker compose build` | N/A | ~30 detik (base image cached) | VPS |
| `docker compose up -d` | N/A | ~5 detik | VPS |

**Kesimpulan:** Semua operasi berat dijalankan di GitHub Runner (4 vCPU, 16GB RAM). VPS hanya melakukan Docker build (dengan layer caching) dan restart container. Ini mencegah CPU VPS 2 Core mengalami spike yang bisa mengganggu production traffic.

---

## 10. Skrip Remote Deployment (deploy.sh)

### Mengapa Skrip Terpisah? (Opsional)

Jika deployment script menjadi terlalu panjang untuk di-inline di dalam YAML, Anda bisa memindahkannya ke file terpisah di `deployment/scripts/deploy.sh` dan mengeksekusinya melalui SSH action:

```yaml
# Alternatif: eksekusi script yang sudah ada di VPS
- name: Deploy via SSH
  uses: appleboy/ssh-action@v1.2.0
  with:
    host: ${{ secrets.VPS_SSH_HOST }}
    username: ${{ secrets.VPS_SSH_USER }}
    key: ${{ secrets.VPS_SSH_KEY }}
    port: ${{ secrets.VPS_SSH_PORT }}
    script: bash /var/www/mer-system/production/deployment/scripts/deploy.sh production ${{ github.sha }}
```

> [!NOTE]
> Pada panduan ini, deployment script sudah di-inline langsung di dalam file YAML pipeline karena: (1) lebih mudah di-review dalam pull request, (2) tidak ada dependency terhadap file yang sudah ada di VPS, dan (3) seluruh logika deployment terlihat dalam satu file. Pendekatan file terpisah disarankan jika script melebihi ~50 baris.

---

## 11. Rollback Strategy

### Skenario: Deployment Production Gagal

Jika health check gagal setelah deployment production, lakukan rollback dengan langkah berikut:

### Metode 1: Re-run Pipeline pada Commit Sebelumnya

```
Navigasi: github.com > Repository mer-system > Actions > Production Pipeline
```

1. Cari pipeline **terakhir yang berhasil** (berlabel hijau/success)
2. Klik pipeline tersebut
3. Klik tombol **"Re-run all jobs"** (pojok kanan atas)
4. Pipeline akan berjalan ulang menggunakan kode dari commit yang berhasil tersebut
5. Karena pipeline production memerlukan approval, reviewer akan diminta approve lagi

**Mengapa ini berhasil:** GitHub Actions menyimpan state setiap workflow run beserta commit SHA-nya. Re-run menggunakan kode dari commit tersebut, bukan commit terbaru.

### Metode 2: Rollback Manual via SSH (Emergency)

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# === EMERGENCY ROLLBACK ===
# Gunakan jika GitHub Actions tidak accessible atau pipeline terlalu lambat.

cd /var/www/mer-system/production

# Jika menggunakan git (deploy key masih tersedia sebagai fallback):
# Ganti <COMMIT_SHA> dengan SHA commit terakhir yang stabil.
# git checkout <COMMIT_SHA>

# Restore database dari pre-deployment backup
LATEST_BACKUP=$(ls -t /var/www/mer-system/shared/backups/pre_deploy_*.sql.gz | head -1)
echo "Restoring from: $LATEST_BACKUP"

gunzip -c "$LATEST_BACKUP" | docker exec -i mer-db-prod \
    psql -U mer_dbadmin -d mer_production

# Rebuild dan restart
docker compose -f deployment/production/docker-compose.yml build app
docker compose -f deployment/production/docker-compose.yml up -d --force-recreate --no-deps app

# Tunggu dan verifikasi
sleep 15
curl -sf http://127.0.0.1:80/health && echo "ROLLBACK SUCCESS" || echo "ROLLBACK FAILED - ESCALATE"
```

### Metode 3: Revert Commit via Git

> **Konteks Eksekusi:** `Terminal Komputer Lokal`

```bash
# Membuat commit baru yang membatalkan perubahan dari commit bermasalah.
# Ini lebih aman dari force-push karena history tetap terjaga (auditable).
cd ~/projects/mer-system

git revert <COMMIT_SHA_BERMASALAH> --no-edit
git push origin main

# Production pipeline akan otomatis terpicu dengan kode yang sudah di-revert.
# Reviewer tetap perlu approve deployment.
```

---

## 12. Verifikasi

### Verifikasi 1: Test Pipeline Staging

> **Konteks Eksekusi:** `Terminal Komputer Lokal`

```bash
# Buat perubahan kecil untuk memicu pipeline
cd ~/projects/mer-system
git checkout staging

# Tambahkan komentar di route file sebagai test
echo "" >> routes/web.php
echo "// CI/CD Pipeline test - $(date)" >> routes/web.php

git add .
git commit -m "ci: test staging pipeline deployment"
git push origin staging
```

Setelah push, buka:

```
Navigasi: github.com > Repository mer-system > Actions
```

Anda harus melihat **"Staging Pipeline"** berjalan. Klik untuk melihat progress real-time:

| Job | Step | Status Diharapkan |
|-----|------|-------------------|
| CI | Checkout | Lulus |
| CI | Setup PHP | Lulus |
| CI | Composer Install | Lulus |
| CI | Build Assets | Lulus |
| CI | Test Suite | Lulus |
| CI | Composer Audit | Lulus |
| CD | SCP Transfer | Lulus |
| CD | SSH Deploy | Lulus |

### Verifikasi 2: Test Pipeline Production

```bash
# Merge staging ke main via Pull Request
# Terminal Lokal:
git checkout main
git merge staging
git push origin main
```

Di GitHub Actions:
1. **CI job** berjalan dan harus lulus
2. **CD job** menampilkan **"Waiting for review"**
3. Reviewer menerima notifikasi
4. Reviewer klik **"Review deployments"** > **"Approve"**
5. CD job melanjutkan deployment

### Verifikasi 3: Cek Kesehatan Deployment

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
echo "========================================="
echo "  MER System - CI/CD Deployment Check"
echo "========================================="

echo ""
echo "[1] Container Status (Production)"
docker compose -f /var/www/mer-system/production/deployment/production/docker-compose.yml ps

echo ""
echo "[2] Container Status (Staging)"
docker compose -f /var/www/mer-system/staging/deployment/staging/docker-compose.yml ps 2>/dev/null || echo "  Staging belum di-deploy"

echo ""
echo "[3] Health Check (Production)"
HTTP_PROD=$(curl -sf -o /dev/null -w "%{http_code}" http://127.0.0.1:80/health 2>/dev/null || echo "N/A")
echo "  HTTP Status: $HTTP_PROD"

echo ""
echo "[4] Health Check (Staging)"
HTTP_STAGING=$(curl -sf -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/health 2>/dev/null || echo "N/A")
echo "  HTTP Status: $HTTP_STAGING"

echo ""
echo "[5] Permission Check"
echo "  Production storage/: $(ls -ld /var/www/mer-system/production/storage/ 2>/dev/null | awk '{print $3, $4}')"
echo "  Staging storage/  : $(ls -ld /var/www/mer-system/staging/storage/ 2>/dev/null | awk '{print $3, $4}')"

echo ""
echo "[6] Pre-deployment Backups"
echo "  Total: $(find /var/www/mer-system/shared/backups -name 'pre_deploy_*' 2>/dev/null | wc -l) backup(s)"
echo "  Latest: $(ls -t /var/www/mer-system/shared/backups/pre_deploy_*.sql.gz 2>/dev/null | head -1 || echo 'NONE')"

echo ""
echo "========================================="
```

### Checklist Verifikasi Akhir

| # | Item | Cara Verifikasi | Status Diharapkan |
|---|------|----------------|-------------------|
| 1 | GitHub Secrets terdaftar | Settings > Secrets | 4 secrets listed |
| 2 | Environment "staging" ada | Settings > Environments | Configured, no reviewers |
| 3 | Environment "production" ada | Settings > Environments | Configured, reviewers assigned |
| 4 | staging.yml valid | Push ke staging | Pipeline berjalan |
| 5 | CI: Tests lulus | Actions > Staging Pipeline | All green |
| 6 | CI: Composer Audit lulus | Actions > Staging Pipeline | No vulnerabilities |
| 7 | CD: SCP transfer berhasil | Actions > Staging Pipeline | Step lulus |
| 8 | CD: SSH deploy berhasil | Actions > Staging Pipeline | Health check 200 |
| 9 | Production approval bekerja | Actions > Production Pipeline | "Waiting for review" |
| 10 | Reviewer bisa approve | Actions > Review deployments | Approve button visible |
| 11 | Production deploy berhasil | Actions > Production Pipeline | All green after approval |
| 12 | Pre-deployment backup ada | SSH > ls backups/ | File `.sql.gz` exists |
| 13 | Permission storage/ benar | SSH > ls -la storage/ | UID 33, mode 775 |
| 14 | Staging accessible | curl localhost:8080/health | HTTP 200 |
| 15 | Production accessible | curl localhost:80/health | HTTP 200 |

---

> **Dokumentasi lengkap.** Keenam file dokumentasi kini mencakup seluruh siklus dari hardening OS hingga deployment otomatis:
>
> 1. [01-arsitektur-dan-hardening-os.md](./01-arsitektur-dan-hardening-os.md) — Fondasi keamanan server
> 2. [02-cloudflare-dan-edge-security.md](./02-cloudflare-dan-edge-security.md) — Perlindungan tepi
> 3. [03-docker-production-staging.md](./03-docker-production-staging.md) — Containerization & isolasi
> 4. [04-nginx-waf-dan-laravel.md](./04-nginx-waf-dan-laravel.md) — Web server & aplikasi
> 5. [05-monitoring-dan-backup-strategy.md](./05-monitoring-dan-backup-strategy.md) — Observability & disaster recovery
> 6. [06-cicd-github-actions.md](./06-cicd-github-actions.md) — CI/CD Pipeline Zero-Trust (dokumen ini)
