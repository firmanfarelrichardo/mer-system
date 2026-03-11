#!/usr/bin/env bash
# ===========================================
# MER System — Production Deploy Script
# Fail-Safe Orchestrator (7 Steps)
# ===========================================
# Idempotent & fail-safe deployment untuk VPS Ubuntu 24.04 LTS.
#
# PRINSIP:
# - set -e: berhenti SEGERA jika ada perintah yang gagal.
# - git pull --ff-only: tolak divergent branches, wajib fast-forward.
# - Setiap step memberikan output berwarna untuk monitoring.
# - Script dijalankan dari folder deployment/production/.
#
# PENGGUNAAN:
#   cd /path/to/mer-system/deployment/production
#   bash deploy.sh
# ===========================================

set -e

# -------------------------------------------
# Output helpers (ANSI color codes)
# -------------------------------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

step() {
    echo -e "\n${BOLD}${BLUE}══════════════════════════════════════${NC}"
    echo -e "${BOLD}${CYAN}  $1${NC}"
    echo -e "${BOLD}${BLUE}══════════════════════════════════════${NC}"
}

success() { echo -e "${GREEN}  ✓ $1${NC}"; }
warn()    { echo -e "${YELLOW}  ⚠ $1${NC}"; }
error()   { echo -e "${RED}  ✗ ERROR: $1${NC}" >&2; exit 1; }

# -------------------------------------------
# Validasi environment
# -------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"
cd "$SCRIPT_DIR"

if [ ! -f ".env" ]; then
    error ".env tidak ditemukan di ${SCRIPT_DIR}. Salin dari .env.example dan isi nilainya."
fi

echo -e "\n${BOLD}${GREEN}╔══════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║     MER System — Production Deploy    ║${NC}"
echo -e "${BOLD}${GREEN}╚══════════════════════════════════════╝${NC}"
echo -e "  Direktori  : ${SCRIPT_DIR}"
echo -e "  Timestamp  : $(date '+%Y-%m-%d %H:%M:%S %Z')"

# -------------------------------------------
# STEP 1: Pull latest code dari branch production
# --ff-only: hanya izinkan fast-forward. Jika ada divergent branches,
# skrip berhenti (set -e) daripada membuat merge commit otomatis
# yang bisa mengacaukan state production secara tidak terduga.
# -------------------------------------------
step "Step 1/7 — git pull origin production (--ff-only)"
cd "$PROJECT_ROOT"
git fetch origin production
git checkout production
git pull --ff-only origin production
success "Kode berhasil diperbarui dari branch production"
cd "$SCRIPT_DIR"

# -------------------------------------------
# STEP 2: Build & start containers
# -d: detached mode (background).
# --build: rebuild image dengan kode terbaru.
# --remove-orphans: hapus container service yang sudah dihapus.
# --wait: tunggu semua healthcheck PASS sebelum lanjut.
# -------------------------------------------
step "Step 2/7 — docker compose up -d --build"
docker compose up -d --build --remove-orphans --wait
success "Semua services berjalan dan healthcheck passed"

