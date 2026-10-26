ARG PHP_VERSION=8.5.12-fpm-alpine3.24

FROM php:${PHP_VERSION} AS composer-builder
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN apk add --no-cache git unzip
WORKDIR /var/www/html
COPY composer.json composer.lock ./
COPY app ./app
COPY lib ./lib
COPY src ./src
COPY publico ./publico
COPY index.php ./
ENV COMPOSER_ROOT_VERSION=0.2.1
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress \
    && composer check-platform-reqs --no-dev

FROM php:${PHP_VERSION}
WORKDIR /var/www/html
COPY --from=composer-builder /var/www/html /var/www/html
USER www-data
