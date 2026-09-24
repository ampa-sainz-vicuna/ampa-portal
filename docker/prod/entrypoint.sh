#!/bin/sh
# Arranque del contenedor de producción.
set -e

# ---------------------------------------------------------------------------
# APP_SECRET sale de la clave de firma, como en listados.
#
# Symfony exige que APP_SECRET valga algo, pero el portal no lo usa para nada:
# no hay sesiones de PHP, ni formularios con CSRF, ni URLs firmadas. Aun así
# conviene que sea impredecible y que no cambie en cada despliegue, y un hash
# de la clave de firma cumple las dos cosas sin gastar un secreto (el nivel
# gratuito de Secret Manager son seis por cuenta de facturación).
# ---------------------------------------------------------------------------
if [ -z "${APP_SECRET:-}" ] && [ -n "${JWT_KEY:-}" ]; then
    APP_SECRET=$(php -r 'echo hash("sha256", "app-secret|" . getenv("JWT_KEY"));')
    export APP_SECRET
fi

# Aplica las migraciones pendientes antes de aceptar peticiones. Si no hay
# ninguna, no hace nada y tarda un instante.
#
# Es seguro porque Cloud Run está limitado a UNA instancia (--max-instances=1):
# nunca habrá dos contenedores migrando a la vez. Si la base de datos no
# responde, el contenedor no arranca y Cloud Run mantiene la versión anterior.
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Este script corre como root, y la migración de arriba deja en var/cache
# carpetas de root que Apache (www-data) ya no podría escribir. Se devuelven a
# www-data antes de abrir la puerta. (Trampa heredada de fichajes.)
chown -R www-data:www-data var

exec apache2-foreground
