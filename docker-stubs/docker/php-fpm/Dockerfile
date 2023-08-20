ARG PHP_VERSION="8.2"
FROM php:${PHP_VERSION}-fpm
FROM php:8.2-fpm

ARG PHP_VERSION="8.2"
ARG NODE_VERSION="20"
ARG ENVIRONMENT="production"

ENV PHP_IDE_CONFIG="serverName=php-fpm"
ENV NPM_CONFIG_CACHE=/var/www/.cache/npm
ENV NPM_CONFIG_PREFIX=/var/www/.npm-global
ENV DEBIAN_FRONTEND noninteractive
ENV TZ=UTC

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN curl -sL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - \
    && apt-get update \
    # System packages
    && apt-get install -y \
        locales-all \
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
        openssh-client \
        gosu \
        default-mysql-client \
        supervisor \
        # PHP extensions \
        && if [[ "$ENVIRONMENT" = "production" ]]; then PECL="redis"; else PECL="xdebug redis"; fi \
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
        # PECL extensions \
        && pecl install $PECL \
        && docker-php-ext-enable $PECL \
        && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp && mkdir /tmp && chmod 777 /tmp

RUN cp /usr/local/etc/php/php.ini-$ENVIRONMENT /usr/local/etc/php/php.ini
COPY php.ini /usr/local/etc/php/conf.d/99-laravel.ini
COPY supervisord.conf /etc/supervisor/supervisord.conf

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ZSH
RUN mkdir -p \
    /var/www/.config/psysh \
    /var/www/.composer \
    /var/www/.cache \
    /var/www/.npm-global \
    /var/www/.zim
COPY zsh/zshrc /var/www/.zshrc
COPY zsh/zimrc /var/www/.zimrc
RUN curl -fsSL -o /var/www/.zim/zimfw.zsh https://github.com/zimfw/zimfw/releases/latest/download/zimfw.zsh
RUN chsh -s /usr/bin/zsh www-data
RUN chown -R www-data:www-data /var/www
RUn runuser -l www-data -c 'zsh -i -c "zimfw install"'

# Set entrypoint that updates UID of www-user
COPY entrypoint.sh /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
