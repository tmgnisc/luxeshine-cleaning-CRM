#!/usr/bin/env bash
set -e

cd /app

# Ensure an app key exists. On Railway, set APP_KEY as a service secret so it
# stays stable across restarts; this block only runs as a local fallback.
if [ -z "${APP_KEY}" ] && ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
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

# Schema only. Seeding is NOT run on every boot (it would reset/duplicate demo
# data). Seed once after the first deploy with:  php artisan db:seed --force
php artisan migrate --force

# Seed only the very first time (when the users table is still empty). Safe to
# leave on: it no-ops on every subsequent deploy.
if [ "$(php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tail -n1)" = "0" ]; then
  echo "Empty database detected — running seeders once."
  php artisan db:seed --force || true
fi

php artisan config:clear

# Run the queue worker in the background (emails/notifications), then serve.
# Railway routes traffic to $PORT; fall back to 8000 for local docker-compose.
php artisan queue:work --tries=3 --sleep=3 &
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
