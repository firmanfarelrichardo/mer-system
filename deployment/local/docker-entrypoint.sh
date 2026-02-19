#!/bin/bash
set -e

# ===========================================
# MER System - Development Entrypoint
# ===========================================
# Script ini hanya menangani inisialisasi ringan:
# - Setup .env file
# - Cek ketersediaan vendor
# - Clear stale cache
# - Set permission
#
# CATATAN ARSITEKTUR:
# - TIDAK ada loop pengecekan DB/Redis karena
#   docker-compose depends_on + healthcheck
#   sudah menjamin service siap sebelum container ini start
# - TIDAK ada logika NPM/Vite karena sudah ditangani
#   oleh container `vite` yang terpisah
# ===========================================

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

log_message "Starting MER System container initialization..."

# -------------------------------------------
# 1. Environment file setup
#    Buat .env dari .env.example jika belum ada
# -------------------------------------------
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        log_message "Creating .env file from .env.example..."
        cp .env.example .env
        php artisan key:generate --no-interaction
    else
        log_message "WARNING: No .env or .env.example found"
    fi
fi

# -------------------------------------------
# 2. Composer dependency check
#    Di development, composer install dilakukan manual
#    agar developer punya kontrol penuh atas dependency
# -------------------------------------------
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    log_message "Vendor directory not found."
    log_message "Run: docker exec mer-app-dev composer install"
else
    log_message "Vendor directory detected"
fi

# -------------------------------------------
# 3. Clear stale cache
#    Hanya jika vendor sudah ter-install
# -------------------------------------------
if [ -f "vendor/autoload.php" ]; then
    log_message "Clearing stale cache..."
    php artisan config:clear 2>/dev/null || true
    php artisan cache:clear 2>/dev/null || true
    php artisan view:clear 2>/dev/null || true
fi

# -------------------------------------------
# 4. Set permissions untuk storage & bootstrap cache
#    www-data harus bisa menulis ke direktori ini
# -------------------------------------------
log_message "Setting permissions..."
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

log_message "Initialization complete!"
log_message "Starting PHP-FPM server..."

# Execute main command (php-fpm)
exec "$@"
