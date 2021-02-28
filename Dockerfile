FROM php:8.0-cli AS base

# install needed php extensions
RUN apt-get update && apt-get install -y \
         libpng-dev \
         zlib1g-dev \
         libpq-dev \
         && docker-php-ext-install gd \
         && docker-php-ext-install pgsql \
         && docker-php-ext-install pdo_pgsql \
         && docker-php-ext-install pcntl

# install vim for config work on docker volume
RUN apt-get install -y vim

# copy all source files and scripts
COPY . /usr/src/IDDataLogger

# use production php config (no warnings)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# setup entrypoint with automated mode switch (first startup configuration, run, bash)
ENTRYPOINT /usr/src/IDDataLogger/docker-app.sh