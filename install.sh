#!/usr/bin/env bash
#
# Instalador de nframework (Debian/Ubuntu).
#
#   ./install.sh --host=www.ejemplo.com [opciones]
#
# Pasos (cada uno se salta si ya está hecho):
#   1. Paquetes del sistema: extensiones de PHP, Ghostscript, unzip, git (apt-get, con sudo).
#   2. includes/composer.phar descargado del instalador oficial con verificación de firma.
#   3. Dependencias: php composer.phar install --ignore-platform-reqs (en includes/).
#   4. includes/config.php con un bloque para --host (si no existe el archivo).
#   5. include_path de PHP (CLI y FPM) con includes/ del framework.
#   6. php nframework/test.php --host=...: instala la base de datos y muestra lo que falte.
#
# Opciones:
#   --host=<dominio>     dominio del sitio (bloque de config.php y base de datos a instalar)
#   --db=<nombre>        base de MongoDB para un config.php nuevo (por omisión: el dominio sin puntos)
#   --mongo=<cadena>     cadena de conexión para un config.php nuevo (por omisión: mongodb://127.0.0.1)
#   --php=<X.Y>          versión de PHP para los paquetes apt (por omisión: la de `php`)
#   --no-apt             no instalar paquetes del sistema
#   --no-dev             composer install --no-dev (sin PHPUnit/Pint)
#   --yes, -y            no pedir confirmación
#   --help, -h           esta ayuda
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INCLUDES="$ROOT/includes"

HOST=""
DB=""
MONGO="mongodb://127.0.0.1"
PHPV=""
APT=1
NODEV=""
YES=0

for arg in "$@"; do
    case "$arg" in
        --host=*) HOST="${arg#*=}" ;;
        --db=*) DB="${arg#*=}" ;;
        --mongo=*) MONGO="${arg#*=}" ;;
        --php=*) PHPV="${arg#*=}" ;;
        --no-apt) APT=0 ;;
        --no-dev) NODEV="--no-dev" ;;
        -y|--yes) YES=1 ;;
        -h|--help) sed -n '2,/^set -euo/p' "$0" | sed '$d; s/^# \{0,1\}//'; exit 0 ;;
        *) echo "Opción desconocida: $arg (use --help)" >&2; exit 1 ;;
    esac
done

if [ -t 1 ]; then G='\033[0;32m'; Y='\033[1;33m'; R='\033[0;31m'; B='\033[1m'; N='\033[0m'; else G=''; Y=''; R=''; B=''; N=''; fi
paso() { echo -e "\n${B}==> $*${N}"; }
ok() { echo -e "${G}✔${N} $*"; }
aviso() { echo -e "${Y}!${N} $*"; }
error() { echo -e "${R}✘${N} $*" >&2; }
confirmar() {
    [ "$YES" = 1 ] && return 0
    read -r -p "$1 [S/n] " r
    [[ -z "$r" || "$r" =~ ^[sSyY] ]]
}
SUDO=""
if [ "$(id -u)" -ne 0 ]; then SUDO="sudo"; fi

if [ -n "$HOST" ] && ! [[ "$HOST" =~ ^[A-Za-z0-9.-]+(:[0-9]+)?$ ]]; then
    error "Dominio inválido: $HOST"; exit 1
fi

# --- 1. Paquetes del sistema ----------------------------------------------------------------------------
if [ -z "$PHPV" ] && command -v php >/dev/null 2>&1; then
    PHPV="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
fi

