FROM php:8.2-cli

# System deps + PHP extensions for PostgreSQL
RUN apt-get update && apt-get install -y \
        git unzip libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql intl zip opcache \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP deps first (better layer caching)
COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-interaction --prefer-dist || true

COPY . .
RUN composer install --no-interaction --prefer-dist

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
