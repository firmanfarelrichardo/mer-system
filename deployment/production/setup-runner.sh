#!/usr/bin/env bash
# =============================================================================
# MER System — Deployment Orchestrator
# =============================================================================
# Wrapper yang mengatur urutan eksekusi deployment secara end-to-end:
#
#   1. Jalankan deploy.sh (core application deployment)
#   2. JIKA deploy.sh berhasil → jalankan setup-monitoring.sh
#   3. Tampilkan ringkasan final dengan status keseluruhan
#
# ALASAN URUTAN INI:
#   Pemisahan core deployment dan monitoring bukan sekadar estetika.
#   Ada alasan teknis yang kuat:
#
#   a. Validasi terlebih dahulu:
#      Kita ingin tahu aplikasi BISA BERJALAN sebelum menyalakan
#      monitoring. Jika deploy gagal, monitoring untuk apa?
#
#   b. Dependensi satu arah:
#      setup-monitoring.sh BERGANTUNG pada artefak yang dibuat deploy.sh:
#      - external network 'mer-prod-network' (dibuat di Step 4 deploy.sh)
#      - named volume 'nginx-logs' (dibuat saat container web start)
#      Monitoring tidak bisa jalan tanpa artefak ini.
#
#   c. Isolasi kegagalan:
#      Jika monitoring gagal start (OOM, port conflict, dll), aplikasi
#      PRODUKSI tidak terpengaruh. Dua domain yang berbeda tanggung jawab.
#
#   d. Resource management:
#      Monitoring stack mengonsumsi ~1.5GB RAM.
#      Jika dijalankan bersamaan dengan build Docker, VPS bisa kehabisan RAM
#      dan menyebabkan OOM kill pada container yang sedang di-build.
#
# PENGGUNAAN:
#   cd /var/www/mer-system/deployment/production
#   bash setup-runner.sh [--skip-monitoring]
#
# FLAG:
#   --skip-monitoring  Jalankan hanya deploy.sh, lewati setup-monitoring.sh.
#                      Berguna untuk hotfix cepat tanpa restart monitoring stack.
# =============================================================================

set -euo pipefail

# -----------------------------------------------------------------------------
# Konstanta warna
# -----------------------------------------------------------------------------
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly CYAN='\033[0;36m'
readonly BOLD='\033[1m'
readonly NC='\033[0m'

# -----------------------------------------------------------------------------
# Fungsi output
# -----------------------------------------------------------------------------
print_banner()  {
    echo -e "\n${BOLD}${CYAN}${1}${NC}"
}
print_success() { echo -e "${GREEN}  [OK]   $1${NC}"; }
print_warn()    { echo -e "${YELLOW}  [WARN] $1${NC}"; }
print_error()   { echo -e "${RED}  [ERROR] $1${NC}" >&2; }
print_info()    { echo -e "${CYAN}  [INFO] $1${NC}"; }
print_divider() { echo -e "${BOLD}  ─────────────────────────────────────────${NC}"; }

# -----------------------------------------------------------------------------
# Parse argumen CLI
# -----------------------------------------------------------------------------
SKIP_MONITORING=false

for arg in "$@"; do
    case "$arg" in
        --skip-monitoring)
            SKIP_MONITORING=true
            ;;
        --help|-h)
            echo "Penggunaan: bash setup-runner.sh [--skip-monitoring]"
            echo ""
            echo "  --skip-monitoring   Jalankan hanya deploy.sh, lewati monitoring setup."
            exit 0
            ;;
        *)
            echo -e "${RED}  Argumen tidak dikenal: ${arg}${NC}" >&2
            echo "  Gunakan --help untuk melihat opsi yang tersedia."
            exit 1
            ;;
    esac
done

# -----------------------------------------------------------------------------
# Resolusi path
# -----------------------------------------------------------------------------
readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly DEPLOY_SCRIPT="${SCRIPT_DIR}/deploy.sh"
readonly MONITORING_SCRIPT="${SCRIPT_DIR}/setup-monitoring.sh"
readonly AUDIT_LOG_DIR="/var/log/mer-deploy"
readonly AUDIT_LOG_FILE="${AUDIT_LOG_DIR}/orchestrator.log"

