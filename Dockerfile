# Use official PHP 8.2 CLI image
FROM php:8.2-cli

ENV PHP_IDE_CONFIG=serverName=php-cli

# Install Xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Copy custom php.ini
COPY ./docker/php.ini /usr/local/etc/php/php.ini

# Set the working directory
WORKDIR /app

# Expose the Xdebug port
EXPOSE 9003
