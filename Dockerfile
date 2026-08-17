FROM php:8.4-fpm-alpine AS php

# 1. GDの組み立てに必要なパーツを apk でまとめてインストール
RUN apk add --no-cache \
    zlib-dev \
    libpng-dev \
    libjpeg-turbo-dev

# 2. GD と pdo_mysql を「同時に一発で」インストールする
RUN docker-php-ext-install gd pdo_mysql


RUN install -o www-data -g www-data -d /var/www/upload/image/
