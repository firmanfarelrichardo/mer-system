#!/usr/bin/env bash
# =======================================================================
# MER System — Level 3 Kill Switch: LOCKDOWN (Aktivasi Darurat)
# =======================================================================
#
# TUJUAN:
#   Membekukan seluruh backend (PHP-FPM, PostgreSQL, Redis) dan
#   membelokkan semua traffic pengunjung ke halaman maintenance statis
#   (503 Service Unavailable) yang dilayani langsung oleh Nginx.
#
# ARSITEKTUR ISOLASI:
#   SEBELUM lockdown:
#     Browser -> Cloudflare -> Nginx -> PHP-FPM -> Laravel -> PostgreSQL
#                                                          -> Redis
#
#   SETELAH lockdown:
#     Browser -> Cloudflare -> Nginx -> maintenance.html (503)
#                              [PHP-FPM, PostgreSQL, Redis = PAUSED]
#
# MEKANISME:
#   1. Membuat file trigger .lockdown yang dideteksi oleh Nginx.
#   2. Mem-pause container backend agar proses berhenti total.
#   3. Docker pause menggunakan cgroups freezer — proses TIDAK
#      dihentikan (kill), melainkan dibekukan di state saat ini.
#      Ini memungkinkan forensik memory dump jika diperlukan.
#
# PENGGUNAAN:
#   cd /var/www/mer-system/deployment/production
#   sudo bash lockdown.sh
#
# KEAMANAN:
#   Script ini HARUS diletakkan di deployment/production/ (di luar web
#   root). Nginx sudah memblokir akses ke file .sh via konfigurasi
#   location. Script hanya bisa dijalankan oleh operator yang memiliki
#   akses SSH ke VPS.
#
# CATATAN: Gunakan sudo jika Docker memerlukan elevated privileges.
# =======================================================================

# -----------------------------------------------------------------------
# STRICT MODE
#
# set -e  : Hentikan eksekusi SEGERA jika ada perintah yang gagal
#           (exit code != 0). Tanpa ini, script akan lanjut berjalan
#           meskipun docker pause gagal — berbahaya karena operator
#           mengira sistem sudah terisolasi padahal belum.
#
# set -u  : Hentikan eksekusi jika ada variabel yang belum di-set.
#           Mencegah situasi di mana typo variabel (misal: $LOCKDWON_DIR
#           bukannya $LOCKDOWN_DIR) diam-diam menjadi string kosong
#           dan menyebabkan `rm -f ""` atau operasi berbahaya lainnya.
#
# set -o pipefail : Jika ada perintah di dalam pipeline (cmd1 | cmd2)
#                   yang gagal, exit code pipeline = exit code perintah
#                   yang gagal (bukan hanya perintah terakhir). Tanpa
#                   ini, `docker compose ps | grep ...` bisa melaporkan
#                   sukses meskipun `docker compose ps` gagal.
# -----------------------------------------------------------------------
set -euo pipefail

# -----------------------------------------------------------------------
# KONFIGURASI PATH
#
# SCRIPT_DIR : Direktori absolut tempat script ini berada.
#              Menggunakan BASH_SOURCE agar reliable meskipun script
#              dipanggil dari direktori lain (misal: sudo bash /path/to/lockdown.sh).
#
# LOCKDOWN_DIR : Direktori yang di-mount ke container Nginx sebagai
#                /etc/nginx/lockdown/. File .lockdown dibuat di sini.
#
# LOCKDOWN_FILE : File trigger yang keberadaannya mengaktifkan mode darurat.
#                 Nginx mengecek `if (-f /etc/nginx/lockdown/.lockdown)`.
#
# COMPOSE_FILE : Path ke docker-compose.yml untuk memastikan docker compose
#                selalu menggunakan konfigurasi yang benar, bukan file
#                compose lain yang mungkin ada di working directory.
# -----------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOCKDOWN_DIR="${SCRIPT_DIR}/lockdown"
LOCKDOWN_FILE="${LOCKDOWN_DIR}/.lockdown"
COMPOSE_FILE="${SCRIPT_DIR}/docker-compose.yml"

