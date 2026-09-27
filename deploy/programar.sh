#!/usr/bin/env bash
# El trabajo de Cloud Scheduler del LATIDO diario de la suite, a las 4:00
# hora de Madrid. Se ejecuta UNA vez, después de desplegar el portal con el
# latido (repetirlo actualiza el trabajo, no crea otro):
#
#   docker compose run --rm gcloud bash deploy/programar.sh
#
# SIN ESTRENAR (escrito el 26/09/2026 copiando el de tareas, que sí está
# probado en producción).
#
# Cloud Scheduler llama a POST /api/latido del portal con el token OIDC de la
# cuenta de servicio de la suite, emitido para la dirección fija del portal:
# el mismo que el portal ya acepta en /api/personas (SUITE_TOKEN_AUDIENCE,
# SUITE_SERVICE_ACCOUNTS en deploy/desplegar.sh). El portal mira Neon, lanza
# las copias y despierta a las aplicaciones que tengan el latido
# (RunHeartbeat).
#
# Gratis: 3 trabajos por cuenta de facturación. Tareas gasta uno (su resumen
# de las 5:00) y este es el segundo; el tercero queda libre. Si tareas pasa
# su resumen al latido, se puede borrar el suyo.
set -euo pipefail

JOB=suite-latido
# Cloud Scheduler no está en todas las regiones; europe-west1 sí.
REGION=europe-west1
SERVICE=ampa-portal

PROJECT=$(gcloud config get-value project 2>/dev/null)
if [ -z "$PROJECT" ]; then
    echo "No hay proyecto elegido. Ejecuta antes:"
    echo "  docker compose run --rm gcloud gcloud config set project ID_DEL_PROYECTO"
    exit 1
fi

PROJECT_NUMBER=$(gcloud projects describe "$PROJECT" --format='value(projectNumber)')

# La dirección fija de Cloud Run (no la del dominio): es la audiencia que
# acepta el portal para los tokens de la cuenta de servicio. Si no coincide
# con SUITE_TOKEN_AUDIENCE de deploy/desplegar.sh, el latido contesta 401.
RUN_URL="https://${SERVICE}-${PROJECT_NUMBER}.${REGION}.run.app"
SERVICE_ACCOUNT="${PROJECT_NUMBER}-compute@developer.gserviceaccount.com"

echo "1/2  Activando Cloud Scheduler (si ya lo estaba, no hace nada)..."
gcloud services enable cloudscheduler.googleapis.com

# --schedule: a las 4:00 todos los días, en hora de Madrid (cambia sola con
#   el horario de verano). Antes del resumen de tareas de las 5:00, y a una
#   hora a la que nadie está usando nada mientras se hacen las copias.
# --attempt-deadline: cuánto espera la respuesta. El latido despierta a cada
#   aplicación, una detrás de otra (hasta 1 minuto cada una).
# --max-retry-attempts=0: el latido contesta 200 aunque falle un paso (los
#   fallos van al registro y a la alerta); si no contesta en absoluto, mejor
#   no repetirlo entero y lanzar las copias dos veces. Al día siguiente, otro.
ARGS=(
    --location="$REGION"
    --schedule="0 4 * * *"
    --time-zone="Europe/Madrid"
    --uri="${RUN_URL}/api/latido"
    --http-method=POST
    --oidc-service-account-email="$SERVICE_ACCOUNT"
    --oidc-token-audience="$RUN_URL"
    --attempt-deadline=320s
    --max-retry-attempts=0
)

echo "2/2  El trabajo $JOB..."
if gcloud scheduler jobs describe "$JOB" --location="$REGION" >/dev/null 2>&1; then
    gcloud scheduler jobs update http "$JOB" "${ARGS[@]}" --quiet >/dev/null
    echo "     ya existía: actualizado"
else
    gcloud scheduler jobs create http "$JOB" "${ARGS[@]}" --quiet >/dev/null
    echo "     creado"
fi

echo
echo "Listo: todos los días a las 04:00 (Madrid). Para probarlo ya, sin esperar:"
echo "  docker compose run --rm gcloud gcloud scheduler jobs run $JOB --location=$REGION"
echo "y mira la respuesta en los registros de Cloud Run de $SERVICE (cada paso,"
echo "con \"hecho\", \"saltado\" o \"fallo\")."
