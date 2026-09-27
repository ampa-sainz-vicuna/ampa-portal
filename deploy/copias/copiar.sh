#!/usr/bin/env bash
# Copia de seguridad de cada base de la suite: pg_dump y a Drive.
#
# Lo ejecuta el job de Cloud Run ampa-copias, que lanza el latido del portal
# cada noche (RunHeartbeat). Cada base llega en una variable BASE_<NOMBRE>
# con su cadena de conexión, sacada de Secret Manager: el job tiene las de
# todas, el portal no.
#
#   BASE_FICHAJES=postgresql://…  →  fichajes-2026-09-27-0400.dump
#
# DRIVE_FOLDER_ID: la unidad compartida (o una carpeta dentro de ella) donde
# se guardan. Vacía, las copias se dejan en /copias: así se prueba en local
# (deploy/copias/README.md).
# CONSERVAR: cuántas copias se guardan de cada base (por omisión 30, un mes).
# Las más viejas van a la papelera de la unidad, que Drive vacía a los 30 días.
#
# Cada línea que escribe es un JSON con "severity": Cloud Logging lo entiende
# y la alerta de errores (deploy/alertas.sh) recoge los ERROR. Si falla
# alguna base, sigue con las demás y termina con código 1 (la ejecución sale
# como fallida en la consola).
set -uo pipefail

DRIVE_FOLDER_ID=${DRIVE_FOLDER_ID:-}
CONSERVAR=${CONSERVAR:-30}
LOCAL_DIR=${LOCAL_DIR:-/copias}
STAMP=$(TZ=Europe/Madrid date +%Y-%m-%d-%H%M)
WORK=$(mktemp -d)
FAILED=0

log() {
    jq -cn --arg severity "$1" --arg message "$2" '{severity: $severity, message: $message}'
}

# La cadena de Doctrine lleva ?serverVersion=16&charset=utf8, que libpq
# (pg_dump) rechaza. Fuera esos dos; lo demás (sslmode, channel_binding) se
# queda. Y sin "-pooler" en el servidor: Neon pide la conexión directa para
# pg_dump (la de las aplicaciones pasa por su pgbouncer).
libpq_url() {
    local url=${1/-pooler./.} base query kept='' param
    base=${url%%\?*}
    if [ "$base" = "$url" ]; then
        printf '%s' "$url"
        return
    fi
    query=${url#*\?}
    IFS='&' read -ra params <<<"$query"
    for param in "${params[@]}"; do
        case "$param" in
            serverVersion=* | charset=* | '') ;;
            *) kept+="${kept:+&}$param" ;;
        esac
    done
    printf '%s%s' "$base" "${kept:+?$kept}"
}

# El token para Drive, del servidor de metadatos de Cloud Run (sin claves:
# la cuenta de servicio de la suite, miembro de la unidad compartida).
drive_token() {
    curl -sf -H 'Metadata-Flavor: Google' \
        'http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token?scopes=https://www.googleapis.com/auth/drive' \
        | jq -r '.access_token // empty'
}

# Subida "reanudable" en dos pasos: primero el nombre y la carpeta, luego el
# fichero. Así no hace falta montar a mano un multipart/related.
upload() {
    local file=$1 name=$2 token=$3 location
    location=$(curl -sf -D - -o /dev/null -X POST \
        -H "Authorization: Bearer $token" \
        -H 'Content-Type: application/json; charset=UTF-8' \
        -H 'X-Upload-Content-Type: application/octet-stream' \
        --data "$(jq -cn --arg name "$name" --arg parent "$DRIVE_FOLDER_ID" '{name: $name, parents: [$parent]}')" \
        'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true' \
        | tr -d '\r' | awk -F': ' 'tolower($1) == "location" {print $2}')
    [ -n "$location" ] || return 1
    curl -sf -X PUT -H "Authorization: Bearer $token" \
        -H 'Content-Type: application/octet-stream' \
        --data-binary "@$file" "$location" | jq -r '.id // empty'
}

