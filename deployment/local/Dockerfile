# ===========================================
# MER System - Laravel Dockerfile (Development)
# ===========================================

FROM php:8.3-fpm-alpine

# -------------------------------------------
# 1. Install system dependencies & PHP ext
# -------------------------------------------
RUN apk add --no-cache \
        bash \
        curl \
        git \
        libpng \
        libjpeg-turbo \
        freetype \
        libzip \
        libxml2 \
        libpq \
        icu-libs \
        oniguruma \
        zip \
        unzip \
        shadow \
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
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear /tmp/* /var/cache/apk/*

# -------------------------------------------
# 2. Install Composer
# -------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# -------------------------------------------
# 3. Create non-root user for bind-mount parity
# -------------------------------------------
ARG UID=1000
ARG GID=1000

RUN addgroup -g ${GID} laravel \
    && adduser -u ${UID} -G laravel -s /bin/bash -D laravel

# -------------------------------------------
# 4. Prepare working directory
# -------------------------------------------
WORKDIR /var/www/html

RUN mkdir -p storage/logs \
             storage/framework/cache \
             storage/framework/sessions \
             storage/framework/views \
             bootstrap/cache \
    && chown -R laravel:laravel /var/www/html

# -------------------------------------------
# 5. Entrypoint
# -------------------------------------------
COPY deployment/local/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
