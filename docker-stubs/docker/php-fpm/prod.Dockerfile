FROM php:%php_version%-fpm

# Arguments defined in docker-compose.yml
ARG user=laravel
ARG uid

ENV npm_config_cache=/home/laravel/.cache/npm
ENV npm_config_prefix=/home/laravel/.npm-global
ENV PHP_IDE_CONFIG="serverName=php-fpm"

# Install system dependencies
RUN curl -sL https://deb.nodesource.com/setup_%node_version%.x | bash -
RUN apt-get update && apt-get install -y \
    git \
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
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

RUN mkdir /tmp && chmod -R 777 /tmp

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data -u $uid -d /home/$user $user && usermod --shell /usr/bin/zsh $user
COPY --chown=$user:$user .zshrc /home/$user/
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Set working directory
WORKDIR /var/www

USER $user
