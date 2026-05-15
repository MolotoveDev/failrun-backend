#!/bin/sh
set -e

echo "[entrypoint] APP_ENV=${APP_ENV:-prod}"
php bin/console cache:clear --no-warmup --env=prod
php bin/console cache:warmup --env=prod
chown -R www-data:www-data var/
echo "[entrypoint] Iniciando Apache..."
echo "[entrypoint] PHP ve APP_ENV: $(php -r "echo getenv('APP_ENV');")"
exec "$@"
