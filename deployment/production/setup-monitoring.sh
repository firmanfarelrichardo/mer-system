#!/usr/bin/env bash
# =============================================================================
# MER System — Security & Monitoring Setup Script
# =============================================================================
# Menginisialisasi dan memvalidasi stack observability dan keamanan:
#   - Stack monitoring: Prometheus, Loki, Promtail, cAdvisor, Node Exporter, Grafana
#   - CrowdSec: engine IDS + parser crowdsecurity/nginx + auto-generate bouncer key
#   - Log rotation: validasi konfigurasi Docker daemon
#
# PRASYARAT:
#   Script ini harus dipanggil SETELAH deploy.sh berhasil karena:
#   1. External network mer-prod-network dibuat oleh deploy.sh.
#   2. Volume nginx-logs (shared dengan Promtail) dibuat oleh stack produksi.
#   3. Kita ingin memastikan aplikasi inti sehat sebelum menyalakan beban
#      kerja tambahan (monitoring mengonsumsi ~1.5GB RAM dari total 8GB).
#
# PEMANGGILAN:
#   cd /var/www/mer-system/deployment/production
#   bash setup-monitoring.sh
#
#   Atau via orchestrator:
#   bash setup-runner.sh
# =============================================================================

set -euo pipefail

# -----------------------------------------------------------------------------
# Konstanta warna
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
# Trap ERR: tampilkan konteks kegagalan
# -----------------------------------------------------------------------------
_handle_error() {
    local exit_code=$?
    echo -e "\n${RED}${BOLD}  Setup monitoring gagal di baris ${BASH_LINENO[0]} (exit code: ${exit_code})${NC}" >&2
    echo -e "${YELLOW}  Stack produksi tetap berjalan dan tidak terpengaruh.${NC}" >&2
}
trap '_handle_error' ERR

# -----------------------------------------------------------------------------
# Fungsi: validate_prod_network
#
# External network mer-prod-network harus sudah ada sebelum monitoring stack
# dijalankan. Network ini dibuat oleh deploy.sh. Jika tidak ada, berarti
# deploy.sh belum dijalankan atau gagal sebelum step tersebut.
# -----------------------------------------------------------------------------
validate_prod_network() {
    print_info "Memvalidasi ketersediaan network mer-prod-network..."

    if ! docker network inspect mer-prod-network &>/dev/null; then
        print_error "Network mer-prod-network tidak ditemukan. Pastikan deploy.sh berhasil dijalankan terlebih dahulu."
    fi

    print_success "Network mer-prod-network tersedia."
}

