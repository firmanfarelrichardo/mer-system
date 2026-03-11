# ===========================================
# MER System — Production Dockerfile
# Multi-stage build: Composer → Node → Final PHP-FPM
# ===========================================

# ╔══════════════════════════════════════════╗
# ║  Stage 1: Composer — PHP Dependencies    ║
# ╚══════════════════════════════════════════╝
# Flag --ignore-platform-reqs WAJIB karena image composer:2 tidak
# memiliki ekstensi gd, pgsql, dll. Tanpa flag ini composer install
# akan crash dengan "ext-gd is missing".
FROM composer:2 AS composer-builder

WORKDIR /build

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --ignore-platform-reqs \
    --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --optimize

# ╔══════════════════════════════════════════╗
# ║  Stage 2: Node — Vite Frontend Assets    ║
# ╚══════════════════════════════════════════╝
FROM node:22-alpine AS assets-builder

WORKDIR /build

COPY package.json package-lock.json* ./
RUN npm ci

COPY resources/ ./resources/
COPY vite.config.js ./
COPY tailwind.config.js* postcss.config.js* ./
COPY public/ ./public/

RUN npm run build

# ╔══════════════════════════════════════════════════════╗
# ║  Stage 3: Final — PHP 8.2 FPM Alpine (Production)   ║
# ╚══════════════════════════════════════════════════════╝
# php:8.2-fpm-alpine dipilih karena:
# - LTS dengan dukungan security update panjang
# - Alpine meminimalkan attack surface (image ~90MB vs ~450MB debian)
# - fpm variant optimal untuk deployment di belakang Nginx reverse proxy
FROM php:8.2-fpm-alpine

# -------------------------------------------
# System dependencies
# Dipisah: runtime libs (--no-cache) vs build-deps (--virtual).
# .build-deps dihapus di akhir RUN yang SAMA agar final layer bersih.
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
    # -- Konfigurasi GD dengan freetype + jpeg (WAJIB untuk mews/captcha & DOMPDF) --
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
    # -- Redis extension via PECL --
    && pecl install redis \
    && docker-php-ext-enable redis \
    # -- Cleanup build deps untuk memperkecil image --
    && apk del .build-deps \
    && rm -rf /tmp/pear /tmp/* /var/cache/apk/*

# -------------------------------------------
# PHP production config
# php.ini-production = error_reporting ketat, display_errors=Off.
# 99-custom.ini = override spesifik (memory, opcache, upload).
# -------------------------------------------
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY deployment/production/php.ini /usr/local/etc/php/conf.d/99-custom.ini

# -------------------------------------------
# Supervisord config
# PID 1 = supervisord → manages php-fpm + queue-worker + scheduler.
# -------------------------------------------
COPY deployment/production/supervisord.conf /etc/supervisor/supervisord.conf

# -------------------------------------------
# Application source
# -------------------------------------------
WORKDIR /var/www/html

# 1. Vendor dari Composer stage
COPY --from=composer-builder /build/vendor ./vendor

# 2. Seluruh source code aplikasi
COPY . .

# 3. Vite build output (menimpa public/build dari source)
COPY --from=assets-builder /build/public/build ./public/build

# -------------------------------------------
# Permissions
# CRITICAL: ownership /var/www/html → www-data:www-data secara MUTLAK.
# PHP-FPM berjalan sebagai www-data, harus bisa menulis ke storage & cache.
# -------------------------------------------
RUN mkdir -p \
        storage/logs \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 storage bootstrap/cache

# -------------------------------------------
# Entrypoint (sync assets ke shared volume, set permission)
# -------------------------------------------
COPY deployment/production/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000

# HEALTHCHECK: php-fpm-healthcheck (package fcgi) memeriksa FPM status endpoint.
# start-period=60s memberi waktu untuk entrypoint (sync assets, permission).
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
