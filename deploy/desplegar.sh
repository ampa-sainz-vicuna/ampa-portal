#!/usr/bin/env bash
# Sube la versión actual del portal a producción:
#
#   docker compose run --rm gcloud bash deploy/desplegar.sh
#
# SIN ESTRENAR (escrito el 24/09/2026, copiando el de listados, que sí está
# probado). La imagen sí se ha construido y probado en local.
#
# Google construye la imagen con el Dockerfile de la raíz (Cloud Build), la
# guarda (Artifact Registry) y la pone en marcha (Cloud Run). Al arrancar, el
# contenedor aplica las migraciones pendientes.
#
# Requiere haber ejecutado antes deploy/preparar.sh, y que web/package.json
# instale @ampa/ui desde la URL de su release (con "file:" Cloud Build no lo
# encuentra).
set -euo pipefail

SERVICE=ampa-portal
# Bélgica, la misma región que fichajes y listados.
REGION=europe-west1

PROJECT=$(gcloud config get-value project 2>/dev/null)
if [ -z "$PROJECT" ]; then
    echo "No hay proyecto elegido. Ejecuta antes:"
    echo "  docker compose run --rm gcloud gcloud config set project ID_DEL_PROYECTO"
    exit 1
fi

if grep -q '"@ampa/ui": "file:' web/package.json; then
    echo "web/package.json instala @ampa/ui con file: (la versión local)."
    echo "Cloud Build no tiene esa carpeta: publica @ampa/ui e instálala desde"
    echo "la URL de su release antes de desplegar."
    exit 1
fi

PROJECT_NUMBER=$(gcloud projects describe "$PROJECT" --format='value(projectNumber)')

# La dirección pública, en el dominio del AMPA. Tiene que serlo: la cookie de
# sesión es de .ampasainzvicuna.com, y desde la dirección *.run.app el
# navegador no la aceptaría. Se asigna una vez con
#   gcloud beta run domain-mappings create --service=ampa-portal \
#       --domain=portal.ampasainzvicuna.com --region=europe-west1
# y un CNAME "portal" → ghs.googlehosted.com en el DNS de CDmon.
URL="https://portal.ampasainzvicuna.com"

# La dirección fija de Cloud Run. Para ENTRAR no sirve (la cookie), pero es la
# que usan los SERVIDORES de las aplicaciones para preguntar al portal
# (PORTAL_URL en cada una): de un Cloud Run a otro, sin pasar por el dominio.
RUN_URL="https://${SERVICE}-${PROJECT_NUMBER}.${REGION}.run.app"

SECRETS="DATABASE_URL=portal-database-url:latest,JWT_KEY=portal-jwt-key:latest"

# Lo que no es secreto y cambia respecto a desarrollo (api/.env):
#   - la cookie, para todo el dominio del AMPA y solo por HTTPS;
#   - dónde está cada aplicación, para las tarjetas.
ENV_VARS="DEFAULT_URI=${URL}"
ENV_VARS+=",SESSION_COOKIE_DOMAIN=.ampasainzvicuna.com"
ENV_VARS+=",SESSION_COOKIE_SECURE=1"
ENV_VARS+=",URL_FICHAJES=https://empleados.ampasainzvicuna.com"
ENV_VARS+=",URL_LISTADOS=https://listados.ampasainzvicuna.com"
# Facturación aún no está desplegada: es la dirección prevista. Su tarjeta
# solo la ve quien tenga permiso en ella.
ENV_VARS+=",URL_FACTURACION=https://facturacion.ampasainzvicuna.com"

echo "Desplegando el portal en $PROJECT ($REGION). Tarda unos 5 minutos..."
echo

# --max-instances=1: nunca más de un contenedor. Basta de sobra para el AMPA,
#   impide que dos arranques migren la base de datos a la vez y pone techo al
#   gasto.
# --min-instances=0: sin uso se apaga del todo y no cuesta nada. OJO: el
#   portal está en el camino de CADA petición de cada aplicación. Si está
#   dormido, la primera petición de una aplicación espera a que arranque
#   (unos segundos). Normalmente no pasa, porque se acaba de pasar por él para
#   entrar; si molesta, --min-instances=1 lo evita, y cuesta dinero.
# --memory=512Mi: el portal no hace nada pesado.
# --no-invoker-iam-check: la web es pública (el control de acceso lo hace la
#   propia aplicación). Igual que fichajes y listados.
gcloud run deploy "$SERVICE" \
    --source . \
    --region "$REGION" \
    --no-invoker-iam-check \
    --max-instances=1 \
    --min-instances=0 \
    --memory=512Mi \
    --cpu=1 \
    --timeout=60 \
    --set-secrets="$SECRETS" \
    --set-env-vars="$ENV_VARS" \
    --quiet

echo
echo "Limpieza automática, para no salir del nivel gratuito..."

# Cada despliegue deja una imagen nueva en Artifact Registry, que solo regala
# 0,5 GB (compartidos con las demás aplicaciones). Se conservan las 2 últimas.
gcloud artifacts repositories set-cleanup-policies cloud-run-source-deploy \
    --location="$REGION" \
    --policy=deploy/limpieza-imagenes.json >/dev/null \
    && echo "  Imágenes: se conservan las 2 últimas."

for bucket in "gs://run-sources-${PROJECT}-${REGION}" "gs://${PROJECT}_cloudbuild"; do
    if gcloud storage buckets describe "$bucket" >/dev/null 2>&1; then
        gcloud storage buckets update "$bucket" --lifecycle-file=deploy/limpieza-subidas.json >/dev/null \
            && echo "  $bucket: las subidas se borran a los 7 días."
    fi
done

echo
echo "Desplegado en:"
echo "  $URL"
echo
echo "Para los servidores de las aplicaciones (PORTAL_URL):"
echo "  $RUN_URL"
echo
echo "Si es la primera vez:"
echo "  - añade $URL a los orígenes autorizados de JavaScript del cliente OAuth;"
echo "  - da el primer administrador (README, \"Primer arranque\")."
