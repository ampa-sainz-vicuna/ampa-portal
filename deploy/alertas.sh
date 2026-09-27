#!/usr/bin/env bash
# La alerta de errores de TODA la suite: un correo cuando algo falla.
#
#   docker compose run --rm gcloud bash deploy/alertas.sh CORREO
#
# CORREO: a dónde llegan los avisos (uno; para más, repetirlo con otro). Se
# pasa aquí y no se escribe en el repositorio, que es público. Repetirlo con
# el mismo correo no duplica nada: actualiza la alerta.
#
# SIN ESTRENAR (escrito el 26/09/2026).
#
# Qué vigila (una sola alerta en Cloud Monitoring, basada en los registros):
#   - un [error] (o peor) que escriba cualquier aplicación. Todas usan el
#     registro mínimo de Symfony, que escribe "[error] mensaje": el latido
#     del portal cuando algo no sale (una aplicación que no contesta, las
#     copias que no arrancan, Neon por encima del 80 % del cómputo gratuito),
#     el resumen de tareas sin hacer porque el portal no contestaba…;
#   - cualquier respuesta 5xx de una aplicación (el registro de peticiones de
#     Cloud Run);
#   - un fallo del job de las copias (escribe sus errores con severidad ERROR);
#   - un intento fallido de Cloud Scheduler (el latido, el resumen de tareas).
#
# Como mucho, UN correo por hora aunque falle muchas veces: con el primero
# basta para ir a mirar. El correo trae el mensaje y un enlace al registro.
#
# Gratis: Google no cobra las alertas hasta, como pronto, el 1 de septiembre
# de 2027 (página de precios de Google Cloud Observability, consultada el
# 26/09/2026). Entonces, unos céntimos al mes por esta condición: revisarlo.
set -euo pipefail

if [ $# -ne 1 ] || [[ "$1" != *@* ]]; then
    echo "Uso: bash deploy/alertas.sh CORREO"
    exit 1
fi

EMAIL=$1
POLICY_NAME="Suite del AMPA: algo ha fallado"
CHANNEL_NAME="Avisos de la suite ($EMAIL)"

PROJECT=$(gcloud config get-value project 2>/dev/null)
if [ -z "$PROJECT" ]; then
    echo "No hay proyecto elegido. Ejecuta antes:"
    echo "  docker compose run --rm gcloud gcloud config set project ID_DEL_PROYECTO"
    exit 1
fi

# La imagen de gcloud no trae jq. Con la API REST y no con "gcloud alpha
# monitoring", que pide instalar componentes alfa.
command -v jq >/dev/null || apk add --no-cache jq >/dev/null
command -v curl >/dev/null || apk add --no-cache curl >/dev/null

TOKEN=$(gcloud auth print-access-token)
API="https://monitoring.googleapis.com/v3/projects/${PROJECT}"

api() {
    local method=$1 url=$2 body=${3:-}
    if [ -n "$body" ]; then
        curl -sf -X "$method" -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' --data "$body" "$url"
    else
        curl -sf -X "$method" -H "Authorization: Bearer $TOKEN" "$url"
    fi
}

echo "1/3  Activando Cloud Monitoring (si ya lo estaba, no hace nada)..."
gcloud services enable monitoring.googleapis.com logging.googleapis.com

echo "2/3  El canal de correo ($EMAIL)..."
CHANNEL=$(api GET "${API}/notificationChannels?pageSize=100" \
    | jq -r --arg email "$EMAIL" '[.notificationChannels[]? | select(.type == "email" and .labels.email_address == $email)][0].name // empty')
if [ -z "$CHANNEL" ]; then
    CHANNEL=$(api POST "${API}/notificationChannels" "$(jq -cn --arg name "$CHANNEL_NAME" --arg email "$EMAIL" \
        '{type: "email", displayName: $name, labels: {email_address: $email}}')" | jq -r '.name')
    echo "     creado"
else
    echo "     ya existía"
fi

# El filtro, en el lenguaje de Cloud Logging. Ver arriba qué recoge cada línea.
# Los corchetes de "[error]" van como [[] y []] (una clase con un solo
# carácter) para no depender de cómo escapa la barra el lenguaje del filtro.
read -r -d '' FILTER <<'EOF' || true
resource.type=("cloud_run_revision" OR "cloud_run_job" OR "cloud_scheduler_job")
AND (
  textPayload=~"[[](error|critical|alert|emergency)[]]"
  OR (severity>=ERROR AND (httpRequest.status>=500 OR resource.type="cloud_run_job" OR resource.type="cloud_scheduler_job"))
)
EOF

read -r -d '' DOCS <<'EOF' || true
Algo ha fallado en la suite del AMPA (portal, fichajes, listados, facturación, tareas, las copias de seguridad o un trabajo programado).

**Dónde mirar:** el enlace "View logs" de este correo lleva al mensaje. Los del latido del portal empiezan por "Latido:"; los de las copias, por "Copias:"; el aviso de Neon, por "Neon:".

Como mucho llega un correo por hora. Si el fallo sigue, al cabo de un rato llega otro. Scripts y explicación: deploy/alertas.sh del portal.
EOF

POLICY=$(jq -cn \
    --arg name "$POLICY_NAME" \
    --arg filter "$FILTER" \
    --arg docs "$DOCS" \
    --arg channel "$CHANNEL" \
    '{
        displayName: $name,
        documentation: {content: $docs, mimeType: "text/markdown"},
        combiner: "OR",
        conditions: [{
            displayName: "Error en una aplicación, en las copias o en un trabajo programado",
            conditionMatchedLog: {filter: $filter}
        }],
        alertStrategy: {notificationRateLimit: {period: "3600s"}, autoClose: "1800s"},
        notificationChannels: [$channel],
        severity: "ERROR"
    }')

echo "3/3  La alerta..."
EXISTING=$(api GET "${API}/alertPolicies?pageSize=100" \
    | jq -r --arg name "$POLICY_NAME" '[.alertPolicies[]? | select(.displayName == $name)][0].name // empty')
if [ -z "$EXISTING" ]; then
    api POST "${API}/alertPolicies" "$POLICY" >/dev/null
    echo "     creada"
else
    # Conserva los canales que ya tuviera y añade este.
    CHANNELS=$(api GET "https://monitoring.googleapis.com/v3/${EXISTING}" \
        | jq -c --arg channel "$CHANNEL" '(.notificationChannels // []) + [$channel] | unique')
    api PATCH "https://monitoring.googleapis.com/v3/${EXISTING}" \
        "$(jq -c --argjson channels "$CHANNELS" '.notificationChannels = $channels' <<<"$POLICY")" >/dev/null
    echo "     ya existía: actualizada"
fi

echo
echo "Listo. Para probarla sin esperar a que algo falle (con el job de copias ya"
echo "montado), lánzalo una vez con una unidad de Drive que no existe:"
echo "  docker compose run --rm gcloud gcloud run jobs execute ampa-copias --region=europe-west1 --update-env-vars=DRIVE_FOLDER_ID=no-existe"
echo "Falla al subir y en unos minutos tiene que llegar el correo. El cambio es"
echo "solo para esa ejecución: el job sigue con la unidad buena."
