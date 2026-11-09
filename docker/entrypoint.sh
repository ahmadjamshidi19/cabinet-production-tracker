#!/usr/bin/env bash
set -euo pipefail

[ -f .env ] || cp .env.example .env
[ -d vendor ] || composer install --no-interaction --prefer-dist

grep -q '^APP_KEY=.\+' .env || php artisan key:generate --force

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

until pg_isready -h "$DB_HOST" -p "${DB_PORT:-5432}" -U "$DB_USERNAME" >/dev/null 2>&1; do
  echo "waiting for database..."
  sleep 1
done

php artisan migrate --force
exec "$@"
