#!/usr/bin/env sh
set -e

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force
fi

php artisan config:cache

exec "$@"