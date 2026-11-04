FROM composer:2.6 as composer

WORKDIR /app

COPY composer.json composer.lock* ./

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

FROM dunglas/frankenphp

RUN install-php-extensions pdo_mysql opcache

COPY . /app

WORKDIR /app

COPY --from=composer /app/vendor ./vendor

EXPOSE 80