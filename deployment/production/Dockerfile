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
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --optimize

# ==== Stage 3: Production PHP-FPM image ====
FROM php:8.3-fpm-alpine

# -------------------------------------------
# System dependencies (runtime only)
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
# -------------------------------------------
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY deployment/production/php.ini /usr/local/etc/php/conf.d/99-custom.ini

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
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 storage bootstrap/cache

# -------------------------------------------
# Entrypoint
# -------------------------------------------
COPY deployment/production/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