# -------------------------------------------
# STEP 3: Cek APP_KEY — generate & inject ke Host jika belum ada
#
# PRINSIP IMMUTABLE INFRASTRUCTURE:
# Container TIDAK BOLEH memanipulasi konfigurasinya sendiri.
# .env tidak di-mount sebagai file ke dalam container — ia hanya
# dibaca sebagai env_file oleh Docker Compose pada saat container
# dibuat. Perintah `key:generate --force` gagal karena mencoba
# membuka /var/www/html/.env yang tidak ada di filesystem container.
# Pada skenario multi-replica, membiarkan tiap container generate
# kunci sendiri akan menghasilkan APP_KEY yang berbeda-beda,
# menyebabkan kerusakan pada session, cookie, dan enkripsi data.
#
# SOLUSI: Host sebagai single source of truth.
# `--show` → Artisan hanya mencetak key ke stdout, TIDAK menulis .env.
# Host menangkap output, memvalidasi format, menyuntikkan ke .env Host.
# Container kemudian di-rekonstruksi agar env var baru dimuat ke
# memori sebelum migrasi database berjalan di Step 4.
# -------------------------------------------
step "Step 3/7 — Cek APP_KEY"
if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
    warn "APP_KEY belum di-set. Generating..."

    # `key:generate --show`: cetak key baru ke stdout, TIDAK menulis ke .env.
    # `tr -d '\r\n'`: buang carriage return (\r) dan newline (\n) yang bisa
    # muncul di output `docker compose exec`, agar string bersih untuk sed.
    APP_KEY=$(docker compose exec -T app php artisan key:generate --show | tr -d '\r\n')

    # Guard: pastikan format key yang ditangkap valid (harus: base64:<string>).
    # Tolak nilai kosong atau format tidak dikenal sebelum memodifikasi .env Host.
    if [[ ! "$APP_KEY" =~ ^base64:.+$ ]]; then
        error "APP_KEY yang dihasilkan tidak valid: '${APP_KEY}'. Cek output container."
    fi

    # `sed -i`: edit .env Host secara in-place (tanpa file backup sementara).
    # Delimiter `|` dipilih karena nilai base64 mengandung karakter `/`
    # yang akan konflik dengan delimiter default `/` pada perintah sed.
    # Pola `^APP_KEY=.*` mencocokkan dari awal baris hingga akhir nilai lama.
    if grep -q "^APP_KEY=" .env; then
        # Baris APP_KEY sudah ada (nilai kosong) — timpa dengan nilai baru.
        sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
    else
        # Edge case: baris APP_KEY belum ada sama sekali di .env — tambahkan.
        echo "APP_KEY=${APP_KEY}" >> .env
    fi

    # Tampilkan 20 karakter pertama — cukup untuk konfirmasi visual tanpa
    # mengekspos kunci penuh di log deployment yang mungkin direkam/disimpan.
    success "APP_KEY disuntikkan ke .env Host: ${APP_KEY:0:20}..."

    # Re-create container app agar Docker Compose membaca ulang .env Host
    # dan memuat APP_KEY baru sebagai environment variable ke memori container.
    # --no-deps : hanya rekonstruksi service 'app', bukan db/redis.
    # --wait    : block sampai healthcheck PASS sebelum lanjut ke Step 4.
    warn "Merestart container app untuk memuat APP_KEY baru..."
    docker compose up -d --no-deps --wait app
    success "Container app berhasil di-rekonstruksi dengan APP_KEY baru"
else
    success "APP_KEY sudah terisi, skip generate"
fi

# -------------------------------------------
# STEP 4: Database migration
# --force: wajib di production (Laravel menolak tanpa flag ini).
# -T: disable pseudo-TTY (agar skrip bisa berjalan non-interactive).
# -------------------------------------------
step "Step 4/7 — php artisan migrate --force"
docker compose exec -T app php artisan migrate --force
success "Database migration selesai"

# -------------------------------------------
# STEP 5: Optimasi performa
# optimize:clear → hapus semua cache lama.
# optimize → rebuild config + route cache.
# view:cache → compile semua Blade templates.
# event:cache → cache event-listener mapping.
# Urutan clear→rebuild PENTING agar tidak ada stale cache.
# -------------------------------------------
step "Step 5/7 — Optimasi (clear → cache)"
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan optimize
docker compose exec -T app php artisan view:cache
docker compose exec -T app php artisan event:cache
success "Application cache berhasil di-rebuild"

# -------------------------------------------
# STEP 6: Perbaikan permission sisi server
# Memastikan storage & bootstrap/cache dimiliki www-data
# walaupun ada volume mount yang mungkin berubah ownership.
# -------------------------------------------
step "Step 6/7 — Fix permissions (storage & bootstrap/cache)"
docker compose exec -T app chown -R www-data:www-data storage bootstrap/cache
success "Permissions diperbaiki"

# -------------------------------------------
# STEP 7: Restart queue worker
# queue:restart mengirim SIGTERM graceful ke worker aktif.
# Worker menyelesaikan job yang sedang berjalan (grace period =
# stopwaitsecs di supervisord), lalu supervisord respawn worker baru.
# -------------------------------------------
step "Step 7/7 — php artisan queue:restart"
docker compose exec -T app php artisan queue:restart
success "Queue worker restart signal terkirim"

# -------------------------------------------
# Summary
# -------------------------------------------
echo -e "\n${BOLD}${GREEN}╔══════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║  Deployment selesai!                          ║${NC}"
echo -e "${BOLD}${GREEN}╚══════════════════════════════════════════════╝${NC}"
echo -e "  Selesai pada : $(date '+%Y-%m-%d %H:%M:%S %Z')\n"

docker compose ps
