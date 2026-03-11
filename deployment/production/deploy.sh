#!/usr/bin/env bash
# ===========================================
# MER System — Production Deploy Script
# Idempotent Fail-Safe Orchestrator (7 Steps)
# ===========================================
# PRINSIP:
#   - set -e + trap ERR: berhenti SEGERA pada kegagalan apapun.
#   - git reset --hard: VPS selalu mencerminkan remote (no local drift).
#   - Readiness probe: verifikasi DB & Redis SEBELUM migrasi.
#   - Docker BuildKit: cache layer digunakan secara efektif.
#   - Setiap step memberikan output berwarna untuk observability.
#
# PENGGUNAAN:
#   cd /var/www/mer-system/deployment/production
#   bash deploy.sh
#
# ROLLBACK:
#   Script ini diinvoke oleh GitHub Actions workflow yang menangani
#   rollback otomatis ke commit sebelumnya jika script ini gagal.
# ===========================================

set -euo pipefail

# -------------------------------------------
# BuildKit: aktifkan build cache Docker yang lebih efisien.
# DOCKER_BUILDKIT=1 mengaktifkan BuildKit backend.
# COMPOSE_DOCKER_CLI_BUILD=1 memastikan Compose menggunakan CLI
# Docker (bukan builder lama) sehingga BuildKit aktif.
# -------------------------------------------
export DOCKER_BUILDKIT=1
export COMPOSE_DOCKER_CLI_BUILD=1

# -------------------------------------------
# Output helpers
# -------------------------------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

step()    { echo -e "\n${BOLD}${BLUE}══════════════════════════════════════${NC}\n${BOLD}${CYAN}  $1${NC}\n${BOLD}${BLUE}══════════════════════════════════════${NC}"; }
success() { echo -e "${GREEN}  [OK] $1${NC}"; }
warn()    { echo -e "${YELLOW}  [WARN] $1${NC}"; }
error()   { echo -e "${RED}  [ERROR] $1${NC}" >&2; exit 1; }

# -------------------------------------------
# Trap ERR: tampilkan baris yang gagal sebelum exit.
# github.com/koalaman/shellcheck: SC2034 (intentionally unused var)
# -------------------------------------------
_on_error() {
    local EXIT_CODE=$?
    echo -e "\n${RED}${BOLD}  Deploy gagal di baris ${BASH_LINENO[0]} (exit code: ${EXIT_CODE})${NC}" >&2
    echo -e "${YELLOW}  Jalankan workflow lagi atau rollback manual via git reset --hard <commit>${NC}" >&2
}
trap '_on_error' ERR

# -------------------------------------------
# Validasi environment & direktori
# -------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
cd "$SCRIPT_DIR"

[ ! -f ".env" ] && error ".env tidak ditemukan di ${SCRIPT_DIR}. Salin dari .env.example dan isi nilainya."

# Sumber variabel dari .env untuk digunakan dalam readiness probe.
# Hanya baca — tidak memodifikasi environment sistem.
set -a
# shellcheck disable=SC1091
source <(grep -v '^#' .env | grep -v '^[[:space:]]*$')
set +a

echo -e "\n${BOLD}${GREEN}╔══════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║     MER System — Production Deploy    ║${NC}"
echo -e "${BOLD}${GREEN}╚══════════════════════════════════════╝${NC}"
echo -e "  Direktori  : ${SCRIPT_DIR}"
echo -e "  Timestamp  : $(date '+%Y-%m-%d %H:%M:%S %Z')"
echo -e "  Git HEAD   : $(git -C "$PROJECT_ROOT" rev-parse --short HEAD 2>/dev/null || echo 'n/a')"

# ===========================================
# STEP 1: Sinkronisasi kode
# ===========================================
step "Step 1/7 — git fetch + reset --hard origin/production"

cd "$PROJECT_ROOT"
git fetch origin production
git checkout production
git reset --hard origin/production
success "Kode disinkronisasi ke commit: $(git rev-parse --short HEAD)"
cd "$SCRIPT_DIR"

# ===========================================
# STEP 2: Build & start containers
#
# --build: rebuild image dengan kode terbaru (BuildKit cache aktif).
# --remove-orphans: hapus container service yang sudah dihapus.
# --wait: blok hingga semua healthcheck PASS (bukan hanya started).
#
# BuildKit cache:
#   Layer Dockerfile yang tidak berubah (apt install, composer install)
#   di-cache oleh BuildKit. Hanya layer yang berubah (COPY kode) yang
#   di-rebuild. Waktu build turun dari ~3 menit ke ~30 detik pada deploy
#   kedua dan seterusnya.
# ===========================================
step "Step 2/7 — docker compose up -d --build"

docker network create mer-prod-network 2>/dev/null || true
docker compose up -d --build --remove-orphans --wait
success "Semua services berjalan dan healthcheck passed"

