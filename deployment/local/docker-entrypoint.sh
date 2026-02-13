#!/bin/sh
set -e

# Jika folder vendor belum ada, install composer
if [ ! -f "vendor/autoload.php" ]; then
    composer install --no-progress --no-interaction
fi

# Jalankan npm install jika node_modules belum ada
if [ ! -d "node_modules" ]; then
    npm install
fi

# Jalankan migrasi database otomatis
echo "Running migrations..."
php artisan migrate --force

# Start PHP-FPM
exec "$@"