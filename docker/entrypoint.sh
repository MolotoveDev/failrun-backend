#!/bin/sh
set -e

echo "[entrypoint] APP_ENV=${APP_ENV:-prod}"
php bin/console cache:clear --no-warmup --env=prod
php bin/console cache:warmup --env=prod
echo "[entrypoint] Iniciando Apache..."

exec "$@"
