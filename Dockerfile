FROM composer:2 AS composer

FROM php:8.4-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install intl mbstring pdo_mysql xml zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
