#!/usr/bin/env bash
# =============================================================================
# MER System — Core Application Deployment Script
# =============================================================================
# Siklus hidup deployment aplikasi inti:
#   1. Strict environment validation
#   2. Git synchronization (reset --hard)
#   3. APP_KEY generation (sebelum container start — tanpa dependensi container)
#   4. Atomic build & healthcheck (docker compose up --wait)
#   5. Database readiness probe & migration
#   6. Application cache optimization
#   7. Permission enforcement & queue worker restart
#
# DESAIN:
#   - set -euo pipefail + trap ERR: berhenti segera pada kegagalan apapun.
#   - git reset --hard: VPS selalu mencerminkan remote, local drift dibuang.
#   - APP_KEY di-generate dengan openssl SEBELUM container start sehingga
#     healthcheck container tidak gagal karena APP_KEY kosong.
#   - Readiness probe ke DB dan Redis dijalankan dari dalam container app
#     agar menguji jalur koneksi yang persis sama dengan yang dipakai Laravel.
#   - BuildKit aktif: layer Dockerfile yang tidak berubah di-cache,
#     waktu build deploy berikutnya turun drastis.
#
# PEMANGGILAN:
#   cd /var/www/mer-system/deployment/production
#   bash deploy.sh
#
# Skrip ini diinvoke oleh setup-runner.sh dan GitHub Actions workflow.
# Jika skrip ini gagal, workflow menangani rollback otomatis.
# =============================================================================

set -euo pipefail

# Aktifkan BuildKit untuk memanfaatkan cache layer Docker secara efektif.
# COMPOSE_DOCKER_CLI_BUILD=1 memastikan Docker Compose menggunakan CLI Docker
# (bukan builder bawaan Compose yang tidak mendukung BuildKit).
export DOCKER_BUILDKIT=1
export COMPOSE_DOCKER_CLI_BUILD=1

# -----------------------------------------------------------------------------
# Konstanta warna untuk output terminal
# -----------------------------------------------------------------------------
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly CYAN='\033[0;36m'
readonly BOLD='\033[1m'
readonly NC='\033[0m'

# -----------------------------------------------------------------------------
# Fungsi output
# -----------------------------------------------------------------------------
print_step()    { echo -e "\n${BOLD}${BLUE}══════════════════════════════════════${NC}\n${BOLD}${CYAN}  $1${NC}\n${BOLD}${BLUE}══════════════════════════════════════${NC}"; }
print_success() { echo -e "${GREEN}  [OK]   $1${NC}"; }
print_warn()    { echo -e "${YELLOW}  [WARN] $1${NC}"; }
print_error()   { echo -e "${RED}  [ERROR] $1${NC}" >&2; exit 1; }
print_info()    { echo -e "${CYAN}  [INFO] $1${NC}"; }

# -----------------------------------------------------------------------------
# Trap ERR: tampilkan nomor baris kegagalan sebelum script exit.
# Memudahkan debugging tanpa harus membaca seluruh log.
# -----------------------------------------------------------------------------
_handle_error() {
    local exit_code=$?
    echo -e "\n${RED}${BOLD}  Deploy gagal di baris ${BASH_LINENO[0]} (exit code: ${exit_code})${NC}" >&2
    echo -e "${YELLOW}  Rollback otomatis ditangani oleh GitHub Actions workflow.${NC}" >&2
    echo -e "${YELLOW}  Rollback manual: git -C /var/www/mer-system reset --hard <commit>${NC}" >&2
}
trap '_handle_error' ERR

