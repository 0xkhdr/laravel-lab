FROM php:8.4-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git python3 unzip libzip-dev libicu-dev libsqlite3-dev libxml2-dev libonig-dev && docker-php-ext-install pdo_sqlite zip intl bcmath pcntl dom mbstring xml xmlwriter && pecl install redis && docker-php-ext-enable redis && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /workspace
