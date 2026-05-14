#!/bin/sh
set -e

echo "[entrypoint] APP_ENV=${APP_ENV:-prod}"
echo "[entrypoint] Iniciando Apache..."

exec "$@"
