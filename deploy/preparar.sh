#!/usr/bin/env bash
# Preparación de Google Cloud para el portal. Se ejecuta UNA vez (repetirlo no
# rompe nada):
#
#   docker compose run --rm gcloud bash deploy/preparar.sh
#
# SIN ESTRENAR (escrito el 24/09/2026, copiando el de listados, que sí está
# probado). Revisar la primera vez paso a paso.
#
# El proyecto de Google Cloud es el de toda la suite (el mismo de fichajes y
# listados), así que los servicios y el permiso de construir ya estarán; se
# dejan por si algún día se despliega en otro.
#
# Crea los DOS secretos del portal: la clave de firma de la sesión de TODA la
# suite y la cadena de conexión de su base en Neon. Ningún secreto se muestra
# en pantalla ni se guarda en el ordenador.
set -euo pipefail

PROJECT=$(gcloud config get-value project 2>/dev/null)
if [ -z "$PROJECT" ]; then
    echo "No hay proyecto elegido. Ejecuta antes:"
    echo "  docker compose run --rm gcloud gcloud config set project ID_DEL_PROYECTO"
    exit 1
fi

PROJECT_NUMBER=$(gcloud projects describe "$PROJECT" --format='value(projectNumber)')
# Cuenta con la que Cloud Run construye la imagen y ejecuta la app.
SERVICE_ACCOUNT="${PROJECT_NUMBER}-compute@developer.gserviceaccount.com"

echo "Proyecto: $PROJECT"
echo

echo "1/3  Activando servicios (si ya lo estaban, no hace nada)..."
gcloud services enable \
    run.googleapis.com \
    cloudbuild.googleapis.com \
    artifactregistry.googleapis.com \
    secretmanager.googleapis.com

echo "2/3  Permiso para construir la imagen..."
gcloud projects add-iam-policy-binding "$PROJECT" \
    --member="serviceAccount:$SERVICE_ACCOUNT" \
    --role=roles/run.builder \
    --condition=None --quiet >/dev/null

echo "3/3  Secretos..."

# Crea el secreto con lo que llegue por la entrada estándar, si no existe ya, y
# deja que Cloud Run lo lea. Si ya existe NO lo cambia: regenerar la clave
# cerraría la sesión de todo el mundo en TODA la suite.
#
# Prefijo "portal-" porque el proyecto de Google Cloud es el de toda la suite.
secret() {
    local name=$1
    if gcloud secrets describe "$name" >/dev/null 2>&1; then
        cat >/dev/null
        echo "     $name: ya existía, no se toca"
    else
        gcloud secrets create "$name" --replication-policy=automatic --data-file=- >/dev/null
        echo "     $name: creado"
    fi
    gcloud secrets add-iam-policy-binding "$name" \
        --member="serviceAccount:$SERVICE_ACCOUNT" \
        --role=roles/secretmanager.secretAccessor >/dev/null
}

# La imagen de gcloud no trae openssl; hace falta para generar la clave.
command -v openssl >/dev/null || apk add --no-cache openssl >/dev/null

# La clave con la que el portal firma la sesión de la suite (HS256). Es la
# ÚNICA clave de firma de toda la suite: las aplicaciones no la necesitan,
# le preguntan al portal. 32 bytes aleatorios en hexadecimal (64 caracteres):
# HS256 exige al menos 32 bytes.
if gcloud secrets describe portal-jwt-key >/dev/null 2>&1; then
    echo "     portal-jwt-key: ya existía, no se toca"
    gcloud secrets add-iam-policy-binding portal-jwt-key \
        --member="serviceAccount:$SERVICE_ACCOUNT" \
        --role=roles/secretmanager.secretAccessor >/dev/null
else
    openssl rand -hex 32 | tr -d '\n' | secret portal-jwt-key
fi

# La base "suite" en Neon: otra base en el mismo proyecto de Neon que las de
# fichajes y listados, con su propio usuario (se crean en la consola de Neon
# antes de ejecutar esto; README, "Desplegar").
if gcloud secrets describe portal-database-url >/dev/null 2>&1; then
    echo "     portal-database-url: ya existía, no se toca"
    gcloud secrets add-iam-policy-binding portal-database-url \
        --member="serviceAccount:$SERVICE_ACCOUNT" \
        --role=roles/secretmanager.secretAccessor >/dev/null
else
    source deploy/neon.sh
    pedir_cadena_neon || exit 1
    printf '%s' "$NEON_URL" | secret portal-database-url
fi

echo
echo "Listo. Ahora el despliegue:"
echo "  docker compose run --rm gcloud bash deploy/desplegar.sh"
