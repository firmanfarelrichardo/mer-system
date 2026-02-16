# ===========================================
# MER System - Laravel Dockerfile (Development)
# ===========================================

FROM php:8.3-fpm-alpine

# Set working directory
WORKDIR /var/www/html

# Install dependencies
RUN apk add --no-cache \
    git \
    curl \
    bash \
    ca-certificates \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    postgresql-dev \
    icu-dev \
    nodejs \
    npm \
    $PHPIZE_DEPS \
    && update-ca-certificates

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip intl

# Install Redis extension via PECL
RUN pecl channel-update pecl.php.net \
    && pecl install redis \
    && docker-php-ext-enable redis

# Clean up build dependencies
RUN apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear /tmp/* /var/cache/apk/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files first
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install --no-scripts --no-autoloader --prefer-dist

# Copy package.json for npm
COPY package.json package-lock.json* ./

# Install npm dependencies
RUN npm install

# Copy application files
COPY . .

# Generate autoloader
RUN composer dump-autoload

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create storage directories if not exist
RUN mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views \
    && chmod -R 777 /var/www/html/storage

# Copy entrypoint script
COPY deployment/local/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose PHP-FPM port and Vite HMR port
EXPOSE 9000 5173

# Start with entrypoint script
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
