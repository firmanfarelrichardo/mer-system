#!/bin/bash

###############################################################################
# MER System - Local Deployment Helper
# Semua operasi Docker dijalankan dalam scope project "mer-system"
# Tidak ada perintah yang mempengaruhi container/image/volume project lain
###############################################################################

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Detect if running in Git Bash on Windows
if [[ "$OSTYPE" == "msys" ]] || [[ "$OSTYPE" == "win32" ]]; then
    COMPOSE_DIR="/c/laragon/www/mer-system/deployment/local"
    PROJECT_DIR="/c/laragon/www/mer-system"
    DOCKER_EXEC="winpty docker exec -it"
    DOCKER_EXEC_SIMPLE="docker exec"
else
    COMPOSE_DIR="$(cd "$(dirname "$0")" && pwd)"
    PROJECT_DIR="$(dirname "$(dirname "$COMPOSE_DIR")")"
    DOCKER_EXEC="docker exec -it"
    DOCKER_EXEC_SIMPLE="docker exec"
fi

# ---------------------------------------------------------------------------
# PROJECT-SCOPED DOCKER COMPOSE
#
# -p mer-system         → set project name explicitly (label: com.docker.compose.project=mer-system)
# --project-directory   → pastikan compose selalu baca dari direktori yang benar
#   tanpa perlu cd terlebih dahulu
#
# Semua container, image, volume, dan network yang dibuat oleh $DC
# akan memiliki label com.docker.compose.project=mer-system
# sehingga bisa difilter dengan aman tanpa menyentuh project lain.
# ---------------------------------------------------------------------------
COMPOSE_PROJECT="mer-system"
DC="docker compose -p ${COMPOSE_PROJECT} --project-directory ${COMPOSE_DIR}"

# Container names
APP_CONTAINER="mer-app-staging"
WEB_CONTAINER="mer-web-staging"
VITE_CONTAINER="mer-vite-staging"
DB_CONTAINER="mer-db-staging"
REDIS_CONTAINER="mer-redis-staging"

# ---------------------------------------------------------------------------
# Helper: cek apakah container MILIK PROJECT INI sedang running
#
# Menggunakan double-filter:
#   1. label=com.docker.compose.project → hanya container project ini
#   2. name=<container_name>           → nama spesifik
#
# Ini mencegah false-positive jika ada container dari project lain
# yang kebetulan mengandung string "mer-app-dev" di namanya.
# ---------------------------------------------------------------------------
is_container_running() {
    docker ps \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --filter "name=^${1}$" \
        --format '{{.Names}}' | grep -q "."
}

