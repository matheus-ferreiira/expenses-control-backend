FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    libxml2-dev \
    libcurl4-openssl-dev \
    libicu-dev \
    libzip-dev \
    libonig-dev \
    unzip \
    git \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        xml \
        curl \
        ctype \
        fileinfo \
        bcmath \
        intl \
        zip \
        opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

COPY . .

EXPOSE 8000

# The scheduler runs in this same container (there is no separate cron service); the catch-up at boot
# recovers anything missed while the container was down.
CMD php artisan optimize && php artisan app:release-migrate && (php artisan finance:extend-recurrences; php artisan finance:realize-card-charges; true) && (php artisan schedule:work &) && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
