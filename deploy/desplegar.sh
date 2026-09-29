#!/usr/bin/env bash
# Sube la versión actual del portal a producción:
#
#   docker compose run --rm gcloud bash deploy/desplegar.sh
#
# Google construye la imagen con el Dockerfile de la raíz (Cloud Build), la
# guarda (Artifact Registry) y crea con ella una revisión nueva de Cloud Run,
# todavía SIN tráfico. Con esa misma imagen, un job de Cloud Run aplica las
# migraciones pendientes, y solo si salen bien pasa el tráfico a la revisión
# nueva. (Hasta el 28/09/2026 las aplicaba el contenedor al arrancar, y eso
# alargaba cada arranque en frío: ver docker/prod/entrypoint.sh.)
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
# Tareas aún no está desplegada: es la dirección prevista.
ENV_VARS+=",URL_TAREAS=https://tareas.ampasainzvicuna.com"
# Las llamadas del servidor de una aplicación sin nadie detrás (el resumen
# diario de tareas; cliente 0.1.3): el token de identidad de Google de la
# cuenta de servicio con la que corren todas (la de Compute Engine por
# defecto), emitido para la dirección fija del portal. Una sola cuenta: con
# varias, la coma chocaría con la de --set-env-vars.
ENV_VARS+=",SUITE_TOKEN_AUDIENCE=${RUN_URL}"
ENV_VARS+=",SUITE_SERVICE_ACCOUNTS=${PROJECT_NUMBER}-compute@developer.gserviceaccount.com"
# Quién puede leer la ayuda (GET /api/ayuda) desde otra web: en producción,
# solo las aplicaciones del catálogo y el portal, que ya salen de las URL_… y
# de DEFAULT_URI. Vacía y no sin definir: sin definir, Symfony usaría la de
# api/.env, con los localhost de desarrollo.
ENV_VARS+=",HELP_ALLOWED_ORIGINS="

# El latido diario (POST /api/latido; deploy/programar.sh). Cada paso que no
# esté montado se salta sin error:
#   - las copias de seguridad, si existe el job (deploy/copias/preparar.sh);
#   - el cómputo de Neon, si existe el secreto neon-api-key y está puesto el
#     ID del proyecto de Neon aquí debajo (Neon → proyecto ampa → Settings →
#     General → Project ID; no es secreto).
NEON_PROJECT_ID="small-pond-03723796"
if gcloud run jobs describe ampa-copias --region="$REGION" >/dev/null 2>&1; then
    ENV_VARS+=",COPIAS_JOB=projects/${PROJECT}/locations/${REGION}/jobs/ampa-copias"
else
    echo "Sin job de copias (deploy/copias/preparar.sh): el latido no las lanzará."
fi
if [ -n "$NEON_PROJECT_ID" ] && gcloud secrets describe neon-api-key >/dev/null 2>&1; then
    ENV_VARS+=",NEON_PROJECT_ID=${NEON_PROJECT_ID}"
    SECRETS+=",NEON_API_KEY=neon-api-key:latest"
else
    echo "Sin NEON_PROJECT_ID o sin el secreto neon-api-key: el latido no mirará Neon."
fi

MIGRATIONS_JOB=portal-migraciones

NO_TRAFFIC=()
if gcloud run services describe "$SERVICE" --region="$REGION" >/dev/null 2>&1; then
    NO_TRAFFIC=(--no-traffic)
fi

echo "Desplegando el portal en $PROJECT ($REGION). Tarda unos 5 minutos..."
echo

# --max-instances=1: nunca más de un contenedor. Basta de sobra para el AMPA y
#   pone techo al gasto.
# --min-instances=0: sin uso se apaga del todo y no cuesta nada. OJO: el
#   portal está en el camino de CADA petición de cada aplicación. Si está
#   dormido, la primera petición espera a que arranque (unos segundos; menos
#   desde que no migra al arrancar). --min-instances=1 lo evita, y cuesta unos
#   10 $ al mes (1 vCPU y 512 MiB a la tarifa de inactividad, 0,0000025 $ por
#   segundo cada uno; calculado el 28/09/2026): de momento, no.
# --no-traffic: la revisión nueva no recibe a nadie hasta que se han aplicado
#   las migraciones (abajo). Solo vale si el servicio ya existe: la primera
#   vez no hay versión anterior que proteger.
# --memory=512Mi: el portal no hace nada pesado.
# --no-invoker-iam-check: la web es pública (el control de acceso lo hace la
#   propia aplicación). Igual que fichajes y listados.
# --timeout=300: lo más que puede durar una petición. Las normales tardan
#   milisegundos; el latido espera a que cada aplicación despierte y haga su
#   trabajo (hasta 1 minuto cada una, una detrás de otra). Con 60 s, Cloud
#   Run lo cortaría a la primera aplicación lenta.
gcloud run deploy "$SERVICE" \
    --source . \
    --region "$REGION" \
    --no-invoker-iam-check \
    --max-instances=1 \
    --min-instances=0 \
    --memory=512Mi \
    --cpu=1 \
    --timeout=300 \
    --set-secrets="$SECRETS" \
    --set-env-vars="$ENV_VARS" \
    "${NO_TRAFFIC[@]}" \
    --quiet

# ---------------------------------------------------------------------------
# Las migraciones, con la imagen que se acaba de construir y los mismos
# secretos, dentro de Google (la contraseña de la base no sale de allí; el
# mismo montaje que deploy/dar-permisos.sh). El job se queda creado: no guarda
# nada de nadie, y el siguiente despliegue solo le cambia la imagen.
#
# Mientras corre, la versión anterior sigue atendiendo con la base ya migrada.
# Era igual cuando migraba el contenedor al arrancar: las migraciones tienen
# que dejar funcionando la versión anterior (añadir antes que quitar).
# ---------------------------------------------------------------------------
IMAGE=$(gcloud run services describe "$SERVICE" --region="$REGION" \
    --format='value(spec.template.spec.containers[0].image)')
REVISION=$(gcloud run services describe "$SERVICE" --region="$REGION" \
    --format='value(status.latestCreatedRevisionName)')

echo
echo "Aplicando las migraciones de $REVISION..."

gcloud run jobs deploy "$MIGRATIONS_JOB" \
    --image="$IMAGE" \
    --region="$REGION" \
    --set-secrets=DATABASE_URL=portal-database-url:latest,JWT_KEY=portal-jwt-key:latest \
    --command=php \
    --args=bin/console,doctrine:migrations:migrate,--no-interaction,--allow-no-migration \
    --max-retries=0 \
    --task-timeout=300 \
    --quiet >/dev/null

if ! gcloud run jobs execute "$MIGRATIONS_JOB" --region="$REGION" --wait --quiet >/dev/null; then
    echo
    echo "Las migraciones han fallado: el tráfico SIGUE en la versión anterior."
    echo "El porqué, en el registro del job:"
    echo "  gcloud logging read 'resource.type=cloud_run_job AND resource.labels.job_name=$MIGRATIONS_JOB' --freshness=15m --format='value(textPayload)'"
    exit 1
fi
echo "  Migraciones al día."

# De vuelta a "la última revisión se lleva todo el tráfico" (con --no-traffic
# el servicio se queda fijado a la anterior).
gcloud run services update-traffic "$SERVICE" --region="$REGION" --to-latest --quiet >/dev/null
echo "  Tráfico en $REVISION."

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
echo "  - añade $URL a los orígenes autorizados de JavaScript del cliente OAuth,"
echo "    y $URL/api/auth/google/vuelta a sus URI de redirección autorizados;"
echo "  - da el primer administrador (README, \"Primer arranque\")."
