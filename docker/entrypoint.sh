#!/bin/sh
#
# Entrypoint para el contenedor backend.
# Se ejecuta en cada arranque del contenedor (también en cada nueva task de ECS).
#
set -e

echo "[entrypoint] APP_ENV=${APP_ENV:-prod}"

if [ "${APP_ENV}" = "prod" ]; then
    echo "[entrypoint] Limpiando y precalentando caché..."
    php bin/console cache:clear --env=prod --no-debug
    php bin/console cache:warmup --env=prod --no-debug
fi

# Migraciones automáticas opcionales.
# Activa con la variable de entorno RUN_MIGRATIONS=true en la Task Definition
# si quieres que cada deploy aplique migraciones al arrancar.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "[entrypoint] Ejecutando migraciones de Doctrine..."
    php bin/console doctrine:migrations:migrate \
        --no-interaction \
        --allow-no-migration \
        --env=prod
fi

# Pasa el control al CMD del Dockerfile (apache2-foreground)
exec "$@"
