FROM php:8.2-fpm-alpine

RUN apk update && apk upgrade
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli pdo_mysql

# Cek modul
RUN php -m | grep mysqli