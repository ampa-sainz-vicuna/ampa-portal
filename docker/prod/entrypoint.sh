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

# Nada más antes de abrir la puerta: el portal se apaga cuando no se usa, y
# cada arranque lo espera la primera persona que llega (y cada aplicación, que
# le pregunta en cada petición).
#
# Hasta el 28/09/2026 aquí se aplicaban las migraciones, y eso (arrancar la
# consola, despertar a Neon) más el chown de var/ que venía detrás eran 2-4 s
# de cada arranque, aunque no hubiera ninguna migración nueva. Ahora las
# aplica deploy/desplegar.sh, una vez por despliegue y antes de pasarle el
# tráfico a la versión nueva.

exec apache2-foreground
