FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git curl libpq-dev libzip-dev zip unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
    pdo pdo_pgsql zip pcntl

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN composer install --no-interaction --optimize-autoloader

EXPOSE 9000

CMD ["php-fpm"]
