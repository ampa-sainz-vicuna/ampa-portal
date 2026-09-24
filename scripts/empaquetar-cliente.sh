#!/bin/sh
# Empaqueta el cliente (cliente/) en dist/ampa-portal-cliente-X.Y.Z.zip.
#
# Lo usa la Action al publicar una versión, y sirve en local para ver qué va
# dentro antes de publicar:
#
#   docker compose exec -w /var/www/html php sh scripts/empaquetar-cliente.sh 0.1.0
#
# Solo lo que necesita una aplicación para usarlo: nada de tests, ni vendor,
# ni configuración de PHPUnit. Los ficheros van en la raíz del zip (sin
# carpeta delante), que es lo que espera un repositorio "package" de Composer.
set -eu

VERSION="${1:?Falta la versión, p. ej. 0.1.0}"
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
ZIP="$RAIZ/dist/ampa-portal-cliente-$VERSION.zip"

mkdir -p "$RAIZ/dist"
rm -f "$ZIP"

cd "$RAIZ/cliente"
zip -r -X "$ZIP" composer.json README.md config src > /dev/null

echo "$ZIP"
echo "sha1: $(sha1sum "$ZIP" | cut -d' ' -f1)"
