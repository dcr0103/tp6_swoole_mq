FROM php:8.2-fpm-alpine

# 使用阿里云镜像源
RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories \
    && apk update \
    && apk add --no-cache \
        curl wget git unzip libzip-dev libpng-dev \
        libjpeg-turbo-dev freetype-dev icu-dev \
        oniguruma-dev autoconf g++ make openssl-dev \
        rabbitmq-c-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo pdo_mysql mysqli zip gd intl opcache bcmath mbstring

RUN pecl install swoole-5.1.2 redis amqp \
    && docker-php-ext-enable swoole redis amqp

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

EXPOSE 9000 9501

# 默认启动 php-fpm，可以用 docker-compose 启 swoole
CMD ["php-fpm"]