# ---------------------------------------------------------------------------
# Helper: print header banner section
# ---------------------------------------------------------------------------
print_header() {
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  $1 ${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
}

# Function to show menu
show_menu() {
    clear
    echo -e "${CYAN}╔════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║                                                        ║${NC}"
    echo -e "${CYAN}║    ${BLUE}MER System - Local Development Helper${CYAN}            ║${NC}"
    echo -e "${CYAN}║    ${YELLOW}Medication Error Reporting${CYAN}                        ║${NC}"
    echo -e "${CYAN}║                                                        ║${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${YELLOW}Pilih operasi:${NC}"
    echo ""
    echo -e "  ${CYAN}--- Start/Stop ---${NC}"
    echo -e "  ${GREEN}1)${NC} Start Development (docker compose up)"
    echo -e "  ${GREEN}2)${NC} Stop Development"
    echo ""
    echo -e "  ${CYAN}--- Rebuild ---${NC}"
    echo -e "  ${GREEN}3)${NC} Clean Rebuild (Hapus semua & rebuild)"
    echo -e "  ${GREEN}4)${NC} Quick Rebuild"
    echo -e "  ${GREEN}5)${NC} Force Rebuild (No Cache - Deep Clean Build)"
    echo ""
    echo -e "  ${CYAN}--- Status & Logs ---${NC}"
    echo -e "  ${YELLOW}10)${NC} Show Container Status (Running)"
    echo -e "  ${YELLOW}11)${NC} Show All Project Containers"
    echo -e "  ${YELLOW}12)${NC} Show Project Images"
    echo -e "  ${YELLOW}13)${NC} Show Project Volumes & Networks"
    echo -e "  ${YELLOW}14)${NC} Test Endpoint"
    echo ""
    echo -e "  ${CYAN}--- Logs ---${NC}"
    echo -e "  ${YELLOW}15)${NC} Show App Logs"
    echo -e "  ${YELLOW}16)${NC} Show DB Logs"
    echo -e "  ${YELLOW}17)${NC} Show Vite Logs"
    echo -e "  ${YELLOW}18)${NC} Show All Logs"
    echo ""
    echo -e "  ${CYAN}--- Laravel Commands ---${NC}"
    echo -e "  ${GREEN}20)${NC} Run Migrations"
    echo -e "  ${GREEN}21)${NC} Fresh Migration with Seed"
    echo -e "  ${GREEN}22)${NC} Clear All Cache"
    echo -e "  ${GREEN}23)${NC} Run Artisan Command"
    echo -e "  ${GREEN}24)${NC} Access App Shell"
    echo -e "  ${YELLOW}25)${NC} Re-initialize App (composer install + key:generate + migrate)"
    echo -e "  ${GREEN}26)${NC} Install / Update Dependensi PHP (Composer Install)"
    echo ""
    echo -e "  ${CYAN}--- Frontend (Vite) ---${NC}"
    echo -e "  ${GREEN}30)${NC} NPM Install (via Vite container)"
    echo -e "  ${GREEN}31)${NC} NPM Run Build (via Vite container)"
    echo ""
    echo -e "  ${CYAN}--- Database ---${NC}"
    echo -e "  ${GREEN}40)${NC} Access PostgreSQL Shell"
    echo -e "  ${GREEN}41)${NC} Access Redis CLI"
    echo -e "  ${GREEN}42)${NC} Reset Database (Drop & Recreate)"
    echo ""
    echo -e "  ${CYAN}--- Cleanup (Project-Scoped) ---${NC}"
    echo -e "  ${RED}50)${NC} Remove Stopped Containers & Dangling Images"
    echo -e "  ${RED}51)${NC} Remove ALL Project Resources (Termasuk Volume DB)"
    echo ""
    echo -e "  ${CYAN}--- WSL Network ---${NC}"
    echo -e "  ${YELLOW}60)${NC} Show Detected Gateway IP"
    echo -e "  ${GREEN}61)${NC} Auto-Update DB_HOST to WSL Gateway IP"
    echo ""
    echo -e "  ${RED}0)${NC} Exit"
    echo ""
    echo -n "Pilihan [0-61]: "
}

# Function to show container status
show_status() {
    print_header "MER System - Project Container Status [${COMPOSE_PROJECT}]"

    echo -e "${CYAN}Scope: label=com.docker.compose.project=${COMPOSE_PROJECT}${NC}"
    echo ""

    # Tampilkan tabel hanya container milik project ini
    docker ps \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

    echo ""
    echo -e "${YELLOW}Container Details:${NC}"
    echo ""

    for container in $APP_CONTAINER $WEB_CONTAINER $VITE_CONTAINER $DB_CONTAINER $REDIS_CONTAINER; do
        if docker ps \
            --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
            --filter "name=^${container}$" \
            --format '{{.Names}}' | grep -q "."; then
            STATUS=$(docker inspect --format='{{.State.Status}}' "$container" 2>/dev/null)
            HEALTH=$(docker inspect --format='{{.State.Health.Status}}' "$container" 2>/dev/null)
            if [ "$HEALTH" != "<no value>" ] && [ -n "$HEALTH" ]; then
                echo -e "  ${GREEN}●${NC} $container: ${GREEN}$STATUS${NC} (Health: $HEALTH)"
            else
                echo -e "  ${GREEN}●${NC} $container: ${GREEN}$STATUS${NC}"
            fi
        else
            echo -e "  ${RED}○${NC} $container: ${RED}not running${NC}"
        fi
    done

    echo ""
    echo -e "${CYAN}ℹ Gunakan Option 11 untuk melihat container yang stopped${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Function to show ALL project containers (termasuk stopped)
show_all_containers() {
    print_header "MER System - All Project Containers (incl. Stopped)"

    echo -e "${CYAN}Scope: docker ps -a --filter label=com.docker.compose.project=${COMPOSE_PROJECT}${NC}"
    echo ""

    docker ps -a \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --format "table {{.Names}}\t{{.Status}}\t{{.Image}}\t{{.Ports}}"

    echo ""
    echo -e "${YELLOW}Summary:${NC}"

    RUNNING=$(docker ps \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --format '{{.Names}}' | wc -l)
    STOPPED=$(docker ps -a \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --filter "status=exited" \
        --format '{{.Names}}' | wc -l)
    TOTAL=$(docker ps -a \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --format '{{.Names}}' | wc -l)

    echo -e "  Total containers:   ${CYAN}$TOTAL${NC}"
    echo -e "  Running:            ${GREEN}$RUNNING${NC}"
    echo -e "  Stopped:            ${YELLOW}$STOPPED${NC}"

    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Function to show project images only
show_project_images() {
    print_header "MER System - Project Images [${COMPOSE_PROJECT}]"

    echo -e "${CYAN}Scope: docker compose images (hanya milik project ini)${NC}"
    echo ""

    # docker compose images hanya menampilkan image yang digunakan project ini
    $DC images

    echo ""
    echo -e "${YELLOW}Built Images (dengan label project):${NC}"
    echo ""
    docker images \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --format "table {{.Repository}}\t{{.Tag}}\t{{.ID}}\t{{.Size}}" \
        | (head -1; tail -n +2 | sort) \
        || echo -e "${YELLOW}  (Tidak ada built image dengan project label)${NC}"

    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Function to show project volumes & networks only
show_project_volumes_networks() {
    print_header "MER System - Project Volumes & Networks [${COMPOSE_PROJECT}]"

    echo -e "${YELLOW}Project Volumes:${NC}"
    echo ""
    docker volume ls \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --format "table {{.Name}}\t{{.Driver}}"

    echo ""
    echo -e "${YELLOW}Volume Size Details:${NC}"
    for vol in $(docker volume ls \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --format '{{.Name}}'); do
        SIZE=$(docker system df -v 2>/dev/null | grep "^${vol}" | awk '{print $3}')
        echo -e "  ${GREEN}●${NC} $vol: ${CYAN}${SIZE:-N/A}${NC}"
    done

    echo ""
    echo -e "${YELLOW}Project Networks:${NC}"
    echo ""
    docker network ls \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --format "table {{.Name}}\t{{.Driver}}\t{{.Scope}}"

    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Function to test endpoint
test_endpoint() {
    print_header "MER System - Health Check Status"

    # Load port from .env
    APP_PORT=$(grep "^APP_PORT=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
    APP_PORT=${APP_PORT:-8000}
    VITE_PORT=$(grep "^VITE_PORT=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
    VITE_PORT=${VITE_PORT:-5173}
    DB_PORT=$(grep "^FORWARD_DB_PORT=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
    DB_PORT=${DB_PORT:-5433}
    REDIS_PORT=$(grep "^FORWARD_REDIS_PORT=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
    REDIS_PORT=${REDIS_PORT:-6379}

    echo -e "${YELLOW}Container Status (Project: ${COMPOSE_PROJECT}):${NC}"
    for container in $APP_CONTAINER $WEB_CONTAINER $VITE_CONTAINER $DB_CONTAINER $REDIS_CONTAINER; do
        if docker ps \
            --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
            --filter "name=^${container}$" \
            --format '{{.Names}}' | grep -q "."; then
            echo -e "  ${GREEN}✓${NC} $container is running"
        else
            echo -e "  ${RED}✗${NC} $container is NOT running"
        fi
    done
    echo ""

    echo -e "${YELLOW}Service Health:${NC}"

    # Web Server
    echo -n "  Web Server (Nginx):    "
    WEB_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:$APP_PORT 2>/dev/null || echo "000")
    if [ "$WEB_STATUS" = "200" ] || [ "$WEB_STATUS" = "302" ] || [ "$WEB_STATUS" = "301" ]; then
        echo -e "${GREEN}✓ HTTP $WEB_STATUS${NC}"
    elif [ "$WEB_STATUS" = "000" ]; then
        echo -e "${RED}✗ Connection refused${NC}"
    else
        echo -e "${YELLOW}⚠ HTTP $WEB_STATUS${NC}"
    fi

    # PostgreSQL
    echo -n "  PostgreSQL Database:   "
    if docker ps \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --filter "name=^${DB_CONTAINER}$" \
        --format '{{.Names}}' | grep -q "."; then
        if docker exec "$DB_CONTAINER" pg_isready -q 2>/dev/null; then
            echo -e "${GREEN}✓ Ready (host port $DB_PORT)${NC}"
        else
            echo -e "${RED}✗ Not ready${NC}"
        fi
    else
        echo -e "${RED}✗ Container not running${NC}"
    fi

    # Redis
    echo -n "  Redis Cache/Session:   "
    if docker ps \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --filter "name=^${REDIS_CONTAINER}$" \
        --format '{{.Names}}' | grep -q "."; then
        REDIS_RESULT=$(docker exec "$REDIS_CONTAINER" redis-cli ping 2>/dev/null || echo "FAIL")
        if [ "$REDIS_RESULT" = "PONG" ]; then
            echo -e "${GREEN}✓ PONG (host port $REDIS_PORT)${NC}"
        else
            echo -e "${RED}✗ $REDIS_RESULT${NC}"
        fi
    else
        echo -e "${RED}✗ Container not running${NC}"
    fi

    # PHP-FPM
    echo -n "  PHP-FPM Application:   "
    if docker ps \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --filter "name=^${APP_CONTAINER}$" \
        --format '{{.Names}}' | grep -q "."; then
        PHP_VERSION=$(docker exec "$APP_CONTAINER" php -v 2>/dev/null | head -n1 | cut -d' ' -f2)
        if [ -n "$PHP_VERSION" ]; then
            echo -e "${GREEN}✓ PHP $PHP_VERSION${NC}"
        else
            echo -e "${YELLOW}⚠ Running but PHP check failed${NC}"
        fi
    else
        echo -e "${RED}✗ Container not running${NC}"
    fi

    # Vite
    echo -n "  Vite Dev Server:       "
    if docker ps \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --filter "name=^${VITE_CONTAINER}$" \
        --format '{{.Names}}' | grep -q "."; then
        echo -e "${GREEN}✓ Running (port $VITE_PORT)${NC}"
    else
        echo -e "${RED}✗ Container not running${NC}"
    fi

    echo ""
    echo -e "${YELLOW}Access URLs:${NC}"
    echo -e "  ${CYAN}Application:${NC}     http://localhost:$APP_PORT"
    echo -e "  ${CYAN}Vite HMR:${NC}        http://localhost:$VITE_PORT"
    echo -e "  ${CYAN}PostgreSQL:${NC}      localhost:$DB_PORT"
    echo -e "  ${CYAN}Redis:${NC}           localhost:$REDIS_PORT"
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Function to show app logs
show_app_logs() {
    echo ""
    if ! docker ps \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --filter "name=^${APP_CONTAINER}$" \
        --format '{{.Names}}' | grep -q "."; then
        echo -e "${RED}✗ App container is not running!${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi
    echo -e "${CYAN}Showing App Logs (Ctrl+C to exit)...${NC}"
    echo ""
    docker logs "$APP_CONTAINER" --tail 100 -f
}

# Function to show db logs
show_db_logs() {
    echo ""
    if ! docker ps \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --filter "name=^${DB_CONTAINER}$" \
        --format '{{.Names}}' | grep -q "."; then
        echo -e "${RED}✗ Database container is not running!${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi
    echo -e "${CYAN}Showing DB Logs (Ctrl+C to exit)...${NC}"
    echo ""
    docker logs "$DB_CONTAINER" --tail 100 -f
}

# Function to show vite logs
show_vite_logs() {
    echo ""
    if ! docker ps \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --filter "name=^${VITE_CONTAINER}$" \
        --format '{{.Names}}' | grep -q "."; then
        echo -e "${RED}✗ Vite container is not running!${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi
    echo -e "${CYAN}Showing Vite Logs (Ctrl+C to exit)...${NC}"
    echo ""
    docker logs "$VITE_CONTAINER" --tail 100 -f
}

# Function to show all project logs
show_all_logs() {
    echo ""
    echo -e "${CYAN}Showing All Project Logs (Ctrl+C to exit)...${NC}"
    echo ""
    $DC logs --tail 100 -f
}

# Function to run artisan command
run_artisan() {
    echo ""
    if ! docker ps \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --filter "name=^${APP_CONTAINER}$" \
        --format '{{.Names}}' | grep -q "."; then
        echo -e "${RED}✗ App container is not running!${NC}"
        echo -e "${YELLOW}Please start the development environment first (Option 1)${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi

    echo -e "${YELLOW}Common Artisan Commands:${NC}"
    echo -e "  ${CYAN}migrate${NC}              - Run database migrations"
    echo -e "  ${CYAN}migrate:fresh --seed${NC} - Fresh migration with seeders"
    echo -e "  ${CYAN}db:seed${NC}              - Run database seeders"
    echo -e "  ${CYAN}tinker${NC}               - Interactive REPL"
    echo -e "  ${CYAN}route:list${NC}           - List all routes"
    echo -e "  ${CYAN}config:clear${NC}         - Clear config cache"
    echo -e "  ${CYAN}cache:clear${NC}          - Clear application cache"
    echo -e "  ${CYAN}queue:work${NC}           - Process queue jobs"
    echo -e "  ${CYAN}make:controller Name${NC} - Generate controller"
    echo -e "  ${CYAN}make:model Name${NC}      - Generate model"
    echo ""
    echo -e "${YELLOW}Enter artisan command (or 'q' to cancel):${NC}"
    read -p "php artisan " artisan_cmd

    if [ -n "$artisan_cmd" ] && [ "$artisan_cmd" != "q" ]; then
        echo ""
        echo -e "${CYAN}Running: php artisan $artisan_cmd${NC}"
        echo ""
        $DOCKER_EXEC "$APP_CONTAINER" php artisan $artisan_cmd
    else
        echo -e "${YELLOW}Cancelled.${NC}"
    fi

    echo ""
    read -p "Press Enter to continue..."
}

# ---------------------------------------------------------------------------
# app_init: Jalankan composer install + key:generate + migrate
#           di container yang sudah berjalan.
#           Dipanggil otomatis setelah `up -d` di options 1, 3, 4, 5.
#           dan bisa dijalankan manual via Option 25.
# ---------------------------------------------------------------------------
app_init() {
    echo ""
    echo -e "${CYAN}Waiting for app container to be ready...${NC}"

    # Tunggu hingga container berstatus running (max 60 detik)
    local waited=0
    while ! docker ps \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --filter "name=^${APP_CONTAINER}$" \
        --format '{{.Names}}' 2>/dev/null | grep -q "."; do
        sleep 2
        waited=$((waited + 2))
        if [ "$waited" -ge 180 ]; then
            echo -e "${RED}✗ App container did not start within 180s. Check logs: Option 15.${NC}"
            return 1
        fi
        echo -n "."
    done
    echo ""

    echo -e "${GREEN}✓ Container is running — running initialization tasks...${NC}"
    echo ""

    # 1. Composer install
    if docker exec "$APP_CONTAINER" test -f vendor/autoload.php 2>/dev/null; then
        echo -e "  ${GREEN}✓${NC} composer install  ${CYAN}(vendor sudah ada, skip)${NC}"
    else
        echo -e "  ${YELLOW}➤${NC} composer install ..."
        docker exec "$APP_CONTAINER" composer install --no-interaction --prefer-dist --optimize-autoloader
        echo -e "  ${GREEN}✓${NC} composer install selesai"
    fi

    # 2. key:generate
    APP_KEY_VAL=$(docker exec "$APP_CONTAINER" sh -c 'grep "^APP_KEY=" .env 2>/dev/null | cut -d= -f2' 2>/dev/null | tr -d '\r')
    if [ -n "$APP_KEY_VAL" ]; then
        echo -e "  ${GREEN}✓${NC} APP_KEY         ${CYAN}(sudah ada, skip)${NC}"
    else
        echo -e "  ${YELLOW}➤${NC} php artisan key:generate ..."
        docker exec "$APP_CONTAINER" php artisan key:generate --no-interaction --force
        echo -e "  ${GREEN}✓${NC} APP_KEY generated"
    fi

    # 3. migrate
    echo -e "  ${YELLOW}➤${NC} php artisan migrate ..."
    if docker exec "$APP_CONTAINER" php artisan migrate --no-interaction --force; then
        echo -e "  ${GREEN}✓${NC} migrate selesai"
    else
        echo -e "  ${RED}✗${NC} migrate gagal — cek logs: Option 15"
        return 1
    fi

    echo ""
    echo -e "${GREEN}✓ App initialization complete!${NC}"
}

# ---------------------------------------------------------------------------
# Helper: Deteksi IP gateway WSL (= IP host Windows/macOS dilihat dari WSL)
#
# MENGAPA DIBUTUHKAN:
#   Di WSL2, setiap restart WSL/komputer, IP gateway berubah secara dinamis
#   (contoh: 172.24.80.1, 172.28.208.1, dst). Jika Laravel/PHP dijalankan
#   langsung di host WSL (bukan di dalam container) — misalnya saat debug
#   atau menjalankan `php artisan` dari terminal WSL — DB_HOST harus berisi
#   IP gateway ini agar bisa mencapai PostgreSQL yang port-nya di-forward
#   ke host oleh Docker.
#
# URUTAN DETEKSI:
#   1. ip route show default  → paling reliable di WSL2
#   2. /etc/resolv.conf       → fallback WSL1/WSL2 (DNS = gateway)
#   3. host.docker.internal   → fallback Docker Desktop
# ---------------------------------------------------------------------------
detect_wsl_gateway() {
    local ip=""

    # Method 1: default route
    if command -v ip &>/dev/null; then
        ip=$(ip route show default 2>/dev/null | awk '/default/ {print $3; exit}')
    fi

    # Method 2: nameserver dari /etc/resolv.conf
    if [ -z "$ip" ] && [ -f /etc/resolv.conf ]; then
        ip=$(grep -m1 "^nameserver" /etc/resolv.conf 2>/dev/null | awk '{print $2}')
    fi

    # Method 3: host.docker.internal (Docker Desktop adds this to /etc/hosts)
    if [ -z "$ip" ]; then
        ip=$(getent hosts host.docker.internal 2>/dev/null | awk '{print $1; exit}')
    fi

    echo "$ip"
}

# Function to show detected gateway IP and current DB_HOST in all .env files
show_gateway_ip() {
    print_header "WSL Gateway IP Detection"

    echo -e "${YELLOW}Mendeteksi IP gateway WSL (Windows/macOS host IP)...${NC}"
    echo ""

    # Jalankan tiap method secara terpisah untuk ditampilkan
    GW_ROUTE=""
    if command -v ip &>/dev/null; then
        GW_ROUTE=$(ip route show default 2>/dev/null | awk '/default/ {print $3; exit}')
    fi

    GW_RESOLV=""
    if [ -f /etc/resolv.conf ]; then
        GW_RESOLV=$(grep -m1 "^nameserver" /etc/resolv.conf 2>/dev/null | awk '{print $2}')
    fi

    GW_DOCKER=$(getent hosts host.docker.internal 2>/dev/null | awk '{print $1; exit}')

    echo -e "${YELLOW}Detection Methods:${NC}"
    printf "  %-36s %s\n" "ip route (Method 1):" "${CYAN}${GW_ROUTE:-<not detected>}${NC}"
    printf "  %-36s %s\n" "/etc/resolv.conf (Method 2):" "${CYAN}${GW_RESOLV:-<not detected>}${NC}"
    printf "  %-36s %s\n" "host.docker.internal (Method 3):" "${CYAN}${GW_DOCKER:-<not detected>}${NC}"
    echo ""

    GATEWAY=$(detect_wsl_gateway)
    if [ -n "$GATEWAY" ]; then
        echo -e "${GREEN}✓ Detected Gateway IP: ${CYAN}${GATEWAY}${NC}"
    else
        echo -e "${RED}✗ Tidak dapat mendeteksi gateway IP${NC}"
        echo -e "${YELLOW}  Kemungkinan bukan environment WSL, atau perintah 'ip' tidak tersedia.${NC}"
    fi

    echo ""
    echo -e "${YELLOW}Status DB_HOST di semua file .env project:${NC}"
    echo ""

    FOUND=0
    for f in "$PROJECT_DIR/.env" "$COMPOSE_DIR/.env" "$PROJECT_DIR/deployment/production/.env"; do
        if [ -f "$f" ] && grep -q "^DB_HOST=" "$f" 2>/dev/null; then
            CURRENT=$(grep "^DB_HOST=" "$f" | head -1 | cut -d'=' -f2 | tr -d '\r')
            DISPLAY_PATH="${f#$PROJECT_DIR/}"
            if [ "$CURRENT" = "$GATEWAY" ] && [ -n "$GATEWAY" ]; then
                echo -e "  ${GREEN}✓${NC} ${CYAN}${DISPLAY_PATH}${NC}  DB_HOST=${GREEN}${CURRENT}${NC} (up-to-date)"
            else
                echo -e "  ${YELLOW}→${NC} ${CYAN}${DISPLAY_PATH}${NC}  DB_HOST=${YELLOW}${CURRENT}${NC}"
            fi
            FOUND=$((FOUND + 1))
        fi
    done

    if [ "$FOUND" -eq 0 ]; then
        echo -e "  ${YELLOW}(Tidak ada file .env dengan DB_HOST ditemukan)${NC}"
    fi

    echo ""
    echo -e "${CYAN}ℹ Gunakan Option 61 untuk auto-update DB_HOST ke gateway IP di atas.${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Function to auto-update DB_HOST in all .env files to WSL gateway IP
update_db_host_ip() {
    print_header "Auto-Update DB_HOST to WSL Gateway IP"

    GATEWAY=$(detect_wsl_gateway)

    if [ -z "$GATEWAY" ]; then
        echo -e "${RED}✗ Tidak dapat mendeteksi WSL gateway IP!${NC}"
        echo -e "${YELLOW}Pastikan Anda menjalankan script ini di dalam WSL2.${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi

    echo -e "${GREEN}✓ Detected Gateway IP: ${CYAN}${GATEWAY}${NC}"
    echo ""

    # Kumpulkan semua .env files yang memiliki DB_HOST=
    ENV_FILES=()
    for f in "$PROJECT_DIR/.env" "$COMPOSE_DIR/.env" "$PROJECT_DIR/deployment/production/.env"; do
        if [ -f "$f" ] && grep -q "^DB_HOST=" "$f" 2>/dev/null; then
            ENV_FILES+=("$f")
        fi
    done

    if [ ${#ENV_FILES[@]} -eq 0 ]; then
        echo -e "${YELLOW}Tidak ada file .env dengan DB_HOST ditemukan.${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 0
    fi

    echo -e "${YELLOW}Perubahan yang akan dilakukan:${NC}"
    echo ""
    NEEDS_UPDATE=0
    for f in "${ENV_FILES[@]}"; do
        CURRENT=$(grep "^DB_HOST=" "$f" | head -1 | cut -d'=' -f2 | tr -d '\r')
        DISPLAY_PATH="${f#$PROJECT_DIR/}"
        if [ "$CURRENT" = "$GATEWAY" ]; then
            echo -e "  ${GREEN}✓${NC} ${CYAN}${DISPLAY_PATH}${NC}  DB_HOST=${CURRENT} ${GREEN}(sudah up-to-date, skip)${NC}"
        else
            echo -e "  ${YELLOW}→${NC} ${CYAN}${DISPLAY_PATH}${NC}  ${RED}${CURRENT}${NC} → ${GREEN}${GATEWAY}${NC}"
            NEEDS_UPDATE=$((NEEDS_UPDATE + 1))
        fi
    done
    echo ""

    if [ "$NEEDS_UPDATE" -eq 0 ]; then
        echo -e "${GREEN}✓ Semua file sudah menggunakan IP yang benar. Tidak ada yang perlu diupdate.${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 0
    fi

    read -p "Lanjutkan update $NEEDS_UPDATE file? (y/n): " confirm

    if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
        echo ""
        UPDATED=0
        for f in "${ENV_FILES[@]}"; do
            CURRENT=$(grep "^DB_HOST=" "$f" | head -1 | cut -d'=' -f2 | tr -d '\r')
            DISPLAY_PATH="${f#$PROJECT_DIR/}"
            if [ "$CURRENT" = "$GATEWAY" ]; then
                echo -e "  ${GREEN}✓ Skip${NC}    ${DISPLAY_PATH}"
            else
                # Buat backup sebelum edit
                cp "$f" "${f}.bak"
                # sed -i: ganti baris DB_HOST= secara in-place
                sed -i "s|^DB_HOST=.*|DB_HOST=${GATEWAY}|" "$f"
                echo -e "  ${GREEN}✓ Updated${NC} ${DISPLAY_PATH}  (backup: ${DISPLAY_PATH}.bak)"
                UPDATED=$((UPDATED + 1))
            fi
        done
        echo ""
        echo -e "${GREEN}✓ Selesai! ${UPDATED} file diupdate.${NC}"
        if [ "$UPDATED" -gt 0 ]; then
            echo ""
            echo -e "${YELLOW}Jika container sedang berjalan, restart diperlukan agar perubahan aktif:${NC}"
            echo -e "  ${CYAN}Option 2 (Stop Development) → Option 1 (Start Development)${NC}"
        fi
    else
        echo ""
        echo -e "${GREEN}✓ Operation cancelled. No changes made.${NC}"
    fi

    echo ""
    read -p "Press Enter to continue..."
}

# Check .env file
check_env() {
    if [ ! -f "$COMPOSE_DIR/.env" ]; then
        echo -e "${YELLOW}Creating .env file from .env.example...${NC}"
        cp "$COMPOSE_DIR/.env.example" "$COMPOSE_DIR/.env"
        echo -e "${GREEN}✓ .env file created. Please review and update if needed.${NC}"
        read -p "Press Enter to continue..."
    fi
    return 0
}

# Check nginx config
check_nginx_config() {
    local nginx_conf="$COMPOSE_DIR/nginx/default.conf"
    if [ ! -f "$nginx_conf" ]; then
        echo -e "${YELLOW}Nginx config not found at: $nginx_conf${NC}"
        echo -e "${YELLOW}Generating default Laravel nginx config...${NC}"
        mkdir -p "$COMPOSE_DIR/nginx"
        cat > "$nginx_conf" << 'NGINX_EOF'
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php index.html;

    charset utf-8;
    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX_EOF
        echo -e "${GREEN}✓ Default nginx config created at: $nginx_conf${NC}"
        echo -e "${CYAN}ℹ Anda bisa mengedit file ini sesuai kebutuhan.${NC}"
    fi
    return 0
}

# Main loop
while true; do
    show_menu
    read choice

    case $choice in
        # -------------------------------------------------------------------
        # Start/Stop
        # -------------------------------------------------------------------
        1)
            check_env || continue
            check_nginx_config || continue
            echo ""
            echo -e "${GREEN}Starting MER System Development Environment...${NC}"
            $DC up -d

            APP_PORT=$(grep "^APP_PORT=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
            APP_PORT=${APP_PORT:-8000}
            VITE_PORT=$(grep "^VITE_PORT=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
            VITE_PORT=${VITE_PORT:-5173}

            app_init

            echo ""
            echo -e "${GREEN}✓ Development environment started!${NC}"
            echo ""
            echo -e "${YELLOW}Services:${NC}"
            echo -e "  App (Nginx):    http://localhost:$APP_PORT"
            echo -e "  Vite HMR:       http://localhost:$VITE_PORT"
            echo -e "  PostgreSQL:     localhost:5433"
            echo -e "  Redis:          localhost:6379"
            echo ""
            read -p "Press Enter to continue..."
            ;;
        2)
            echo ""
            echo -e "${YELLOW}Stopping MER System Development Environment...${NC}"
            $DC down
            echo -e "${GREEN}✓ Development environment stopped!${NC}"
            read -p "Press Enter to continue..."
            ;;

        # -------------------------------------------------------------------
        # Rebuild
        # -------------------------------------------------------------------
        3)
            check_env || continue
            check_nginx_config || continue
            echo ""
            echo -e "${RED}Clean Rebuild - This will remove ALL project containers, volumes, and rebuild!${NC}"
            echo -e "${RED}WARNING: Database data will be LOST!${NC}"
            read -p "Are you sure? (y/n): " confirm
            if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
                $DC down -v --rmi local
                $DC up -d
                app_init
                echo -e "${GREEN}✓ Clean rebuild complete!${NC}"
            fi
            read -p "Press Enter to continue..."
            ;;
        4)
            check_env || continue
            check_nginx_config || continue
            echo ""
            echo -e "${GREEN}Quick Rebuild (preserving data)...${NC}"
            $DC up -d --build
            app_init
            echo -e "${GREEN}✓ Quick rebuild complete!${NC}"
            read -p "Press Enter to continue..."
            ;;
        5)
            check_env || continue
            check_nginx_config || continue
            echo ""
            echo -e "${RED}Force Rebuild - This will rebuild ALL project images WITHOUT cache!${NC}"
            echo -e "${YELLOW}Containers will restart but database volume will be preserved.${NC}"
            echo ""
            read -p "Are you sure? (y/n): " confirm
            if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
                echo -e "${YELLOW}Stopping project containers...${NC}"
                $DC down

                echo -e "${YELLOW}Building images without cache...${NC}"
                $DC build --no-cache

                echo -e "${YELLOW}Starting services...${NC}"
                $DC up -d

                app_init

                echo ""
                echo -e "${GREEN}✓ Force rebuild completed successfully!${NC}"
                echo -e "${CYAN}All images rebuilt from scratch.${NC}"
            fi
            echo ""
            read -p "Press Enter to continue..."
            ;;

        # -------------------------------------------------------------------
        # Status & Logs
        # -------------------------------------------------------------------
        10)
            show_status
            ;;
        11)
            show_all_containers
            ;;
        12)
            show_project_images
            ;;
        13)
            show_project_volumes_networks
            ;;
        14)
            test_endpoint
            ;;

        # -------------------------------------------------------------------
        # Logs
        # -------------------------------------------------------------------
        15)
            show_app_logs
            ;;
        16)
            show_db_logs
            ;;
        17)
            show_vite_logs
            ;;
        18)
            show_all_logs
            ;;

        # -------------------------------------------------------------------
        # Laravel Commands
        # -------------------------------------------------------------------
        20)
            echo ""
            if ! docker ps \
                --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
                --filter "name=^${APP_CONTAINER}$" \
                --format '{{.Names}}' | grep -q "."; then
                echo -e "${RED}✗ App container is not running!${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi

            # Cek vendor/autoload.php sebelum artisan bisa dijalankan
            if ! docker exec "$APP_CONTAINER" test -f vendor/autoload.php 2>/dev/null; then
                echo -e "${RED}✗ vendor/autoload.php not found!${NC}"
                echo ""
                echo -e "${YELLOW}Jalankan composer install terlebih dahulu:${NC}"
                echo -e "  ${CYAN}docker exec -it ${APP_CONTAINER} composer install${NC}"
                echo ""
                read -p "Jalankan composer install sekarang? (y/n): " do_install
                if [ "$do_install" = "y" ] || [ "$do_install" = "Y" ]; then
                    echo ""
                    docker exec "$APP_CONTAINER" composer install
                    echo ""
                else
                    read -p "Press Enter to continue..."
                    continue
                fi
            fi

            echo -e "${GREEN}Running Database Migrations...${NC}"
            echo -e "${CYAN}This will run pending migrations${NC}"
            echo ""
            if docker exec "$APP_CONTAINER" php artisan migrate; then
                echo ""
                echo -e "${GREEN}✓ Migrations complete${NC}"
            else
                echo ""
                echo -e "${RED}✗ Migration failed (lihat error di atas)${NC}"
            fi
            read -p "Press Enter to continue..."
            ;;
        21)
            echo ""
            if ! docker ps \
                --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
                --filter "name=^${APP_CONTAINER}$" \
                --format '{{.Names}}' | grep -q "."; then
                echo -e "${RED}✗ App container is not running!${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi

            echo -e "${RED}╔════════════════════════════════════════════════════════╗${NC}"
            echo -e "${RED}║         WARNING: FRESH MIGRATION OPERATION             ║${NC}"
            echo -e "${RED}╚════════════════════════════════════════════════════════╝${NC}"
            echo ""
            echo -e "${RED}This will DROP all tables and re-run ALL migrations!${NC}"
            echo -e "${RED}ALL DATA will be PERMANENTLY DELETED!${NC}"
            echo ""
            read -p "Type 'FRESH' to confirm (or anything else to cancel): " confirm

            if [ "$confirm" = "FRESH" ]; then
                # Cek vendor/autoload.php sebelum artisan bisa dijalankan
                if ! docker exec "$APP_CONTAINER" test -f vendor/autoload.php 2>/dev/null; then
                    echo ""
                    echo -e "${RED}✗ vendor/autoload.php not found!${NC}"
                    echo -e "${YELLOW}Jalankan: docker exec -it ${APP_CONTAINER} composer install${NC}"
                    echo ""
                    read -p "Press Enter to continue..."
                    continue
                fi

                echo ""
                echo -e "${YELLOW}Running fresh migration with seeders...${NC}"
                if docker exec "$APP_CONTAINER" php artisan migrate:fresh --seed; then
                    echo ""
                    echo -e "${GREEN}✓ Fresh migration complete with seed data${NC}"
                else
                    echo ""
                    echo -e "${RED}✗ Fresh migration failed (lihat error di atas)${NC}"
                fi
            else
                echo ""
                echo -e "${GREEN}✓ Operation cancelled${NC}"
            fi
            echo ""
            read -p "Press Enter to continue..."
            ;;
        22)
            echo ""
            if ! docker ps \
                --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
                --filter "name=^${APP_CONTAINER}$" \
                --format '{{.Names}}' | grep -q "."; then
                echo -e "${RED}✗ App container is not running!${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi

            echo -e "${CYAN}Clearing All Laravel Cache...${NC}"
            echo ""
            echo -n "  Config cache...      "
            docker exec "$APP_CONTAINER" php artisan config:clear 2>/dev/null && echo -e "${GREEN}✓${NC}" || echo -e "${RED}✗${NC}"
            echo -n "  Route cache...       "
            docker exec "$APP_CONTAINER" php artisan route:clear 2>/dev/null && echo -e "${GREEN}✓${NC}" || echo -e "${RED}✗${NC}"
            echo -n "  View cache...        "
            docker exec "$APP_CONTAINER" php artisan view:clear 2>/dev/null && echo -e "${GREEN}✓${NC}" || echo -e "${RED}✗${NC}"
            echo -n "  Application cache... "
            docker exec "$APP_CONTAINER" php artisan cache:clear 2>/dev/null && echo -e "${GREEN}✓${NC}" || echo -e "${RED}✗${NC}"
            echo ""
            echo -e "${GREEN}✓ All caches cleared${NC}"
            echo ""
            read -p "Press Enter to continue..."
            ;;
        23)
            run_artisan
            ;;
        24)
            echo ""
            if ! docker ps \
                --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
                --filter "name=^${APP_CONTAINER}$" \
                --format '{{.Names}}' | grep -q "."; then
                echo -e "${RED}✗ App container is not running!${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi

            echo -e "${CYAN}Accessing App Container Shell...${NC}"
            echo -e "${YELLOW}You are now in the Laravel application container${NC}"
            echo -e "${YELLOW}Working directory: /var/www/html${NC}"
            echo -e "${YELLOW}Type 'exit' to return to the menu${NC}"
            echo ""
            $DOCKER_EXEC "$APP_CONTAINER" bash
            ;;

        25)
            echo ""
            if ! docker ps \
                --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
                --filter "name=^${APP_CONTAINER}$" \
                --format '{{.Names}}' | grep -q "."; then
                echo -e "${RED}✗ App container is not running! Start dulu dengan Option 1.${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi
            print_header "Re-initialize App"
            app_init
            echo ""
            read -p "Press Enter to continue..."
            ;;

        26)
            # -----------------------------------------------------------
            # Install / Update Dependensi PHP (Composer Install)
            # Berguna sebagai "Sapu Jagat" untuk menyinkronkan ekstensi
            # PHP baru (DOMPDF, Excel, dll) ke seluruh anggota tim.
            # -----------------------------------------------------------
            print_header "Install / Update Dependensi PHP (Composer Install)"

            # Safety Check: pastikan container app sedang berjalan
            if ! is_container_running "${APP_CONTAINER}"; then
                echo -e "${RED}ERROR: Container tidak berjalan.${NC}"
                echo -e "${RED}Silakan pilih menu 'Start Development' terlebih dahulu.${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi

            echo -e "${YELLOW}Menyinkronkan dependensi PHP...${NC}"
            echo -e "${CYAN}  Menjalankan: composer install --no-interaction --prefer-dist --optimize-autoloader${NC}"
            echo ""

            if docker exec "$APP_CONTAINER" \
                composer install --no-interaction --prefer-dist --optimize-autoloader; then
                echo ""
                echo -e "${GREEN}✓ Composer install selesai.${NC}"
            else
                echo ""
                echo -e "${RED}✗ Composer install gagal — periksa output di atas.${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi

            # Cache Clearing: daftarkan class/alias baru ke autoload Laravel
            echo ""
            echo -e "${YELLOW}Membersihkan cache...${NC}"
            echo -e "${CYAN}  Menjalankan: php artisan optimize:clear${NC}"
            echo ""

            if docker exec "$APP_CONTAINER" php artisan optimize:clear; then
                echo ""
                echo -e "${GREEN}✓ Berhasil! Dependensi diperbarui dan cache dibersihkan.${NC}"
                echo -e "${CYAN}  Class dan alias baru (DOMPDF, Excel, dll) sudah terdaftar.${NC}"
            else
                echo ""
                echo -e "${YELLOW}⚠ Cache clear gagal — jalankan Option 22 secara manual.${NC}"
            fi

            echo ""
            read -p "Press Enter to continue..."
            ;;

        # -------------------------------------------------------------------
        # Frontend (Vite)
        # Semua operasi NPM dialihkan ke container 'vite' (node:22-alpine)
        # karena PHP-FPM container TIDAK memiliki Node.js
        # -------------------------------------------------------------------
        30)
            echo ""
            if ! docker ps \
                --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
                --filter "name=^${VITE_CONTAINER}$" \
                --format '{{.Names}}' | grep -q "."; then
                echo -e "${RED}✗ Vite container is not running!${NC}"
                echo -e "${YELLOW}Start the environment first (Option 1)${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi

            echo -e "${GREEN}Running NPM Install (via Vite container)...${NC}"
            echo -e "${CYAN}Container: ${VITE_CONTAINER}${NC}"
            echo ""
            docker exec "$VITE_CONTAINER" npm install
            echo ""
            echo -e "${GREEN}✓ NPM install complete${NC}"
            read -p "Press Enter to continue..."
            ;;
        31)
            echo ""
            if ! docker ps \
                --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
                --filter "name=^${VITE_CONTAINER}$" \
                --format '{{.Names}}' | grep -q "."; then
                echo -e "${RED}✗ Vite container is not running!${NC}"
                echo -e "${YELLOW}Start the environment first (Option 1)${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi

            echo -e "${GREEN}Running NPM Build (via Vite container)...${NC}"
            echo -e "${CYAN}This will build production assets with Vite${NC}"
            echo ""
            docker exec "$VITE_CONTAINER" npm run build
            echo ""
            echo -e "${GREEN}✓ Build complete - assets ready for production${NC}"
            read -p "Press Enter to continue..."
            ;;

        # -------------------------------------------------------------------
        # Database
        # -------------------------------------------------------------------
        40)
            echo ""
            if ! docker ps \
                --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
                --filter "name=^${DB_CONTAINER}$" \
                --format '{{.Names}}' | grep -q "."; then
                echo -e "${RED}✗ Database container is not running!${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi

            echo -e "${CYAN}Accessing PostgreSQL Shell...${NC}"
            echo ""
            echo -e "${YELLOW}Database Schemas:${NC}"
            echo -e "  - ${CYAN}tenant${NC}      - Multi-tenant organization data"
            echo -e "  - ${CYAN}akun${NC}        - User accounts and permissions"
            echo -e "  - ${CYAN}master${NC}      - Master data (categories, matrices)"
            echo -e "  - ${CYAN}pelaporan${NC}   - Incident reporting"
            echo -e "  - ${CYAN}audit${NC}       - Audit trails"
            echo ""
            echo -e "${YELLOW}Common Commands:${NC}"
            echo -e "  ${CYAN}\\dt${NC}              - List tables in current schema"
            echo -e "  ${CYAN}\\dt akun.*${NC}       - List tables in 'akun' schema"
            echo -e "  ${CYAN}\\l${NC}               - List all databases"
            echo -e "  ${CYAN}\\dn${NC}              - List all schemas"
            echo -e "  ${CYAN}\\q${NC}               - Quit psql"
            echo ""

            DB_DATABASE=$(grep "^DB_DATABASE=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
            DB_USERNAME=$(grep "^DB_USERNAME=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')

            echo -e "${GREEN}Connecting to: ${DB_DATABASE:-mer_system}${NC}"
            echo ""
            $DOCKER_EXEC "$DB_CONTAINER" psql -U "${DB_USERNAME:-postgres}" -d "${DB_DATABASE:-mer_system}"
            ;;
        41)
            echo ""
            if ! docker ps \
                --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
                --filter "name=^${REDIS_CONTAINER}$" \
                --format '{{.Names}}' | grep -q "."; then
                echo -e "${RED}✗ Redis container is not running!${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi

            echo -e "${CYAN}Accessing Redis CLI...${NC}"
            echo ""
            echo -e "${YELLOW}Common Commands:${NC}"
            echo -e "  ${CYAN}PING${NC}                - Test connection"
            echo -e "  ${CYAN}KEYS *${NC}              - List all keys (use cautiously)"
            echo -e "  ${CYAN}GET key${NC}             - Get value of key"
            echo -e "  ${CYAN}SET key value${NC}       - Set key value"
            echo -e "  ${CYAN}DEL key${NC}             - Delete key"
            echo -e "  ${CYAN}FLUSHDB${NC}             - Clear current database"
            echo -e "  ${CYAN}INFO${NC}                - Server information"
            echo -e "  ${CYAN}DBSIZE${NC}              - Number of keys"
            echo -e "  ${CYAN}quit${NC}                - Exit redis-cli"
            echo ""
            echo -e "${GREEN}Connecting to Redis...${NC}"
            echo ""
            $DOCKER_EXEC "$REDIS_CONTAINER" redis-cli
            ;;
        42)
            echo ""
            echo -e "${RED}╔════════════════════════════════════════════════════════╗${NC}"
            echo -e "${RED}║         WARNING: DATABASE RESET OPERATION              ║${NC}"
            echo -e "${RED}╚════════════════════════════════════════════════════════╝${NC}"
            echo ""
            echo -e "${RED}This will:${NC}"
            echo -e "  ${YELLOW}1.${NC} Stop all project services"
            echo -e "  ${YELLOW}2.${NC} Remove the PostgreSQL data volume"
            echo -e "  ${YELLOW}3.${NC} Restart services"
            echo ""
            echo -e "${RED}ALL DATA INCLUDING:${NC}"
            echo -e "  - All database tables and records"
            echo -e "  - All schemas (tenant, akun, master, pelaporan, audit)"
            echo -e "  - All custom changes"
            echo ""
            echo -e "${RED}WILL BE PERMANENTLY DESTROYED!${NC}"
            echo ""
            read -p "Type 'RESET' to confirm (or anything else to cancel): " confirm

            if [ "$confirm" = "RESET" ]; then
                echo ""
                echo -e "${YELLOW}[1/4] Stopping project services...${NC}"
                $DC down

                echo -e "${YELLOW}[2/4] Removing project database volume...${NC}"
                # Filter hanya volume milik project ini
                VOLUME_NAME=$(docker volume ls \
                    --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
                    --filter "name=mer-db-data" \
                    --format '{{.Name}}')
                if [ -n "$VOLUME_NAME" ]; then
                    docker volume rm "$VOLUME_NAME" 2>/dev/null \
                        && echo -e "${GREEN}  ✓ Volume $VOLUME_NAME removed${NC}" \
                        || echo -e "${YELLOW}  ⚠ Volume not found or in use${NC}"
                else
                    echo -e "${YELLOW}  ⚠ No project database volume found${NC}"
                fi

                echo -e "${YELLOW}[3/4] Starting database service...${NC}"
                $DC up -d db

                echo -e "${YELLOW}[4/4] Starting all services...${NC}"
                $DC up -d

                echo ""
                echo -e "${GREEN}✓ Database reset complete!${NC}"
                echo -e "${CYAN}DDL scripts and migrations will run automatically.${NC}"
                echo ""
                echo -e "${YELLOW}You may want to run:${NC}"
                echo -e "  - Option 20: Run Migrations"
                echo -e "  - Option 21: Fresh Migration with Seed"
            else
                echo ""
                echo -e "${GREEN}✓ Operation cancelled. No changes made.${NC}"
            fi
            echo ""
            read -p "Press Enter to continue..."
            ;;

        # -------------------------------------------------------------------
        # Cleanup — Project-Scoped
        #
        # AMAN: Hanya menghapus resource yang memiliki label
        #       com.docker.compose.project=mer-system
        #
        # Tidak menggunakan:
        #   × docker container prune  → menghapus semua stopped container
        #   × docker image prune       → menghapus semua dangling image
        #   × docker volume prune      → menghapus semua unused volume
        #   × docker system prune      → menghapus semua resource sistem
        # -------------------------------------------------------------------
        50)
            echo ""
            echo -e "${YELLOW}╔════════════════════════════════════════════════════════╗${NC}"
            echo -e "${YELLOW}║     Cleanup Project Resources [${COMPOSE_PROJECT}]           ║${NC}"
            echo -e "${YELLOW}╚════════════════════════════════════════════════════════╝${NC}"
            echo ""
            echo -e "${CYAN}Hanya membersihkan resource milik project '${COMPOSE_PROJECT}':${NC}"
            echo -e "  - Stopped containers (project ini saja)"
            echo -e "  - Dangling images (built images project ini saja)"
            echo ""
            echo -e "${GREEN}AMAN: Tidak menyentuh project Docker lain di komputer ini.${NC}"
            echo -e "${GREEN}AMAN: Volume database TIDAK dihapus.${NC}"
            echo ""
            read -p "Continue? (y/n): " confirm

            if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
                echo ""

                echo -e "${YELLOW}[1/2] Removing stopped project containers...${NC}"
                STOPPED_IDS=$(docker ps -a \
                    --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
                    --filter "status=exited" \
                    --format '{{.ID}}')
                if [ -n "$STOPPED_IDS" ]; then
                    echo "$STOPPED_IDS" | xargs docker rm \
                        && echo -e "${GREEN}  ✓ Stopped containers removed${NC}" \
                        || echo -e "${YELLOW}  ⚠ Some containers could not be removed${NC}"
                else
                    echo -e "${GREEN}  ✓ No stopped containers found${NC}"
                fi

                echo -e "${YELLOW}[2/2] Removing dangling project images...${NC}"
                DANGLING_IDS=$(docker images \
                    --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
                    --filter "dangling=true" \
                    --format '{{.ID}}')
                if [ -n "$DANGLING_IDS" ]; then
                    echo "$DANGLING_IDS" | xargs docker rmi \
                        && echo -e "${GREEN}  ✓ Dangling images removed${NC}" \
                        || echo -e "${YELLOW}  ⚠ Some images could not be removed (mungkin masih digunakan)${NC}"
                else
                    echo -e "${GREEN}  ✓ No dangling project images found${NC}"
                fi

                echo ""
                echo -e "${GREEN}✓ Project cleanup complete!${NC}"
                echo -e "${CYAN}ℹ Gunakan Option 51 jika ingin hapus semua termasuk volume.${NC}"
            else
                echo ""
                echo -e "${GREEN}✓ Operation cancelled${NC}"
            fi
            echo ""
            read -p "Press Enter to continue..."
            ;;
        51)
            echo ""
            echo -e "${RED}╔════════════════════════════════════════════════════════╗${NC}"
            echo -e "${RED}║       WARNING: COMPLETE PROJECT REMOVAL                ║${NC}"
            echo -e "${RED}╚════════════════════════════════════════════════════════╝${NC}"
            echo ""
            echo -e "${RED}Ini akan PERMANEN menghapus semua resource project '${COMPOSE_PROJECT}':${NC}"
            echo -e "  ${YELLOW}✗${NC} All MER System containers"
            echo -e "  ${YELLOW}✗${NC} All MER System images (built)"
            echo -e "  ${YELLOW}✗${NC} All MER System volumes (TERMASUK DATABASE)"
            echo -e "  ${YELLOW}✗${NC} All MER System networks"
            echo ""
            echo -e "${GREEN}AMAN: Project Docker lain TIDAK akan terpengaruh.${NC}"
            echo -e "${RED}DATA DATABASE AKAN HILANG SELAMANYA!${NC}"
            echo ""
            read -p "Type 'DELETE ALL' to confirm (or anything else to cancel): " confirm

            if [ "$confirm" = "DELETE ALL" ]; then
                echo ""
                echo -e "${YELLOW}Removing all '${COMPOSE_PROJECT}' resources...${NC}"
                # docker compose down -v --rmi local:
                #   -v          → hapus volumes yang terdefinisi di compose file
                #   --rmi local → hapus images yang di-build (bukan pulled images)
                # Karena -p mer-system, scope hanya project ini.
                $DC down -v --rmi local 2>/dev/null
                echo ""
                echo -e "${GREEN}✓ All MER System resources removed!${NC}"
                echo -e "${CYAN}Rebuild dari awal menggunakan Option 3${NC}"
            else
                echo ""
                echo -e "${GREEN}✓ Operation cancelled. No changes made.${NC}"
            fi
            echo ""
            read -p "Press Enter to continue..."
            ;;

        # -------------------------------------------------------------------
        # WSL Network
        # -------------------------------------------------------------------
        60)
            show_gateway_ip
            ;;
        61)
            update_db_host_ip
            ;;

        0)
            echo ""
            echo -e "${GREEN}Goodbye!${NC}"
            echo ""
            exit 0
            ;;
        *)
            echo ""
            echo -e "${RED}Invalid choice!${NC}"
            sleep 2
            ;;
    esac
done
