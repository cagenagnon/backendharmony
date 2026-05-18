# =============================================================================
# Stage 1: builder
# =============================================================================
FROM php:8.4-fpm-alpine3.19 AS builder

RUN apk add --no-cache \
    $PHPIZE_DEPS \
    curl-dev \
    bash curl git unzip \
    libzip-dev libpng-dev oniguruma-dev libxml2-dev icu-dev postgresql-dev \
    linux-headers

RUN docker-php-ext-install \
    pdo pdo_mysql pdo_pgsql \
    mbstring xml dom curl zip bcmath opcache intl pcntl fileinfo ctype

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction --no-plugins --no-scripts --no-dev \
    --prefer-dist --optimize-autoloader

COPY . .

RUN composer run-script post-autoload-dump --no-interaction \
    && php artisan package:discover --ansi

# =============================================================================
# Stage 2: runtime — php artisan serve handles $PORT natively (no nginx/supervisord)
# =============================================================================
FROM php:8.4-fpm-alpine3.19 AS runtime

LABEL org.opencontainers.image.description="Harmony Backend API - Laravel 12"

RUN apk add --no-cache bash curl libzip libpng oniguruma libxml2 icu-libs libpq

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

COPY docker/php.ini /usr/local/etc/php/php.ini
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY --from=builder /app /var/www/html
WORKDIR /var/www/html

RUN addgroup -g 1001 -S laravel \
    && adduser -u 1001 -S laravel -G laravel \
    && mkdir -p storage/logs \
               storage/framework/cache \
               storage/framework/sessions \
               storage/framework/views \
               bootstrap/cache \
    && chown -R laravel:laravel /var/www/html \
    && chmod -R 775 storage bootstrap/cache

USER laravel

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]

HEALTHCHECK --interval=15s --timeout=5s --start-period=90s --retries=5 \
    CMD curl -sf "http://localhost:${PORT:-8080}/up" || exit 1
