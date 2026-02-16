#!/bin/bash

###############################################################################
# MER System - Local Deployment Helper
# Main script untuk memudahkan deployment di local environment
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

# Container names
APP_CONTAINER="mer-system-app-dev"
WEB_CONTAINER="mer-system-web-dev"
DB_CONTAINER="mer-system-db-dev"
REDIS_CONTAINER="mer-system-redis-dev"

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
    echo ""
    echo -e "  ${CYAN}--- Status & Logs ---${NC}"
    echo -e "  ${YELLOW}10)${NC} Show Container Status"
    echo -e "  ${YELLOW}11)${NC} Show App Logs"
    echo -e "  ${YELLOW}12)${NC} Show DB Logs"
    echo -e "  ${YELLOW}13)${NC} Show All Logs"
    echo -e "  ${YELLOW}14)${NC} Test Endpoint"
    echo ""
    echo -e "  ${CYAN}--- Laravel Commands ---${NC}"
    echo -e "  ${GREEN}20)${NC} Run Migrations"
    echo -e "  ${GREEN}21)${NC} Fresh Migration with Seed"
    echo -e "  ${GREEN}22)${NC} Clear All Cache"
    echo -e "  ${GREEN}23)${NC} Run Artisan Command"
    echo -e "  ${GREEN}24)${NC} Access App Shell"
    echo ""
    echo -e "  ${CYAN}--- NPM Commands ---${NC}"
    echo -e "  ${GREEN}30)${NC} NPM Install"
    echo -e "  ${GREEN}31)${NC} NPM Run Build"
    echo ""
    echo -e "  ${CYAN}--- Database ---${NC}"
    echo -e "  ${GREEN}40)${NC} Access PostgreSQL Shell"
    echo -e "  ${GREEN}41)${NC} Access Redis CLI"
    echo -e "  ${GREEN}42)${NC} Reset Database (Drop & Recreate)"
    echo ""
    echo -e "  ${CYAN}--- Cleanup ---${NC}"
    echo -e "  ${RED}50)${NC} Cleanup Docker Resources"
    echo -e "  ${RED}51)${NC} Remove All Containers & Volumes"
    echo ""
    echo -e "  ${RED}0)${NC} Exit"
    echo ""
    echo -n "Pilihan [0-51]: "
}

# Function to show container status
show_status() {
    echo ""
    echo -e "${BLUE}Container Status:${NC}"
    echo ""
    docker ps --filter "name=mer-system" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
    echo ""
    read -p "Press Enter to continue..."
}

# Function to show app logs
show_app_logs() {
    echo ""
    echo -e "${CYAN}Showing App Logs (Ctrl+C to exit)...${NC}"
    echo ""
    docker logs $APP_CONTAINER --tail 100 -f
}

# Function to show db logs
show_db_logs() {
    echo ""
    echo -e "${CYAN}Showing DB Logs (Ctrl+C to exit)...${NC}"
    echo ""
    docker logs $DB_CONTAINER --tail 100 -f
}

# Function to show all logs
show_all_logs() {
    echo ""
    echo -e "${CYAN}Showing All Service Logs (Ctrl+C to exit)...${NC}"
    echo ""
    cd "$COMPOSE_DIR"
    docker compose logs --tail 100 -f
}