# Deja las CONSERVAR copias más nuevas de esa base y manda el resto a la
# papelera de la unidad.
prune() {
    local prefix=$1 token=$2 query ids id count=0
    query="'$DRIVE_FOLDER_ID' in parents and name contains '$prefix-' and trashed = false"
    ids=$(curl -sf -G -H "Authorization: Bearer $token" \
        --data-urlencode "q=$query" \
        --data-urlencode 'orderBy=createdTime desc' \
        --data-urlencode 'fields=files(id,name)' \
        --data-urlencode 'pageSize=200' \
        --data-urlencode 'supportsAllDrives=true' \
        --data-urlencode 'includeItemsFromAllDrives=true' \
        --data-urlencode 'corpora=allDrives' \
        'https://www.googleapis.com/drive/v3/files' \
        | jq -r --arg prefix "$prefix-" --argjson keep "$CONSERVAR" \
            '[.files[] | select(.name | startswith($prefix))] | .[$keep:] | .[].id') || return 1
    for id in $ids; do
        curl -sf -X PATCH -H "Authorization: Bearer $token" -H 'Content-Type: application/json' \
            --data '{"trashed": true}' \
            "https://www.googleapis.com/drive/v3/files/$id?supportsAllDrives=true" >/dev/null || return 1
        count=$((count + 1))
    done
    printf '%s' "$count"
}

TOKEN=''
if [ -n "$DRIVE_FOLDER_ID" ]; then
    TOKEN=$(drive_token)
    if [ -z "$TOKEN" ]; then
        log ERROR "Copias: no se ha podido pedir el token de Drive al servidor de metadatos. No se ha copiado nada."
        exit 1
    fi
else
    mkdir -p "$LOCAL_DIR"
    log WARNING "Copias: sin DRIVE_FOLDER_ID, se dejan en $LOCAL_DIR (solo para probar)."
fi

BASES=$(env | awk -F= '/^BASE_[A-Z0-9_]+=/ {print $1}' | sort)
if [ -z "$BASES" ]; then
    log ERROR "Copias: no hay ninguna variable BASE_<NOMBRE>. No se ha copiado nada."
    exit 1
fi

for var in $BASES; do
    name=$(printf '%s' "${var#BASE_}" | tr 'A-Z_' 'a-z-')
    file="$WORK/$name-$STAMP.dump"

    # --format=custom: comprimido, y pg_restore puede sacar solo una tabla.
    # --no-owner --no-privileges: se restaura con el usuario que sea (el de
    # la base nueva), sin arrastrar el de Neon.
    if ! error=$(pg_dump --format=custom --no-owner --no-privileges \
        --dbname="$(libpq_url "${!var}")" --file="$file" 2>&1); then
        log ERROR "Copias: $name no se ha podido copiar: $error"
        FAILED=1
        continue
    fi

    # Una copia que no se puede leer no es una copia.
    if ! pg_restore --list "$file" >/dev/null 2>&1; then
        log ERROR "Copias: la copia de $name ha salido ilegible."
        FAILED=1
        continue
    fi

    size=$(du -h "$file" | cut -f1)

    if [ -z "$DRIVE_FOLDER_ID" ]; then
        mv "$file" "$LOCAL_DIR/"
        log INFO "Copias: $name ($size) en $LOCAL_DIR."
        continue
    fi

    if ! id=$(upload "$file" "$(basename "$file")" "$TOKEN") || [ -z "$id" ]; then
        log ERROR "Copias: la copia de $name ($size) no se ha podido subir a Drive. ¿Sigue la cuenta de servicio en la unidad compartida?"
        FAILED=1
        continue
    fi

    if ! trashed=$(prune "$name" "$TOKEN"); then
        log ERROR "Copias: $name subida, pero no se han podido quitar las viejas (la unidad se irá llenando)."
        FAILED=1
        continue
    fi

    log INFO "Copias: $name ($size) subida a Drive; ${trashed:-0} vieja(s) a la papelera."
    rm -f "$file"
done

rm -rf "$WORK"

if [ "$FAILED" -ne 0 ]; then
    log ERROR "Copias: terminadas con errores (mira los mensajes de arriba)."
    exit 1
fi

log INFO "Copias: todas bien."