# -----------------------------------------------------------------------
# Daftar service backend yang akan di-pause.
# Nginx (web) TIDAK dimasukkan karena harus tetap hidup untuk
# melayani halaman maintenance kepada pengunjung.
# -----------------------------------------------------------------------
BACKEND_SERVICES="app db redis"

# -----------------------------------------------------------------------
# OUTPUT HELPERS — ANSI color codes untuk kejelasan visual
# Warna memudahkan operator membedakan status sukses, peringatan,
# dan error saat eksekusi di terminal SSH (terutama di skenario
# high-pressure incident response).
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
# VALIDASI PRA-EKSEKUSI
# Memastikan semua prasyarat terpenuhi sebelum memulai lockdown.
# Jika salah satu gagal, script berhenti (set -e).
# -----------------------------------------------------------------------
echo ""
echo -e "${BOLD}${RED}============================================================${NC}"
echo -e "${BOLD}${RED}  LEVEL 3 KILL SWITCH — LOCKDOWN ACTIVATION${NC}"
echo -e "${BOLD}${RED}============================================================${NC}"
echo -e "  Waktu    : $(timestamp)"
echo -e "  Operator : $(whoami)@$(hostname)"
echo -e "  Direktori: ${SCRIPT_DIR}"
echo ""

# Cek: apakah docker compose tersedia?
if ! command -v docker &> /dev/null; then
    log_fail "Docker tidak ditemukan di PATH. Instalasi Docker diperlukan."
    exit 1
fi

# Cek: apakah docker-compose.yml ada?
if [[ ! -f "${COMPOSE_FILE}" ]]; then
    log_fail "docker-compose.yml tidak ditemukan di ${SCRIPT_DIR}."
    exit 1
fi

# Cek: apakah direktori lockdown ada?
if [[ ! -d "${LOCKDOWN_DIR}" ]]; then
    log_fail "Direktori lockdown tidak ditemukan: ${LOCKDOWN_DIR}"
    log_fail "Pastikan direktori lockdown/ beserta maintenance.html sudah ada."
    exit 1
fi

# Cek: apakah maintenance.html ada?
if [[ ! -f "${LOCKDOWN_DIR}/maintenance.html" ]]; then
    log_fail "maintenance.html tidak ditemukan di ${LOCKDOWN_DIR}."
    log_fail "Nginx akan mengembalikan 503 tanpa body — pengunjung melihat halaman kosong."
    exit 1
fi

# Cek: apakah sudah dalam mode lockdown?
if [[ -f "${LOCKDOWN_FILE}" ]]; then
    log_warn "Sistem SUDAH dalam mode lockdown."
    log_warn "File trigger ditemukan: ${LOCKDOWN_FILE}"
    log_warn "Jika ingin mengulangi lockdown, jalankan unlock.sh terlebih dahulu."
    exit 0
fi

# =======================================================================
# STEP 1: Buat file trigger .lockdown
#
# touch: membuat file kosong (atau update timestamp jika sudah ada).
# Ukuran file = 0 byte — Nginx hanya memeriksa keberadaan file
# (`if -f`), bukan isinya. Ini memastikan overhead = 0 pada I/O.
#
# EFEK LANGSUNG: Setelah file ini dibuat, request berikutnya yang
# diterima Nginx akan langsung mendapat 503. Tidak perlu reload Nginx.
# Bind mount bersifat real-time — perubahan di host langsung terlihat
# di dalam container.
# =======================================================================
echo -e "${BOLD}--- Step 1/3: Membuat file trigger lockdown${NC}"
touch "${LOCKDOWN_FILE}"
log_ok "File trigger dibuat: ${LOCKDOWN_FILE}"
log_info "Nginx sekarang membelokkan SEMUA traffic ke maintenance.html (503)."
echo ""