# ===========================================
# STEP 3: Readiness probe — DB & Redis
#
# MENGAPA PERLU:
#   --wait di Step 2 menjamin healthcheck container INTERNAL pass,
#   tetapi Laravel memerlukan verifikasi bahwa koneksi dari container
#   APP ke DB/Redis berfungsi dengan kredensial yang benar di .env.
#   Kegagalan di sini lebih informatif daripada "migrate gagal" tanpa
#   pesan jelas.
#
#   Probe dijalankan dari dalam container app agar menggunakan
#   jaringan internal Docker (bukan jaringan host). Ini menguji
#   koneksi yang sama persis dengan yang digunakan Laravel.
# ===========================================
step "Step 3/7 — Readiness probe (DB & Redis)"

# --- PostgreSQL probe ---
echo "  Menguji koneksi ke PostgreSQL..."
DB_PROBE_RESULT=$(docker compose exec -T app \
    php -r "
try {
    \$pdo = new PDO(
        'pgsql:host=db;port=5432;dbname=${DB_DATABASE}',
        '${DB_USERNAME}',
        '${DB_PASSWORD}',
        [PDO::ATTR_TIMEOUT => 5]
    );
    echo 'OK';
} catch (PDOException \$e) {
    echo 'FAIL:' . \$e->getMessage();
}
" 2>/dev/null || echo "FAIL:exec error")

if [[ "$DB_PROBE_RESULT" != "OK" ]]; then
    error "Koneksi PostgreSQL gagal: ${DB_PROBE_RESULT#FAIL:}"
fi
success "PostgreSQL: koneksi berhasil"

# --- Redis probe ---
echo "  Menguji koneksi ke Redis..."
REDIS_PROBE_RESULT=$(docker compose exec -T app \
    php -r "
try {
    \$redis = new Redis();
    \$redis->connect('redis', 6379, 5);
    \$redis->auth('${REDIS_PASSWORD}');
    echo \$redis->ping() === true || \$redis->ping() === '+PONG' ? 'OK' : 'FAIL:no PONG';
} catch (Exception \$e) {
    echo 'FAIL:' . \$e->getMessage();
}
" 2>/dev/null || echo "FAIL:exec error")

if [[ "$REDIS_PROBE_RESULT" != "OK" ]]; then
    error "Koneksi Redis gagal: ${REDIS_PROBE_RESULT#FAIL:}"
fi
success "Redis: koneksi berhasil"

# ===========================================
# STEP 4: Cek & inject APP_KEY
# ===========================================
step "Step 4/7 — Cek APP_KEY"

if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
    warn "APP_KEY belum di-set. Generating..."

    APP_KEY=$(docker compose exec -T app php artisan key:generate --show | tr -d '\r\n')

    if [[ ! "$APP_KEY" =~ ^base64:.+$ ]]; then
        error "APP_KEY yang dihasilkan tidak valid: '${APP_KEY}'"
    fi

    if grep -q "^APP_KEY=" .env; then
        sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
    else
        echo "APP_KEY=${APP_KEY}" >> .env
    fi

    success "APP_KEY disuntikkan ke .env: ${APP_KEY:0:20}..."

    warn "Merestart container app untuk memuat APP_KEY baru..."
    docker compose up -d --no-deps --wait app
    success "Container app berhasil di-rekonstruksi"
else
    success "APP_KEY sudah terisi, skip generate"
fi

# ===========================================
# STEP 5: Database migration
# ===========================================
step "Step 5/7 — php artisan migrate --force"

docker compose exec -T app php artisan migrate --force
success "Database migration selesai"

# ===========================================
# STEP 6: Optimasi cache aplikasi
# ===========================================
step "Step 6/7 — Optimasi (clear → rebuild cache)"

docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan optimize
docker compose exec -T app php artisan view:cache
docker compose exec -T app php artisan event:cache
success "Application cache di-rebuild"

# ===========================================
# STEP 7: Fix permissions & restart queue worker
# ===========================================
step "Step 7/7 — Permissions + queue:restart"

docker compose exec -T app chown -R www-data:www-data storage bootstrap/cache
success "Permissions diperbaiki"

docker compose exec -T app php artisan queue:restart
success "Queue worker restart signal terkirim"

# -------------------------------------------
# Summary
# -------------------------------------------
echo -e "\n${BOLD}${GREEN}╔══════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║  Deployment selesai!                          ║${NC}"
echo -e "${BOLD}${GREEN}╚══════════════════════════════════════════════╝${NC}"
echo -e "  Commit    : $(git -C "$PROJECT_ROOT" rev-parse HEAD)"
echo -e "  Selesai   : $(date '+%Y-%m-%d %H:%M:%S %Z')\n"

docker compose ps