# -----------------------------------------------------------------------------
# Fungsi: validate_scripts_exist
#
# Pastikan kedua skrip yang akan dipanggil benar-benar ada di filesystem.
# Gagal lebih awal jika skrip hilang (misal: git reset gagal sebagian).
# -----------------------------------------------------------------------------
validate_scripts_exist() {
    local missing=()

    [[ ! -f "$DEPLOY_SCRIPT" ]]     && missing+=("deploy.sh")
    [[ ! -f "$MONITORING_SCRIPT" ]] && missing+=("setup-monitoring.sh")

    if [[ ${#missing[@]} -gt 0 ]]; then
        echo -e "${RED}  [ERROR] Skrip berikut tidak ditemukan di ${SCRIPT_DIR}:${NC}" >&2
        for f in "${missing[@]}"; do
            echo -e "${RED}    - ${f}${NC}" >&2
        done
        exit 1
    fi
}

# -----------------------------------------------------------------------------
# Fungsi: write_audit_log
#
# Mencatat metadata setiap run ke file log persisten.
# Log ini bisa diquery via Grafana/Loki untuk audit trail deployment.
#
# Argumen:
#   $1 - status: "SUCCESS" | "FAILED" | "PARTIAL" (deploy OK, monitoring gagal)
#   $2 - detail: pesan tambahan (opsional)
# -----------------------------------------------------------------------------
write_audit_log() {
    local status="$1"
    local detail="${2:-}"

    mkdir -p "$AUDIT_LOG_DIR"

    {
        echo "timestamp=$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
        echo "status=${status}"
        echo "skip_monitoring=${SKIP_MONITORING}"
        echo "detail=${detail}"
        echo "---"
    } >> "$AUDIT_LOG_FILE"
}

# =============================================================================
# MAIN ORCHESTRATION
# =============================================================================

echo -e "\n${BOLD}${GREEN}"
echo "  ╔════════════════════════════════════════════╗"
echo "  ║   MER System — Deployment Orchestrator    ║"
echo "  ╚════════════════════════════════════════════╝"
echo -e "${NC}"
echo -e "  Direktori : ${SCRIPT_DIR}"
echo -e "  Timestamp : $(date '+%Y-%m-%d %H:%M:%S %Z')"
if [[ "$SKIP_MONITORING" == "true" ]]; then
    echo -e "  Mode      : ${YELLOW}deploy only (--skip-monitoring aktif)${NC}"
else
    echo -e "  Mode      : full (deploy + monitoring)"
fi
echo ""

validate_scripts_exist

# -----------------------------------------------------------------------------
# FASE 1: Core Application Deployment
#
# Variabel DEPLOY_EXIT_CODE menyimpan exit code deploy.sh.
# Cara 'if bash script; then' tidak bisa digunakan bersama 'set -e'
# karena 'set -e' tidak berlaku di dalam kondisi if.
# Solusinya: gunakan || true untuk mencegah set -e menghentikan skrip,
# lalu periksa exit code secara manual.
# -----------------------------------------------------------------------------
print_banner "FASE 1 — Core Application Deployment"
print_divider

DEPLOY_EXIT_CODE=0
bash "$DEPLOY_SCRIPT" || DEPLOY_EXIT_CODE=$?

if [[ $DEPLOY_EXIT_CODE -ne 0 ]]; then
    echo ""
    print_error "deploy.sh gagal dengan exit code ${DEPLOY_EXIT_CODE}."
    print_error "Monitoring setup DIBATALKAN untuk mencegah state tidak konsisten."
    print_warn  "Rollback ditangani oleh GitHub Actions workflow."
    write_audit_log "FAILED" "deploy.sh exit_code=${DEPLOY_EXIT_CODE}"
    exit $DEPLOY_EXIT_CODE
fi

print_success "deploy.sh selesai dengan sukses."

# -----------------------------------------------------------------------------
# FASE 2: Security & Monitoring Setup
#
# Hanya dijalankan jika FASE 1 berhasil.
# Kegagalan monitoring adalah 'soft failure': dicatat sebagai PARTIAL,
# bukan FAILED — karena aplikasi produksi tetap berjalan normal.
# -----------------------------------------------------------------------------
if [[ "$SKIP_MONITORING" == "true" ]]; then
    echo ""
    print_warn "FASE 2 dilewati (--skip-monitoring aktif)."
    write_audit_log "SUCCESS" "monitoring_skipped=true"
else
    echo ""
    print_banner "FASE 2 — Security & Monitoring Setup"
    print_divider

    MONITORING_EXIT_CODE=0
    bash "$MONITORING_SCRIPT" || MONITORING_EXIT_CODE=$?

    if [[ $MONITORING_EXIT_CODE -ne 0 ]]; then
        echo ""
        print_warn "setup-monitoring.sh gagal (exit code: ${MONITORING_EXIT_CODE})."
        print_warn "Aplikasi produksi tetap berjalan. Investigasi segera:"
        print_warn "  docker compose -f docker-compose.monitoring.yml logs --tail 50"
        write_audit_log "PARTIAL" "monitoring_exit_code=${MONITORING_EXIT_CODE}"
        # Tidak exit dengan kode error agar workflow tidak menandai ini sebagai
        # rollback target. Monitoring adalah concern terpisah dari app stability.
        echo ""
    else
        print_success "setup-monitoring.sh selesai dengan sukses."
        write_audit_log "SUCCESS" "all_phases_completed=true"
    fi
fi

# =============================================================================
# Final summary
# =============================================================================
echo -e "\n${BOLD}${GREEN}"
echo "  ╔════════════════════════════════════════════╗"
echo "  ║   Orchestration selesai.                   ║"
echo "  ╚════════════════════════════════════════════╝"
echo -e "${NC}"
echo -e "  Selesai   : $(date '+%Y-%m-%d %H:%M:%S %Z')"
echo -e "  Audit log : ${AUDIT_LOG_FILE}"
echo ""

# Tampilkan status akhir semua service yang relevan.
echo "=== Status akhir stack produksi ==="
docker compose -f "${SCRIPT_DIR}/docker-compose.yml" ps 2>/dev/null || true

if [[ "$SKIP_MONITORING" == "false" ]]; then
    echo ""
    echo "=== Status akhir stack monitoring ==="
    docker compose -f "${SCRIPT_DIR}/docker-compose.monitoring.yml" ps 2>/dev/null || true
fi
