ARG PHP_VERSION='7.4'

FROM composer:latest as composer

COPY composer.json /assets/

WORKDIR /assets

RUN composer validate
RUN composer install

FROM php:${PHP_VERSION}-cli

ENV COMPOSER_HOME /composer
ENV PATH /composer/vendor/bin:$PATH

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY . /var/www/bundle
COPY --from=composer /assets/vendor /var/www/bundle/vendor

WORKDIR /var/www/bundle

RUN composer phpcs
RUN composer phpstan
