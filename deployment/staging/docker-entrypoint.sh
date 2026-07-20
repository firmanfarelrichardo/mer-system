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
# 2. Composer install (auto jika vendor belum ada)
# -------------------------------------------
if [ ! -f "vendor/autoload.php" ]; then
    log_message "vendor/autoload.php not found - running composer install..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
    log_message "composer install complete"
else
    log_message "Vendor directory detected - skipping composer install"
fi

# -------------------------------------------
# 3. Generate app key (jika APP_KEY belum di-set)
# -------------------------------------------
APP_KEY_VAL=$(grep "^APP_KEY=" .env 2>/dev/null | cut -d'=' -f2 | tr -d '\r')
if [ -z "$APP_KEY_VAL" ]; then
    log_message "APP_KEY is empty - generating application key..."
    php artisan key:generate --no-interaction --force
    log_message "Application key generated"
else
    log_message "APP_KEY already set - skipping key:generate"
fi

# -------------------------------------------
# 4. Clear stale cache
# -------------------------------------------
log_message "Clearing stale cache..."
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

# -------------------------------------------
# 5. Run database migrations (idempotent - aman dijalankan setiap start)
#    depends_on healthcheck sudah menjamin DB siap sebelum ini berjalan
# -------------------------------------------
log_message "Running database migrations..."
php artisan migrate --no-interaction --force
log_message "Migrations complete"

# -------------------------------------------
# 6. Set permissions untuk storage & bootstrap cache
#    - Buat file log agar PHP-FPM tidak gagal menulis
#    - chown + chmod diperlukan karena bind-mount
#      bisa menimpa ownership yang sudah diset di Dockerfile
# -------------------------------------------
log_message "Setting permissions..."
mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
touch storage/logs/laravel.log
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
chmod 664 storage/logs/laravel.log 2>/dev/null || true

log_message "Initialization complete!"
log_message "Starting PHP-FPM server..."

# Execute main command (php-fpm)
exec "$@"