# -----------------------------------------------------------------------------
# Fungsi: validate_required_env_vars
#
# Memastikan semua variabel wajib tersedia dan tidak kosong di file .env.
# Gagal lebih awal (fail-fast) dengan pesan eksplisit sebelum menyentuh
# Docker atau database — mencegah deployment setengah jalan.
# -----------------------------------------------------------------------------
validate_required_env_vars() {
    local env_file="$1"
    local required_vars=(
        APP_NAME
        APP_URL
        DB_DATABASE
        DB_USERNAME
        DB_PASSWORD
        REDIS_PASSWORD
    )

    local missing=()

    for var in "${required_vars[@]}"; do
        # Ambil nilai dari .env; nilai kosong atau tidak ada dianggap missing.
        local value
        value=$(grep -E "^${var}=" "$env_file" | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs)
        if [[ -z "$value" ]]; then
            missing+=("$var")
        fi
    done

    if [[ ${#missing[@]} -gt 0 ]]; then
        echo -e "${RED}  [ERROR] Variabel wajib berikut kosong atau tidak ada di .env:${NC}" >&2
        for var in "${missing[@]}"; do
            echo -e "${RED}    - ${var}${NC}" >&2
        done
        echo -e "${YELLOW}  Isi nilai yang hilang di: ${SCRIPT_DIR}/.env${NC}" >&2
        exit 1
    fi

    print_success "Semua variabel wajib terisi."
}

# -----------------------------------------------------------------------------
# Fungsi: generate_app_key
#
# Membangkitkan APP_KEY menggunakan openssl TANPA memerlukan container
# yang sedang berjalan. Ini penting karena container app tidak bisa
# melewati healthcheck jika APP_KEY kosong — menyebabkan deadlock:
# container gagal start → tidak bisa generate key → tidak bisa start.
#
# Format Laravel: base64:<32 random bytes dalam base64>
# Identik dengan output 'php artisan key:generate --show'.
# -----------------------------------------------------------------------------
generate_app_key() {
    local env_file="$1"

    if grep -q "^APP_KEY=base64:" "$env_file" 2>/dev/null; then
        print_success "APP_KEY sudah terisi, tidak perlu generate."
        return
    fi

    print_warn "APP_KEY kosong. Membangkitkan dengan openssl..."

    # openssl rand -base64 32 menghasilkan 256-bit random key dalam base64.
    # Format persis sama dengan yang dihasilkan php artisan key:generate.
    local raw_key
    raw_key=$(openssl rand -base64 32)
    local app_key="base64:${raw_key}"

    # Injeksi ke .env: timpa baris APP_KEY= yang ada, atau tambah jika belum ada.
    if grep -q "^APP_KEY=" "$env_file"; then
        sed -i "s|^APP_KEY=.*|APP_KEY=${app_key}|" "$env_file"
    else
        echo "APP_KEY=${app_key}" >> "$env_file"
    fi

    print_success "APP_KEY berhasil di-generate dan disuntikkan ke .env."
    print_info   "Key (20 karakter pertama): ${app_key:0:27}..."
}

# -----------------------------------------------------------------------------
# Fungsi: probe_database_connection
#
# Menguji koneksi dari container app ke PostgreSQL menggunakan PDO.
# Dijalankan dari dalam container agar menguji jalur jaringan Docker internal
# (container-to-container), bukan dari host — persis seperti koneksi Laravel.
#
# Argumen diambil dari variabel environment yang sudah di-source dari .env.
# -----------------------------------------------------------------------------
probe_database_connection() {
    print_info "Menguji koneksi ke PostgreSQL..."

    local result
    result=$(docker compose exec -T app \
        php -r "
try {
    \$pdo = new PDO(
        'pgsql:host=db;port=5432;dbname=${DB_DATABASE}',
        '${DB_USERNAME}',
        '${DB_PASSWORD}',
        [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo 'OK';
} catch (PDOException \$e) {
    echo 'FAIL:' . \$e->getMessage();
}
" 2>/dev/null || echo "FAIL:exec_error")

    if [[ "$result" != "OK" ]]; then
        print_error "Koneksi PostgreSQL gagal: ${result#FAIL:}"
    fi

    print_success "PostgreSQL: koneksi berhasil."
}

# -----------------------------------------------------------------------------
# Fungsi: probe_redis_connection
#
# Menguji koneksi dari container app ke Redis menggunakan ekstensi Redis PHP.
# Autentikasi diverifikasi — kegagalan auth lebih baik terdeteksi di sini
# daripada saat Laravel mencoba menulis sesi/cache.
# -----------------------------------------------------------------------------
probe_redis_connection() {
    print_info "Menguji koneksi ke Redis..."

    local result
    result=$(docker compose exec -T app \
        php -r "
try {
    \$redis = new Redis();
    \$redis->connect('redis', 6379, 5);
    \$redis->auth('${REDIS_PASSWORD}');
    \$pong = \$redis->ping();
    echo (\$pong === true || \$pong === '+PONG' || \$pong === 'PONG') ? 'OK' : 'FAIL:no_PONG';
} catch (Exception \$e) {
    echo 'FAIL:' . \$e->getMessage();
}
" 2>/dev/null || echo "FAIL:exec_error")

    if [[ "$result" != "OK" ]]; then
        print_error "Koneksi Redis gagal: ${result#FAIL:}"
    fi

    print_success "Redis: koneksi berhasil."
}

# =============================================================================
# INISIALISASI
# =============================================================================

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
readonly ENV_FILE="${SCRIPT_DIR}/.env"

cd "$SCRIPT_DIR"

# Pastikan .env ada sebelum melakukan apapun.
[[ ! -f "$ENV_FILE" ]] && print_error ".env tidak ditemukan di ${SCRIPT_DIR}. Salin dari .env.example dan isi nilainya."

echo -e "\n${BOLD}${GREEN}╔══════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║   MER System — Core Application Deploy   ║${NC}"
echo -e "${BOLD}${GREEN}╚══════════════════════════════════════════╝${NC}"
echo -e "  Direktori  : ${SCRIPT_DIR}"
echo -e "  Timestamp  : $(date '+%Y-%m-%d %H:%M:%S %Z')"
echo -e "  Git HEAD   : $(git -C "$PROJECT_ROOT" rev-parse --short HEAD 2>/dev/null || echo 'n/a')"

# =============================================================================
# STEP 1/7 — Validasi environment
#
# Semua variabel wajib harus terisi sebelum deployment dimulai.
# Kegagalan di sini tidak menyentuh container atau database.
# =============================================================================
print_step "Step 1/7 — Strict environment validation"

validate_required_env_vars "$ENV_FILE"

# Sumber variabel dari .env ke environment proses ini.
# Dibutuhkan oleh readiness probe (Step 5) yang meneruskan
# nilai DB_* dan REDIS_PASSWORD ke PHP inline script.
# set -a: ekspor otomatis semua variabel yang di-set.
set -a
# shellcheck disable=SC1091
source <(grep -v '^#' "$ENV_FILE" | grep -v '^[[:space:]]*$')
set +a

# =============================================================================
# STEP 2/7 — Git synchronization
#
# VPS production adalah immutable deployment target.
# git reset --hard memaksa filesystem VPS identik dengan remote branch.
# File yang di-gitignore (.env, storage/) tidak tersentuh.
# =============================================================================
print_step "Step 2/7 — Git synchronization (reset --hard)"

cd "$PROJECT_ROOT"
git fetch origin production
git checkout production
git reset --hard origin/production
print_success "Kode disinkronisasi ke commit: $(git rev-parse --short HEAD)"
cd "$SCRIPT_DIR"

# =============================================================================
# STEP 3/7 — APP_KEY generation (sebelum container start)
#
# APP_KEY harus ada di .env SEBELUM 'docker compose up' karena:
#   - Container app membaca APP_KEY saat start untuk boot Laravel.
#   - Healthcheck container (cgi-fcgi) akan gagal jika Laravel tidak bisa boot.
#   - Jika --wait digunakan dan healthcheck gagal, step ini exit non-zero.
#
# Key di-generate menggunakan openssl — tidak memerlukan container yang
# sudah berjalan. Format identik dengan 'php artisan key:generate --show'.
# =============================================================================
print_step "Step 3/7 — APP_KEY check & generation"

generate_app_key "$ENV_FILE"

# Reload .env setelah potensi perubahan APP_KEY.
set -a
# shellcheck disable=SC1091
source <(grep -v '^#' "$ENV_FILE" | grep -v '^[[:space:]]*$')
set +a

# =============================================================================
# STEP 4/7 — Atomic build & healthcheck
#
# --build         : rebuild image dengan kode terbaru (BuildKit cache aktif).
# --remove-orphans: hapus container service yang sudah dihapus dari compose file.
# --wait          : blok hingga SEMUA healthcheck PASS — menjamin semua
#                   service ready sebelum migration dijalankan.
#
# Jika ada container yang gagal healthcheck dalam timeout, step ini exit
# dengan kode non-zero dan trap ERR aktif.
# =============================================================================
print_step "Step 4/7 — Atomic build & healthcheck (docker compose up --wait)"

# Buat external network jika belum ada. Idempoten: || true abaikan
# error "network already exists" tanpa menyembunyikan error lain.
docker network create mer-prod-network 2>/dev/null || true

docker compose up -d --build --remove-orphans --wait
print_success "Semua services running dan healthcheck passed."

# =============================================================================
# STEP 5/7 — Database & Redis readiness probe
#
# 'docker compose up --wait' menjamin healthcheck container internal pass.
# Probe ini tambahan — memverifikasi bahwa koneksi dari PHP-FPM ke DB/Redis
# berfungsi dengan kredensial yang ada di .env production.
#
# Mendeteksi kegagalan lebih awal dengan pesan yang informatif:
# "Koneksi PostgreSQL gagal: password authentication failed for user X"
# vs. "php artisan migrate gagal" tanpa konteks.
# =============================================================================
print_step "Step 5/7 — Database & Redis readiness probe"

probe_database_connection
probe_redis_connection

print_info "Menjalankan database migration..."
docker compose exec -T app php artisan migrate --force
print_success "Database migration selesai."

# =============================================================================
# STEP 6/7 — Application cache optimization
#
# Urutan clear → rebuild WAJIB untuk mencegah stale cache:
#   optimize:clear → buang semua cache lama (config, route, view, event)
#   optimize      → rebuild config cache + route cache
#   view:cache    → compile semua Blade template ke PHP
#   event:cache   → cache mapping event-listener
# =============================================================================
print_step "Step 6/7 — Application cache optimization"

docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan optimize
docker compose exec -T app php artisan view:cache
docker compose exec -T app php artisan event:cache
print_success "Application cache di-rebuild."

# =============================================================================
# STEP 7/7 — Permission enforcement & queue worker restart
#
# chown -R www-data: diperlukan karena volume mount bisa mengubah ownership
# saat container di-rebuild. Storage dan bootstrap/cache harus writable
# oleh www-data (user PHP-FPM di dalam container).
#
# queue:restart: kirim SIGTERM graceful ke semua worker aktif.
# Supervisord respawn worker baru yang memuat kode terbaru.
# =============================================================================
print_step "Step 7/7 — Permission enforcement & queue worker restart"

docker compose exec -T app chown -R www-data:www-data storage bootstrap/cache
print_success "Ownership storage & bootstrap/cache diperbaiki."

docker compose exec -T app php artisan queue:restart
print_success "Queue worker restart signal terkirim."

# =============================================================================
# Deployment summary
# =============================================================================
echo -e "\n${BOLD}${GREEN}╔══════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║  Core deployment selesai.                     ║${NC}"
echo -e "${BOLD}${GREEN}╚══════════════════════════════════════════════╝${NC}"
echo -e "  Commit  : $(git -C "$PROJECT_ROOT" rev-parse HEAD)"
echo -e "  Selesai : $(date '+%Y-%m-%d %H:%M:%S %Z')\n"

docker compose ps
