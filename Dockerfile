FROM php:8.2-fpm-alpine AS base

ENV WORK_DIR=/var/www/application

WORKDIR ${WORK_DIR}

# Runtime dependencies
RUN apk update && apk add --no-cache \
        bash \
        zlib \
        libmemcached \
        libmemcached-libs \
        mysql-client \
        unzip \
        dcron \
    && apk add --no-cache --virtual .build-deps \
        autoconf \
        build-base \
        zlib-dev \
        libmemcached-dev \
        linux-headers \
        mysql-dev \
    && pecl install memcached xhprof \
    && docker-php-ext-enable memcached xhprof \
    && docker-php-ext-install pdo pdo_mysql sockets \
    && apk del .build-deps

# Установка Composer
# RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY composer.json composer.lock ./

# RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

COPY . .
COPY crontab /etc/crontabs/root

RUN chmod 0644 /etc/crontabs/root

EXPOSE 9000

CMD ["php-fpm"]
