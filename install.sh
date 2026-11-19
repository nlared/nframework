#!/usr/bin/env bash
#
# Instalador de nframework (Debian/Ubuntu).
#
#   ./install.sh --host=www.ejemplo.com [opciones]
#
# Dos modos de instalación:
#   single (sitio único)  el repositorio clonado ES el document root del sitio: todos los archivos del framework
#                         (admin/, nframework/, router.php, docs/...) quedan junto a las páginas del sitio.
#   multi  (multisitio)   el framework vive en una carpeta compartida (p. ej. /opt/nframework) y cada vhost
#                         tiene su propio document root (--docroot) con ligas simbólicas a admin/, nframework/
#                         y router.php. Todos los sitios comparten includes/ (código, vendor y config.php, con
#                         un bloque por dominio). Repita el comando con otro --host/--docroot por cada sitio.
#
#   ./install.sh --mode=single --host=www.ejemplo.com
#   ./install.sh --mode=multi --host=www.ejemplo.com --docroot=/var/www/www.ejemplo.com
#
# Pasos (cada uno se salta si ya está hecho):
#   1. Paquetes del sistema: extensiones de PHP, Ghostscript, unzip, git (apt-get, con sudo).
#   2. includes/composer.phar descargado del instalador oficial con verificación de firma.
#   3. Dependencias: php composer.phar install --ignore-platform-reqs (en includes/).
#   4. includes/config.php con un bloque para --host (crea el archivo o agrega el bloque si falta).
#   5. include_path de PHP (CLI y FPM) con includes/ del framework.
#   6. Multisitio: document root del sitio con las ligas simbólicas y un index.php de ejemplo.
#   7. php nframework/test.php --host=...: instala la base de datos y muestra lo que falte.
#   Al final imprime un bloque server de nginx para el sitio.
#
# Opciones:
#   --mode=<single|multi> modo de instalación (si se omite se pregunta; con --yes: single)
#   --docroot=<dir>      multisitio: document root del vhost (se crea si no existe)
#   --docs               multisitio: liga también /docs (documentación ejecutable; no en producción)
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
MODE=""
DOCROOT=""
DOCS=0
DB=""
MONGO="mongodb://127.0.0.1"
PHPV=""
APT=1
NODEV=""
YES=0

for arg in "$@"; do
    case "$arg" in
        --host=*) HOST="${arg#*=}" ;;
        --mode=*) MODE="${arg#*=}" ;;
        --docroot=*) DOCROOT="${arg#*=}" ;;
        --docs) DOCS=1 ;;
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

# --- Modo de instalación --------------------------------------------------------------------------------
if [ -z "$MODE" ]; then
    if [ "$YES" = 1 ] || ! [ -t 0 ]; then
        MODE="single"
    else
        echo -e "${B}Modo de instalación${N}"
        echo "  1) Sitio único: $ROOT es el document root del sitio."
        echo "  2) Multisitio: el framework queda en $ROOT y cada vhost liga admin/, nframework/ y router.php."
        read -r -p "Elija [1/2] (1): " r
        case "$r" in 2|m|multi) MODE="multi" ;; *) MODE="single" ;; esac
    fi
fi
case "$MODE" in
    single|unico|único) MODE="single" ;;
    multi|multisitio|multisite) MODE="multi" ;;
    *) error "--mode debe ser single o multi"; exit 1 ;;
esac

if [ "$MODE" = multi ]; then
    if [ -z "$DOCROOT" ] && [ "$YES" = 0 ] && [ -t 0 ]; then
        read -r -p "Document root del sitio (p. ej. /var/www/${HOST:-www.ejemplo.com}): " DOCROOT
    fi
    if [ -z "$DOCROOT" ]; then
        error "El modo multisitio requiere --docroot=<carpeta del vhost>"; exit 1
    fi
    if [ -z "$HOST" ]; then
        error "El modo multisitio requiere --host=<dominio> (bloque de config.php y base de datos del sitio)"; exit 1
    fi
    DOCROOT="$(realpath -m "$DOCROOT")"
    case "$DOCROOT/" in
        "$ROOT/"*) error "El document root no puede estar dentro del framework ($ROOT)"; exit 1 ;;
    esac
    case "$ROOT/" in
        "$DOCROOT/"*) error "El framework ($ROOT) no puede estar dentro del document root $DOCROOT"; exit 1 ;;
    esac
