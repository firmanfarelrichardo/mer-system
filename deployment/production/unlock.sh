#!/usr/bin/env bash
# =======================================================================
# MER System — Level 3 Kill Switch: UNLOCK (Pemulihan Sistem)
# =======================================================================
#
# TUJUAN:
#   Memulihkan sistem ke operasi normal setelah lockdown darurat.
#   Kebalikan dari lockdown.sh: menghapus file trigger dan
#   melakukan unpause pada seluruh container backend.
#
# URUTAN PEMULIHAN (PENTING):
#   1. Unpause container backend TERLEBIH DAHULU (db -> redis -> app).
#   2. BARU kemudian hapus file trigger .lockdown.
#
#   KENAPA urutan ini?
#   Jika file .lockdown dihapus duluan sebelum container backend hidup,
#   ada jendela waktu (race condition) di mana:
#   - Nginx sudah berhenti menampilkan maintenance.html.
#   - Traffic pengunjung diteruskan ke PHP-FPM (location / -> try_files).
#   - PHP-FPM masih paused -> Nginx mengembalikan 502 Bad Gateway.
#   - Pengguna melihat error 502 yang tidak informatif.
#
#   Dengan unpause duluan, saat file .lockdown dihapus, PHP-FPM
#   sudah siap menerima request. Transisi mulus tanpa error.
#
# PENGGUNAAN:
#   cd /var/www/mer-system/deployment/production
#   sudo bash unlock.sh
# =======================================================================

# -----------------------------------------------------------------------
# STRICT MODE (penjelasan identik dengan lockdown.sh)
# -----------------------------------------------------------------------
set -euo pipefail

# -----------------------------------------------------------------------
# KONFIGURASI PATH
# -----------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOCKDOWN_DIR="${SCRIPT_DIR}/lockdown"
LOCKDOWN_FILE="${LOCKDOWN_DIR}/.lockdown"
COMPOSE_FILE="${SCRIPT_DIR}/docker-compose.yml"

# -----------------------------------------------------------------------
# Urutan unpause: db -> redis -> app (kebalikan dari lockdown).
#
# KENAPA urutan dibalik?
# - db pertama : PostgreSQL harus siap menerima koneksi sebelum
#                PHP-FPM (app) mencoba query.
# - redis kedua: session store & queue harus aktif sebelum app.
# - app terakhir: PHP-FPM, queue worker, dan scheduler baru hidup
#                 setelah semua dependency-nya siap.
#
# Ini mengikuti prinsip dependency graph yang sama dengan
# docker-compose depends_on saat startup.
# -----------------------------------------------------------------------
BACKEND_SERVICES="db redis app"

# -----------------------------------------------------------------------
# OUTPUT HELPERS
# -----------------------------------------------------------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

timestamp() { date '+%Y-%m-%d %H:%M:%S %Z'; }
log_info()  { echo -e "${CYAN}  [INFO]${NC}  $1"; }
log_ok()    { echo -e "${GREEN}  [OK]${NC}    $1"; }
log_warn()  { echo -e "${YELLOW}  [WARN]${NC}  $1"; }
log_fail()  { echo -e "${RED}  [FAIL]${NC}  $1" >&2; }

# -----------------------------------------------------------------------
# VALIDASI
# -----------------------------------------------------------------------
echo ""
echo -e "${BOLD}${GREEN}============================================================${NC}"
echo -e "${BOLD}${GREEN}  LEVEL 3 KILL SWITCH — UNLOCK (PEMULIHAN)${NC}"
echo -e "${BOLD}${GREEN}============================================================${NC}"
echo -e "  Waktu    : $(timestamp)"
echo -e "  Operator : $(whoami)@$(hostname)"
echo -e "  Direktori: ${SCRIPT_DIR}"
echo ""

if ! command -v docker &> /dev/null; then
    log_fail "Docker tidak ditemukan di PATH."
    exit 1
fi

if [[ ! -f "${COMPOSE_FILE}" ]]; then
    log_fail "docker-compose.yml tidak ditemukan di ${SCRIPT_DIR}."
    exit 1
fi

# Cek: apakah memang sedang dalam mode lockdown?
if [[ ! -f "${LOCKDOWN_FILE}" ]]; then
    log_warn "Sistem TIDAK sedang dalam mode lockdown."
    log_warn "File trigger tidak ditemukan: ${LOCKDOWN_FILE}"
    log_warn "Jika container masih paused, jalankan: docker compose unpause"
    exit 0
