#!/usr/bin/env bash
# ===========================================
# MER System - Production Deploy Script
# ===========================================
# Idempotent & fail-safe deployment script untuk VPS Ubuntu 24.04 LTS.
#
# PRINSIP:
# - set -e: script berhenti SEGERA jika ada perintah yang gagal.
#   Mencegah eksekusi lanjutan dalam keadaan sistem yang tidak konsisten.
# - Setiap step memberikan output berwarna untuk monitoring progress.
# - Script dijalankan dari dalam folder deployment/production/.
#
# PENGGUNAAN:
#   cd /path/to/mer-system/deployment/production
#   bash deploy.sh
# ===========================================

set -e

# -------------------------------------------
# Output helpers dengan ANSI color codes
# -------------------------------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color / Reset

step() {
    echo -e "\n${BOLD}${BLUE}══════════════════════════════════════${NC}"
    echo -e "${BOLD}${CYAN}  $1${NC}"
    echo -e "${BOLD}${BLUE}══════════════════════════════════════${NC}"
}

success() {
    echo -e "${GREEN}  ✓ $1${NC}"
}

warn() {
    echo -e "${YELLOW}  ⚠ $1${NC}"
}

error() {
    echo -e "${RED}  ✗ ERROR: $1${NC}" >&2
    exit 1
}

# -------------------------------------------
# Validasi environment: pastikan .env ada dan APP_KEY diisi
# sebelum mulai deployment agar set -e tidak exit di tengah build.
# -------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

if [ ! -f ".env" ]; then
    error ".env tidak ditemukan di ${SCRIPT_DIR}. Salin dari .env.example dan isi nilainya."
fi

if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    warn "APP_KEY belum di-set atau tidak dalam format base64. Pastikan sudah diisi."
fi

echo -e "\n${BOLD}${GREEN}╔══════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║     MER System - Production Deploy    ║${NC}"
echo -e "${BOLD}${GREEN}╚══════════════════════════════════════╝${NC}"
echo -e "  Direktori  : ${SCRIPT_DIR}"
echo -e "  Timestamp  : $(date '+%Y-%m-%d %H:%M:%S %Z')"

# -------------------------------------------
# STEP 1: Pull latest code
# Pastikan kode di VPS selalu sinkron dengan branch main.
# git pull akan gagal jika ada untracked changes (set -e menangkap ini).
# -------------------------------------------
step "Step 1/6 - git pull origin main"
# cd ke root project (2 level di atas deployment/production/)
cd "$(dirname "$(dirname "$SCRIPT_DIR")")"
git pull origin main
success "Kode berhasil diperbarui dari branch main"

# Kembali ke direktori deployment/production untuk docker compose commands
cd "$SCRIPT_DIR"

# -------------------------------------------
# STEP 2: Build Docker images
# --pull: selalu download base image versi terbaru (patch security update).
# Tidak menggunakan --no-cache agar build lebih cepat dengan layer cache,
# tapi --pull memastikan base image (php:8.2-fpm-alpine) selalu terbaru.
# -------------------------------------------
step "Step 2/6 - docker compose build"
docker compose build --pull
success "Docker images berhasil dibangun"

# -------------------------------------------
# STEP 3: Start services
# -d (detached mode): container berjalan di background.
# --remove-orphans: hapus container service yang sudah dihapus dari compose file.
# --wait: tunggu sampai semua healthcheck PASS sebelum lanjut.
#   Ini memastikan db dan redis sudah ready sebelum migration dijalankan.
# -------------------------------------------
step "Step 3/6 - docker compose up -d"
docker compose up -d --remove-orphans --wait
success "Semua services berhasil dijalankan dan healthcheck passed"

# -------------------------------------------
# STEP 4: Database migration
# --force wajib di environment production karena Laravel akan menolak
# migration tanpa flag ini sebagai proteksi dari eksekusi tidak sengaja.
# Jalankan di dalam container 'app' yang sudah pasti running.
# -------------------------------------------
step "Step 4/6 - php artisan migrate --force"
docker compose exec app php artisan migrate --force
success "Database migration selesai"

# -------------------------------------------
# STEP 5: Clear & rebuild application cache
# optimize:clear: hapus semua cache lama (config, route, view, event).
# optimize: rebuild config cache, route cache, dan view cache sekaligus.
# Urutan ini penting-clear dulu, baru rebuild-agar tidak ada stale cache.
# OPcache juga di-clear secara implisit melalui restart proses PHP-FPM.
# -------------------------------------------
step "Step 5/6 - optimize:clear → optimize"
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan optimize
success "Application cache berhasil di-rebuild"

# -------------------------------------------
# STEP 6: Restart queue worker
# queue:restart mengirim sinyal SIGTERM graceful ke worker yang sedang berjalan.
# Worker akan menyelesaikan job aktif dulu (grace period = stopwaitsecs di supervisord),
# lalu supervisord otomatis respawn worker baru dengan kode terbaru.
# -------------------------------------------
step "Step 6/6 - php artisan queue:restart"
docker compose exec app php artisan queue:restart
success "Queue worker restart signal terkirim"

# -------------------------------------------
# Summary
# -------------------------------------------
echo -e "\n${BOLD}${GREEN}╔══════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║  Deployment selesai!                          ║${NC}"
echo -e "${BOLD}${GREEN}╚══════════════════════════════════════════════╝${NC}"
echo -e "  Selesai pada : $(date '+%Y-%m-%d %H:%M:%S %Z')\n"

docker compose ps
