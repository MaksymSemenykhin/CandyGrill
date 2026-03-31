FROM php:8.3-cli-bookworm

ARG WWWUSER=1000
ARG WWWGROUP=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libzip-dev \
        libmemcached-dev \
        zlib1g-dev \
        libssl-dev \
        libsasl2-dev \
        pkg-config \
        dpkg-dev \
    && export PKG_CONFIG_PATH="/usr/lib/$(dpkg-architecture -qDEB_HOST_MULTIARCH)/pkgconfig:/usr/lib/pkgconfig" \
    && printf '%s\n' '' | pecl install memcached \
    && docker-php-ext-enable memcached \
    && docker-php-ext-install pdo_mysql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupmod -o -g "${WWWGROUP}" www-data \
    && usermod -o -u "${WWWUSER}" -g www-data www-data

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
