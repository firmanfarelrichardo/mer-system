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
# PHP-FPM healthcheck endpoint
# Menggunakan drop-in file (zz-healthcheck.conf) — BUKAN dengan memodifikasi
# www.conf bawaan — agar isolated, idempotent, dan mudah dilacak di version control.
# Prefix 'zz-' memastikan file ini di-load TERAKHIR oleh PHP-FPM (alphabetical order),
# sehingga tidak tertimpa atau menimpa konfigurasi pool yang sudah ada.
# ping.path harus di-set pada pool yang sama (www) agar FPM menjawab di port 9000.
# -------------------------------------------
RUN echo '[www]' > /usr/local/etc/php-fpm.d/zz-healthcheck.conf \
    && echo 'ping.path = /ping' >> /usr/local/etc/php-fpm.d/zz-healthcheck.conf

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

# -------------------------------------------
# HEALTHCHECK via cgi-fcgi (binary dari paket fcgi yang sudah terinstal)
#
# Mengapa cgi-fcgi dan bukan php-fpm-healthcheck?
# php-fpm-healthcheck adalah shell script pihak ketiga yang TIDAK ada di Alpine.
# cgi-fcgi adalah protokol FastCGI native yang berkomunikasi langsung ke port 9000,
# tanpa perlu HTTP server atau curl — minimal attack surface, zero dependensi tambahan.
#
# Cara kerja:
#   SCRIPT_NAME + SCRIPT_FILENAME membentuk request FastCGI ke endpoint /ping.
#   FPM mengembalikan response HTTP sederhana; exit code 0 = sehat, non-0 = sakit.
#
# start-period=90s: memberi waktu penuh untuk entrypoint (sync assets + permission)
# DAN supervisor startup (FPM + worker). Tanpa ini, --wait di deploy.sh akan
# menerima false-negative dan membuat container dianggap unhealthy sebelum siap.
# -------------------------------------------
HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 > /dev/null 2>&1 || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
