# 1) Dependencias PHP
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock* ./

RUN composer install --no-dev --no-interaction --no-progress \
    --prefer-dist --optimize-autoloader

# 2) CSS con el CLI standalone de Tailwind (sin Node)
FROM debian:bookworm-slim AS css

ARG TAILWIND_VERSION=v4.3.3

ARG TARGETARCH

RUN apt-get update && apt-get install -y --no-install-recommends curl ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN case "$TARGETARCH" in arm64) ARCH=arm64 ;; *) ARCH=x64 ;; esac \
    && curl -fsSL -o /usr/local/bin/tailwindcss \
    "https://github.com/tailwindlabs/tailwindcss/releases/download/${TAILWIND_VERSION}/tailwindcss-linux-${ARCH}" \
    && chmod +x /usr/local/bin/tailwindcss

WORKDIR /src

COPY resources/css ./resources/css

COPY app/Views ./app/Views

RUN mkdir -p /out && tailwindcss -i resources/css/app.css -o /out/app.css --minify

# 3) Imagen final
FROM dunglas/frankenphp:1-php8.3

RUN install-php-extensions opcache pdo_sqlite sodium \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /app

COPY . /app

COPY --from=composer /app/vendor ./vendor

COPY --from=css /out/app.css ./public/css/app.css

# Usuario sin privilegios (RNF-11)

ARG USER=appuser

RUN useradd ${USER} \
    && setcap CAP_NET_BIND_SERVICE=+ep /usr/local/bin/frankenphp \
    && mkdir -p storage/cache/views storage/backups storage/logs \
    && chown -R ${USER}:${USER} /data/caddy /config/caddy storage

USER ${USER}

EXPOSE 80