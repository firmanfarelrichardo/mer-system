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

# Container names (updated to match new docker-compose.yml)
APP_CONTAINER="mer-app-dev"
WEB_CONTAINER="mer-web-dev"
DB_CONTAINER="mer-db-dev"
REDIS_CONTAINER="mer-redis-dev"

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
    echo -e "  ${YELLOW}11)${NC} Show All Containers (docker ps -a)"
    echo -e "  ${YELLOW}12)${NC} Show Docker Images"
    echo -e "  ${YELLOW}13)${NC} Show Docker Volumes & Networks"
    echo -e "  ${YELLOW}14)${NC} Show Docker System Usage"
    echo -e "  ${YELLOW}15)${NC} Test Endpoint"
    echo ""
    echo -e "  ${CYAN}--- Logs ---${NC}"
    echo -e "  ${YELLOW}16)${NC} Show App Logs"
    echo -e "  ${YELLOW}17)${NC} Show DB Logs"
    echo -e "  ${YELLOW}18)${NC} Show All Logs"
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
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}         MER System - Running Containers               ${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    
    echo -e "${CYAN}Command: docker ps --filter \"name=mer-\"${NC}"
    echo ""
    docker ps --filter "name=mer-" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
    echo ""
    echo -e "${YELLOW}Container Details:${NC}"
    echo ""
    
    # Check each container individually
    for container in $APP_CONTAINER $WEB_CONTAINER $DB_CONTAINER $REDIS_CONTAINER; do
        if docker ps --format '{{.Names}}' | grep -q "^${container}$"; then
            STATUS=$(docker inspect --format='{{.State.Status}}' $container 2>/dev/null)
            HEALTH=$(docker inspect --format='{{.State.Health.Status}}' $container 2>/dev/null)
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
    echo -e "${CYAN}💡 Use Option 11 to see ALL containers (including stopped)${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Function to show all containers
show_all_containers() {
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}           Docker Containers (All States)              ${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    
    echo -e "${CYAN}Command: docker ps -a${NC}"
    echo ""
    docker ps -a
    
    echo ""
    echo -e "${YELLOW}Summary:${NC}"
    RUNNING=$(docker ps -q | wc -l)
    STOPPED=$(docker ps -aq -f status=exited | wc -l)
    TOTAL=$(docker ps -aq | wc -l)
    
    echo -e "  Total containers:   ${CYAN}$TOTAL${NC}"
    echo -e "  Running:            ${GREEN}$RUNNING${NC}"
    echo -e "  Stopped:            ${YELLOW}$STOPPED${NC}"
    
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Function to show docker images
show_docker_images() {
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}                   Docker Images                       ${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    
    echo -e "${CYAN}Command: docker images${NC}"
    echo ""
    docker images
    
    echo ""
    echo -e "${YELLOW}MER System Images:${NC}"
    echo ""
    docker images | grep -E "(mer-|REPOSITORY)" || echo -e "${YELLOW}No MER System images found${NC}"
    
    echo ""
    echo -e "${YELLOW}Summary:${NC}"
    TOTAL_IMAGES=$(docker images -q | wc -l)
    DANGLING=$(docker images -f "dangling=true" -q | wc -l)
    TOTAL_SIZE=$(docker images --format "{{.Size}}" | sed 's/[A-Z]//g' | awk '{s+=$1} END {print s}')
    
    echo -e "  Total images:       ${CYAN}$TOTAL_IMAGES${NC}"
    if [ "$DANGLING" -gt 0 ]; then
        echo -e "  Dangling (unused):  ${YELLOW}$DANGLING${NC} ${RED}(can be pruned)${NC}"
    else
        echo -e "  Dangling (unused):  ${GREEN}0${NC}"
    fi
    
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Function to show volumes and networks
show_volumes_networks() {
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}          Docker Volumes & Networks                    ${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    
    echo -e "${YELLOW}Docker Volumes:${NC}"
    echo ""
    docker volume ls
    
    echo ""
    echo -e "${YELLOW}MER System Volumes:${NC}"
    echo ""
    docker volume ls | grep -E "(mer-|DRIVER)" || echo -e "${YELLOW}No MER System volumes found${NC}"
    
    echo ""
    echo -e "${YELLOW}Docker Networks:${NC}"
    echo ""
    docker network ls
    
    echo ""
    echo -e "${YELLOW}MER System Networks:${NC}"
    echo ""
    docker network ls | grep -E "(mer-|NETWORK)" || echo -e "${YELLOW}No MER System networks found${NC}"
    
    echo ""
    echo -e "${CYAN}Volume Details:${NC}"
    for vol in $(docker volume ls -q | grep "mer-"); do
        SIZE=$(docker system df -v | grep "$vol" | awk '{print $3}')
        echo -e "  ${GREEN}●${NC} $vol: ${CYAN}${SIZE:-N/A}${NC}"
    done
    
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Function to show docker system usage
show_docker_usage() {
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}             Docker System Usage & Info                ${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    
    echo -e "${YELLOW}System Disk Usage:${NC}"
    echo ""
    docker system df
    
    echo ""
    echo -e "${YELLOW}Detailed Space Usage:${NC}"
    echo ""
    docker system df -v | head -20
    
    echo ""
    echo -e "${YELLOW}Docker Info:${NC}"
    echo ""
    
    DOCKER_VERSION=$(docker --version | cut -d' ' -f3 | sed 's/,//')
    CONTAINERS_TOTAL=$(docker ps -aq | wc -l)
    CONTAINERS_RUNNING=$(docker ps -q | wc -l)
    IMAGES_TOTAL=$(docker images -q | wc -l)
    VOLUMES_TOTAL=$(docker volume ls -q | wc -l)
    
    echo -e "  Docker Version:     ${CYAN}$DOCKER_VERSION${NC}"
    echo -e "  Containers:         ${CYAN}$CONTAINERS_RUNNING${NC}/${CYAN}$CONTAINERS_TOTAL${NC} running"
    echo -e "  Images:             ${CYAN}$IMAGES_TOTAL${NC}"
    echo -e "  Volumes:            ${CYAN}$VOLUMES_TOTAL${NC}"
    
    echo ""
    echo -e "${YELLOW}Quick Cleanup Recommendations:${NC}"
    
    STOPPED=$(docker ps -aq -f status=exited | wc -l)
    DANGLING=$(docker images -f "dangling=true" -q | wc -l)
    
    if [ "$STOPPED" -gt 0 ]; then
        echo -e "  ${YELLOW}⚠${NC} ${STOPPED} stopped container(s) - can be removed with: ${CYAN}docker container prune${NC}"
    fi
    
    if [ "$DANGLING" -gt 0 ]; then
        echo -e "  ${YELLOW}⚠${NC} ${DANGLING} dangling image(s) - can be removed with: ${CYAN}docker image prune${NC}"
    fi
    
    if [ "$STOPPED" -eq 0 ] && [ "$DANGLING" -eq 0 ]; then
        echo -e "  ${GREEN}✓${NC} System is clean - no cleanup needed"
    fi
    
    echo ""
    echo -e "${CYAN}Use Option 50 for automated cleanup${NC}"
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Function to show app logs
show_app_logs() {
    echo ""
    if ! docker ps --format '{{.Names}}' | grep -q "^${APP_CONTAINER}$"; then
        echo -e "${RED}✗ App container is not running!${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi
    
    echo -e "${CYAN}Showing App Logs (Ctrl+C to exit)...${NC}"
    echo ""
    docker logs $APP_CONTAINER --tail 100 -f
}

# Function to show db logs
show_db_logs() {
    echo ""
    if ! docker ps --format '{{.Names}}' | grep -q "^${DB_CONTAINER}$"; then
        echo -e "${RED}✗ Database container is not running!${NC}"
        echo ""
        read -p "Press Enter to continue..."
        return 1
    fi
    
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
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}           MER System - Health Check Status           ${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""

    # Load port from .env
    APP_PORT=$(grep "^APP_PORT=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
    APP_PORT=${APP_PORT:-8000}
    
    DB_PORT=$(grep "^FORWARD_DB_PORT=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
    DB_PORT=${DB_PORT:-5432}
    
    REDIS_PORT=$(grep "^FORWARD_REDIS_PORT=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
    REDIS_PORT=${REDIS_PORT:-6379}

    # Check containers are running
    echo -e "${YELLOW}Container Status:${NC}"
    for container in $APP_CONTAINER $WEB_CONTAINER $DB_CONTAINER $REDIS_CONTAINER; do
        if docker ps --format '{{.Names}}' | grep -q "^${container}$"; then
            echo -e "  ${GREEN}✓${NC} $container is running"
        else
            echo -e "  ${RED}✗${NC} $container is NOT running"
        fi
    done
    echo ""

    # Check services
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
    if docker ps --format '{{.Names}}' | grep -q "^${DB_CONTAINER}$"; then
        if docker exec $DB_CONTAINER pg_isready -q 2>/dev/null; then
            echo -e "${GREEN}✓ Ready (port $DB_PORT)${NC}"
        else
            echo -e "${RED}✗ Not ready${NC}"
        fi
    else
        echo -e "${RED}✗ Container not running${NC}"
    fi

    # Redis
    echo -n "  Redis Cache/Session:   "
    if docker ps --format '{{.Names}}' | grep -q "^${REDIS_CONTAINER}$"; then
        REDIS_RESULT=$(docker exec $REDIS_CONTAINER redis-cli ping 2>/dev/null || echo "FAIL")
        if [ "$REDIS_RESULT" = "PONG" ]; then
            echo -e "${GREEN}✓ PONG (port $REDIS_PORT)${NC}"
        else
            echo -e "${RED}✗ $REDIS_RESULT${NC}"
        fi
    else
        echo -e "${RED}✗ Container not running${NC}"
    fi
    
    # PHP-FPM
    echo -n "  PHP-FPM Application:   "
    if docker ps --format '{{.Names}}' | grep -q "^${APP_CONTAINER}$"; then
        PHP_VERSION=$(docker exec $APP_CONTAINER php -v 2>/dev/null | head -n1 | cut -d' ' -f2)
        if [ -n "$PHP_VERSION" ]; then
            echo -e "${GREEN}✓ PHP $PHP_VERSION${NC}"
        else
            echo -e "${YELLOW}⚠ Running but PHP check failed${NC}"
        fi
    else
        echo -e "${RED}✗ Container not running${NC}"
    fi

    echo ""
    echo -e "${YELLOW}Access URLs:${NC}"
    echo -e "  ${CYAN}Application:${NC}     http://localhost:$APP_PORT"
    echo -e "  ${CYAN}PostgreSQL:${NC}      localhost:$DB_PORT"
    echo -e "  ${CYAN}Redis:${NC}           localhost:$REDIS_PORT"
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
    echo ""
    read -p "Press Enter to continue..."
}

# Function to run artisan command
run_artisan() {
    echo ""
    
    # Check if app container is running
    if ! docker ps --format '{{.Names}}' | grep -q "^${APP_CONTAINER}$"; then
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
        $DOCKER_EXEC $APP_CONTAINER php artisan $artisan_cmd
    else
        echo -e "${YELLOW}Cancelled.${NC}"
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
            docker compose up 
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
                docker compose up 
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
            docker compose up 
            echo -e "${GREEN}✓ Quick rebuild complete!${NC}"
            read -p "Press Enter to continue..."
            ;;
        5)
            check_env || continue
            check_nginx_config || continue
            echo ""
            echo -e "${RED}Force Rebuild - This will rebuild ALL images WITHOUT cache!${NC}"
            echo -e "${YELLOW}Containers will restart but database volume will be preserved.${NC}"
            echo ""
            read -p "Are you sure? (y/n): " confirm

            if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
                cd "$COMPOSE_DIR"

                echo -e "${YELLOW}Stopping containers...${NC}"
                docker compose down

                echo -e "${YELLOW}Building images without cache...${NC}"
                docker compose build --no-cache

                echo -e "${YELLOW}Starting services...${NC}"
                docker compose up

                echo ""
                echo -e "${GREEN}✓ Force rebuild completed successfully!${NC}"
                echo -e "${CYAN}All images rebuilt from scratch.${NC}"
            fi

            echo ""
            read -p "Press Enter to continue..."
            ;;
        # Status & Logs
        10)
            show_status
            ;;
        11)
            show_all_containers
            ;;
        12)
            show_docker_images
            ;;
        13)
            show_volumes_networks
            ;;
        14)
            show_docker_usage
            ;;
        15)
            test_endpoint
            ;;
        
        # Logs
        16)
            show_app_logs
            ;;
        17)
            show_db_logs
            ;;
        18)
            show_all_logs
            ;;

        # Laravel Commands
        20)
            echo ""
            if ! docker ps --format '{{.Names}}' | grep -q "^${APP_CONTAINER}$"; then
                echo -e "${RED}✗ App container is not running!${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi
            
            echo -e "${GREEN}Running Database Migrations...${NC}"
            echo -e "${CYAN}This will run pending migrations${NC}"
            echo ""
            docker exec $APP_CONTAINER php artisan migrate
            echo ""
            echo -e "${GREEN}✓ Migrations complete${NC}"
            read -p "Press Enter to continue..."
            ;;
        21)
            echo ""
            if ! docker ps --format '{{.Names}}' | grep -q "^${APP_CONTAINER}$"; then
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
                echo ""
                echo -e "${YELLOW}Running fresh migration with seeders...${NC}"
                docker exec $APP_CONTAINER php artisan migrate:fresh --seed
                echo ""
                echo -e "${GREEN}✓ Fresh migration complete with seed data${NC}"
            else
                echo ""
                echo -e "${GREEN}✓ Operation cancelled${NC}"
            fi
            echo ""
            read -p "Press Enter to continue..."
            ;;
        22)
            echo ""
            if ! docker ps --format '{{.Names}}' | grep -q "^${APP_CONTAINER}$"; then
                echo -e "${RED}✗ App container is not running!${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi
            
            echo -e "${CYAN}Clearing All Laravel Cache...${NC}"
            echo ""
            echo -n "  Config cache...      "
            docker exec $APP_CONTAINER php artisan config:clear 2>/dev/null && echo -e "${GREEN}✓${NC}" || echo -e "${RED}✗${NC}"
            echo -n "  Route cache...       "
            docker exec $APP_CONTAINER php artisan route:clear 2>/dev/null && echo -e "${GREEN}✓${NC}" || echo -e "${RED}✗${NC}"
            echo -n "  View cache...        "
            docker exec $APP_CONTAINER php artisan view:clear 2>/dev/null && echo -e "${GREEN}✓${NC}" || echo -e "${RED}✗${NC}"
            echo -n "  Application cache... "
            docker exec $APP_CONTAINER php artisan cache:clear 2>/dev/null && echo -e "${GREEN}✓${NC}" || echo -e "${RED}✗${NC}"
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
            if ! docker ps --format '{{.Names}}' | grep -q "^${APP_CONTAINER}$"; then
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
            $DOCKER_EXEC $APP_CONTAINER sh
            ;;

        # NPM Commands
        30)
            echo ""
            if ! docker ps --format '{{.Names}}' | grep -q "^${APP_CONTAINER}$"; then
                echo -e "${RED}✗ App container is not running!${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi
            
            echo -e "${GREEN}Running NPM Install...${NC}"
            echo -e "${CYAN}This will install all Node.js dependencies from package.json${NC}"
            echo ""
            docker exec $APP_CONTAINER npm install
            echo ""
            echo -e "${GREEN}✓ NPM install complete${NC}"
            read -p "Press Enter to continue..."
            ;;
        31)
            echo ""
            if ! docker ps --format '{{.Names}}' | grep -q "^${APP_CONTAINER}$"; then
                echo -e "${RED}✗ App container is not running!${NC}"
                echo ""
                read -p "Press Enter to continue..."
                continue
            fi
            
            echo -e "${GREEN}Running NPM Build...${NC}"
            echo -e "${CYAN}This will build frontend assets with Vite${NC}"
            echo ""
            docker exec $APP_CONTAINER npm run build
            echo ""
            echo -e "${GREEN}✓ Build complete - assets ready for production${NC}"
            read -p "Press Enter to continue..."
            ;;

        # Database
        40)
            echo ""
            if ! docker ps --format '{{.Names}}' | grep -q "^${DB_CONTAINER}$"; then
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
            echo -e "  ${CYAN}\\dt akun.*${NC}      - List tables in 'akun' schema"
            echo -e "  ${CYAN}\\l${NC}               - List all databases"
            echo -e "  ${CYAN}\\dn${NC}              - List all schemas"
            echo -e "  ${CYAN}\\q${NC}               - Quit psql"
            echo ""
            
            DB_DATABASE=$(grep "^DB_DATABASE=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
            DB_USERNAME=$(grep "^DB_USERNAME=" "$COMPOSE_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
            
            echo -e "${GREEN}Connecting to: ${DB_DATABASE:-mer_system}${NC}"
            echo ""
            $DOCKER_EXEC $DB_CONTAINER psql -U "${DB_USERNAME:-postgres}" -d "${DB_DATABASE:-mer_system}"
            ;;
        41)
            echo ""
            if ! docker ps --format '{{.Names}}' | grep -q "^${REDIS_CONTAINER}$"; then
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
            $DOCKER_EXEC $REDIS_CONTAINER redis-cli
            ;;
        42)
            echo ""
            echo -e "${RED}╔════════════════════════════════════════════════════════╗${NC}"
            echo -e "${RED}║         WARNING: DATABASE RESET OPERATION              ║${NC}"
            echo -e "${RED}╚════════════════════════════════════════════════════════╝${NC}"
            echo ""
            echo -e "${RED}This will:${NC}"
            echo -e "  ${YELLOW}1.${NC} Stop all services"
            echo -e "  ${YELLOW}2.${NC} Remove the PostgreSQL data volume"
            echo -e "  ${YELLOW}3.${NC} Restart services (DDL & migrations will run)"
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
                echo -e "${YELLOW}[1/4] Stopping services...${NC}"
                cd "$COMPOSE_DIR"
                docker compose down
                
                echo -e "${YELLOW}[2/4] Removing database volume...${NC}"
                VOLUME_NAME=$(docker volume ls -q --filter name=mer-db-data)
                if [ -n "$VOLUME_NAME" ]; then
                    docker volume rm "$VOLUME_NAME" 2>/dev/null && echo -e "${GREEN}  ✓ Volume removed${NC}" || echo -e "${YELLOW}  ⚠ Volume not found or already removed${NC}"
                else
                    echo -e "${YELLOW}  ⚠ No volume found to remove${NC}"
                fi
                
                echo -e "${YELLOW}[3/4] Starting database service...${NC}"
                docker compose up -d db
                
                echo -e "${YELLOW}[4/4] Waiting for database to be ready...${NC}"
                sleep 5
                
                echo -e "${YELLOW}Starting all services...${NC}"
                docker compose up -d
                
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

        # Cleanup
        50)
            echo ""
            echo -e "${YELLOW}╔════════════════════════════════════════════════════════╗${NC}"
            echo -e "${YELLOW}║          Docker Cleanup Operation                      ║${NC}"
            echo -e "${YELLOW}╚════════════════════════════════════════════════════════╝${NC}"
            echo ""
            echo -e "${CYAN}This will clean up:${NC}"
            echo -e "  - Stopped containers"
            echo -e "  - Dangling images"
            echo -e "  - Unused volumes"
            echo -e "  - Build cache"
            echo ""
            read -p "Continue? (y/n): " confirm
            
            if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
                echo ""
                echo -e "${YELLOW}Current Docker usage:${NC}"
                docker system df
                echo ""
                
                echo -e "${YELLOW}[1/4] Pruning stopped containers...${NC}"
                docker container prune -f
                
                echo -e "${YELLOW}[2/4] Pruning dangling images...${NC}"
                docker image prune -f
                
                echo -e "${YELLOW}[3/4] Pruning unused volumes...${NC}"
                docker volume prune -f
                
                echo -e "${YELLOW}[4/4] Pruning build cache...${NC}"
                docker builder prune -f
                
                echo ""
                echo -e "${GREEN}✓ Cleanup complete!${NC}"
                echo ""
                echo -e "${YELLOW}Docker usage after cleanup:${NC}"
                docker system df
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
            echo -e "${RED}║       WARNING: COMPLETE REMOVAL OPERATION              ║${NC}"
            echo -e "${RED}╚════════════════════════════════════════════════════════╝${NC}"
            echo ""
            echo -e "${RED}This will PERMANENTLY remove:${NC}"
            echo -e "  ${YELLOW}✗${NC} All MER System containers"
            echo -e "  ${YELLOW}✗${NC} All MER System images"
            echo -e "  ${YELLOW}✗${NC} All MER System volumes (INCLUDING DATABASE)"
            echo -e "  ${YELLOW}✗${NC} All MER System networks"
            echo ""
            echo -e "${RED}ALL DATA WILL BE LOST!${NC}"
            echo ""
            read -p "Type 'DELETE ALL' to confirm (or anything else to cancel): " confirm
            
            if [ "$confirm" = "DELETE ALL" ]; then
                echo ""
                echo -e "${YELLOW}Removing all MER System resources...${NC}"
                cd "$COMPOSE_DIR"
                docker compose down -v --rmi all 2>/dev/null
                echo ""
                echo -e "${GREEN}✓ All MER System resources removed!${NC}"
                echo -e "${CYAN}You can rebuild from scratch using Option 3${NC}"
            else
                echo ""
                echo -e "${GREEN}✓ Operation cancelled. No changes made.${NC}"
            fi
            echo ""
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
