FROM php:8.3-fpm-bookworm AS base

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring zip exif pcntl gd bcmath sockets \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM node:22-bookworm AS frontend-build

WORKDIR /app

COPY package.json package-lock.json vite.config.js .
COPY resources ./resources
COPY public ./public

RUN npm ci && npm run build

FROM base AS vendor

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader --no-scripts

FROM nginx:1.27-alpine AS web

COPY --from=frontend-build /app/public /var/www/html/public
COPY docker/nginx/cloud.conf /etc/nginx/conf.d/default.conf
RUN mkdir -p /var/www/html/storage/app/public \
    && ln -s /var/www/html/storage/app/public /var/www/html/public/storage

FROM base AS app

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
COPY --from=frontend-build /app/public/build ./public/build

RUN composer dump-autoload --no-interaction --no-dev --optimize \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rw storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/php/cloud-pool.conf /usr/local/etc/php-fpm.d/zz-cloud-pool.conf
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]

CMD ["php-fpm"]
