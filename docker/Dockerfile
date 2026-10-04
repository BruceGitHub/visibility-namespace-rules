# Standalone checker image: bundles PHPStan + these rules so consuming
# projects don't need to install the package (and can't hit dependency
# conflicts with their own PHPStan/Core).
FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /opt/visibility

COPY composer.json composer.lock ./
COPY src ./src
COPY extension.neon ./

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

COPY docker/entrypoint.sh /usr/local/bin/visibility-namespace-rules
RUN chmod +x /usr/local/bin/visibility-namespace-rules

WORKDIR /app

ENTRYPOINT ["visibility-namespace-rules"]
CMD ["src"]
