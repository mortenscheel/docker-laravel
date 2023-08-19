ARG PHP_VERSION="8.2"
FROM php:${PHP_VERSION}-fpm

ARG PHP_VERSION="8.2"
ARG NODE_VERSION="20"
ARG UID=1000
ARG GID=1000

ENV PHP_IDE_CONFIG="serverName=php-fpm"
ENV NPM_CONFIG_CACHE=/var/www/.cache/npm
ENV NPM_CONFIG_PREFIX=/var/www/.npm-global

# Install system dependencies
RUN curl -sL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash -
RUN apt-get update && apt-get install -y \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    libzip-dev \
    unzip \
    nodejs \
    nano \
    zsh \
    default-mysql-client \
    # Install PHP extensions \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        calendar \
        soap \
    # Xdebug
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp

RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini
COPY php.ini /usr/local/etc/php/conf.d/99-laravel.ini

RUN mkdir /tmp && chmod -R 777 /tmp

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN usermod  --uid $UID www-data
RUN groupmod --gid $GID www-data

RUN mkdir -p /var/www/.config/psysh /var/www/.composer /var/www/.cache /var/www/.npm-global && chown -R www-data:www-data /var/www

# ZSH
COPY .zshrc /var/www/.zshrc
RUN chsh -s /usr/bin/zsh www-data

# Set working directory
WORKDIR /var/www/html
