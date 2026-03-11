#!/usr/bin/env bash
# ===========================================
# MER System — GitHub Actions Self-Hosted Runner Setup
# ===========================================
# Script ini mengotomatiskan pendaftaran GitHub Actions Runner
# di VPS sehingga pipeline tidak perlu SSH dari luar ke VPS.
#
# CARA KERJA:
#   Runner berjalan sebagai service systemd di VPS dengan user
#   non-root. Runner menghubungi GitHub secara OUTBOUND (polling)
#   setiap ~2 detik. Tidak ada port yang perlu dibuka ke publik.
#
# PENGGUNAAN:
#   1. Buat token di GitHub:
#      Repository > Settings > Actions > Runners > New self-hosted runner
#      Salin token yang ditampilkan (berlaku 1 jam).
#
#   2. Jalankan script ini di VPS:
#      bash /var/www/mer-system/deployment/production/setup-runner.sh
#
#   3. Script ini TIDAK perlu dijalankan ulang untuk setiap deploy.
#      Runner bekerja sebagai service latar belakang yang persisten.
#
# PRASYARAT:
#   - VPS Ubuntu 22.04 / 24.04 LTS
#   - User yang menjalankan script ini harus bisa sudo
#   - Docker Engine terinstall
#   - Repository sudah di-clone ke /var/www/mer-system
# ===========================================

set -euo pipefail

# -------------------------------------------
# Warna output
# -------------------------------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

info()    { echo -e "${CYAN}  [INFO] $1${NC}"; }
success() { echo -e "${GREEN}  [OK]   $1${NC}"; }
warn()    { echo -e "${YELLOW}  [WARN] $1${NC}"; }
error()   { echo -e "${RED}  [ERROR] $1${NC}" >&2; exit 1; }

# -------------------------------------------
# Validasi dependensi
# -------------------------------------------
command -v curl  >/dev/null 2>&1 || error "curl tidak terinstall. Jalankan: apt-get install -y curl"
command -v git   >/dev/null 2>&1 || error "git tidak terinstall."
command -v docker>/dev/null 2>&1 || error "Docker Engine tidak terinstall."

echo -e "\n${BOLD}╔══════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║  MER System — Runner Setup                ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════╝${NC}\n"

# -------------------------------------------
# Konfigurasi — edit sesuai kebutuhan
# -------------------------------------------
REPO_URL="https://github.com/firmanfarelrichardo/mer-system"
RUNNER_NAME="${RUNNER_NAME:-mer-runner-$(hostname -s)}"
RUNNER_USER="github-runner"
RUNNER_DIR="/opt/actions-runner"
RUNNER_VERSION="2.320.0"

# -------------------------------------------
# Input: token registrasi
# -------------------------------------------
if [ -z "${RUNNER_TOKEN:-}" ]; then
    echo -e "${YELLOW}Buka URL berikut untuk mendapatkan token runner:${NC}"
    echo -e "  ${REPO_URL}/settings/actions/runners/new"
    echo ""
    read -rsp "  Masukkan RUNNER_TOKEN: " RUNNER_TOKEN
    echo ""
fi

[ -z "$RUNNER_TOKEN" ] && error "RUNNER_TOKEN tidak boleh kosong."

# -------------------------------------------
# Buat user runner jika belum ada
# -------------------------------------------
info "Membuat user '${RUNNER_USER}'..."
if id "$RUNNER_USER" &>/dev/null; then
    warn "User '${RUNNER_USER}' sudah ada, skip."
else
    sudo useradd -m -s /bin/bash "$RUNNER_USER"
    success "User '${RUNNER_USER}' dibuat."
fi

# Tambahkan runner ke grup docker agar bisa run Docker tanpa sudo.
sudo usermod -aG docker "$RUNNER_USER"
success "User '${RUNNER_USER}' ditambahkan ke grup docker."

# -------------------------------------------
# Download GitHub Actions Runner
# -------------------------------------------
info "Membuat direktori runner: ${RUNNER_DIR}"
sudo mkdir -p "$RUNNER_DIR"
sudo chown "${RUNNER_USER}:${RUNNER_USER}" "$RUNNER_DIR"

ARCH=$(uname -m)
case "$ARCH" in
    x86_64)  RUNNER_ARCH="x64" ;;
    aarch64) RUNNER_ARCH="arm64" ;;
    *)       error "Arsitektur tidak didukung: ${ARCH}" ;;
esac

RUNNER_PKG="actions-runner-linux-${RUNNER_ARCH}-${RUNNER_VERSION}.tar.gz"
RUNNER_URL="https://github.com/actions/runner/releases/download/v${RUNNER_VERSION}/${RUNNER_PKG}"

if [ ! -f "${RUNNER_DIR}/run.sh" ]; then
    info "Mengunduh runner v${RUNNER_VERSION} (${RUNNER_ARCH})..."
    sudo -u "$RUNNER_USER" curl -fsSL -o "/tmp/${RUNNER_PKG}" "$RUNNER_URL"
    sudo -u "$RUNNER_USER" tar xzf "/tmp/${RUNNER_PKG}" -C "$RUNNER_DIR"
    rm -f "/tmp/${RUNNER_PKG}"
    success "Runner di-extract ke ${RUNNER_DIR}"
else
    warn "Runner sudah ada di ${RUNNER_DIR}, skip download."
fi

# -------------------------------------------
# Registrasi runner ke GitHub
# -------------------------------------------
info "Mendaftarkan runner ke ${REPO_URL}..."

sudo -u "$RUNNER_USER" "${RUNNER_DIR}/config.sh" \
    --url "$REPO_URL" \
    --token "$RUNNER_TOKEN" \
    --name "$RUNNER_NAME" \
    --labels "self-hosted,production-vps,linux" \
    --work "/opt/actions-runner/_work" \
    --unattended \
    --replace

success "Runner '${RUNNER_NAME}' berhasil didaftarkan."

# -------------------------------------------
# Install sebagai systemd service
# -------------------------------------------
info "Menginstall runner sebagai systemd service..."

sudo "${RUNNER_DIR}/svc.sh" install "$RUNNER_USER"
sudo "${RUNNER_DIR}/svc.sh" start

success "Service 'actions.runner.*' aktif."

# -------------------------------------------
# Verifikasi
# -------------------------------------------
sleep 3
if sudo systemctl is-active --quiet "$(sudo systemctl list-units --type=service | grep 'actions.runner' | awk '{print $1}' | head -1)"; then
    success "Runner berjalan dan siap menerima job."
else
    warn "Tidak dapat memverifikasi status service secara otomatis."
    info  "Cek manual: sudo systemctl status 'actions.runner.*'"
fi

echo ""
echo -e "${GREEN}${BOLD}Setup selesai.${NC}"
echo ""
echo "  Verifikasi di GitHub:"
echo "  ${REPO_URL}/settings/actions/runners"
echo ""
echo "  Runner yang terdaftar harus berstatus 'Idle' setelah"
echo "  beberapa detik. Jika masih 'Offline', cek log service:"
echo "  sudo journalctl -u 'actions.runner.*' -f"
echo ""
