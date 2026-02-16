# ===========================================
# SI Project TIK - Production Dockerfile
# Multi-stage build for optimized image
# ===========================================

# Stage 1: Build assets
FROM node:22-alpine AS assets-builder

WORKDIR /app

# Copy package files
COPY package.json package-lock.json* ./

# Install dependencies
RUN npm ci --only=production

# Copy source files
COPY resources/ ./resources/
COPY vite.config.js ./
COPY tailwind.config.js* ./
COPY postcss.config.js* ./

# Build assets
RUN npm run build

# Stage 2: Install PHP dependencies
FROM composer:latest AS composer-builder

WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies (no dev)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --optimize-autoloader

# Copy application files
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --no-dev --optimize

# Stage 3: Production image
FROM php:8.2-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    postgresql-dev \
    icu-dev

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip intl opcache

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copy PHP production config
COPY deployment/production/php.ini /usr/local/etc/php/conf.d/99-custom.ini

# Copy nginx config
COPY deployment/production/nginx.conf /etc/nginx/nginx.conf

# Copy supervisor config
COPY deployment/production/supervisord.conf /etc/supervisord.conf

# Set working directory
WORKDIR /var/www/html

# Copy application from composer stage
COPY --from=composer-builder /app .

# Copy built assets from node stage
COPY --from=assets-builder /app/public/build ./public/build

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create required directories
RUN mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views \
    && mkdir -p /var/log/supervisor \
    && mkdir -p /run/nginx

# Copy entrypoint
COPY deployment/production/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
