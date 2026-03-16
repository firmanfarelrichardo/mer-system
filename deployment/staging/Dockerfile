# ===========================================
# MER System - Laravel Dockerfile (Development)
# PHP 8.3 FPM Alpine dengan UID/GID alignment
# ===========================================

FROM php:8.3-fpm-alpine

# -------------------------------------------
# 1. Install system dependencies & PHP extensions
#    - shadow: diperlukan untuk usermod/groupmod
#    - Build deps dipisah agar bisa dihapus setelah compile
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
# 3. Align www-data UID/GID dengan host user
#
#    MENGAPA: PHP-FPM berjalan sebagai www-data.
#    Pada bind-mount, file yang ditulis oleh www-data
#    harus memiliki UID/GID yang sama dengan user host
#    agar tidak terjadi konflik permission.
#
#    Default www-data di Alpine: UID=82, GID=82
#    Host Linux user biasanya: UID=1000, GID=1000
#
#    Dengan usermod/groupmod, kita selaraskan keduanya
#    sehingga file yang dibuat di container memiliki
#    ownership yang benar di host filesystem.
# -------------------------------------------
ARG UID=1000
ARG GID=1000

RUN groupmod -g ${GID} www-data \
    && usermod -u ${UID} -g ${GID} www-data \
    && usermod -s /bin/bash www-data

# -------------------------------------------
# 4. Prepare working directory
# -------------------------------------------
WORKDIR /var/www/html

RUN mkdir -p storage/logs \
             storage/framework/cache \
             storage/framework/sessions \
             storage/framework/views \
             bootstrap/cache \
    && chown -R www-data:www-data /var/www/html

# -------------------------------------------
# 5. Entrypoint & Command
# -------------------------------------------
COPY deployment/staging/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
