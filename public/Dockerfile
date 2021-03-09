FROM php:8.0-apache AS base

# install needed php extensions
RUN apt-get update && apt-get install -y \
         libpng-dev \
         zlib1g-dev \
         libpq-dev \
         && docker-php-ext-install gd \
         && docker-php-ext-install pgsql \
         && docker-php-ext-install pdo_pgsql \
         && docker-php-ext-install pcntl

# copy puplic http content
COPY . /var/www/html/vwid

# link our environment config (created by fetcher container)
RUN ln -s /usr/config/.env /var/www/.env

# use production php config (no warnings)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"