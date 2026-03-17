# ===========================================
# MER System - Production Dockerfile
# Multi-stage build for optimized image
# ===========================================

# ==== Stage 1: Build frontend assets ====
FROM node:22-alpine AS assets-builder

WORKDIR /build

COPY package.json package-lock.json* ./
RUN npm ci

COPY resources/ ./resources/
COPY vite.config.js ./
COPY tailwind.config.js* postcss.config.js* ./
COPY public/ ./public/

RUN npm run build

# ==== Stage 2: Install PHP dependencies ====
FROM composer:2 AS composer-builder

WORKDIR /build

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --no-dev --optimize

# ==== Stage 3: Production PHP-FPM image ====
# php:8.2-fpm-alpine dipilih karena:
# - LTS dengan dukungan security update panjang
# - Alpine meminimalkan attack surface (image lebih kecil, paket minimal)
# - fpm variant optimal untuk deployment di belakang Nginx proxy
FROM php:8.2-fpm-alpine

# -------------------------------------------
# System dependencies (runtime only)
# Dipisah build-deps dan runtime-deps untuk meminimalkan layer size.
# .build-deps dihapus di akhir RUN yang sama agar layer tidak membawa bekas compiler.
# -------------------------------------------
RUN apk add --no-cache \
        curl \
        libpng \
        libjpeg-turbo \
        freetype \
        libzip \
        libxml2 \
        libpq \
        icu-libs \
        oniguruma \
        supervisor \
        fcgi \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        libxml2-dev \
        postgresql-dev \
        icu-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear /tmp/* /var/cache/apk/*

# -------------------------------------------
# PHP production config
# php.ini-production mengaktifkan error_reporting yang ketat &
# menonaktifkan display_errors secara default. File 99-custom.ini
# override nilai-nilai spesifik (memory, opcache, dll).
# -------------------------------------------
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY deployment/production/php.ini /usr/local/etc/php/conf.d/99-custom.ini

# -------------------------------------------
# Supervisord config
# Supervisord digunakan sebagai PID 1 untuk mengelola multiple processes
# (php-fpm + queue worker) dalam satu container sesuai best practice
# single-container multi-process untuk deployment sederhana di VPS.
# -------------------------------------------
COPY deployment/production/supervisord.conf /etc/supervisor/supervisord.conf

# -------------------------------------------
# Application source
# -------------------------------------------
WORKDIR /var/www/html

COPY --from=composer-builder /build/vendor ./vendor
COPY . .
COPY --from=assets-builder /build/public/build ./public/build

# -------------------------------------------
# Permissions
# -------------------------------------------
RUN mkdir -p \
        storage/logs \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
        /var/log/supervisor \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 storage bootstrap/cache

# -------------------------------------------
# Entrypoint
# -------------------------------------------
COPY deployment/production/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000

# HEALTHCHECK menggunakan php-fpm-healthcheck (dari package fcgi) karena
# memberikan check yang sesungguhnya ke PHP-FPM status endpoint,
# lebih akurat dibanding sekedar TCP check.
# start-period=60s memberi waktu cukup untuk entrypoint (migrate, cache, sync assets).
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
# Supervisord sebagai PID 1 menggantikan php-fpm langsung.
# Ini memungkinkan satu container mengelola php-fpm + queue worker secara bersamaan.
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]

# ===========================================
# MER System - PHP-FPM Pool Configuration
# File: www.conf (override default pool)
# ===========================================

COPY deployment/production/www.conf /usr/local/etc/php-fpm.d/www.conf
