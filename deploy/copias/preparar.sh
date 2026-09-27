#!/usr/bin/env bash
# Monta (o actualiza) el job de Cloud Run de las copias de seguridad:
#
#   docker compose run --rm gcloud bash deploy/copias/preparar.sh ID_DE_LA_UNIDAD
#
# ID_DE_LA_UNIDAD: la unidad compartida de Drive donde se guardan (pasos en
# deploy/copias/README.md). Repetirlo no rompe nada: reconstruye la imagen y
# actualiza el job.
#
# SIN ESTRENAR (escrito el 26/09/2026). La copia y la restauración sí están
# probadas en local contra la base de desarrollo; la subida a Drive, no.
set -euo pipefail

if [ $# -ne 1 ] || [ -z "$1" ]; then
    echo "Uso: bash deploy/copias/preparar.sh ID_DE_LA_UNIDAD_COMPARTIDA"
    echo "(deploy/copias/README.md, paso 1)"
    exit 1
fi

DRIVE_FOLDER_ID=$1
JOB=ampa-copias
REGION=europe-west1

PROJECT=$(gcloud config get-value project 2>/dev/null)
if [ -z "$PROJECT" ]; then
    echo "No hay proyecto elegido. Ejecuta antes:"
    echo "  docker compose run --rm gcloud gcloud config set project ID_DEL_PROYECTO"
    exit 1
fi

PROJECT_NUMBER=$(gcloud projects describe "$PROJECT" --format='value(projectNumber)')
SERVICE_ACCOUNT="${PROJECT_NUMBER}-compute@developer.gserviceaccount.com"
# El mismo repositorio de imágenes que usan los servicios (y su limpieza:
# se conservan las 2 últimas de cada una).
IMAGE="${REGION}-docker.pkg.dev/${PROJECT}/cloud-run-source-deploy/${JOB}"

# Cada base, con el secreto que ya usa su aplicación. Si mañana cambia la
# contraseña de una (nueva versión del secreto), el job coge la nueva solo
# (:latest). Una aplicación nueva: añadirla aquí.
BASES=(
    "BASE_PORTAL=portal-database-url"
    "BASE_FICHAJES=database-url"
    "BASE_LISTADOS=listados-database-url"
    "BASE_FACTURACION=facturacion-database-url"
    "BASE_TAREAS=tareas-database-url"
)

SECRETS=""
for pair in "${BASES[@]}"; do
    secret=${pair#*=}
    if gcloud secrets describe "$secret" >/dev/null 2>&1; then
        SECRETS+="${SECRETS:+,}${pair}:latest"
    else
        echo "Aviso: no existe el secreto $secret; esa base no se copiará."
    fi
done

echo "1/3  Construyendo la imagen (Cloud Build, un par de minutos)..."
gcloud builds submit deploy/copias --tag="$IMAGE" --quiet >/dev/null

echo "2/3  El job $JOB..."
# --max-retries=0: si falla, que lo diga (la alerta) y ya; mañana, otra.
# --task-timeout: sobra con 15 minutos (hoy tarda segundos).
# --memory: pg_dump de bases pequeñas; 512 MiB de sobra.
gcloud run jobs deploy "$JOB" \
    --image="$IMAGE" \
    --region="$REGION" \
    --service-account="$SERVICE_ACCOUNT" \
    --set-secrets="$SECRETS" \
    --set-env-vars="DRIVE_FOLDER_ID=${DRIVE_FOLDER_ID},CONSERVAR=30" \
    --max-retries=0 \
    --task-timeout=900 \
    --memory=512Mi \
    --cpu=1 \
    --quiet >/dev/null

echo "3/3  Permiso para que el portal lo lance (Invocador de Cloud Run, solo sobre este job)..."
# Justo después de crear el job, Google a veces contesta "concurrent policy
# changes" (pasó el 27/09/2026): se reintenta unas veces.
for attempt in 1 2 3 4 5; do
    if gcloud run jobs add-iam-policy-binding "$JOB" \
        --region="$REGION" \
        --member="serviceAccount:$SERVICE_ACCOUNT" \
        --role=roles/run.invoker \
        --quiet >/dev/null 2>&1; then
        break
    fi
    if [ "$attempt" -eq 5 ]; then
        echo "     No se ha podido dar el permiso. Repite el script."
        exit 1
    fi
    sleep $((attempt * 5))
done

echo
echo "Listo. Pruébalo ya (tarda un minuto):"
echo "  docker compose run --rm gcloud gcloud run jobs execute $JOB --region=$REGION --wait"
echo "y mira la unidad de Drive: tiene que haber un .dump por base."
echo
echo "Después, vuelve a desplegar el portal (deploy/desplegar.sh): al ver el job,"
echo "le pone COPIAS_JOB y el latido las lanzará cada noche."