else
    if [ -n "$DOCROOT" ] && [ "$(realpath -m "$DOCROOT")" != "$ROOT" ]; then
        error "En sitio único el repositorio es el document root. Clónelo ahí y ejecute el instalador desde esa carpeta:"
        echo "    git clone https://github.com/nlared/nframework.git $DOCROOT && cd $DOCROOT && ./install.sh --mode=single --host=${HOST:-<dominio>}"
        exit 1
    fi
    DOCROOT="$ROOT"
fi
ok "Modo: $([ "$MODE" = multi ] && echo "multisitio (framework en $ROOT, sitio en $DOCROOT)" || echo "sitio único ($ROOT)")"

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
DB="${DB:-$(echo "${HOST%%:*}" | tr -c 'A-Za-z0-9_\n' '_')}"
SCHEME="https"
[[ "$HOST" == *.local || "$HOST" == localhost* ]] && SCHEME="http"
# Escribe el bloque `case` de --host: crea config.php o lo inserta antes del `default:` de uno existente.
# Los valores pasan por var_export: comillas o barras en --mongo no rompen el archivo.
config_bloque() {
    php -r '
        [$accion, $host, $db, $mongo, $url, $file] = array_slice($argv, 1);
        $e = fn($v) => var_export($v, true);
        $cookie = explode(":", $host)[0];
        $bloque = "    case {$e($host)}:\n        \$config = [\n"
            . "            \"mongo_connection_string\" => {$e($mongo)},\n"
            . "            \"sitedb\" => {$e($db)},\n"
            . "            \"cookie_domain\" => {$e($cookie)},\n"
            . "            \"url\" => {$e($url)},\n"
            . "        ];\n        break;\n\n";
        if ($accion === "crear") {
            exit(file_put_contents($file, "<?php\n// Generado por install.sh. Todas las opciones: includes/config.example.php\n"
                . "switch (\$_SERVER[\"HTTP_HOST\"] ?? \"localhost\") {\n" . $bloque
                . "    default:\n        http_response_code(400);\n        exit(\"Dominio no configurado.\");\n}\n") ? 0 : 1);
        }
        $php = file_get_contents($file);
        if (preg_match_all("/^[ \t]*default[ \t]*:/m", $php, $mm, PREG_OFFSET_CAPTURE) !== 1) {
            exit(2);   // sin un único default: no se adivina dónde insertar
        }
        $pos = $mm[0][0][1];
        copy($file, $file . ".bak");
        exit(file_put_contents($file, substr($php, 0, $pos) . "    // Agregado por install.sh\n" . $bloque . substr($php, $pos)) ? 0 : 1);
    ' "$1" "$HOST" "$DB" "$MONGO" "$SCHEME://$HOST" "$INCLUDES/config.php"
}
if [ -f "$INCLUDES/config.php" ]; then
    if [ -z "$HOST" ] || grep -qE "case +['\"]$HOST['\"]" "$INCLUDES/config.php"; then
        ok "config.php existe${HOST:+ y tiene el bloque de $HOST}"
    elif confirmar "config.php no tiene un bloque para $HOST. Agregarlo (base $DB, $MONGO)?"; then
        if config_bloque agregar; then
            ok "Bloque de $HOST agregado a config.php (copia previa en config.php.bak)"
        else
            aviso "No se pudo insertar el bloque (config.php sin un único 'default:'): agréguelo a mano (vea includes/config.example.php)."
        fi
    else
        aviso "Agregue a config.php un bloque para $HOST (vea includes/config.example.php)."
    fi
elif [ -z "$HOST" ]; then
    cp "$INCLUDES/config.example.php" "$INCLUDES/config.php"
    aviso "Se copió config.example.php a config.php: edite el bloque de su dominio."
else
    config_bloque crear
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

