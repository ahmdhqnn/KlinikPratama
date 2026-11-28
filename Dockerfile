FROM php:8.4-fpm-alpine

RUN apk add --no-cache libpng libjpeg-turbo freetype libzip icu-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev icu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd zip intl \
    && apk del .build-deps

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-klinik.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-klinik.conf

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
