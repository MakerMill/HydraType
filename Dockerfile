ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-cli

ARG XDEBUG_VERSION=3.5.3

ENV PHP_IDE_CONFIG=serverName=php-cli

# Pin the extension so PHP-version comparisons use the same runtime components.
RUN pecl install xdebug-${XDEBUG_VERSION} && docker-php-ext-enable xdebug

# Copy custom php.ini
COPY ./docker/php.ini /usr/local/etc/php/php.ini

# Set the working directory
WORKDIR /app

# Expose the Xdebug port
EXPOSE 9003
