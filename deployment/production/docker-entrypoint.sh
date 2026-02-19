#!/bin/sh
set -e

# ===========================================
# MER System - Production Entrypoint
# ===========================================
# Script ini menangani inisialisasi production:
# - Database migration (--force untuk non-interactive)
# - Laravel optimization (config/route/view cache)
# - Sync public assets ke shared volume (replica-safe)
# - Set permission
#
# CATATAN ARSITEKTUR:
# - TIDAK ada loop pengecekan DB karena docker-compose
#   depends_on + healthcheck sudah menjamin PostgreSQL
#   ready sebelum container ini start
# - Public assets sync menggunakan mkdir-based lock
#   agar aman jika dijalankan dengan multiple replicas
# ===========================================

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

log_message "Starting MER System production container..."

# -------------------------------------------
# 1. Run database migrations
#    --force: wajib untuk environment production
#    (Laravel menolak migration tanpa flag ini di production)
# -------------------------------------------
log_message "Running database migrations..."
php artisan migrate --force

# -------------------------------------------
# 2. Optimize application for production
#    Meng-cache config, route, dan view ke file PHP
#    sehingga tidak perlu parsing ulang setiap request
# -------------------------------------------
log_message "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# -------------------------------------------
# 3. Sync public assets ke shared volume
#
#    ARSITEKTUR:
#    - Image production sudah memiliki public assets
#      (termasuk Vite build output) di /var/www/html/public/
#    - Volume shared-public di-mount ke /public-shared
#      (BUKAN ke /var/www/html/public) agar tidak men-shadow
#      file bawaan image
#    - Script ini meng-copy assets ke /public-shared
#    - Nginx membaca dari volume shared-public
#
#    REPLICA SAFETY (mkdir-based lock):
#    - mkdir bersifat atomic di POSIX filesystem
#    - Hanya satu replica yang bisa membuat lock directory
#    - Replica lain menunggu sampai lock dilepas
#    - Trap EXIT memastikan lock dilepas walau terjadi error
#    - Ini mencegah race condition saat multiple replicas
#      mencoba sync secara bersamaan
# -------------------------------------------
if [ -d "/public-shared" ]; then
    LOCKDIR="/public-shared/.sync-lock"

    # Bersihkan stale lock (jika container sebelumnya crash)
    # Lock dianggap stale jika lebih dari 120 detik
    if [ -d "$LOCKDIR" ]; then
        LOCK_AGE=$(( $(date +%s) - $(stat -c %Y "$LOCKDIR" 2>/dev/null || echo 0) ))
        if [ "$LOCK_AGE" -gt 120 ]; then
            log_message "Removing stale sync lock (age: ${LOCK_AGE}s)..."
            rm -rf "$LOCKDIR"
        fi
    fi

    # Acquire lock
    RETRIES=0
    MAX_RETRIES=30
    while ! mkdir "$LOCKDIR" 2>/dev/null; do
        RETRIES=$((RETRIES + 1))
        if [ "$RETRIES" -ge "$MAX_RETRIES" ]; then
            log_message "WARNING: Could not acquire sync lock after ${MAX_RETRIES} attempts. Proceeding without sync."
            break
        fi
        log_message "Waiting for sync lock... (attempt $RETRIES/$MAX_RETRIES)"
        sleep 2
    done

    if [ "$RETRIES" -lt "$MAX_RETRIES" ]; then
        # Pastikan lock dilepas saat exit (normal atau error)
        trap 'rm -rf "$LOCKDIR"' EXIT

        log_message "Syncing public assets to shared volume..."
        cp -a /var/www/html/public/. /public-shared/

        rm -rf "$LOCKDIR"
        trap - EXIT

        log_message "Public assets synced successfully"
    fi
fi

# -------------------------------------------
# 4. Set permissions
# -------------------------------------------
log_message "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

log_message "Initialization complete. Starting PHP-FPM..."

# Execute main command (php-fpm)
exec "$@"
