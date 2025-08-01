FROM php:8.2-fpm

# Install extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        mariadb-client \
        libzip-dev \
        unzip \
    && docker-php-ext-install pdo_mysql pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]
