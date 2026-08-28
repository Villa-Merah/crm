FROM composer:2 AS composer-development

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --ignore-platform-req=ext-intl

FROM composer:2 AS composer-production

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --ignore-platform-req=ext-intl

FROM node:22-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY --from=composer-production /app/vendor ./vendor
COPY vite.config.js ./
COPY resources ./resources
COPY app ./app
RUN npm run build

FROM php:8.4-fpm-bookworm AS php-base

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libicu-dev \
        libpng-dev \
        libzip-dev \
        default-mysql-client \
        unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        pcntl \
        pdo_mysql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-sivmi.ini
COPY docker/php/entrypoint.sh /usr/local/bin/sivmi-entrypoint

WORKDIR /var/www/html

RUN chmod +x /usr/local/bin/sivmi-entrypoint

ENTRYPOINT ["sivmi-entrypoint"]
CMD ["php-fpm"]

FROM php-base AS development

COPY . .
COPY --from=composer-development /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache

FROM php-base AS production

ENV APP_ENV=production \
    APP_DEBUG=false \
    INSTALL_DEPENDENCIES_ON_START=false \
    OPTIMIZE_ON_START=true

COPY . .
COPY --from=composer-production /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && composer check-platform-reqs --no-dev \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache
