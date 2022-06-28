FROM composer/composer
FROM php:8.1-apache

COPY --from=composer/composer /usr/bin/composer /usr/bin/composer

RUN apt update && apt install zip -y
RUN docker-php-ext-install pdo_mysql
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN a2enmod rewrite
WORKDIR /var/www/html
USER 1000

COPY . /var/www/html
RUN composer install


