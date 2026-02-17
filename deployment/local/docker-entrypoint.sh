#!/bin/bash
set -e

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

log_message "Starting MER System container initialization..."

# Wait for database connection
log_message "Waiting for database connection..."
max_tries=15
counter=0
until php -r "try { new PDO('pgsql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); echo 'OK'; } catch(Exception \$e) { exit(1); }" 2>/dev/null || [ $counter -eq $max_tries ]; do
    counter=$((counter+1))
    log_message "Waiting for database... (attempt $counter/$max_tries)"
    sleep 3
done

if [ $counter -eq $max_tries ]; then
    log_message "WARNING: Could not connect to database after $max_tries attempts. Continuing anyway..."
fi

# Wait for Redis connection
log_message "Waiting for Redis connection..."
max_tries_redis=10
counter_redis=0
until php -r "try { \$r = new Redis(); \$r->connect(getenv('REDIS_HOST') ?: 'redis', (int)(getenv('REDIS_PORT') ?: 6379)); echo 'OK'; } catch(Exception \$e) { exit(1); }" 2>/dev/null || [ $counter_redis -eq $max_tries_redis ]; do
    counter_redis=$((counter_redis+1))
    log_message "Waiting for Redis... (attempt $counter_redis/$max_tries_redis)"
    sleep 2
done

if [ $counter_redis -eq $max_tries_redis ]; then
    log_message "WARNING: Could not connect to Redis after $max_tries_redis attempts. Continuing anyway..."
fi

# Skip composer install in development - run manually after container starts
# This prevents permission issues with bind-mounts
if [ "${APP_ENV:-local}" != "local" ]; then
    if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
        log_message "Installing Composer dependencies..."
        composer install --prefer-dist --no-interaction
    fi
else
    log_message "Development mode: Skipping automatic composer install"
    log_message "Run: docker exec mer-app-dev composer install"
fi

# Check if .env exists, if not copy from .env.example
if [ ! -f ".env" ]; then
    log_message "Creating .env file from .env.example..."
    cp .env.example .env
    php artisan key:generate
fi

# Skip migrations in development - run manually
if [ "${APP_ENV:-local}" != "local" ]; then
    log_message "Running database migrations..."
    php artisan migrate --force || log_message "Migration failed or no migrations to run"
else
    log_message "Development mode: Skipping automatic migrations"
    log_message "Run migrations manually: docker exec mer-app-dev php artisan migrate"
fi

# Clear cache only if vendor exists (composer has run)
if [ -f "vendor/autoload.php" ]; then
    log_message "Clearing cache..."
    php artisan config:clear 2>/dev/null || true
    php artisan cache:clear 2>/dev/null || true
    php artisan view:clear 2>/dev/null || true
else
    log_message "Skipping cache clear (vendor not installed yet)"
fi

# Set permissions for storage directories
log_message "Setting permissions..."
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

log_message "Initialization complete!"

# Start Vite dev server in background if npm is available
if command -v npm &> /dev/null; then
    log_message "Starting Vite dev server in background..."
    npm run dev -- --host 0.0.0.0 &
fi

# Execute the main command (php-fpm)
log_message "Starting PHP-FPM server..."
exec "$@"