if [ "$APT" = 1 ]; then
    paso "Paquetes del sistema"
    if ! command -v apt-get >/dev/null 2>&1; then
        aviso "apt-get no disponible: instale a mano PHP 8.3+ con las extensiones de la sección Requirements del README."
    else
        if [ -z "$PHPV" ]; then
            error "PHP no está instalado. Instale PHP 8.3+ (Ubuntu: sudo add-apt-repository ppa:ondrej/php) o indique --php=X.Y"
            exit 1
        fi
        PKGS=(git unzip ghostscript "php$PHPV-cli" "php$PHPV-fpm")
        for ext in mongodb intl gd curl sqlite3 zip mbstring xml imagick; do
            PKGS+=("php$PHPV-$ext")
        done
        FALTAN=()
        for p in "${PKGS[@]}"; do
            dpkg -s "$p" >/dev/null 2>&1 || FALTAN+=("$p")
        done
        if [ ${#FALTAN[@]} -eq 0 ]; then
            ok "Paquetes instalados (PHP $PHPV)"
        elif confirmar "Instalar con apt-get: ${FALTAN[*]}?"; then
            $SUDO apt-get update
            # Si un paquete no existe (p. ej. php8.x-mongodb fuera del PPA ondrej/sury) apt cancela todo:
            # entonces se reintenta uno por uno para instalar los demás.
            if ! $SUDO apt-get install -y "${FALTAN[@]}"; then
                aviso "Falló la instalación conjunta; se intenta paquete por paquete."
                for p in "${FALTAN[@]}"; do
                    $SUDO apt-get install -y "$p" || aviso "No se pudo instalar $p"
                done
            fi
            if ! php -m 2>/dev/null | grep -qi '^mongodb$'; then
                aviso "La extensión mongodb no quedó instalada. Alternativa con PECL:"
                echo "    sudo apt-get install php$PHPV-dev php-pear && sudo pecl install mongodb"
                echo "    echo 'extension=mongodb.so' | sudo tee /etc/php/$PHPV/mods-available/mongodb.ini && sudo phpenmod -v $PHPV mongodb"
            fi
        else
            aviso "Se omitió la instalación de paquetes."
        fi
    fi
fi

if ! command -v php >/dev/null 2>&1; then
    error "No hay intérprete php en PATH."; exit 1
fi
if ! php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
    aviso "PHP $(php -r 'echo PHP_VERSION;') es menor que 8.3: nframework puede no funcionar."
fi

# --- 2. composer.phar -----------------------------------------------------------------------------------
paso "Composer (includes/composer.phar)"
if [ -f "$INCLUDES/composer.phar" ]; then
    php "$INCLUDES/composer.phar" self-update --no-interaction >/dev/null 2>&1 || true
    ok "$(php "$INCLUDES/composer.phar" --version --no-ansi 2>/dev/null | head -1)"
else
    TMP="$(mktemp -d)"
    trap 'rm -rf "$TMP"' EXIT
    php -r "copy('https://composer.github.io/installer.sig', '$TMP/installer.sig');"
    php -r "copy('https://getcomposer.org/installer', '$TMP/composer-setup.php');"
    ESPERADO="$(tr -d '[:space:]' < "$TMP/installer.sig")"
    ACTUAL="$(php -r "echo hash_file('sha384', '$TMP/composer-setup.php');")"
    if [ -z "$ESPERADO" ] || [ "$ESPERADO" != "$ACTUAL" ]; then
        error "La firma del instalador de Composer no coincide; no se instaló."; exit 1
    fi
    php "$TMP/composer-setup.php" --quiet --install-dir="$INCLUDES" --filename=composer.phar
    ok "Descargado $INCLUDES/composer.phar"
fi

# --- 3. Dependencias ------------------------------------------------------------------------------------
paso "Dependencias de PHP (includes/vendor)"
# --ignore-platform-reqs: instala aunque falte alguna extensión o la versión de PHP del CLI difiera de la de
# FPM. Lo que falte lo reporta nframework/test.php (paso 6).
( cd "$INCLUDES" && php composer.phar install --no-interaction --ignore-platform-reqs $NODEV )
ok "Dependencias instaladas"

# --- 4. config.php --------------------------------------------------------------------------------------
paso "Configuración (includes/config.php)"
if [ -f "$INCLUDES/config.php" ]; then
    if [ -n "$HOST" ] && ! grep -qF "case '$HOST'" "$INCLUDES/config.php"; then
        aviso "config.php existe pero no tiene un bloque para $HOST: agréguelo (vea includes/config.example.php)."
    else
        ok "config.php existe"
    fi
elif [ -z "$HOST" ]; then
    cp "$INCLUDES/config.example.php" "$INCLUDES/config.php"
    aviso "Se copió config.example.php a config.php: edite el bloque de su dominio."
else
    DB="${DB:-$(echo "${HOST%%:*}" | tr -c 'A-Za-z0-9_\n' '_')}"
    SCHEME="https"
    [[ "$HOST" == *.local || "$HOST" == localhost* ]] && SCHEME="http"
    # Los valores pasan por var_export: comillas o barras en --mongo no rompen el archivo.
    php -r '
        [$host, $db, $mongo, $url, $file] = array_slice($argv, 1);
        $e = fn($v) => var_export($v, true);
        $cookie = explode(":", $host)[0];
        file_put_contents($file, "<?php\n// Generado por install.sh. Todas las opciones: includes/config.example.php\n"
            . "switch (\$_SERVER[\"HTTP_HOST\"] ?? \"localhost\") {\n"
            . "    case {$e($host)}:\n        \$config = [\n"
            . "            \"mongo_connection_string\" => {$e($mongo)},\n"
            . "            \"sitedb\" => {$e($db)},\n"
            . "            \"cookie_domain\" => {$e($cookie)},\n"
            . "            \"url\" => {$e($url)},\n"
            . "        ];\n        break;\n\n"
            . "    default:\n        http_response_code(400);\n        exit(\"Dominio no configurado.\");\n}\n");
    ' "$HOST" "$DB" "$MONGO" "$SCHEME://$HOST" "$INCLUDES/config.php"
    chmod 640 "$INCLUDES/config.php"
    ok "config.php creado para $HOST (base $DB, $MONGO)"
fi

# --- 5. include_path ------------------------------------------------------------------------------------
paso "include_path de PHP"
if [ -n "$PHPV" ] && [ -d "/etc/php/$PHPV" ]; then
    LINEA="include_path = \".:$INCLUDES:/usr/share/php\""
    REINICIAR=0
    for sapi in cli fpm apache2; do
        DIR="/etc/php/$PHPV/$sapi/conf.d"
        [ -d "$DIR" ] || continue
        if grep -qs "$INCLUDES" "$DIR"/*.ini "/etc/php/$PHPV/$sapi/php.ini"; then
            ok "PHP $PHPV $sapi ya incluye $INCLUDES"
        elif confirmar "Agregar $INCLUDES al include_path de PHP $PHPV $sapi ($DIR/99-nframework.ini)?"; then
            printf '; Generado por install.sh\n%s\n' "$LINEA" | $SUDO tee "$DIR/99-nframework.ini" >/dev/null
            ok "Escrito $DIR/99-nframework.ini"
            [ "$sapi" = fpm ] && REINICIAR=1
        else
            aviso "Se omitió PHP $PHPV $sapi. A mano: echo '$LINEA' | sudo tee $DIR/99-nframework.ini"
        fi
    done
    if [ "$REINICIAR" = 1 ] && command -v systemctl >/dev/null 2>&1; then
        $SUDO systemctl restart "php$PHPV-fpm" && ok "php$PHPV-fpm reiniciado" || aviso "Reinicie php$PHPV-fpm a mano"
    fi
else
    aviso "No se encontró /etc/php/$PHPV: agregue $INCLUDES al include_path de PHP a mano."
fi

# --- 6. Base de datos y diagnóstico ---------------------------------------------------------------------
paso "Base de datos y diagnóstico (nframework/test.php)"
if [ -z "$HOST" ]; then
    aviso "Sin --host no se instala la base de datos. Ejecute: php nframework/test.php --host=<dominio>"
else
    php "$ROOT/nframework/test.php" --host="$HOST" || true
fi

echo -e "\n${B}Siguiente:${N} configure el servidor web (document root en $ROOT, todo lo que no sea archivo a /router.php,"
echo "y el bloque de seguridad de docs/security.php) y MongoDB si aún no está instalado."
