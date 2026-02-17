#!/bin/sh
set -e

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

log_message "Starting MER System production container..."

# Wait for database
log_message "Waiting for database connection..."
max_tries=30
counter=0
until php -r "try { new PDO('pgsql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); echo 'OK'; } catch(Exception \$e) { exit(1); }" 2>/dev/null || [ $counter -eq $max_tries ]; do
    counter=$((counter+1))
    log_message "Waiting for database... (attempt $counter/$max_tries)"
    sleep 2
done

if [ $counter -eq $max_tries ]; then
    log_message "ERROR: Could not connect to database after $max_tries attempts"
    exit 1
fi

log_message "Database connection established"

# Run migrations
log_message "Running database migrations..."
php artisan migrate --force

# Optimize for production
log_message "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Copy public assets to shared volume (for Nginx)
if [ -d /var/www/html/public ]; then
    log_message "Syncing public assets to shared volume..."
    cp -ru /var/www/html/public/* /var/www/html/public/ 2>/dev/null || true
fi

# Set permissions
log_message "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

log_message "Initialization complete. Starting PHP-FPM..."

# Execute main command
exec "$@"