fi

# =======================================================================
# STEP 1: Unpause backend containers
#
# docker compose unpause mengirimkan SIGCONT ke proses yang dibekukan.
# Proses melanjutkan eksekusi dari titik terakhir — seperti menekan
# tombol "play" setelah "pause" pada video.
#
# Tidak ada cold start, tidak ada re-initialization:
# - PostgreSQL langsung menerima query (connection pool masih utuh).
# - Redis langsung melayani GET/SET (data masih di memory).
# - PHP-FPM langsung memproses request (worker sudah di-fork).
# =======================================================================
echo -e "${BOLD}--- Step 1/3: Menghidupkan kembali container backend${NC}"
cd "${SCRIPT_DIR}"

for service in ${BACKEND_SERVICES}; do
    log_info "Menghidupkan service: ${service}..."
    if docker compose -f "${COMPOSE_FILE}" unpause "${service}" 2>/dev/null; then
        log_ok "Service ${service} berhasil dihidupkan (unpaused)."
    else
        log_warn "Service ${service} gagal di-unpause (mungkin sudah running atau tidak ada)."
    fi
done
echo ""

# =======================================================================
# STEP 2: Hapus file trigger .lockdown
#
# rm -f: hapus file tanpa error jika file tidak ada (-f = force).
# Setelah file dihapus, request berikutnya yang diterima Nginx akan
# melewati pengecekan `if (-f ...)` dan diteruskan ke PHP-FPM secara
# normal. Transisinya real-time karena bind mount.
# =======================================================================
echo -e "${BOLD}--- Step 2/3: Menghapus file trigger lockdown${NC}"
rm -f "${LOCKDOWN_FILE}"
log_ok "File trigger dihapus: ${LOCKDOWN_FILE}"
log_info "Nginx sekarang meneruskan traffic ke PHP-FPM (operasi normal)."
echo ""

# =======================================================================
# STEP 3: Verifikasi + Restart queue worker
#
# Queue worker perlu di-restart karena:
# Saat di-pause, worker mungkin sedang memproses job yang sekarang sudah
# expired (timeout). Restart memastikan worker memulai dari state bersih
# dan mengambil job terbaru dari antrian Redis.
# =======================================================================
echo -e "${BOLD}--- Step 3/3: Verifikasi dan finalisasi${NC}"

# Restart queue worker agar job processing kembali bersih
log_info "Mengirim signal restart ke queue worker..."
if docker compose -f "${COMPOSE_FILE}" exec -T app php artisan queue:restart 2>/dev/null; then
    log_ok "Queue worker restart signal terkirim."
else
    log_warn "Gagal restart queue worker. Jalankan manual: docker compose exec app php artisan queue:restart"
fi

echo ""
docker compose -f "${COMPOSE_FILE}" ps
echo ""

# =======================================================================
# SUMMARY
# =======================================================================
echo -e "${BOLD}${GREEN}============================================================${NC}"
echo -e "${BOLD}${GREEN}  SISTEM BERHASIL DIPULIHKAN${NC}"
echo -e "${BOLD}${GREEN}============================================================${NC}"
echo ""
echo -e "  Status          : ${GREEN}OPERASI NORMAL${NC}"
echo -e "  Waktu pemulihan : $(timestamp)"
echo ""
echo -e "  ${BOLD}Semua container aktif:${NC}"
echo -e "    - web   (Nginx)"
echo -e "    - app   (PHP-FPM + Queue Worker + Scheduler)"
echo -e "    - db    (PostgreSQL 16)"
echo -e "    - redis (Redis 7)"
echo ""
echo -e "  ${YELLOW}REKOMENDASI POST-INCIDENT:${NC}"
echo -e "    1. Periksa log aplikasi  : docker compose logs app --tail=200"
echo -e "    2. Periksa log database  : docker compose logs db --tail=200"
echo -e "    3. Periksa log Nginx     : docker compose logs web --tail=200"
echo -e "    4. Verifikasi login      : buka browser, coba login ke sistem"
echo -e "    5. Cek job queue         : docker compose exec app php artisan queue:monitor"
echo -e "    6. Dokumentasikan insiden: catat timeline kejadian lengkap"
echo ""