# Function to test endpoint
test_endpoint() {
    echo ""
    echo -e "${BLUE}Testing Endpoint...${NC}"
    echo ""

    # Load port from .env
    APP_PORT=$(grep "^APP_PORT=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
    APP_PORT=${APP_PORT:-8000}

    echo -n "Nginx (Web):      "
    WEB_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:$APP_PORT 2>/dev/null || echo "000")
    if [ "$WEB_STATUS" = "200" ] || [ "$WEB_STATUS" = "302" ]; then
        echo -e "${GREEN}✓ $WEB_STATUS OK${NC}"
    else
        echo -e "${RED}✗ $WEB_STATUS${NC}"
    fi

    echo -n "PostgreSQL:       "
    DB_PORT=$(grep "^FORWARD_DB_PORT=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
    DB_PORT=${DB_PORT:-5432}
    if docker exec $DB_CONTAINER pg_isready -q 2>/dev/null; then
        echo -e "${GREEN}✓ Ready${NC}"
    else
        echo -e "${RED}✗ Not ready${NC}"
    fi

    echo -n "Redis:            "
    REDIS_RESULT=$(docker exec $REDIS_CONTAINER redis-cli ping 2>/dev/null || echo "FAIL")
    if [ "$REDIS_RESULT" = "PONG" ]; then
        echo -e "${GREEN}✓ PONG${NC}"
    else
        echo -e "${RED}✗ $REDIS_RESULT${NC}"
    fi

    echo ""
    echo -e "${YELLOW}URL: http://localhost:$APP_PORT${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Function to run artisan command
run_artisan() {
    echo ""
    echo -e "${YELLOW}Masukkan artisan command (contoh: migrate, tinker, route:list):${NC}"
    read -p "php artisan " artisan_cmd

    if [ -n "$artisan_cmd" ]; then
        $DOCKER_EXEC $APP_CONTAINER php artisan $artisan_cmd
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
        # Start/Stop
        1)
            check_env || continue
            check_nginx_config || continue
            echo ""
            echo -e "${GREEN}Starting MER System Development Environment...${NC}"
            cd "$COMPOSE_DIR"
            docker compose up -d --build
            echo ""

            # Load port from .env
            APP_PORT=$(grep "^APP_PORT=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
            APP_PORT=${APP_PORT:-8000}

            echo -e "${GREEN}✓ Development environment started!${NC}"
            echo ""
            echo -e "${YELLOW}Services:${NC}"
            echo -e "  App (Nginx):    http://localhost:$APP_PORT"
            echo -e "  Vite HMR:      http://localhost:5173"
            echo -e "  PostgreSQL:     localhost:5432"
            echo -e "  Redis:          localhost:6379"
            echo ""
            read -p "Press Enter to continue..."
            ;;
        2)
            echo ""
            echo -e "${YELLOW}Stopping MER System Development Environment...${NC}"
            cd "$COMPOSE_DIR"
            docker compose down
            echo -e "${GREEN}✓ Development environment stopped!${NC}"
            read -p "Press Enter to continue..."
            ;;

        # Rebuild
        3)
            check_env || continue
            check_nginx_config || continue
            echo ""
            echo -e "${RED}Clean Rebuild - This will remove ALL containers, volumes, and rebuild!${NC}"
            echo -e "${RED}WARNING: Database data will be LOST!${NC}"
            read -p "Are you sure? (y/n): " confirm
            if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
                cd "$COMPOSE_DIR"
                docker compose down -v --rmi local
                docker compose up -d --build
                echo -e "${GREEN}✓ Clean rebuild complete!${NC}"
            fi
            read -p "Press Enter to continue..."
            ;;
        4)
            check_env || continue
            check_nginx_config || continue
            echo ""
            echo -e "${GREEN}Quick Rebuild (preserving data)...${NC}"
            cd "$COMPOSE_DIR"
            docker compose up -d --build
            echo -e "${GREEN}✓ Quick rebuild complete!${NC}"
            read -p "Press Enter to continue..."
            ;;

        # Status & Logs
        10)
            show_status
            ;;
        11)
            show_app_logs
            ;;
        12)
            show_db_logs
            ;;
        13)
            show_all_logs
            ;;
        14)
            test_endpoint
            ;;

        # Laravel Commands
        20)
            echo ""
            echo -e "${GREEN}Running Migrations...${NC}"
            docker exec $APP_CONTAINER php artisan migrate
            echo ""
            read -p "Press Enter to continue..."
            ;;
        21)
            echo ""
            echo -e "${RED}Fresh Migration - This will delete all data!${NC}"
            read -p "Are you sure? (y/n): " confirm
            if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
                docker exec $APP_CONTAINER php artisan migrate:fresh --seed
            fi
            echo ""
            read -p "Press Enter to continue..."
            ;;
        22)
            echo ""
            echo -e "${CYAN}Clearing All Cache...${NC}"
            docker exec $APP_CONTAINER php artisan optimize:clear 2>/dev/null || echo "App not running"
            echo -e "${GREEN}✓ Cache cleared${NC}"
            echo ""
            read -p "Press Enter to continue..."
            ;;
        23)
            run_artisan
            ;;
        24)
            echo ""
            echo -e "${CYAN}Accessing App Shell...${NC}"
            $DOCKER_EXEC $APP_CONTAINER sh
            ;;

        # NPM Commands
        30)
            echo ""
            echo -e "${GREEN}Running NPM Install...${NC}"
            docker exec $APP_CONTAINER npm install
            echo ""
            read -p "Press Enter to continue..."
            ;;
        31)
            echo ""
            echo -e "${GREEN}Running NPM Build...${NC}"
            docker exec $APP_CONTAINER npm run build
            echo ""
            read -p "Press Enter to continue..."
            ;;

        # Database
        40)
            echo ""
            echo -e "${CYAN}Accessing PostgreSQL Shell...${NC}"
            echo -e "${YELLOW}Schemas: tenant, akun, master, pelaporan, audit${NC}"
            echo ""
            DB_DATABASE=$(grep "^DB_DATABASE=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
            DB_USERNAME=$(grep "^DB_USERNAME=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
            $DOCKER_EXEC $DB_CONTAINER psql -U "${DB_USERNAME:-postgres}" -d "${DB_DATABASE:-mer_system}"
            ;;
        41)
            echo ""
            echo -e "${CYAN}Accessing Redis CLI...${NC}"
            $DOCKER_EXEC $REDIS_CONTAINER redis-cli
            ;;
        42)
            echo ""
            echo -e "${RED}Reset Database - This will DROP and RECREATE the database!${NC}"
            echo -e "${RED}All data including schemas and tables will be DESTROYED!${NC}"
            read -p "Are you sure? (y/n): " confirm
            if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
                echo -e "${YELLOW}Stopping services...${NC}"
                cd "$COMPOSE_DIR"
                docker compose down
                echo -e "${YELLOW}Removing database volume...${NC}"
                docker volume rm "$(docker volume ls -q --filter name=mer-db-data)" 2>/dev/null || true
                echo -e "${YELLOW}Restarting services...${NC}"
                docker compose up -d --build
                echo -e "${GREEN}✓ Database reset complete! DDL & migrations will run on startup.${NC}"
            fi
            read -p "Press Enter to continue..."
            ;;

        # Cleanup
        50)
            echo ""
            echo -e "${YELLOW}Cleaning up Docker resources...${NC}"
            echo ""
            docker container prune -f
            docker image prune -f
            docker volume prune -f
            echo ""
            echo -e "${GREEN}✓ Cleanup complete!${NC}"
            docker system df
            echo ""
            read -p "Press Enter to continue..."
            ;;
        51)
            echo ""
            echo -e "${RED}This will remove ALL MER System containers, images, and volumes!${NC}"
            read -p "Are you sure? (y/n): " confirm
            if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
                cd "$COMPOSE_DIR"
                docker compose down -v --rmi all 2>/dev/null
                echo -e "${GREEN}✓ All resources removed!${NC}"
            fi
            read -p "Press Enter to continue..."
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
