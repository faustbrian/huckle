FROM php:8.5-cli
WORKDIR /usr/src/myapp

RUN apt-get update && apt-get install zip unzip git curl libicu-dev -y \
    && pecl install pcov \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && docker-php-ext-enable pcov \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer \
    && mkdir /.composer && chmod 777 /.composer
