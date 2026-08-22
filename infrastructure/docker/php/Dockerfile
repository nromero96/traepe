FROM composer:2.8.12@sha256:0d264a0f1e5be23ba363447768df7b30c33d542711ea12e37770ed7b13bf4eaa AS composer_binary

FROM php:8.4.24-fpm-alpine3.23@sha256:71f8fc3f5890b93aefb48602028f1fd93ab61adfe11979953a4cc03bc6e15643

RUN apk add --no-cache \
        fcgi \
        icu-libs \
        libpq \
        libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        linux-headers \
        postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip \
    && pecl install redis-6.2.0 \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

COPY --from=composer_binary /usr/bin/composer /usr/local/bin/composer
COPY infrastructure/docker/php/conf.d/traepe.ini /usr/local/etc/php/conf.d/traepe.ini
COPY infrastructure/docker/php/php-fpm.d/zz-traepe.conf /usr/local/etc/php-fpm.d/zz-traepe.conf

WORKDIR /var/www/html

COPY apps/api/composer.json apps/api/composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist

COPY apps/api/ ./
RUN composer dump-autoload --no-interaction --no-scripts

CMD ["php-fpm", "-F"]
