FROM composer:2.2.4 as vendor
COPY composer.json composer.lock ./
RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --no-dev


FROM php:8.1.1-fpm-alpine3.15

RUN apk --no-cache --update add build-base bash git tzdata \
    libzip-dev libpq-dev busybox-suid $PHPIZE_DEPS && \
    pecl install redis && \
    pecl install xdebug

RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql && \
   docker-php-ext-install bcmath pdo pgsql pdo_mysql pdo_pgsql zip && \
    docker-php-ext-enable redis xdebug

WORKDIR /var/www/vhost/

COPY --chown=www-data:www-data --from=vendor /app/vendor/ ./vendor

COPY --chown=www-data:www-data . .

USER www-data

EXPOSE 9000


