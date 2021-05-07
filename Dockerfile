ARG PHP_VERSION='7.4'

FROM composer:latest as composer
FROM php:${PHP_VERSION}-cli

RUN apt-get update \
   && apt-get install -y libzip-dev zlib1g-dev \
   && docker-php-ext-install zip

ENV COMPOSER_HOME /composer
ENV PATH /composer/vendor/bin:$PATH

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY composer.json /var/www/bundle/

WORKDIR /var/www/bundle

RUN composer validate
RUN composer install

COPY . /var/www/bundle

RUN composer install
RUN composer phpcs
RUN composer phpstan
