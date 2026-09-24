#!/usr/bin/env bash
# Da permisos en PRODUCCIÓN con el comando de consola del portal:
#
#   docker compose run --rm gcloud bash deploy/dar-permisos.sh \
#       admin@ampasainzvicuna.com portal:admin listados:usuario --nombre=Admin --segundo-correo=alguien@gmail.com
#
# (Nombres con espacios, entre comillas: --nombre="Nombre Apellido".)
#
# Para lo que la pantalla de permisos no puede: el PRIMER administrador y
# cargar de golpe accesos que ya existían. Lo demás, desde la pantalla.
#
# Cómo: crea un "job" de Cloud Run con la MISMA imagen que el portal en
# marcha y sus mismos secretos, lo ejecuta y lo borra. Así el comando corre
# dentro de Google con la base de producción, y la cadena de conexión (con su
# contraseña) no pasa por tu ordenador ni por el terminal. Usado así el
# 24/09/2026 para dar de alta a Admin y a Alberto.
#
# Solo suma permisos, nunca quita (bin/console app:permisos:dar).
set -euo pipefail

if [ $# -lt 2 ]; then
    echo "Uso: bash deploy/dar-permisos.sh <correo> <aplicacion:rol>... [--nombre=\"…\"] [--segundo-correo=…] [--avisos=primary|secondary|both]"
    exit 1
fi

REGION=europe-west1
JOB=portal-permisos

IMAGE=$(gcloud run services describe ampa-portal --region="$REGION" \
    --format='value(spec.template.spec.containers[0].image)')

# El comando entero, con cada argumento entre comillas simples para que un
# nombre con espacios llegue como UN argumento. Va como un solo argumento de
# "sh -c": gcloud separa --args por comas, y así las comas no importan.
COMMAND="php bin/console app:permisos:dar"
for arg in "$@"; do
    COMMAND+=" '${arg//\'/\'\\\'\'}'"
done

echo "Dando permisos en producción (tarda un minuto)..."

gcloud run jobs deploy "$JOB" \
    --image="$IMAGE" \
    --region="$REGION" \
    --set-secrets=DATABASE_URL=portal-database-url:latest,JWT_KEY=portal-jwt-key:latest \
    --command=sh \
    --args="^|^-c|$COMMAND" \
    --max-retries=0 \
    --task-timeout=300 \
    --quiet >/dev/null

EXECUTION=$(gcloud run jobs execute "$JOB" --region="$REGION" --wait --quiet \
    --format='value(metadata.name)' 2>/dev/null || true)

# El registro tarda unos segundos en estar disponible.
sleep 20
gcloud logging read \
    "resource.type=cloud_run_job AND resource.labels.job_name=$JOB AND labels.\"run.googleapis.com/execution_name\"=$EXECUTION" \
    --freshness=15m --format='value(textPayload)' | grep -v '^\s*$' | grep -v 'Container called exit' | tac

# El job guarda en su configuración los correos que se le han pasado: fuera.
gcloud run jobs delete "$JOB" --region="$REGION" --quiet >/dev/null
