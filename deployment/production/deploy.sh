#!/bin/bash

###############################################################################
# MER System - Production Maintenance Helper
#
# Script interaktif untuk maintenance sistem MER di environment PRODUCTION.
# Hanya menyediakan operasi yang AMAN untuk production dengan data penting.
#
# KEAMANAN:
# - Tidak ada opsi reset database keseluruhan
# - Tidak ada opsi fresh migration
# - Tidak ada opsi hapus seluruh laporan/user
# - Semua operasi destructive memiliki konfirmasi ganda
#
# PENGGUNAAN:
#   cd /path/to/mer-system/deployment/production
#   bash deploy.sh
###############################################################################

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# ---------------------------------------------------------------------------
# Resolve directories
# ---------------------------------------------------------------------------
COMPOSE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$(dirname "$COMPOSE_DIR")")"

# ---------------------------------------------------------------------------
# Docker Compose command
#
# Menggunakan docker compose langsung dengan --project-directory
# agar bisa dijalankan dari direktori manapun.
# ---------------------------------------------------------------------------
DC="docker compose --project-directory ${COMPOSE_DIR}"

# ---------------------------------------------------------------------------
# Container names (sesuai docker-compose.yml production)
#
# - app: tidak punya container_name eksplisit, diakses via `docker compose exec`
# - web, db, redis: punya container_name eksplisit
# ---------------------------------------------------------------------------
WEB_CONTAINER="mer-web-prod"
DB_CONTAINER="mer-db-prod"
REDIS_CONTAINER="mer-redis-prod"

# ---------------------------------------------------------------------------
# Helper: cek apakah service sedang running via docker compose
# ---------------------------------------------------------------------------
is_service_running() {
    $DC ps --status running --format '{{.Service}}' 2>/dev/null | grep -qw "$1"
}

# ---------------------------------------------------------------------------
# Helper: jalankan perintah di container app via docker compose exec
# ---------------------------------------------------------------------------
dc_exec() {
    $DC exec "$@"
}