# --- 6. Document root del sitio (multisitio) ------------------------------------------------------------
# liga <ruta en el sitio> <destino en el framework>
liga() {
    local link="$DOCROOT/$1" dest="$ROOT/$2"
    if [ -L "$link" ]; then
        if [ "$(readlink -f "$link")" = "$(readlink -f "$dest")" ]; then
            ok "/$1 → $dest"; return
        fi
        if ! confirmar "/$1 apunta a $(readlink "$link"). Reemplazarla por $dest?"; then
            aviso "Se dejó /$1 como está"; return
        fi
        $DOCSUDO rm "$link"
    elif [ -e "$link" ]; then
        aviso "$link ya existe y no es una liga: no se tocó (bórrelo o renómbrelo si debe venir del framework)"; return
    fi
    $DOCSUDO mkdir -p "$(dirname "$link")"
    $DOCSUDO ln -s "$dest" "$link"
    ok "/$1 → $dest"
}
if [ "$MODE" = multi ]; then
    paso "Document root del sitio ($DOCROOT)"
    DOCSUDO=""
    PADRE="$DOCROOT"
    while [ ! -e "$PADRE" ]; do PADRE="$(dirname "$PADRE")"; done
    [ -w "$PADRE" ] || DOCSUDO="$SUDO"
    if [ ! -d "$DOCROOT" ]; then
        $DOCSUDO mkdir -p "$DOCROOT"
        ok "Creado $DOCROOT"
    fi
    [ -w "$DOCROOT" ] || DOCSUDO="$SUDO"
    liga admin admin
    liga nframework nframework
    liga router.php router.php
    if [ "$DOCS" = 1 ]; then liga docs docs; liga css/index.css css/index.css; fi
    # Fondos de las pantallas de cuenta (login, registro...): ligas a archivos sueltos, el sitio puede reemplazarlos.
    liga img/bg-1.jpg img/bg-1.jpg
    liga img/bg-2.jpg img/bg-2.jpg
    if [ ! -e "$DOCROOT/index.php" ] && [ ! -e "$DOCROOT/index.html" ]; then
        $DOCSUDO tee "$DOCROOT/index.php" >/dev/null <<'PHP'
<?php
// Página de inicio creada por install.sh: reemplácela por la del sitio.
require 'include.php';

$nframework->usecommon = true;
$nframework->metas['title'] = 'Inicio';
?>
<div class="container">
    <h1>Hola, <?= htmlspecialchars($user->name ?? $user->username) ?></h1>
    <p>Sitio construido sobre nframework. <a href="/admin/">Panel de administración</a></p>
</div>
PHP
        ok "Creado $DOCROOT/index.php de ejemplo"
    fi
    if [ -n "$DOCSUDO" ]; then
        aviso "$DOCROOT se escribió con sudo: ajuste el dueño (p. ej. sudo chown -R \$USER:www-data $DOCROOT)."
    fi
fi

# --- 7. Base de datos y diagnóstico ---------------------------------------------------------------------
paso "Base de datos y diagnóstico (nframework/test.php)"
if [ -z "$HOST" ]; then
    aviso "Sin --host no se instala la base de datos. Ejecute: php nframework/test.php --host=<dominio>"
else
    php "$ROOT/nframework/test.php" --host="$HOST" --docroot="$DOCROOT" || true
fi

# --- Servidor web ---------------------------------------------------------------------------------------
SOCK="/run/php/php${PHPV:-8.3}-fpm.sock"
echo -e "\n${B}Siguiente:${N} configure nginx (y MongoDB si aún no está instalado). Bloque sugerido para ${HOST:-el sitio}:\n"
cat <<NGINX
server {
    listen 80;
    server_name ${HOST:-www.ejemplo.com};
    root $DOCROOT;
    index index.php index.html;

    # nframework: nunca servir código, configuración, logs ni archivos ocultos (docs/security.php).
    location ^~ /.well-known/ { try_files \$uri /router.php\$is_args\$args; }
    location ~ /\.                { return 404; }
    location ~ ^/includes(/|\$)   { return 404; }
    location ~ /vendor/           { return 404; }
    location ~* (\.log|\.sh|/composer\.[^/]*|/phpunit\.xml[^/]*)\$ { return 404; }
NGINX
[ "$MODE" = single ] && echo '    location ~ ^/(tests|src|stubs)(/|$) { return 404; }'
cat <<NGINX

    location / {
        try_files \$uri \$uri/ /router.php\$is_args\$args;
    }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$SOCK;
    }
}
NGINX
echo -e "\nGuárdelo en /etc/nginx/sites-available/${HOST:-sitio}, lígelo en sites-enabled y ejecute: sudo nginx -t && sudo systemctl reload nginx"
if [ "$MODE" = multi ]; then echo "Para otro sitio con el mismo framework: $0 --mode=multi --host=<dominio> --docroot=<carpeta>"; fi
