#!/usr/bin/env bash
set -e

cd /app

# Ensure an app key exists.
if ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
  cp -n .env.example .env 2>/dev/null || true
  php artisan key:generate --force || true
fi

# Wait for MySQL to accept connections.
echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT}…"
until mysqladmin ping -h "${DB_HOST}" -P "${DB_PORT}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --silent 2>/dev/null; do
  sleep 2
done
echo "MySQL is up."

php artisan storage:link || true
php artisan migrate --force --seed
php artisan config:clear

# Run the queue worker in the background (emails/notifications), then serve.
php artisan queue:work --tries=3 --sleep=3 &
exec php artisan serve --host=0.0.0.0 --port=8000