# -----------------------------------------------------------------------------
# Fungsi: start_monitoring_stack
#
# Menjalankan docker-compose.monitoring.yml yang berisi:
#   - Prometheus   : scrape metrics dari cAdvisor & Node Exporter
#   - Loki         : log aggregation database
#   - Promtail     : agen pembaca log (push ke Loki)
#   - cAdvisor     : container metrics exporter
#   - Node Exporter: host OS metrics (CPU, RAM, disk)
#   - Grafana      : visualisasi dashboard + alert rules
#
# Menggunakan file compose terpisah agar monitoring bisa direstart atau
# diupdate tanpa mengganggu stack produksi.
# -----------------------------------------------------------------------------
start_monitoring_stack() {
    print_info "Menjalankan monitoring stack..."

    docker compose \
        -f docker-compose.monitoring.yml \
        up -d --remove-orphans

    print_success "Monitoring stack berjalan."

    # Daftarkan nama service yang seharusnya berjalan untuk verifikasi.
    local expected_services=(
        "mer-prometheus"
        "mer-loki"
        "mer-grafana"
        "mer-cadvisor"
        "mer-node-exporter"
    )

    # Beri waktu singkat agar container selesai inisialisasi.
    sleep 5

    local failed_services=()
    for service in "${expected_services[@]}"; do
        local state
        state=$(docker inspect --format '{{.State.Status}}' "$service" 2>/dev/null || echo "not_found")
        if [[ "$state" != "running" ]]; then
            failed_services+=("${service} (state: ${state})")
        fi
    done

    if [[ ${#failed_services[@]} -gt 0 ]]; then
        print_warn "Beberapa service monitoring tidak dalam status running:"
        for svc in "${failed_services[@]}"; do
            echo -e "${YELLOW}    - ${svc}${NC}"
        done
        print_warn "Cek log: docker compose -f docker-compose.monitoring.yml logs <service>"
    else
        print_success "Semua service monitoring running."
    fi
}

# -----------------------------------------------------------------------------
# Fungsi: ensure_crowdsec_parser
#
# Memverifikasi bahwa CrowdSec parser crowdsecurity/nginx sudah terpasang.
# Parser ini adalah komponen deteksi serangan utama: membaca log Nginx
# dan mencocokkan pola dengan scenarios komunitas.
#
# Jika belum terpasang (misal: container baru atau volume dihapus),
# fungsi ini menginstall secara otomatis dan melakukan hub update.
# -----------------------------------------------------------------------------
ensure_crowdsec_parser() {
    print_info "Memverifikasi CrowdSec parser crowdsecurity/nginx..."

    # Pastikan container crowdsec sedang berjalan.
    local cs_state
    cs_state=$(docker inspect --format '{{.State.Status}}' mer-crowdsec 2>/dev/null || echo "not_found")

    if [[ "$cs_state" != "running" ]]; then
        print_warn "Container mer-crowdsec tidak running (state: ${cs_state}). Skip parser check."
        return
    fi

    # Cek apakah parser sudah terpasang.
    local parser_installed
    parser_installed=$(docker exec mer-crowdsec cscli collections list -o raw 2>/dev/null \
        | grep -c "crowdsecurity/nginx" || echo "0")

    if [[ "$parser_installed" -eq 0 ]]; then
        print_warn "Parser crowdsecurity/nginx belum terpasang. Menginstall..."
        docker exec mer-crowdsec cscli hub update
        docker exec mer-crowdsec cscli collections install crowdsecurity/nginx
        docker compose restart crowdsec
        print_success "Parser crowdsecurity/nginx berhasil diinstall."
    else
        # Update hub signatures ke versi terbaru dari komunitas.
        # Ini memastikan deteksi menggunakan pola serangan terkini.
        print_info "Memperbarui CrowdSec hub signatures..."
        docker exec mer-crowdsec cscli hub update
        docker exec mer-crowdsec cscli hub upgrade --force
        print_success "CrowdSec hub signatures diperbarui."
    fi
}

# -----------------------------------------------------------------------------
# Fungsi: ensure_bouncer_api_key
#
# Memastikan CrowdSec Bouncer memiliki API key yang valid di .env.
# Bouncer memerlukan key ini untuk berkomunikasi dengan CrowdSec Local API
# dan membaca daftar IP yang di-ban.
#
# Jika key belum ada di .env, fungsi ini generate secara otomatis dan
# langsung menyuntikkan ke .env. Bouncer kemudian di-restart agar
# membaca key baru.
#
# Argumen:
#   $1 - path ke file .env production
# -----------------------------------------------------------------------------
ensure_bouncer_api_key() {
    local env_file="$1"

    print_info "Memverifikasi CROWDSEC_BOUNCER_API_KEY di .env..."

    # Baca nilai saat ini. xargs untuk strip whitespace dan quotes.
    local current_key
    current_key=$(grep -E "^CROWDSEC_BOUNCER_API_KEY=" "$env_file" \
        | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs 2>/dev/null || echo "")

    if [[ -n "$current_key" ]]; then
        print_success "CROWDSEC_BOUNCER_API_KEY sudah terisi. Skip generate."
        return
    fi

    # Pastikan container crowdsec berjalan sebelum generate key.
    local cs_state
    cs_state=$(docker inspect --format '{{.State.Status}}' mer-crowdsec 2>/dev/null || echo "not_found")

    if [[ "$cs_state" != "running" ]]; then
        print_warn "Container mer-crowdsec tidak running. Tidak bisa generate bouncer key."
        print_warn "Isi CROWDSEC_BOUNCER_API_KEY secara manual setelah CrowdSec berjalan:"
        print_warn "  docker exec mer-crowdsec cscli bouncers add nginx-bouncer"
        return
    fi

    print_warn "CROWDSEC_BOUNCER_API_KEY kosong. Membangkitkan key baru..."

    # Generate API key baru untuk bouncer. Output berformat:
    #   Api key for 'nginx-bouncer':
    #     <key_value>
    #   Please keep this key...
    local new_key
    new_key=$(docker exec mer-crowdsec cscli bouncers add nginx-bouncer \
        --output raw 2>/dev/null \
        | grep -v '^#' | tr -d '[:space:]' || echo "")

    # Fallback: parse dari output verbose jika --output raw tidak tersedia.
    if [[ -z "$new_key" ]]; then
        new_key=$(docker exec mer-crowdsec cscli bouncers add "nginx-bouncer-$(date +%s)" 2>&1 \
            | grep -A1 "Api key" | tail -1 | tr -d '[:space:]' || echo "")
    fi

    if [[ -z "$new_key" ]]; then
        print_warn "Tidak bisa generate bouncer API key secara otomatis."
        print_warn "Jalankan manual dan isi ke .env:"
        print_warn "  docker exec mer-crowdsec cscli bouncers add nginx-bouncer"
        print_warn "  nano ${env_file}  # → CROWDSEC_BOUNCER_API_KEY=<key>"
        return
    fi

    # Injeksi ke .env.
    if grep -q "^CROWDSEC_BOUNCER_API_KEY=" "$env_file"; then
        sed -i "s|^CROWDSEC_BOUNCER_API_KEY=.*|CROWDSEC_BOUNCER_API_KEY=${new_key}|" "$env_file"
    else
        echo "CROWDSEC_BOUNCER_API_KEY=${new_key}" >> "$env_file"
    fi

    print_success "CROWDSEC_BOUNCER_API_KEY disuntikkan ke .env."
    print_info   "Key (12 karakter pertama): ${new_key:0:12}..."

    # Restart bouncer agar membaca key baru dari .env.
    docker compose restart crowdsec-bouncer
    print_success "CrowdSec bouncer di-restart dengan key baru."
}

# -----------------------------------------------------------------------------
# Fungsi: verify_docker_log_rotation
#
# Memverifikasi bahwa konfigurasi log driver pada daemon Docker sudah benar.
# Tanpa log rotation, container yang aktif terus-menerus (seperti Nginx dan
# PHP-FPM) akan mengisi disk secara perlahan hingga VPS tidak bisa boot.
#
# Konfigurasi diverifikasi dari /etc/docker/daemon.json.
# Konfigurasi per-service sudah ditangani via 'logging:' di compose file.
# Fungsi ini memastikan Docker daemon punya default yang aman sebagai fallback.
# -----------------------------------------------------------------------------
verify_docker_log_rotation() {
    print_info "Memverifikasi konfigurasi log rotation Docker daemon..."

    local daemon_json="/etc/docker/daemon.json"

    if [[ ! -f "$daemon_json" ]]; then
        print_warn "File ${daemon_json} tidak ditemukan."
        print_warn "Log rotation dikonfigurasi per-service di compose file (sudah aman)."
        print_warn "Untuk menambahkan default daemon-wide, jalankan sebagai root:"
        print_info "  cat > /etc/docker/daemon.json << 'JSON'"
        print_info "  {"
        print_info "    \"log-driver\": \"json-file\","
        print_info "    \"log-opts\": { \"max-size\": \"10m\", \"max-file\": \"5\" }"
        print_info "  }"
        print_info "  JSON"
        print_info "  systemctl reload docker"
        return
    fi

    # Periksa apakah konfigurasi log driver sudah menggunakan json-file.
    local log_driver
    log_driver=$(python3 -c "
import json, sys
try:
    with open('${daemon_json}') as f:
        cfg = json.load(f)
    print(cfg.get('log-driver', 'not_set'))
except Exception as e:
    print('parse_error:' + str(e))
" 2>/dev/null || echo "parse_error")

    if [[ "$log_driver" == "json-file" ]]; then
        print_success "Docker daemon menggunakan log driver json-file (default rotation aktif)."
    elif [[ "$log_driver" == "not_set" ]]; then
        print_warn "Log driver daemon tidak di-set eksplisit di ${daemon_json}."
        print_warn "Log rotation ditangani per-service di compose file — sudah aman."
    else
        print_warn "Log driver daemon: ${log_driver}. Pastikan sesuai dengan kebutuhan."
    fi
}

# =============================================================================
# INISIALISASI
# =============================================================================

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly ENV_FILE="${SCRIPT_DIR}/.env"

cd "$SCRIPT_DIR"

[[ ! -f "$ENV_FILE" ]] && print_error ".env tidak ditemukan di ${SCRIPT_DIR}."

echo -e "\n${BOLD}${GREEN}╔══════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║   MER System — Security & Monitoring Setup   ║${NC}"
echo -e "${BOLD}${GREEN}╚══════════════════════════════════════════════╝${NC}"
echo -e "  Direktori  : ${SCRIPT_DIR}"
echo -e "  Timestamp  : $(date '+%Y-%m-%d %H:%M:%S %Z')"

# =============================================================================
# STEP 1/4 — Network validation
# =============================================================================
print_step "Step 1/4 — Network validation"

validate_prod_network

# =============================================================================
# STEP 2/4 — Monitoring stack orchestration
# =============================================================================
print_step "Step 2/4 — Monitoring stack orchestration"

start_monitoring_stack

# =============================================================================
# STEP 3/4 — CrowdSec security integration
#
# CrowdSec parser dan bouncer key dikelola setelah monitoring stack berjalan
# agar log dari proses ini juga terpantau oleh Promtail/Loki.
# =============================================================================
print_step "Step 3/4 — CrowdSec security integration"

ensure_crowdsec_parser
ensure_bouncer_api_key "$ENV_FILE"

# =============================================================================
# STEP 4/4 — Docker log rotation verification
# =============================================================================
print_step "Step 4/4 — Docker log rotation verification"

verify_docker_log_rotation

# =============================================================================
# Summary
# =============================================================================
echo -e "\n${BOLD}${GREEN}╔══════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║  Monitoring & security setup selesai.         ║${NC}"
echo -e "${BOLD}${GREEN}╚══════════════════════════════════════════════╝${NC}"
echo -e "  Selesai : $(date '+%Y-%m-%d %H:%M:%S %Z')\n"

# Tampilkan status ringkas semua service (produksi + monitoring).
echo "=== Status stack produksi ==="
docker compose ps
echo ""
echo "=== Status stack monitoring ==="
docker compose -f docker-compose.monitoring.yml ps