dc_exec_it() {
    $DC exec -it "$@"
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

# ---------------------------------------------------------------------------
# Helper: load value dari .env file
# ---------------------------------------------------------------------------
env_val() {
    grep "^${1}=" "${COMPOSE_DIR}/.env" 2>/dev/null | head -1 | cut -d'=' -f2 | tr -d '\r'
}

# ---------------------------------------------------------------------------
# Menu
# ---------------------------------------------------------------------------
show_menu() {
    clear
    echo -e "${CYAN}╔════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║                                                        ║${NC}"
    echo -e "${CYAN}║    ${BLUE}MER System - Production Maintenance${CYAN}             ║${NC}"
    echo -e "${CYAN}║    ${YELLOW}Medication Error Reporting${CYAN}                        ║${NC}"
    echo -e "${CYAN}║                                                        ║${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${YELLOW}Pilih operasi:${NC}"
    echo ""
    echo -e "  ${CYAN}--- Start/Stop ---${NC}"
    echo -e "  ${GREEN}1)${NC} Start Production (docker compose up -d)"
    echo -e "  ${GREEN}2)${NC} Stop Production"
    echo ""
    echo -e "  ${CYAN}--- Rebuild ---${NC}"
    echo -e "  ${GREEN}3)${NC} Quick Rebuild (Preserving Data)"
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
    echo -e "  ${YELLOW}17)${NC} Show Web (Nginx) Logs"
    echo -e "  ${YELLOW}18)${NC} Show All Logs"
    echo ""
    echo -e "  ${CYAN}--- Laravel Commands ---${NC}"
    echo -e "  ${GREEN}20)${NC} Run Migrations"
    echo -e "  ${GREEN}22)${NC} Clear All Cache"
    echo -e "  ${GREEN}23)${NC} Run Artisan Command"
    echo -e "  ${GREEN}24)${NC} Access App Shell"
    echo -e "  ${GREEN}26)${NC} Install / Update Dependensi PHP (Composer Install)"
    echo ""
    echo -e "  ${CYAN}--- Database ---${NC}"
    echo -e "  ${GREEN}40)${NC} Access PostgreSQL Shell"
    echo -e "  ${GREEN}41)${NC} Access Redis CLI"
    echo ""
    echo -e "  ${CYAN}--- Cleanup (Project-Scoped) ---${NC}"
    echo -e "  ${YELLOW}50)${NC} Remove Stopped Containers & Dangling Images"
    echo ""
    echo -e "  ${CYAN}--- Data Management ---${NC}"
    echo -e "  ${YELLOW}73)${NC} Hapus Laporan Spesifik (Berdasarkan Nomor Laporan)"
    echo -e "  ${YELLOW}74)${NC} Hapus User Spesifik (Berdasarkan Username)"
    echo ""
    echo -e "  ${CYAN}--- Deployment ---${NC}"
    echo -e "  ${GREEN}80)${NC} Full Deploy (pull → build → up → migrate → optimize → queue:restart)"
    echo ""
    echo -e "  ${RED}0)${NC} Exit"
    echo ""
    echo -n "Pilihan [0-80]: "
}

# ═══════════════════════════════════════════════════════════════════════════
# FUNCTIONS
# ═══════════════════════════════════════════════════════════════════════════

# ---------------------------------------------------------------------------
# Show container status (running only)
# ---------------------------------------------------------------------------
show_status() {
    print_header "MER System - Production Container Status"

    $DC ps --format "table {{.Name}}\t{{.Service}}\t{{.Status}}\t{{.Ports}}"

    echo ""
    echo -e "${YELLOW}Container Details:${NC}"
    echo ""

    for service in app web db redis; do
        if is_service_running "$service"; then
            echo -e "  ${GREEN}●${NC} ${service}: ${GREEN}running${NC}"
        else
            echo -e "  ${RED}○${NC} ${service}: ${RED}not running${NC}"
        fi
    done

    echo ""
    echo -e "${CYAN}ℹ Gunakan Option 11 untuk melihat container yang stopped${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# ---------------------------------------------------------------------------
# Show ALL project containers (termasuk stopped)
# ---------------------------------------------------------------------------
show_all_containers() {
    print_header "MER System - All Production Containers (incl. Stopped)"

    $DC ps -a --format "table {{.Name}}\t{{.Service}}\t{{.Status}}\t{{.Image}}"

    echo ""
    echo -e "${YELLOW}Summary:${NC}"

    RUNNING=$($DC ps --status running --format '{{.Name}}' 2>/dev/null | wc -l)
    TOTAL=$($DC ps -a --format '{{.Name}}' 2>/dev/null | wc -l)
    STOPPED=$((TOTAL - RUNNING))

    echo -e "  Total containers:   ${CYAN}$TOTAL${NC}"
    echo -e "  Running:            ${GREEN}$RUNNING${NC}"
    echo -e "  Stopped:            ${YELLOW}$STOPPED${NC}"

    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# ---------------------------------------------------------------------------
# Show project images
# ---------------------------------------------------------------------------
show_project_images() {
    print_header "MER System - Production Images"

    $DC images

    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# ---------------------------------------------------------------------------
# Show project volumes & networks
# ---------------------------------------------------------------------------
show_project_volumes_networks() {
    print_header "MER System - Production Volumes & Networks"

    echo -e "${YELLOW}Compose Volumes:${NC}"
    echo ""
    $DC config --volumes 2>/dev/null | while read -r vol; do
        echo -e "  ${GREEN}●${NC} $vol"
    done

    echo ""
    echo -e "${YELLOW}Docker Volumes (aktif):${NC}"
    echo ""
    docker volume ls --format "table {{.Name}}\t{{.Driver}}" | grep -i "production\|mer" || \
        echo -e "  ${YELLOW}(Tidak ada volume terkait ditemukan)${NC}"

    echo ""
    echo -e "${YELLOW}Docker Networks:${NC}"
    echo ""
    docker network ls --format "table {{.Name}}\t{{.Driver}}\t{{.Scope}}" | grep -i "prod\|mer" || \
        echo -e "  ${YELLOW}(Tidak ada network terkait ditemukan)${NC}"

    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# ---------------------------------------------------------------------------
# Test endpoint / health check
# ---------------------------------------------------------------------------
test_endpoint() {
    print_header "MER System - Production Health Check"

    APP_PORT=$(env_val "APP_PORT")
    APP_PORT=${APP_PORT:-80}

    echo -e "${YELLOW}Service Status:${NC}"
    for service in app web db redis; do
        if is_service_running "$service"; then
            echo -e "  ${GREEN}✓${NC} ${service} is running"
        else
            echo -e "  ${RED}✗${NC} ${service} is NOT running"
        fi
    done
    echo ""

    echo -e "${YELLOW}Service Health:${NC}"

    # Web Server (Nginx)
    echo -n "  Web Server (Nginx):    "
    WEB_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:${APP_PORT} 2>/dev/null || echo "000")
    if [ "$WEB_STATUS" = "200" ] || [ "$WEB_STATUS" = "302" ] || [ "$WEB_STATUS" = "301" ]; then
        echo -e "${GREEN}✓ HTTP $WEB_STATUS${NC}"
    elif [ "$WEB_STATUS" = "000" ]; then
        echo -e "${RED}✗ Connection refused${NC}"
    else
        echo -e "${YELLOW}⚠ HTTP $WEB_STATUS${NC}"
    fi

    # PostgreSQL
    echo -n "  PostgreSQL Database:   "
    if is_service_running "db"; then
        if dc_exec db pg_isready -q 2>/dev/null; then
            echo -e "${GREEN}✓ Ready${NC}"
        else
            echo -e "${RED}✗ Not ready${NC}"
        fi
    else
        echo -e "${RED}✗ Container not running${NC}"
    fi

    # Redis
    echo -n "  Redis Cache/Session:   "
    if is_service_running "redis"; then
        REDIS_PASS=$(env_val "REDIS_PASSWORD")
        if [ -n "$REDIS_PASS" ]; then
            REDIS_RESULT=$(dc_exec redis redis-cli -a "$REDIS_PASS" --no-auth-warning ping 2>/dev/null || echo "FAIL")
        else
            REDIS_RESULT=$(dc_exec redis redis-cli ping 2>/dev/null || echo "FAIL")
        fi
        if [ "$REDIS_RESULT" = "PONG" ]; then
            echo -e "${GREEN}✓ PONG${NC}"
        else
            echo -e "${RED}✗ $REDIS_RESULT${NC}"
        fi
    else
        echo -e "${RED}✗ Container not running${NC}"
    fi

    # PHP-FPM
    echo -n "  PHP-FPM Application:   "
    if is_service_running "app"; then
        PHP_VERSION=$(dc_exec app php -v 2>/dev/null | head -n1 | cut -d' ' -f2)
        if [ -n "$PHP_VERSION" ]; then
            echo -e "${GREEN}✓ PHP $PHP_VERSION${NC}"
        else
            echo -e "${YELLOW}⚠ Running but PHP check failed${NC}"
        fi
    else
        echo -e "${RED}✗ Container not running${NC}"
    fi

    echo ""
    APP_URL=$(env_val "APP_URL")
    echo -e "${YELLOW}Access URL:${NC}"
    echo -e "  ${CYAN}Application:${NC}     ${APP_URL:-http://localhost:${APP_PORT}}"
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# ---------------------------------------------------------------------------
# Log functions
# ---------------------------------------------------------------------------
show_app_logs() {
    echo ""
    if ! is_service_running "app"; then
        echo -e "${RED}✗ App container is not running!${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi
    echo -e "${CYAN}Showing App Logs (Ctrl+C to exit)...${NC}"
    echo ""
    $DC logs app --tail 100 -f
}

show_db_logs() {
    echo ""
    if ! is_service_running "db"; then
        echo -e "${RED}✗ Database container is not running!${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi
    echo -e "${CYAN}Showing DB Logs (Ctrl+C to exit)...${NC}"
    echo ""
    $DC logs db --tail 100 -f
}

show_web_logs() {
    echo ""
    if ! is_service_running "web"; then
        echo -e "${RED}✗ Web (Nginx) container is not running!${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi
    echo -e "${CYAN}Showing Web (Nginx) Logs (Ctrl+C to exit)...${NC}"
    echo ""
    $DC logs web --tail 100 -f
}

show_all_logs() {
    echo ""
    echo -e "${CYAN}Showing All Project Logs (Ctrl+C to exit)...${NC}"
    echo ""
    $DC logs --tail 100 -f
}

# ---------------------------------------------------------------------------
# Run artisan command
# ---------------------------------------------------------------------------
run_artisan() {
    echo ""
    if ! is_service_running "app"; then
        echo -e "${RED}✗ App container is not running!${NC}"
        echo -e "${YELLOW}Silakan start dulu dengan Option 1${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi

    echo -e "${YELLOW}Common Artisan Commands:${NC}"
    echo -e "  ${CYAN}migrate${NC}              - Run database migrations"
    echo -e "  ${CYAN}db:seed${NC}              - Run database seeders"
    echo -e "  ${CYAN}tinker${NC}               - Interactive REPL"
    echo -e "  ${CYAN}route:list${NC}           - List all routes"
    echo -e "  ${CYAN}config:clear${NC}         - Clear config cache"
    echo -e "  ${CYAN}cache:clear${NC}          - Clear application cache"
    echo -e "  ${CYAN}queue:work${NC}           - Process queue jobs"
    echo -e "  ${CYAN}queue:restart${NC}        - Restart queue workers"
    echo -e "  ${CYAN}optimize${NC}             - Cache config, routes, views"
    echo -e "  ${CYAN}optimize:clear${NC}       - Clear all optimization cache"
    echo ""
    echo -e "${YELLOW}Enter artisan command (or 'q' to cancel):${NC}"
    read -p "php artisan " artisan_cmd

    if [ -n "$artisan_cmd" ] && [ "$artisan_cmd" != "q" ]; then
        echo ""
        echo -e "${CYAN}Running: php artisan $artisan_cmd${NC}"
        echo ""
        dc_exec_it app php artisan $artisan_cmd
    else
        echo -e "${YELLOW}Cancelled.${NC}"
    fi

    echo ""
    read -p "Press Enter to continue..."
}

# ---------------------------------------------------------------------------
# Full Deploy (flow dari script lama)
#
# 6 steps: git pull → build → up → migrate → optimize → queue:restart
# ---------------------------------------------------------------------------
full_deploy() {
    print_header "MER System - Full Production Deployment"

    echo -e "${YELLOW}Ini akan menjalankan deployment lengkap:${NC}"
    echo -e "  ${CYAN}1.${NC} git pull origin production"
    echo -e "  ${CYAN}2.${NC} docker compose build --pull"
    echo -e "  ${CYAN}3.${NC} docker compose up -d --remove-orphans --wait"
    echo -e "  ${CYAN}4.${NC} php artisan migrate --force"
    echo -e "  ${CYAN}5.${NC} php artisan optimize:clear → optimize"
    echo -e "  ${CYAN}6.${NC} php artisan queue:restart"
    echo ""
    read -p "Lanjutkan deployment? (y/n): " confirm

    if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
        echo ""
        echo -e "${GREEN}✓ Deployment cancelled.${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 0
    fi

    echo ""

    # Validasi .env
    if [ ! -f "${COMPOSE_DIR}/.env" ]; then
        echo -e "${RED}✗ .env tidak ditemukan di ${COMPOSE_DIR}!${NC}"
        echo -e "${YELLOW}Salin dari .env.example dan isi nilainya.${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi

    APP_KEY_VAL=$(grep "APP_KEY=base64:" "${COMPOSE_DIR}/.env" 2>/dev/null)
    if [ -z "$APP_KEY_VAL" ]; then
        echo -e "${YELLOW}⚠ APP_KEY belum di-set atau tidak dalam format base64.${NC}"
        read -p "Lanjutkan tetap? (y/n): " key_confirm
        if [ "$key_confirm" != "y" ] && [ "$key_confirm" != "Y" ]; then
            echo ""
            read -p "Press Enter to continue..."
            return 1
        fi
    fi

    echo -e "${BOLD}${GREEN}╔══════════════════════════════════════╗${NC}"
    echo -e "${BOLD}${GREEN}║     MER System - Production Deploy    ║${NC}"
    echo -e "${BOLD}${GREEN}╚══════════════════════════════════════╝${NC}"
    echo -e "  Direktori  : ${COMPOSE_DIR}"
    echo -e "  Timestamp  : $(date '+%Y-%m-%d %H:%M:%S %Z')"

    # Step 1: git pull
    echo ""
    echo -e "${BOLD}${BLUE}══════════════════════════════════════${NC}"
    echo -e "${BOLD}${CYAN}  Step 1/6 - git pull origin production${NC}"
    echo -e "${BOLD}${BLUE}══════════════════════════════════════${NC}"
    cd "$PROJECT_DIR"
    if git pull origin production; then
        echo -e "${GREEN}  ✓ Kode berhasil diperbarui${NC}"
    else
        echo -e "${RED}  ✗ git pull gagal!${NC}"
        cd "$COMPOSE_DIR"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi
    cd "$COMPOSE_DIR"

    # Step 2: build
    echo ""
    echo -e "${BOLD}${BLUE}══════════════════════════════════════${NC}"
    echo -e "${BOLD}${CYAN}  Step 2/6 - docker compose build${NC}"
    echo -e "${BOLD}${BLUE}══════════════════════════════════════${NC}"
    if $DC build --pull; then
        echo -e "${GREEN}  ✓ Docker images berhasil dibangun${NC}"
    else
        echo -e "${RED}  ✗ Build gagal!${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi

    # Step 3: up
    echo ""
    echo -e "${BOLD}${BLUE}══════════════════════════════════════${NC}"
    echo -e "${BOLD}${CYAN}  Step 3/6 - docker compose up -d${NC}"
    echo -e "${BOLD}${BLUE}══════════════════════════════════════${NC}"
    if $DC up -d --remove-orphans --wait; then
        echo -e "${GREEN}  ✓ Semua services berhasil dijalankan${NC}"
    else
        echo -e "${RED}  ✗ Gagal start services!${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi

    # Step 4: migrate
    echo ""
    echo -e "${BOLD}${BLUE}══════════════════════════════════════${NC}"
    echo -e "${BOLD}${CYAN}  Step 4/6 - php artisan migrate --force${NC}"
    echo -e "${BOLD}${BLUE}══════════════════════════════════════${NC}"
    if dc_exec app php artisan migrate --force; then
        echo -e "${GREEN}  ✓ Database migration selesai${NC}"
    else
        echo -e "${RED}  ✗ Migration gagal!${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi

    # Step 5: optimize
    echo ""
    echo -e "${BOLD}${BLUE}══════════════════════════════════════${NC}"
    echo -e "${BOLD}${CYAN}  Step 5/6 - optimize:clear → optimize${NC}"
    echo -e "${BOLD}${BLUE}══════════════════════════════════════${NC}"
    dc_exec app php artisan optimize:clear
    dc_exec app php artisan optimize
    echo -e "${GREEN}  ✓ Application cache berhasil di-rebuild${NC}"

    # Step 6: queue:restart
    echo ""
    echo -e "${BOLD}${BLUE}══════════════════════════════════════${NC}"
    echo -e "${BOLD}${CYAN}  Step 6/6 - php artisan queue:restart${NC}"
    echo -e "${BOLD}${BLUE}══════════════════════════════════════${NC}"
    dc_exec app php artisan queue:restart
    echo -e "${GREEN}  ✓ Queue worker restart signal terkirim${NC}"

    # Summary
    echo ""
    echo -e "${BOLD}${GREEN}╔══════════════════════════════════════════════╗${NC}"
    echo -e "${BOLD}${GREEN}║  Deployment selesai!                          ║${NC}"
    echo -e "${BOLD}${GREEN}╚══════════════════════════════════════════════╝${NC}"
    echo -e "  Selesai pada : $(date '+%Y-%m-%d %H:%M:%S %Z')"
    echo ""
    $DC ps
    echo ""
    read -p "Press Enter to continue..."
}

# ---------------------------------------------------------------------------
# Check .env file
# ---------------------------------------------------------------------------
check_env() {
    if [ ! -f "${COMPOSE_DIR}/.env" ]; then
        echo -e "${RED}✗ File .env tidak ditemukan di ${COMPOSE_DIR}!${NC}"
        echo -e "${YELLOW}Salin dari .env.example dan isi nilainya terlebih dahulu.${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi
    return 0
}

# ═══════════════════════════════════════════════════════════════════════════
# MAIN LOOP
# ═══════════════════════════════════════════════════════════════════════════
while true; do
    show_menu
    read choice

    case $choice in
        # -------------------------------------------------------------------
        # Start/Stop
        # -------------------------------------------------------------------
        1)
            check_env || continue
            echo ""
            echo -e "${GREEN}Starting MER System Production...${NC}"
            $DC up -d --remove-orphans

            echo ""
            echo -e "${GREEN}✓ Production environment started!${NC}"
            echo ""
            $DC ps
            echo ""
            read -p "Press Enter to continue..."
            ;;
        2)
            echo ""
            echo -e "${YELLOW}Stopping MER System Production...${NC}"
            $DC down
            echo -e "${GREEN}✓ Production environment stopped!${NC}"
            read -p "Press Enter to continue..."
            ;;

        # -------------------------------------------------------------------
        # Rebuild (Quick - preserving data)
        # -------------------------------------------------------------------
        3)
            check_env || continue
            echo ""
            echo -e "${YELLOW}╔════════════════════════════════════════════════════════╗${NC}"
            echo -e "${YELLOW}║     Quick Rebuild (Data Tetap Aman)                    ║${NC}"
            echo -e "${YELLOW}╚════════════════════════════════════════════════════════╝${NC}"
            echo ""
            echo -e "${CYAN}Ini akan rebuild images dan restart containers.${NC}"
            echo -e "${GREEN}Database dan volume data TIDAK akan terhapus.${NC}"
            echo ""
            read -p "Lanjutkan? (y/n): " confirm
            if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
                echo ""
                echo -e "${GREEN}Quick Rebuild (preserving data)...${NC}"
                $DC up -d --build --remove-orphans
                echo ""
                echo -e "${GREEN}✓ Quick rebuild complete!${NC}"
                echo ""
                $DC ps
            else
                echo ""
                echo -e "${GREEN}✓ Operation cancelled.${NC}"
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
            show_web_logs
            ;;
        18)
            show_all_logs
            ;;

        # -------------------------------------------------------------------
        # Laravel Commands
        # -------------------------------------------------------------------
        20)
            echo ""
            if ! is_service_running "app"; then
                echo -e "${RED}✗ App container is not running!${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi

            echo -e "${GREEN}Running Database Migrations...${NC}"
            echo -e "${CYAN}This will run pending migrations with --force flag${NC}"
            echo ""
            if dc_exec app php artisan migrate --force; then
                echo ""
                echo -e "${GREEN}✓ Migrations complete${NC}"
            else
                echo ""
                echo -e "${RED}✗ Migration failed (lihat error di atas)${NC}"
            fi
            read -p "Press Enter to continue..."
            ;;
        22)
            echo ""
            if ! is_service_running "app"; then
                echo -e "${RED}✗ App container is not running!${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi

            echo -e "${CYAN}Clearing All Laravel Cache...${NC}"
            echo ""
            echo -n "  Config cache...      "
            dc_exec app php artisan config:clear 2>/dev/null && echo -e "${GREEN}✓${NC}" || echo -e "${RED}✗${NC}"
            echo -n "  Route cache...       "
            dc_exec app php artisan route:clear 2>/dev/null && echo -e "${GREEN}✓${NC}" || echo -e "${RED}✗${NC}"
            echo -n "  View cache...        "
            dc_exec app php artisan view:clear 2>/dev/null && echo -e "${GREEN}✓${NC}" || echo -e "${RED}✗${NC}"
            echo -n "  Application cache... "
            dc_exec app php artisan cache:clear 2>/dev/null && echo -e "${GREEN}✓${NC}" || echo -e "${RED}✗${NC}"
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
            if ! is_service_running "app"; then
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
            dc_exec_it app bash || dc_exec_it app sh
            ;;
        26)
            print_header "Install / Update Dependensi PHP (Composer Install)"

            if ! is_service_running "app"; then
                echo -e "${RED}✗ App container is not running!${NC}"
                echo -e "${YELLOW}Silakan start dulu dengan Option 1${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi

            echo -e "${YELLOW}Menyinkronkan dependensi PHP...${NC}"
            echo -e "${CYAN}  Menjalankan: composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev${NC}"
            echo ""

            if dc_exec app \
                composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev; then
                echo ""
                echo -e "${GREEN}✓ Composer install selesai.${NC}"
            else
                echo ""
                echo -e "${RED}✗ Composer install gagal - periksa output di atas.${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi

            echo ""
            echo -e "${YELLOW}Membersihkan cache...${NC}"
            echo -e "${CYAN}  Menjalankan: php artisan optimize:clear${NC}"
            echo ""

            if dc_exec app php artisan optimize:clear; then
                echo ""
                echo -e "${GREEN}✓ Berhasil! Dependensi diperbarui dan cache dibersihkan.${NC}"
            else
                echo ""
                echo -e "${YELLOW}⚠ Cache clear gagal - jalankan Option 22 secara manual.${NC}"
            fi

            echo ""
            read -p "Press Enter to continue..."
            ;;

        # -------------------------------------------------------------------
        # Database
        # -------------------------------------------------------------------
        40)
            echo ""
            if ! is_service_running "db"; then
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

            DB_DATABASE=$(env_val "DB_DATABASE")
            DB_USERNAME=$(env_val "DB_USERNAME")

            echo -e "${GREEN}Connecting to: ${DB_DATABASE:-mer_system}${NC}"
            echo ""
            dc_exec_it db psql -U "${DB_USERNAME:-postgres}" -d "${DB_DATABASE:-mer_system}"
            ;;
        41)
            echo ""
            if ! is_service_running "redis"; then
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
            echo -e "  ${CYAN}DEL key${NC}             - Delete key"
            echo -e "  ${CYAN}INFO${NC}                - Server information"
            echo -e "  ${CYAN}DBSIZE${NC}              - Number of keys"
            echo -e "  ${CYAN}quit${NC}                - Exit redis-cli"
            echo ""
            echo -e "${GREEN}Connecting to Redis...${NC}"
            echo ""

            REDIS_PASS=$(env_val "REDIS_PASSWORD")
            if [ -n "$REDIS_PASS" ]; then
                dc_exec_it redis redis-cli -a "$REDIS_PASS" --no-auth-warning
            else
                dc_exec_it redis redis-cli
            fi
            ;;

        # -------------------------------------------------------------------
        # Cleanup - Project-Scoped (AMAN)
        # -------------------------------------------------------------------
        50)
            echo ""
            echo -e "${YELLOW}╔════════════════════════════════════════════════════════╗${NC}"
            echo -e "${YELLOW}║     Cleanup Stopped Containers & Dangling Images       ║${NC}"
            echo -e "${YELLOW}╚════════════════════════════════════════════════════════╝${NC}"
            echo ""
            echo -e "${CYAN}Ini akan membersihkan:${NC}"
            echo -e "  - Stopped containers dari project ini"
            echo -e "  - Dangling (unused) images dari project ini"
            echo ""
            echo -e "${GREEN}AMAN: Volume database TIDAK dihapus.${NC}"
            echo -e "${GREEN}AMAN: Running containers TIDAK terpengaruh.${NC}"
            echo ""
            read -p "Continue? (y/n): " confirm

            if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
                echo ""

                echo -e "${YELLOW}[1/2] Removing stopped project containers...${NC}"
                STOPPED_IDS=$($DC ps -a --status exited --format '{{.ID}}' 2>/dev/null)
                if [ -n "$STOPPED_IDS" ]; then
                    echo "$STOPPED_IDS" | xargs docker rm \
                        && echo -e "${GREEN}  ✓ Stopped containers removed${NC}" \
                        || echo -e "${YELLOW}  ⚠ Some containers could not be removed${NC}"
                else
                    echo -e "${GREEN}  ✓ No stopped containers found${NC}"
                fi

                echo -e "${YELLOW}[2/2] Removing dangling images...${NC}"
                DANGLING_IDS=$(docker images --filter "dangling=true" --format '{{.ID}}' 2>/dev/null)
                if [ -n "$DANGLING_IDS" ]; then
                    echo "$DANGLING_IDS" | xargs docker rmi 2>/dev/null \
                        && echo -e "${GREEN}  ✓ Dangling images removed${NC}" \
                        || echo -e "${YELLOW}  ⚠ Some images could not be removed${NC}"
                else
                    echo -e "${GREEN}  ✓ No dangling images found${NC}"
                fi

                echo ""
                echo -e "${GREEN}✓ Cleanup complete!${NC}"
            else
                echo ""
                echo -e "${GREEN}✓ Operation cancelled${NC}"
            fi
            echo ""
            read -p "Press Enter to continue..."
            ;;

        # -------------------------------------------------------------------
        # Data Management (Spesifik - Aman)
        # -------------------------------------------------------------------
        73)
            echo ""
            if ! is_service_running "app"; then
                echo -e "${RED}✗ App container is not running!${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi

            echo -e "${YELLOW}╔════════════════════════════════════════════════════════╗${NC}"
            echo -e "${YELLOW}║     Hapus Laporan Spesifik (Berdasarkan Nomor)         ║${NC}"
            echo -e "${YELLOW}╚════════════════════════════════════════════════════════╝${NC}"
            echo ""
            echo -e "${CYAN}Ini hanya menghapus SATU laporan berdasarkan nomor laporan.${NC}"
            echo ""
            read -p "Masukkan Nomor Laporan yang akan dihapus (atau 'q' untuk batal): " laporan_id
            if [ -n "$laporan_id" ] && [ "$laporan_id" != "q" ]; then
                echo ""
                echo -e "${YELLOW}Akan menghapus laporan: ${CYAN}${laporan_id}${NC}"
                read -p "Lanjutkan? (y/n): " confirm
                if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
                    dc_exec_it app php artisan data:delete-laporan "$laporan_id"
                else
                    echo -e "${GREEN}✓ Operation cancelled.${NC}"
                fi
            else
                echo -e "${GREEN}✓ Operation cancelled.${NC}"
            fi
            echo ""
            read -p "Press Enter to continue..."
            ;;
        74)
            echo ""
            if ! is_service_running "app"; then
                echo -e "${RED}✗ App container is not running!${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi

            echo -e "${YELLOW}╔════════════════════════════════════════════════════════╗${NC}"
            echo -e "${YELLOW}║     Hapus User Spesifik (Berdasarkan Username)         ║${NC}"
            echo -e "${YELLOW}╚════════════════════════════════════════════════════════╝${NC}"
            echo ""
            echo -e "${CYAN}Ini hanya menghapus SATU user berdasarkan username.${NC}"
            echo ""
            read -p "Masukkan Username yang akan dihapus (atau 'q' untuk batal): " user_id
            if [ -n "$user_id" ] && [ "$user_id" != "q" ]; then
                echo ""
                echo -e "${YELLOW}Akan menghapus user: ${CYAN}${user_id}${NC}"
                read -p "Lanjutkan? (y/n): " confirm
                if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
                    dc_exec_it app php artisan data:delete-user "$user_id"
                else
                    echo -e "${GREEN}✓ Operation cancelled.${NC}"
                fi
            else
                echo -e "${GREEN}✓ Operation cancelled.${NC}"
            fi
            echo ""
            read -p "Press Enter to continue..."
            ;;

        # -------------------------------------------------------------------
        # Deployment
        # -------------------------------------------------------------------
        80)
            full_deploy
            ;;

        # -------------------------------------------------------------------
        # Exit
        # -------------------------------------------------------------------
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
