FROM php:8.5-fpm

ARG HOST_UID=1000
ARG HOST_GID=1000
ARG HOST_USER_NAME=hostuser
ARG HOST_GROUP_NAME=hostgroup

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
        pcntl \
        pdo_mysql \
        zip \
    && curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash \
    && apt-get install -y --no-install-recommends symfony-cli \
    && if ! getent group "${HOST_GID}" >/dev/null; then groupadd --gid "${HOST_GID}" "${HOST_GROUP_NAME}"; fi \
    && if ! getent passwd "${HOST_UID}" >/dev/null; then useradd --uid "${HOST_UID}" --gid "${HOST_GID}" --create-home --shell /bin/sh "${HOST_USER_NAME}"; fi \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/backend/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/backend/php/entrypoint.sh /usr/local/bin/jardin-sonore-php-entrypoint

RUN chmod +x /usr/local/bin/jardin-sonore-php-entrypoint

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/jardin-sonore-php-entrypoint"]
CMD ["php-fpm"]
