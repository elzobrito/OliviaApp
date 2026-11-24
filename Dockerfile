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
ENV COMPOSER_ROOT_VERSION=2.0.1
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress \
    && composer check-platform-reqs --no-dev

FROM php:${PHP_VERSION}
# Extensoes exigidas pelo framework: pdo_mysql e zip sao compiladas aqui;
# pdo_sqlite, fileinfo, openssl e sodium ja vem na imagem oficial.
# Toolchain e headers entram e saem no mesmo RUN: nada de compilador na camada final.
RUN set -eux; \
    apk add --no-cache libzip; \
    apk add --no-cache --virtual .olivia-build-deps $PHPIZE_DEPS libzip-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql zip; \
    apk del --no-network .olivia-build-deps; \
    rm -rf /tmp/pear; \
    cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; \
    php -r 'foreach (["pdo_mysql","pdo_sqlite","zip","fileinfo","openssl","sodium"] as $e) { if (!extension_loaded($e)) { fwrite(STDERR, "missing extension: $e\n"); exit(1); } }'
COPY docker/php/production.ini $PHP_INI_DIR/conf.d/zz-olivia-production.ini
WORKDIR /var/www/html
COPY --from=composer-builder /var/www/html /var/www/html
USER www-data
