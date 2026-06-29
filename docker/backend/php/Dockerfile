FROM php:8.4-fpm

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        apt-transport-https \
        default-mysql-client \
        ca-certificates \
        curl \
        git \
        gnupg \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        sockets \
        zip \
    && curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash \
    && apt-get install -y --no-install-recommends symfony-cli \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/backend/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/backend/php/entrypoint.sh /usr/local/bin/jardin-sonore-php-entrypoint

RUN chmod +x /usr/local/bin/jardin-sonore-php-entrypoint

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/jardin-sonore-php-entrypoint"]
CMD ["php-fpm"]
