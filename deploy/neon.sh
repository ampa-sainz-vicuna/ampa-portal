#!/usr/bin/env bash
# Pide la cadena de conexión de Neon por el terminal, sin mostrarla, y la deja
# en la variable NEON_URL lista para guardar en Secret Manager.
#
# No se ejecuta sola: la cargan con "source" deploy/preparar.sh (la primera vez)
# (copiado de listados, con la base "suite").
#
# Comprueba la cadena ENTERA antes de darla por buena. El 24/09/2026 se guardó
# cortada —la contraseña copiada de PowerShell traía un salto de línea, la
# cadena quedó en dos líneas y solo se leyó la primera— y la aplicación no
# arrancó. Mirar solo que empezase por postgresql:// no bastaba.

pedir_cadena_neon() {
    echo
    echo "     Pega la cadena de conexión de Neon (empieza por postgresql://)."
    echo "     Que sea la de la base SUITE (la del portal), con el usuario suite."
    echo "     No se verá lo que pegas; pulsa Enter al terminar."
    local url
    read -rs url
    echo

    # Fuera espacios y retornos de carro que se cuelan al copiar en Windows.
    url=$(printf '%s' "$url" | tr -d '\r' | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')

    # usuario:contraseña@servidor/base, con o sin ?parámetros detrás.
    if ! [[ "$url" =~ ^postgres(ql)?://[^:/@]+:[^@]+@[^/@]+/[^/?]+(\?.*)?$ ]]; then
        echo "     Esa cadena no está completa. Tiene que tener esta forma:"
        echo "       postgresql://usuario:contraseña@servidor/suite?sslmode=require"
        echo "     Si la montaste en el bloc de notas, comprueba que está en UNA"
        echo "     sola línea (sin salto de línea detrás de la contraseña)."
        echo "     No se ha guardado nada."
        return 1
    fi

    local base=${url#*@*/}
    base=${base%%\?*}
    if [ "$base" != "suite" ]; then
        echo "     La cadena apunta a la base \"$base\", no a \"suite\"."
        echo "     No se ha guardado nada."
        return 1
    fi

    # Doctrine necesita saber la versión de PostgreSQL y la codificación.
    url="postgresql://${url#*://}"
    local sep
    case "$url" in *\?*) sep='&' ;; *) sep='?' ;; esac
    case "$url" in *serverVersion=*) ;; *) url="${url}${sep}serverVersion=16&charset=utf8" ;; esac

    NEON_URL=$url
}
