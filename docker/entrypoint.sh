#!/bin/sh
set -e

echo "[entrypoint] APP_ENV=${APP_ENV:-prod}"
echo "[entrypoint] Limpiando caché..."
php bin/console cache:clear --no-warmup --env=prod || true
echo "[entrypoint] Iniciando Apache..."

exec "$@"