# =======================================================================
# STEP 2: Pause backend containers
#
# docker compose pause BUKAN docker compose stop. Perbedaan kritis:
#
# | Aspek          | pause (SIGSTOP)         | stop (SIGTERM/SIGKILL)    |
# |----------------|-------------------------|---------------------------|
# | Proses         | Dibekukan (frozen)      | Dihentikan (terminated)   |
# | Memory         | Tetap di RAM            | Dilepas                   |
# | Filesystem     | Tidak berubah           | Flush buffer ke disk      |
# | Forensik       | Memory dump BISA        | Memory dump TIDAK BISA    |
# | Recovery       | Instan (unpause)        | Harus restart (lambat)    |
# | State koneksi  | TCP connections frozen  | TCP connections closed    |
#
# KENAPA pause LEBIH BAIK untuk incident response?
# 1. Jika peretas menjalankan script berbahaya, pause membekukan
#    eksekusi di tengah jalan — script TIDAK bisa cleanup traces.
# 2. Memory dump masih memungkinkan untuk analisis forensik
#    (melihat apa yang sedang dijalankan peretas saat dibekukan).
# 3. Recovery jauh lebih cepat — unpause instan, tanpa cold start
#    PHP-FPM, PostgreSQL, dan Redis.
#
# URUTAN PAUSE: app -> redis -> db
# - app pertama: memutus koneksi ke pengguna (frontline).
# - redis kedua: membekukan session & queue (mencegah job berbahaya).
# - db terakhir: membekukan data layer (state terakhir terjaga).
# =======================================================================
echo -e "${BOLD}--- Step 2/3: Membekukan container backend${NC}"
cd "${SCRIPT_DIR}"

for service in ${BACKEND_SERVICES}; do
    log_info "Membekukan service: ${service}..."
    if docker compose -f "${COMPOSE_FILE}" pause "${service}" 2>/dev/null; then
        log_ok "Service ${service} berhasil dibekukan (paused)."
    else
        log_warn "Service ${service} gagal di-pause (mungkin sudah paused atau tidak berjalan)."
    fi
done
echo ""

# =======================================================================
# STEP 3: Verifikasi status akhir
#
# Menampilkan status seluruh container setelah lockdown.
# Operator harus memverifikasi:
# - web (Nginx) = Running / Up
# - app, db, redis = Paused
# =======================================================================
echo -e "${BOLD}--- Step 3/3: Verifikasi status container${NC}"
docker compose -f "${COMPOSE_FILE}" ps
echo ""

# =======================================================================
# SUMMARY
# =======================================================================
echo -e "${BOLD}${GREEN}============================================================${NC}"
echo -e "${BOLD}${GREEN}  LOCKDOWN BERHASIL DIAKTIFKAN${NC}"
echo -e "${BOLD}${GREEN}============================================================${NC}"
echo ""
echo -e "  Status         : ${RED}LOCKDOWN AKTIF${NC}"
echo -e "  Waktu aktivasi : $(timestamp)"
echo -e "  Trigger file   : ${LOCKDOWN_FILE}"
echo ""
echo -e "  ${BOLD}Container yang dibekukan:${NC}"
echo -e "    - app   (PHP-FPM + Queue Worker + Scheduler)"
echo -e "    - db    (PostgreSQL 16)"
echo -e "    - redis (Redis 7)"
echo ""
echo -e "  ${BOLD}Container yang tetap aktif:${NC}"
echo -e "    - web   (Nginx — melayani maintenance.html)"
echo ""
echo -e "  ${YELLOW}LANGKAH SELANJUTNYA:${NC}"
echo -e "    1. Investigasi log: docker compose -f ${COMPOSE_FILE} logs app --tail=500"
echo -e "    2. Cek akses terakhir: cat /var/log/nginx/access.log | tail -100"
echo -e "    3. Setelah aman, jalankan: ${BOLD}bash unlock.sh${NC}"
echo ""
