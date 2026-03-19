#!/bin/sh
set -e

# ===========================================
# MER System — Production Entrypoint
# ===========================================
# Menangani inisialisasi container saat startup:
# 1. Sync public assets ke shared volume (untuk Nginx)
# 2. Set permissions pada storage
#
# CATATAN:
# - Migration & optimasi TIDAK dilakukan di sini.
#   Keduanya dijalankan oleh deploy.sh (Step 4 & 5)
#   SETELAH semua container sehat, agar urutan terkontrol.
# - Public assets sync menggunakan mkdir-based lock
#   (atomic di POSIX) untuk keamanan multi-replica.
# ===========================================

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

log_message "Starting MER System production container..."

# -------------------------------------------
# 1. Sync public assets ke shared volume
#
#    Image sudah memiliki public assets (termasuk Vite build)
#    di /var/www/html/public/. Volume shared-public di-mount
#    ke /public-shared (bukan /var/www/html/public) agar tidak
#    men-shadow file bawaan image. Nginx membaca dari volume ini.
# -------------------------------------------
if [ -d "/public-shared" ]; then
    LOCKDIR="/public-shared/.sync-lock"

    # Bersihkan stale lock (container sebelumnya mungkin crash)
    if [ -d "$LOCKDIR" ]; then
        LOCK_AGE=$(( $(date +%s) - $(stat -c %Y "$LOCKDIR" 2>/dev/null || echo 0) ))
        if [ "$LOCK_AGE" -gt 120 ]; then
            log_message "Removing stale sync lock (age: ${LOCK_AGE}s)..."
            rm -rf "$LOCKDIR"
        fi
    fi

    # Acquire lock (mkdir atomic)
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
        trap 'rm -rf "$LOCKDIR"' EXIT

        log_message "Syncing public assets to shared volume..."
        cp -a /var/www/html/public/. /public-shared/

        rm -rf "$LOCKDIR"
        trap - EXIT

        log_message "Public assets synced successfully"
    fi
fi

# -------------------------------------------
# 2. Set permissions
# -------------------------------------------
log_message "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

log_message "Initialization complete. Starting supervisord..."

# Execute CMD (supervisord)
exec "$@"
