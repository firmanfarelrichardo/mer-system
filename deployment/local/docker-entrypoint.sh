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

# Install composer dependencies if not present
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    log_message "Installing Composer dependencies..."
    composer install --prefer-dist
fi

# Check if .env exists, if not copy from .env.example
if [ ! -f ".env" ]; then
    log_message "Creating .env file from .env.example..."
    cp .env.example .env
    php artisan key:generate
fi

# Run migrations
log_message "Running database migrations..."
php artisan migrate --force || log_message "Migration failed or no migrations to run"

# Clear and cache config for development
log_message "Clearing cache..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Set permissions
log_message "Setting permissions..."
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

log_message "Initialization complete!"

# Start Vite dev server in background if npm is available
if command -v npm &> /dev/null; then
    log_message "Starting Vite dev server in background..."
    npm run dev -- --host 0.0.0.0 &
fi

# Execute the main command (php-fpm)
log_message "Starting PHP-FPM server..."
exec "$@"
