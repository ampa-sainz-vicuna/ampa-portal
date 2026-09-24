#!/usr/bin/env bash
# Da permisos en la base de PRODUCCIÓN desde tu ordenador, con el mismo
# comando que en desarrollo:
#
#   docker compose run --rm php bash ../deploy/dar-permisos-produccion.sh \
#       admin@ampasainzvicuna.com portal:admin listados:usuario --nombre="Nombre Apellido"
#
# Sirve para lo que la pantalla de permisos no puede: el PRIMER administrador
# y pasar al portal los accesos que ya había en fichajes y listados (hoja de
# ruta 4a). Después, todo desde la pantalla.
#
# Pide la cadena de conexión de Neon sin mostrarla (no queda en el historial
# del terminal ni en ningún fichero) y ejecuta bin/console app:permisos:dar
# contra ella. Solo suma permisos: nunca quita.
#
# SIN ESTRENAR (24/09/2026).
set -euo pipefail

if [ $# -lt 2 ]; then
    echo "Uso: bash ../deploy/dar-permisos-produccion.sh <correo> <aplicacion:rol>... [--nombre=\"…\"] [--segundo-correo=…]"
    exit 1
fi

source "$(dirname "$0")/neon.sh"
pedir_cadena_neon || exit 1

# APP_ENV=prod: sin la barra de depuración y con la misma configuración que
# el contenedor de Cloud Run. La clave de firma no hace falta para esto, pero
# Symfony la pide al arrancar: vale cualquiera de 32 bytes.
DATABASE_URL="$NEON_URL" APP_ENV=prod APP_DEBUG=0 \
    JWT_KEY="solo-para-el-comando-no-firma-nada-0123456789" \
    php bin/console app:permisos:dar "$@"
