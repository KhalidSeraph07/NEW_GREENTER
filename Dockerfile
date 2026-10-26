FROM postgres:16.15-alpine@sha256:f4610795587492cd4fb887c2dc3de301b813278bb6f616992061141b5f15ac36 AS postgres
# El arranque no root evita necesitar gosu y su runtime Go vulnerable detectado en H1.
RUN apk upgrade --no-cache && rm /usr/local/bin/gosu
USER postgres

FROM composer:2.10.3@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc AS composer
FROM php:8.3.36-cli-alpine3.24@sha256:86a7a8f46bbf73120a19cf72246f606b6de9aaa84a00971c25d187fbae44c211 AS php

RUN apk upgrade --no-cache \
    && apk add --no-cache libpq oniguruma libzip xmlsec unzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev oniguruma-dev libzip-dev \
    && docker-php-ext-install -j2 mbstring pdo_pgsql zip \
    && apk del .build-deps

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/99-h1.ini
WORKDIR /workspace
USER 1000:1000
CMD ["php", "artisan", "poc:invoice"]
