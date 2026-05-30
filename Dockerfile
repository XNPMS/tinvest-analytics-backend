FROM php:8.2-fpm-alpine3.19

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
    libstdc++ \
    ca-certificates \
    php82 \
    php82-fpm \
    php82-mbstring \
    php82-pecl-grpc \
    php82-pecl-protobuf \
    php82-tokenizer \
    php82-json \
    php82-openssl \
    && rm -rf /var/cache/apk/*

RUN apk add --no-cache --virtual .build-deps \
    autoconf \
    build-base \
    zlib-dev \
    libmemcached-dev \
    linux-headers \
    mysql-dev \
    && pecl install memcached \
    && docker-php-ext-enable memcached \
    && docker-php-ext-install pdo pdo_mysql sockets bcmath \
    && apk del .build-deps

RUN mkdir -p /usr/local/lib/php/extensions/no-debug-non-zts-20220829 && \
    ln -sf /usr/lib/php82/modules/*.so /usr/local/lib/php/extensions/no-debug-non-zts-20220829/

COPY docker/php/conf.d/extensions.ini /usr/local/etc/php/conf.d/extensions.ini
COPY docker/php/conf.d/custom.ini /usr/local/etc/php/conf.d/custom.ini

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY composer.json composer.lock ./

#RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts

COPY . .
#RUN rm -f config/autoload/development.local.php
COPY crontab /etc/crontabs/root

RUN chmod 0644 /etc/crontabs/root

EXPOSE 9000

CMD ["php-fpm"]
