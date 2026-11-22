<?php

/**
 * Asigna $value en $arr siguiendo una ruta con separador ('direccion.calle' → $arr['direccion']['calle']),
 * creando los niveles que falten.
 *
 * @param array<mixed> $arr Arreglo que se modifica (por referencia).
 * @param string $path Ruta de llaves separadas por $separator.
 * @param mixed $value Valor a asignar.
 * @param string $separator Separador de la ruta.
 * @return void
 */
function assignArrayByPath(&$arr, $path, $value, $separator = '.')
{
    $keys = explode($separator, $path);
    foreach ($keys as $key) {
        $arr = &$arr[$key];
    }
    $arr = $value;
}
/**
 * Quita los DIRECTORY_SEPARATOR finales de una ruta.
 *
 * @param string $path
 * @return string
 */
function remove_trailing_separator($path)
{
    return rtrim($path, DIRECTORY_SEPARATOR);
}
/**
 * Deja en un nombre de archivo solo letras ASCII, dígitos, punto, guion y guion bajo.
 *
 * @param string $filename
 * @return string
 */
function clean_filename($filename)
{
    return preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
}
/**
 * Versión de /main.js para la URL (?v=): cambia si cambia la plantilla o la llave pública de notificaciones,
 * que son lo único que define su contenido.
 *
 * @global class_config $config
 */
function nfMainJsVersion(): string
{
    global $config;
    $template = dirname(__DIR__) . '/nframework/templates/main.js';
    return substr(md5((string) @filemtime($template) . '|' . (string) ($config['notifications']['publicKey'] ?? '')), 0, 10);
}

/**
 * true si la URL apunta a algo que nunca debe servirse por web: archivos o carpetas ocultos (.git, .env;
 * se permite /.well-known/), /includes/ (núcleo y config.php), cualquier vendor/, *.log, *.sh, composer.* y
 * phpunit.xml*. Con $frameworkRoot (el document root es el repositorio del framework) también tests/, src/
 * y stubs/ de la raíz; en un sitio esas rutas pueden ser páginas propias.
 * nginx entrega los archivos que existen sin pasar por PHP: el bloqueo real va en su configuración
 * (ver docs/security.php); esto cubre lo que sí llega a PHP (router.php, .php dentro de includes/).
 *
 * @param string $uri URL o ruta de la petición (se decodifica).
 * @param bool $frameworkRoot true si el document root es el repositorio del framework.
 * @return bool
 */
function nfIsBlockedPath(string $uri, bool $frameworkRoot = false): bool
{
    $path = str_replace('\\', '/', rawurldecode((string) parse_url($uri, PHP_URL_PATH)));
    $path = '/' . ltrim($path, '/');
    if (preg_match('#/\.(?!well-known(/|$))#i', $path)
        || preg_match('#^/includes(/|$)#i', $path)
        || preg_match('#/vendor(/|$)#i', $path)
        || ($frameworkRoot && preg_match('#^/(tests|src|stubs)(/|$)#i', $path))) {
        return true;
    }
    return (bool) preg_match('/\.(log|sh)$|^composer\.|^phpunit\.xml/i', basename($path));
}

/**
 * Valida el nombre de un archivo subido por /nframework/uploadfile.php.
 * Rechaza archivos ocultos (.htaccess), rutas (../), caracteres especiales y extensiones protegidas
 * (ejecutables o de configuración), revisando todas las extensiones (x.php.jpg) sin distinguir mayúsculas.
 * $allowext: extensiones protegidas que el control acepta explícitamente (opción 'allowext').
 * Las que ejecuta PHP (php, phtml, phar...) solo se aceptan si $dir está fuera de $docroot.
 *
 * @param string $filename Nombre del archivo (sin ruta).
 * @param string[] $allowext Extensiones protegidas aceptadas explícitamente, con o sin punto.
 * @param string $dir Carpeta destino.
 * @param string|null $docroot Document root; null = $_SERVER['DOCUMENT_ROOT'].
 * @return bool
 */
function nfValidUploadFilename(string $filename, array $allowext = [], string $dir = '', ?string $docroot = null): bool
{
    if ($filename === '' || $filename[0] === '.' || str_contains($filename, '..') || strlen($filename) > 255) {
        return false;
    }
    if (strpbrk($filename, "?[]/\\=<>:;'\"\$#*|~`!{}%+\0") !== false) {
        return false;
    }
    $protected = '/^(php\d*|phtml|pht|phar|phps|pgif|inc|shtml|htaccess|htpasswd|ini|asp|aspx|ascx|jsp|jspx|cgi|pl|py|rb|exe|bat|cmd|com|sh|bash|ps1|vbs|scr|msi|dll|so)$/i';
    $phpexec = '/^(php\d*|phtml|pht|phar|phps|pgif|inc|shtml)$/i';
    $allowext = array_map(fn($ext) => strtolower(ltrim((string) $ext, '.')), $allowext);
    $docroot ??= $_SERVER['DOCUMENT_ROOT'] ?? '';
    foreach (array_slice(explode('.', $filename), 1) as $extension) {
        if (!preg_match($protected, $extension)) {
            continue;
        }
        if (!in_array(strtolower($extension), $allowext, true)) {
            return false;
        }
        // Un .php dentro del document root se podría ejecutar con solo abrir su URL.
        if (preg_match($phpexec, $extension) && nfPathInside($dir, $docroot)) {
            return false;
        }
    }
    return true;
}

/**
 * true si $path está dentro de $base (o si no se puede determinar: ante la duda, se asume que sí).
 *
 * @param string $path Ruta a revisar (puede no existir).
 * @param string $base Carpeta base.
 * @return bool
 */
function nfPathInside(string $path, string $base): bool
{
    if ($base === '') {
        return true;
    }
    $realbase = realpath($base);
    if ($realbase === false) {
        return true;
    }
    // La carpeta puede no existir aún (create_dir): se resuelve el ancestro existente más cercano.
    $realpath = false;
    for ($probe = $path; $probe !== '' && $realpath === false; $probe = $parent) {
        $realpath = realpath($probe);
        $parent = dirname($probe);
        if ($parent === $probe) {
            break;
        }
    }
    if ($realpath === false) {
        return true;
    }
    $realbase = rtrim($realbase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return str_starts_with(rtrim($realpath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $realbase);
}
/**
 * Diferencia recursiva: elementos de $array1 que no están (o difieren con !==) en $array2, conservando la
 * estructura.
 *
 * @param array<mixed> $array1
 * @param array<mixed> $array2
 * @return array<mixed>
 */
function array_diff_recursive($array1, $array2)
{
    $result = [];
    foreach ($array1 as $key => $value) {
        if (is_array($value)) {
            if (!isset($array2[$key]) || !is_array($array2[$key])) {
                $result[$key] = $value;
            } else {
                $recursiveDiff = array_diff_recursive($value, $array2[$key]);
                if (!empty($recursiveDiff)) {
                    $result[$key] = $recursiveDiff;
                }
            }
        } elseif (!array_key_exists($key, $array2) || $array2[$key] !== $value) {
            $result[$key] = $value;
        }
    }

    return $result;
}
/**
 * Elimina una llave anidada indicada con '\' como separador ('a\b\c'); no hace nada si la ruta no existe.
 *
 * @param array<mixed> $array Arreglo que se modifica (por referencia).
 * @param string $path Ruta separada por '\'.
 * @return void
 */
function unsetNestedKey(&$array, $path)
{
    $keys = explode('\\', $path);
    $temp = &$array;

    foreach ($keys as $key) {
        if (!isset($temp[$key])) {
            return; // Stop if the path doesn't exist
        }
        $parent = &$temp;
        $temp = &$temp[$key]; // Traverse deeper
    }
    unset($parent[$key]); // Unset last key
}
/**
 * Registra en $_SESSION['_gc_tracker'] una variable de sesión para limpiarla después.
 *
 * @param string $key Llave de la variable en $_SESSION.
 * @param int $time Marca de tiempo Unix asociada.
 * @return void
 */
function addVarToGarbage($key, $time)
{
    $_SESSION['_gc_tracker'][$key] = $time;
}
/**
 * Quita una variable del registro $_SESSION['_gc_tracker'].
 *
 * @param string $key
 * @return void
 */
function removeVarFromGarbage($key)
{
    unset($_SESSION['_gc_tracker'][$key]);
}

/**
 * $array[$key] si existe y no es null; null en otro caso.
 *
 * @param array<mixed>|ArrayAccess<mixed, mixed> $array
 * @param int|string $key
 * @return mixed
 */
function ifset($array, $key): mixed
{
    return isset($array[$key]) ? $array[$key] : null;
}

/**
 * Limpia el árbol de un menú (formato de Admin → Menús / jstree) y deja solo lo que usa buildMetroMenu():
 * [['text' => ..., 'data' => ['link', 'icon', 'onfunction', 'target'], 'children' => [...]], ...].
 * Descarta nodos sin texto y lo que exceda $maxDepth niveles. Lanza InvalidArgumentException si $nodes no
 * es una lista.
 *
 * @param mixed $nodes Lista de nodos tal como llega (JSON decodificado).
 * @param int $maxDepth Niveles máximos.
 * @param int $depth Nivel actual (uso interno de la recursión).
 * @return list<array{text: string, data: array{link: string, icon: string, onfunction: string, target: string}, children?: list<array<string, mixed>>}>
 * @throws InvalidArgumentException Si $nodes no es una lista.
 */
function nfNormalizeMenuNodes($nodes, int $maxDepth = 4, int $depth = 1): array
{
    if (!is_array($nodes) || !array_is_list($nodes)) {
        throw new InvalidArgumentException('El menú debe ser una lista de elementos.');
    }
    $clean = [];
    foreach ($nodes as $node) {
        if (!is_array($node)) {
            continue;
        }
        $text = trim((string) ($node['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $item = [
            'text' => mb_substr($text, 0, 100),
            'data' => [
                'link' => trim((string) ($data['link'] ?? '')) ?: '#',
                // Solo clases CSS (mif-home, fg-red...): el ícono se imprime dentro de class="".
                'icon' => trim(preg_replace('/[^A-Za-z0-9 _-]/', '', (string) ($data['icon'] ?? ''))),
                'onfunction' => trim((string) ($data['onfunction'] ?? '')),
                'target' => ($data['target'] ?? '') === '_blank' ? '_blank' : '',
            ],
        ];
        $children = $node['children'] ?? [];
        if ($depth < $maxDepth && is_array($children) && $children !== []) {
            $item['children'] = nfNormalizeMenuNodes(array_values($children), $maxDepth, $depth + 1);
        }
        $clean[] = $item;
    }
    return $clean;
}

/**
 * Cuenta los elementos de un menú (todos los niveles).
 *
 * @param list<array<string, mixed>> $nodes Nodos ya normalizados.
 * @return int
 */
function nfCountMenuNodes(array $nodes): int
{
    $count = 0;
    foreach ($nodes as $node) {
        $count += 1 + (is_array($node['children'] ?? null) ? nfCountMenuNodes($node['children']) : 0);
    }
    return $count;
}

/**
 * Condición de visibilidad de un elemento de menú (campo OnFunction de Admin → Menús): código PHP que se
 * evalúa con $user, $config, $m y $nframework disponibles. Acepta una expresión ($user->in('admins')) o
 * código con return. Vacía = visible; un error = oculto.
 *
 * @param string $code Expresión PHP o código con return.
 * @return bool
 */
function nfMenuCondition(string $code): bool
{
    $code = trim($code);
    if ($code === '') {
        return true;
    }
    if (!preg_match('/\breturn\b/', $code)) {
        $code = 'return (' . rtrim($code, "; \t\n\r") . ');';
    }
    try {
        return (bool) eval('global $user, $config, $m, $nframework; ' . $code);
    } catch (Throwable $e) {
        error_log('nfMenuCondition: ' . $e->getMessage());
        return false;
    }
}

/**
 * HTML de un menú de Metro UI a partir de nodos normalizados (ver nfNormalizeMenuNodes()); los hijos se vuelven
 * d-menu desplegables y se omiten los nodos cuya condición (onfunction) es falsa.
 *
 * @param list<array<string, mixed>> $nodes
 * @param string $menuClass Clase del <ul> raíz (h-menu, v-menu, d-menu...); vacío = <ul> sin atributos.
 * @param string $data_role Valor de data-role del <ul> raíz.
 * @return string HTML
 */
function buildMetroMenu(array $nodes, string $menuClass = '', string $data_role = ''): string
{
    $html = $menuClass ? "<ul class=\"$menuClass\" data-role=\"$data_role\">\n" : "<ul>\n";

    foreach ($nodes as $node) {
        // Un nodo mal formado (menú editado a mano o guardado a medias) se omite en lugar de romper la página.
        if (!is_array($node)) {
            continue;
        }
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $text = htmlspecialchars((string) ($node['text'] ?? ''));
        $link = htmlspecialchars((string) ($data['link'] ?? '#'));
        $icon = trim(preg_replace('/[^A-Za-z0-9 _-]/', '', (string) ($data['icon'] ?? '')));
        $label = ($icon !== '' ? '<span class="' . $icon . ' icon"></span> ' : '') . $text;
        $target = ($data['target'] ?? '') === '_blank' ? ' target="_blank" rel="noopener"' : '';

        if (!nfMenuCondition((string) ($data['onfunction'] ?? ''))) {
            continue;
        }

        if (!empty($node['children']) && is_array($node['children'])) {
            $html .= "<li><a href=\"#\" class=\"dropdown-toggle\">$label</a>" . buildMetroMenu($node['children'], "d-menu", "dropdown");
        } else {
            $html .= "<li><a href=\"$link\"$target>$label</a>";
        }

        $html .= "</li>\n";
    }

    $html .= "</ul>\n";
    return $html;
}


/**
 * HTML del menú $menuName de Admin → Menús (colección menus, desde la caché local). Devuelve '' si no
 * existe o su JSON es inválido (y lo anota en el log).
 *
 * @global MongoDB\Client $m
 * @global class_config $config
 * @param string $menuName Nombre del menú (p. ej. '_nav').
 * @param string $menuClass Clase del <ul> raíz.
 * @return string HTML
 */
function nfMetroMenu($menuName, $menuClass = 'h-menu'): string
{
    global $m, $config;
    // El JSON del menú se guarda en la caché local; se vacía al guardar desde Admin → Menús.
    $json = nfCacheRemember('menu:' . $menuName, nfCacheTtl(), function () use ($m, $config, $menuName) {
        $menu = $m->{$config['sitedb']}->menus->findOne(['name' => $menuName], ['projection' => ['code' => 1]]);
        return $menu ? (string) $menu->code : null;
    });
    $nodes = $json !== null && $json !== '' ? json_decode($json, true) : null;
    if (!is_array($nodes)) {
        // Menú inexistente, vacío o con JSON inválido: la página se muestra sin él.
        if ($json !== null && $json !== '') {
            $why = json_last_error() !== JSON_ERROR_NONE ? json_last_error_msg() : 'no es una lista de elementos';
            error_log('nfMetroMenu: el menú ' . $menuName . ' no tiene un JSON válido (' . $why . '); ábralo en Admin → Menús y guárdelo');
        }
        return '';
    }
    return buildMetroMenu($nodes, $menuClass);
}

/**
 * Documento de Admin → Páginas por path (incluye fragmentos como _header, _footer, _home), como
 * arreglo y desde la caché local. null si no existe.
 *
 * @global MongoDB\Client $m
 * @global class_config $config
 * @param string $path Path de la página ('/contacto', '_header').
 * @return array<string, mixed>|null
 */
function nfPage(string $path): ?array
{
    global $m, $config;
    return nfCacheRemember('page:' . $path, nfCacheTtl(), function () use ($m, $config, $path) {
        $doc = $m->{$config['sitedb']}->pages->findOne(['path' => $path]);
        return $doc ? mongoToArray($doc) : null;
    });
}

/**
 * Reemplaza en $html las llamadas {{ funcion(arg1, 'arg2') }} por el resultado de la función PHP del mismo
 * nombre (los argumentos llegan como texto sin comillas). Si la función no existe deja [undefined function:
 * nombre]. Solo para contenido de confianza (páginas del CMS): puede llamar cualquier función definida.
 *
 * @param string $html
 * @return string
 */
function renderEmbeddedFunctions(string $html): string
{
    return preg_replace_callback('/{{\s*(\w+)\((.*?)\)\s*}}/', function ($matches) {
        $funcName = $matches[1];
        $args = array_map('trim', explode(',', $matches[2]));

        // Remove quotes from string arguments
        $args = array_map(function ($arg) {
            return trim($arg, "'\" ");
        }, $args);

        // Check if function exists and call it
        if (function_exists($funcName)) {
            return call_user_func_array($funcName, $args);
        }

        return "[undefined function: $funcName]";
    }, $html);
}

/**
 * Convierte un valor BSON a un escalar legible: UTCDateTime → fecha ATOM, ObjectId/Decimal128 → texto,
 * Binary → base64, Regex → patrón, Javascript → código. Otros valores se devuelven igual.
 *
 * @param mixed $value
 * @return mixed
 */
function normalizeBsonValue($value): mixed
{
    switch (true) {
        case $value instanceof MongoDB\BSON\UTCDateTime:
            return $value->toDateTime()->format(DATE_ATOM);
        case $value instanceof MongoDB\BSON\ObjectId:
            return (string) $value;
        case $value instanceof MongoDB\BSON\Binary:
            return base64_encode($value->getData());
        case $value instanceof MongoDB\BSON\Regex:
            return $value->getPattern();
        case $value instanceof MongoDB\BSON\Decimal128:
            return (string) $value;
        case $value instanceof MongoDB\BSON\Javascript:
            return $value->getCode();
        case $value instanceof MongoDB\BSON\Timestamp:
            return "Timestamp({$value->getTimestamp()}, {$value->getIncrement()})";
        case $value instanceof MongoDB\BSON\MinKey:
            return 'MinKey';
        case $value instanceof MongoDB\BSON\MaxKey:
            return 'MaxKey';
        default:
            return $value;
    }
}

/**
 * Aplana un documento a llaves con punto (['a' => ['b' => 1]] → ['a.b' => 1]), normalizando los valores BSON
 * con normalizeBsonValue().
 *
 * @param array<mixed>|object $document
 * @param string $prefix Prefijo de las llaves (uso interno de la recursión).
 * @return array<string, mixed>
 */
function flattenDocument($document, $prefix = '')
{
    $flat = [];
    foreach ($document as $key => $value) {
        $fullKey = $prefix === '' ? $key : "{$prefix}.{$key}";

        if (is_array($value) || is_object($value)) {
            $flat += flattenDocument((array) $value, $fullKey);
        } else {
            $flat[$fullKey] = normalizeBsonValue($value);
        }
    }
    return $flat;
}


/**
 * Evalúa en PHP una consulta estilo MongoDB contra un documento ya cargado (reglas de seguridad, filtros
 * en memoria). Operadores lógicos: $and, $or, $nor. De campo (ver matchesField()): $gt, $lt, $gte, $lte,
 * $eq, $ne, $in, $nin, $regex + $options, $exists, $size y $type (solo tipos de gettype(): string,
 * integer, array, object, boolean, double).
 *
 * @param array<mixed> $doc Documento (notación con punto en las llaves de $query).
 * @param array<string, mixed> $query
 * @return bool true si el documento cumple la consulta.
 */
function matchesQuery($doc, $query)
{
    // Handle logical operators first
    if (isset($query['$and'])) {
        foreach ($query['$and'] as $subQuery) {
            if (!matchesQuery($doc, $subQuery)) {
                return false;
            }
        }
        unset($query['$and']);
    }

    if (isset($query['$or'])) {
        $orMatched = false;
        foreach ($query['$or'] as $subQuery) {
            if (matchesQuery($doc, $subQuery)) {
                $orMatched = true;
                break;
            }
        }
        if (!$orMatched)
            return false;
        unset($query['$or']);
    }

    if (isset($query['$nor'])) {
        foreach ($query['$nor'] as $subQuery) {
            if (matchesQuery($doc, $subQuery)) {
                return false;
            }
        }
        unset($query['$nor']);
    }

    // Handle field-level queries
    foreach ($query as $field => $condition) {
        if (!matchesField($doc, $field, $condition)) {
            return false;
        }
    }

    return true;
}

/**
 * true si el valor de $field (notación con punto) en $doc cumple $condition: valor directo (===) o arreglo de
 * operadores ($gt, $lt, $gte, $lte, $eq, $ne, $in, $nin, $regex + $options, $exists, $size, $type). Un operador
 * desconocido no coincide. Usada por matchesQuery().
 *
 * @param array<mixed> $doc
 * @param string $field
 * @param mixed $condition
 * @return bool
 */
function matchesField($doc, $field, $condition)
{
    // Get field value (supports dot notation)
    $fieldValue = getNestedValue($doc, $field);

    // Handle direct value comparison
    if (!is_array($condition)) {
        return $fieldValue === $condition;
    }

    // Handle operators
    foreach ($condition as $op => $value) {
        switch ($op) {
            case '$gt':
                if (!($fieldValue > $value))
                    return false;
                break;
            case '$lt':
                if (!($fieldValue < $value))
                    return false;
                break;
            case '$gte':
                if (!($fieldValue >= $value))
                    return false;
                break;
            case '$lte':
                if (!($fieldValue <= $value))
                    return false;
                break;
            case '$eq':
                if ($fieldValue !== $value)
                    return false;
                break;
            case '$ne':
                if ($fieldValue === $value)
                    return false;
                break;
            case '$in':
                if (!in_array($fieldValue, $value, true))
                    return false;
                break;
            case '$nin':
                if (in_array($fieldValue, $value, true))
                    return false;
                break;
            case '$regex':
                $pattern = '/' . str_replace('/', '\/', $value) . '/';
                if (isset($condition['$options'])) {
                    $pattern .= $condition['$options'];
                }
                if (!preg_match($pattern, (string) $fieldValue))
                    return false;
                break;
            case '$exists':
                $exists = hasNestedKey($doc, $field);
                if ($value && !$exists)
                    return false;
                if (!$value && $exists)
                    return false;
                break;
            case '$size':
                if (!is_array($fieldValue) || count($fieldValue) !== $value)
                    return false;
                break;
            case '$type':
                if (gettype($fieldValue) !== $value)
                    return false;
                break;
            case '$options':
                // Skip options, handled by $regex
                break;
            default:
                // Unknown operator
                return false;
        }
    }

    return true;
}

/**
 * Valor de $array en la ruta con punto $key ('direccion.calle'), o null si algún nivel no existe.
 *
 * @param array<mixed> $array
 * @param string $key Llave o ruta con punto.
 * @return mixed
 */
function getNestedValue($array, $key)
{
    if (strpos($key, '.') === false) {
        return $array[$key] ?? null;
    }

    $keys = explode('.', $key);
    $current = $array;

    foreach ($keys as $k) {
        if (!is_array($current) || !isset($current[$k])) {
            return null;
        }
        $current = $current[$k];
    }

    return $current;
}

/**
 * true si la ruta con punto $key existe en $array y su valor no es null (isset).
 *
 * @param array<mixed> $array
 * @param string $key Llave o ruta con punto.
 * @return bool
 */
function hasNestedKey($array, $key)
{
    if (strpos($key, '.') === false) {
        return isset($array[$key]);
    }

    $keys = explode('.', $key);
    $current = $array;

    foreach ($keys as $k) {
        if (!is_array($current) || !isset($current[$k])) {
            return false;
        }
        $current = $current[$k];
    }

    return true;
}


/**
 * Simplifica una consulta Mongo cuyo $and o $or tiene un solo elemento, devolviendo ese elemento.
 *
 * @param array<string, mixed> $query
 * @return array<string, mixed>
 */
function fixSingleQuery($query)
{
    if (isset($query['$and']) && count($query['$and']) == 1) {
        return $query['$and'][0];
    } elseif (isset($query['$or']) && count($query['$or']) == 1) {
        return $query['$or'][0];
    }
    return $query;
}

/*
$rules[]=fixSingleQuery(json_decode('{"$and":[{"path":{"$regex":"wp-includes","$options":"i"}}]}',true));
$rules[]=fixSingleQuery(json_decode('{"$and":[{"host":{"$regex":"localhost","$options":"i"}}]}',true));
$rules[]=fixSingleQuery(json_decode('{"$and":[{"host":"localhost"}]}',true));

$query=fixSingleQuery(['$or'=>$rules]);*/




/**
 * Registra un intento para $key (p.ej. 'login:IP'). Devuelve false si se superó $limit
 * dentro de la ventana de $blockTime segundos; en ese caso bloquea durante $blockTime.
 *
 * @global MongoDB\Client $m
 * @global class_config $config
 * @param string $key Llave del contador, p. ej. 'login:' . $ip.
 * @param int $limit Intentos permitidos dentro de la ventana.
 * @param int $blockTime Ventana y duración del bloqueo, en segundos.
 * @return bool true = se permite el intento; false = bloqueado.
 */
function nflogAttempt($key, $limit = 5, $blockTime = 300)
{
    global $m, $config;
    $collection = $m->{$config['sitedb']}->nf_attempts;
    $now = time();
    $record = $collection->findOne(['ip' => $key]);
    if ($record && isset($record['blocked_until']) && $record['blocked_until']->toDateTime()->getTimestamp() > $now) {
        return false; // Bloqueado
    }

    $lastAttempt = ($record && isset($record['last_attempt'])) ? $record['last_attempt']->toDateTime()->getTimestamp() : 0;
    // Ventana expirada o bloqueo terminado: se reinicia el contador.
    $count = ($lastAttempt < $now - $blockTime || isset($record['blocked_until'])) ? 1 : $record['count'] + 1;

    $set = ['count' => $count, 'last_attempt' => new MongoDB\BSON\UTCDateTime($now * 1000)];
    $update = ['$set' => $set, '$unset' => ['blocked_until' => '']];
    if ($count > $limit) {
        $update = ['$set' => $set + ['blocked_until' => new MongoDB\BSON\UTCDateTime(($now + $blockTime) * 1000)]];
    }
    $collection->updateOne(['ip' => $key], $update, ['upsert' => true]);

    return $count <= $limit;
}

/**
 * Borra el contador de intentos de $key (p. ej. tras un inicio de sesión correcto).
 *
 * @global MongoDB\Client $m
 * @global class_config $config
 * @param string $key
 * @return void
 */
function nflogReset($key): void
{
    global $m, $config;
    $m->{$config['sitedb']}->nf_attempts->deleteOne(['ip' => $key]);
}

/**
 * Cifra un id de sesión con AES-256-GCM y lo devuelve en base64url ('g' . IV . TAG . datos). Lo descifra
 * decryptSessionId().
 *
 * @param string $sessionId
 * @param string $key Llave (se deriva con sha256).
 * @return string
 */
function encryptSessionId($sessionId, $key)
{
    // AES-256-GCM (autenticado): 'g' . IV(12) . TAG(16) . datos
    $iv = random_bytes(12);
    $tag = '';
    $encrypted = openssl_encrypt((string) $sessionId, 'aes-256-gcm', hash('sha256', (string) $key, true), OPENSSL_RAW_DATA, $iv, $tag);
    return rtrim(strtr(base64_encode('g' . $iv . $tag . $encrypted), '+/', '-_'), '=');
}

/**
 * Descifra lo que produjo encryptSessionId(); también acepta el formato anterior AES-256-CBC.
 *
 * @param mixed $encryptedData Texto base64url.
 * @param string $key
 * @return string|false false si el dato no es válido o no se puede descifrar.
 */
function decryptSessionId($encryptedData, $key)
{
    if (!is_string($encryptedData) || $encryptedData === '') {
        return false;
    }
    $data = base64_decode(strtr($encryptedData, '-_', '+/'), true);
    if ($data === false) {
        return false;
    }
    if (strlen($data) > 29 && $data[0] === 'g') {
        return openssl_decrypt(substr($data, 29), 'aes-256-gcm', hash('sha256', (string) $key, true), OPENSSL_RAW_DATA, substr($data, 1, 12), substr($data, 13, 16));
    }
    // Formato anterior (AES-256-CBC) por compatibilidad
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}

/**
 * Carpeta de la caché local del sitio, o null si no se puede usar.
 * Por defecto {tmp}/nframework_cache_{uid}/{sitedb}: una por usuario del sistema (FPM y CLI no se
 * mezclan) con permisos 0700, porque los archivos se deserializan. $config['cache_dir'] la cambia.
 *
 * @global class_config $config
 * @param string $sub Subcarpeta opcional.
 * @return string|null
 */
function nfCacheDir(string $sub = ''): ?string
{
    global $config;
    static $dirs = [];
    $site = (string) ($config['sitedb'] ?? 'default');
    $base = !empty($config['cache_dir'])
        ? rtrim((string) $config['cache_dir'], '/')
        : sys_get_temp_dir() . '/nframework_cache_' . (function_exists('posix_geteuid') ? posix_geteuid() : 'x');
    $dir = $base . '/' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $site) . ($sub !== '' ? '/' . $sub : '');
    if (!array_key_exists($dir, $dirs)) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $owned = !function_exists('posix_geteuid') || @fileowner($dir) === posix_geteuid();
        $dirs[$dir] = (is_dir($dir) && is_writable($dir) && $owned) ? $dir : null;
    }
    return $dirs[$dir];
}

/**
 * Devuelve el valor guardado en caché bajo $key o, si no existe o caducó, lo calcula con $loader
 * y lo guarda $ttl segundos. Para lo que se lee en cada petición y cambia poco (configuración,
 * reglas, rutas): evita una consulta a MongoDB por petición. Con $ttl <= 0 o sin carpeta de
 * caché siempre llama a $loader.
 *
 * @template T
 * @param string $key
 * @param int $ttl Segundos.
 * @param callable(): T $loader Calcula el valor; debe ser serializable.
 * @return T
 */
function nfCacheRemember(string $key, int $ttl, callable $loader): mixed
{
    $dir = $ttl > 0 ? nfCacheDir() : null;
    if ($dir === null) {
        return $loader();
    }
    $file = $dir . '/' . md5($key) . '.cache';
    $mtime = @filemtime($file);
    if ($mtime !== false && $mtime + $ttl > time()) {
        $data = @file_get_contents($file);
        if ($data !== false) {
            $value = @unserialize($data);
            if ($value !== false || $data === serialize(false)) {
                return $value;
            }
        }
    }
    $value = $loader();
    // Escritura atómica: otra petición nunca lee un archivo a medias.
    $tmp = @tempnam($dir, 'tmp');
    if ($tmp !== false && @file_put_contents($tmp, serialize($value)) !== false) {
        @rename($tmp, $file);
    } elseif ($tmp !== false) {
        @unlink($tmp);
    }
    return $value;
}

/**
 * Borra una entrada de la caché local o, sin $key, todas las del sitio.
 *
 * @param string|null $key null = todas las entradas del sitio.
 * @return void
 */
function nfCacheForget(?string $key = null): void
{
    $dir = nfCacheDir();
    if ($dir === null) {
        return;
    }
    $files = $key === null ? (glob($dir . '/*.cache') ?: []) : [$dir . '/' . md5($key) . '.cache'];
    foreach ($files as $file) {
        @unlink($file);
    }
}

/**
 * Segundos que duran en caché la configuración, las reglas de seguridad y las rutas de páginas.
 * Los cambios hechos desde /admin/ la vacían al momento; otros servidores que compartan la base
 * los ven a más tardar en este tiempo. 0 desactiva la caché.
 *
 * @global class_config $config
 * @return int
 */
function nfCacheTtl(): int
{
    global $config;
    return isset($config['cache_ttl']) ? max(0, (int) $config['cache_ttl']) : 60;
}

/**
 * Normaliza una opción de configuración tipo lista: acepta arreglo, BSONArray o texto
 * separado por saltos de línea, comas o espacios (como se captura en el panel).
 *
 * @param mixed $value Arreglo, Traversable (BSONArray) o texto.
 * @return list<string> Valores sin vacíos, sin comentarios (#...) y sin repetidos.
 */
function nfConfigList($value): array
{
    if ($value instanceof Traversable) {
        $value = iterator_to_array($value, false);
    }
    if (is_string($value)) {
        $value = preg_split('/[\s,;]+/', $value);
    }
    if (!is_array($value)) {
        return [];
    }
    $items = array_filter(array_map(fn($v) => is_scalar($v) ? trim((string) $v) : '', $value), fn($v) => $v !== '' && $v[0] !== '#');
    return array_values(array_unique($items));
}

/**
 * Indica si $ip coincide con una IP exacta o un rango CIDR (IPv4 o IPv6), p.ej. '173.245.48.0/20'.
 *
 * @param string $ip
 * @param string $rule IP exacta o rango CIDR.
 * @return bool
 */
function nfIpMatches(string $ip, string $rule): bool
{
    if (!str_contains($rule, '/')) {
        return $ip === $rule;
    }
    [$subnet, $bits] = explode('/', $rule, 2);
    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin) || !ctype_digit($bits)) {
        return false;
    }
    $bits = (int) $bits;
    if ($bits > strlen($ipBin) * 8) {
        return false;
    }
    $bytes = intdiv($bits, 8);
    if (substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
        return false;
    }
    $rest = $bits % 8;
    if ($rest === 0) {
        return true;
    }
    $mask = chr((0xff << (8 - $rest)) & 0xff);
    return (($ipBin[$bytes] & $mask) === ($subnetBin[$bytes] & $mask));
}

/**
 * Devuelve $url solo si apunta a este sitio (ruta relativa) o a un host permitido
 * en $config['allowed_redirect_hosts']; en otro caso devuelve $default.
 *
 * @global class_config $config
 * @param mixed $url URL recibida del usuario.
 * @param string $default URL si $url no es segura.
 * @return string
 */
function nfSafeRedirect($url, string $default = '/'): string
{
    global $config;
    if (!is_string($url) || $url === '' || preg_match('/[\x00-\x1f\\\\]/', $url)) {
        return $default;
    }
    if ($url[0] === '/' && !str_starts_with($url, '//')) {
        return $url;
    }
    $parts = parse_url($url);
    if (empty($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
        return $default;
    }
    $allowed = array_map('strtolower', array_merge(
        [nfSiteHost()],
        nfConfigList($config['allowed_redirect_hosts'] ?? [])
    ));
    return in_array(strtolower($parts['host']), $allowed, true) ? $url : $default;
}

/**
 * Host público del sitio. Usa $config['url'] si existe para no confiar en la cabecera Host
 * (evita envenenamiento de enlaces en correos de restablecimiento/activación).
 *
 * @global class_config $config
 * @return string
 */
function nfSiteHost(): string
{
    global $config;
    if (!empty($config['url'])) {
        $host = parse_url($config['url'], PHP_URL_HOST);
        if (!empty($host)) {
            return $host;
        }
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host) ? $host : 'localhost';
}

/**
 * Ruta del logotipo del sistema: $config['logo'] (por omisión $DOCUMENT_ROOT/img/nf/logo.png) o,
 * si ese archivo no existe, el logotipo de nframework (img/nlarednf.png del framework, no del sitio).
 *
 * @global class_config $config
 * @return string Ruta absoluta en disco.
 */
function nfLogoPath(): string
{
    global $config;
    $logo = (string) ($config['logo'] ?? '');
    return $logo !== '' && is_file($logo) ? $logo : dirname(__DIR__) . '/img/nlarednf.png';
}

/**
 * true si el GD del servidor puede generar AVIF.
 *
 * @return bool
 */
function nfSupportsAvif(): bool
{
    static $supported = null;
    return $supported ??= function_exists('imageavif') && !empty(gd_info()['AVIF Support']);
}

/**
 * Registra en $_SESSION['nf']['browser']['avif'] si el navegador acepta AVIF. Chrome, Edge y Firefox lo
 * anuncian en el Accept de las páginas; Safari solo en el de las imágenes, así que también se aprende
 * cuando pide una. Solo se activa: una petición sin image/avif (fetch, descargas) no la apaga.
 * include.php la llama en cada petición.
 *
 * @param string|null $accept Cabecera Accept; null = la de la petición.
 * @return void
 */
function nfDetectImageSupport(?string $accept = null): void
{
    $accept ??= (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    if (preg_match('#image/avif(?!\s*;\s*q\s*=\s*0(?:\.0*)?\s*(?:,|$))#i', $accept)) {
        $_SESSION['nf']['browser']['avif'] = true;
    }
}

/**
 * Formato para las imágenes que genera el servidor (/images/pngto{formato}/...): 'avif' si el navegador
 * lo acepta (ver nfDetectImageSupport()) y GD lo puede generar; si no 'webp', que aceptan todos los
 * navegadores actuales.
 *
 * @return 'avif'|'webp'
 */
function nfImageFormat(): string
{
    return !empty($_SESSION['nf']['browser']['avif']) && nfSupportsAvif() ? 'avif' : 'webp';
}

/**
 * Arma un .ico con imágenes PNG embebidas (formato aceptado por todos los navegadores actuales).
 * $pngs es [tamaño => contenido PNG]; tamaños de 1 a 256 píxeles.
 *
 * @param array<int, string> $pngs [tamaño en píxeles => bytes del PNG].
 * @return string Bytes del .ico.
 */
function nfPngToIco(array $pngs): string
{
    $header = pack('vvv', 0, 1, count($pngs));
    $dir = '';
    $data = '';
    $offset = 6 + 16 * count($pngs);
    foreach ($pngs as $size => $png) {
        $size = max(1, min(256, (int) $size));
        // Ancho y alto ocupan un byte: 0 significa 256.
        $dir .= pack('CCCCvvVV', $size % 256, $size % 256, 0, 0, 1, 32, strlen($png), $offset + strlen($data));
        $data .= $png;
    }
    return $header . $dir . $data;
}

/**
 * Protección CSRF para peticiones que modifican estado (POST/PUT/PATCH/DELETE): si el navegador
 * envía Origin (o en su defecto Referer), debe apuntar a este sitio, a un host de
 * $config['allowed_redirect_hosts'] o de $config['csrf_trusted_origins']. Las llamadas
 * servidor a servidor (webhooks, callbacks) no envían esas cabeceras y no se ven afectadas.
 * Las rutas en $config['csrf_exempt_paths'] (prefijos) se omiten.
 *
 * @global class_config $config
 * @return bool
 */
function nfIsTrustedRequestOrigin(): bool
{
    global $config;
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return true;
    }
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    foreach (nfConfigList($config['csrf_exempt_paths'] ?? []) as $prefix) {
        if (str_starts_with($path, $prefix)) {
            return true;
        }
    }
    $source = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($source === '') {
        $source = $_SERVER['HTTP_REFERER'] ?? '';
        if ($source === '') {
            return true;
        }
    }
    // Origin: null (iframes con sandbox, redirecciones entre sitios) no identifica a nadie de confianza.
    $parts = parse_url($source);
    if (empty($parts['host'])) {
        return false;
    }
    $host = strtolower($parts['host']);
    $hostPort = $host . (isset($parts['port']) ? ':' . $parts['port'] : '');
    if ($hostPort === strtolower($_SERVER['HTTP_HOST'] ?? '')) {
        return true;
    }
    $allowed = array_map('strtolower', array_merge(
        [nfSiteHost()],
        nfConfigList($config['allowed_redirect_hosts'] ?? []),
        nfConfigList($config['csrf_trusted_origins'] ?? [])
    ));
    return in_array($host, $allowed, true) || in_array($hostPort, $allowed, true);
}

/**
 * true si $id es un ObjectId o un texto de 24 caracteres hexadecimales. Úselo antes de toMongoId() con datos de
 * la petición.
 *
 * @param mixed $id
 * @return bool
 */
function isValidObjectId($id): bool
{
    return (is_string($id) && preg_match('/^[a-f\d]{24}$/i', $id) === 1) || $id instanceof MongoDB\BSON\ObjectId;
}

/**
 * Corta la petición si el usuario actual no pertenece a alguno de los grupos indicados.
 *
 * @global User $user
 * @global class_nframework $nframework
 * @global mixed $result
 * @param string ...$groups Basta pertenecer a uno.
 * @return void Sin permiso: AJAX → 403 con $result['error']; si no, redirige a /. En ambos casos termina (exit).
 */
function requireGroup(string ...$groups): void
{
    global $user, $nframework, $result;
    foreach ($groups as $group) {
        if (isset($user) && $user->in($group)) {
            return;
        }
    }
    if (isset($nframework) && $nframework->isAjax()) {
        // nfshutdown() responde con $result en peticiones AJAX; lo impreso aquí se descartaría.
        http_response_code(403);
        $result = ['error' => 'No autorizado'];
        if (!empty($GLOBALS['nfshutdowndisable'])) {
            header('Content-Type: application/json');
            echo json_encode($result);
        }
    } else {
        header('Location: /');
    }
    exit();
}

/**
 * Elimina recursivamente operadores peligrosos de una consulta Mongo proveniente del cliente
 * ($where, $function, $accumulator, etc. permiten ejecutar JavaScript en el servidor).
 *
 * @param mixed $query
 * @return mixed La misma estructura sin los operadores prohibidos; los escalares se devuelven igual.
 */
function nfSanitizeMongoQuery($query)
{
    static $forbidden = ['$where', '$function', '$accumulator', '$expr', '$jsonSchema', '$lookup', '$unionWith', '$merge', '$out'];
    if (!is_array($query)) {
        return $query;
    }
    $clean = [];
    foreach ($query as $key => $value) {
        if (is_string($key) && in_array(strtolower($key), $forbidden, true)) {
            continue;
        }
        $clean[$key] = nfSanitizeMongoQuery($value);
    }
    return $clean;
}

/**
 * Verifica una contraseña contra el hash almacenado (password_hash o hashes legados sin sal).
 *
 * @global class_config $config
 * @param string $password Contraseña en claro.
 * @param mixed $stored Hash guardado.
 * @return bool
 */
function nfPasswordVerify(string $password, $stored): bool
{
    global $config;
    if (!is_string($stored) || $stored === '') {
        return false;
    }
    $info = password_get_info($stored);
    if (!empty($info['algo'])) {
        return password_verify($password, $stored);
    }
    foreach ((array) ($config['users']['algos'] ?? ['sha512']) as $algo) {
        if (in_array($algo, hash_algos(), true) && hash_equals($stored, hash($algo, $password))) {
            return true;
        }
    }
    return false;
}

/** Longitud mínima de contraseña para registro y restablecimiento. */
const NF_PASSWORD_MIN_LENGTH = 8;

/**
 * Hash de contraseña con password_hash(PASSWORD_DEFAULT). Verificar con nfPasswordVerify().
 *
 * @param string $password
 * @return string
 */
function nfPasswordHash(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Crea un PHPMailer configurado con los datos SMTP del sitio ($config['smtp']).
 * Vive aquí y no en nframework/router.php para que también funcione en páginas y en job_worker.php.
 * $smtp permite usar otra configuración (p. ej. probar la del formulario de Admin → Sitio antes de guardarla).
 *
 * @global class_config $config
 * @param array{host?: string, port?: int|string, auth?: bool, username?: string, password?: string, secure?: 'ssl'|'tls'|'', timeout?: int, fromemail?: string, fromname?: string}|null $smtp null = $config['smtp'].
 * @return \PHPMailer\PHPMailer\PHPMailer Con excepciones activadas: send() lanza PHPMailer\PHPMailer\Exception si falla.
 */
function nfMailer(?array $smtp = null): \PHPMailer\PHPMailer\PHPMailer
{
    global $config;
    $smtp ??= $config['smtp'] ?? [];
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->CharSet = 'UTF-8';
    $mail->Host = $smtp['host'] ?? '';
    $mail->SMTPAuth = boolval($smtp['auth'] ?? false);
    $mail->Username = $smtp['username'] ?? '';
    $mail->Password = $smtp['password'] ?? '';
    if (($smtp['secure'] ?? '') === 'ssl') {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } elseif (($smtp['secure'] ?? '') === 'tls') {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mail->Port = (int) ($smtp['port'] ?? 25);
    if (!empty($smtp['timeout'])) {
        $mail->Timeout = (int) $smtp['timeout'];
    }
    $mail->setFrom($smtp['fromemail'] ?? '', $smtp['fromname'] ?? '');
    $mail->isHTML(true);
    return $mail;
}
/**
 * Descifra $encryptedSessionId (ver encryptSessionId()), lo fija como id de sesión y la inicia.
 *
 * @param string $encryptedSessionId
 * @param string $key
 * @return bool true si la sesión quedó activa.
 */
function isValidSession($encryptedSessionId, $key)
{
    if (empty($encryptedSessionId) || empty($key)) {
        return false;
    }

    $sessionId = decryptSessionId($encryptedSessionId, $key);
    if ($sessionId === false) {
        return false; // Desencriptación fallida
    }

    session_id($sessionId);
    session_start();

    return session_status() === PHP_SESSION_ACTIVE;
}

/**
 * Borra una carpeta y todo su contenido. No valida la ruta: nunca la arme con datos de la petición.
 *
 * @param string $dir
 * @return bool Resultado de rmdir().
 */
function delTree($dir)
{
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}


/**
 * Tamaño total en bytes de los archivos de una carpeta (recursivo); 0 si no existe.
 *
 * @param string $path
 * @return int
 */
function GetDirectorySize($path)
{
    $bytestotal = 0;
    $path = realpath($path);
    if ($path !== false && $path != '' && file_exists($path)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object) {
            $bytestotal += $object->getSize();
        }
    }
    return $bytestotal;
}

/**
 * Tamaño legible: formatSize(1536) → '1.50KB'.
 *
 * @param int|float $bytes
 * @param int $decimals
 * @return string
 */
function formatSize($bytes, $decimals = 2)
{
    $size = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $factor = (int) floor((strlen((string) $bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ($size[$factor] ?? '');
}

/**
 * urlencode() que además reemplaza secuencias UTF-8 mal decodificadas (Ã¡, Ã±...) por entidades HTML.
 * Heredado; para URLs nuevas use urlencode()/rawurlencode().
 *
 * @param string $rb
 * @return string
 */
function nfurlencode($rb)
{
    $rb = urlencode($rb);
    ## Sustituyo caracteres en la cadena final
    $rb = str_replace("Ã¡", "&aacute;", $rb);
    $rb = str_replace("Ã©", "&eacute;", $rb);
    $rb = str_replace("Â®", "&reg;", $rb);
    $rb = str_replace("Ã­", "&iacute;", $rb);
    $rb = str_replace("ï¿½", "&iacute;", $rb);
    $rb = str_replace("Ã³", "&oacute;", $rb);
    $rb = str_replace("Ãº", "&uacute;", $rb);
    $rb = str_replace("n~", "&ntilde;", $rb);
    $rb = str_replace("Âº", "&ordm;", $rb);
    $rb = str_replace("Âª", "&ordf;", $rb);
    $rb = str_replace("ÃƒÂ¡", "&aacute;", $rb);
    $rb = str_replace("Ã±", "&ntilde;", $rb);
    $rb = str_replace("Ã‘", "&Ntilde;", $rb);
    $rb = str_replace("ÃƒÂ±", "&ntilde;", $rb);
    $rb = str_replace("n~", "&ntilde;", $rb);
    $rb = str_replace("Ãš", "&Uacute;", $rb);
    return $rb;
}
/**
 * Formatea un UTCDateTime (en UTC) con date(); null si no es un UTCDateTime.
 *
 * @param mixed $mongoDate
 * @param string $format Formato de DateTime::format().
 * @return string|null
 */
function mongoDateToReadable($mongoDate, $format = 'Y-m-d H:i:s')
{
    if ($mongoDate instanceof MongoDB\BSON\UTCDateTime) {
        $dateTime = $mongoDate->toDateTime();
        return $dateTime->format($format);
    }
    return null;
}
/**
 * Convierte un texto de fecha aceptado por DateTime ('2026-11-21 10:00', 'now') en UTCDateTime, conservando los
 * milisegundos.
 *
 * @param string $dateString
 * @return MongoDB\BSON\UTCDateTime
 * @throws Exception Si el texto no es una fecha válida.
 */
function readableToMongoDate($dateString)
{
    $dateTime = new DateTime($dateString);
    $milliseconds = ($dateTime->getTimestamp() * 1000) + (int) ($dateTime->format('v'));
    return new MongoDB\BSON\UTCDateTime($milliseconds);
}
/**
 * Migración: convierte a ObjectId los valores de $fieldName que estén guardados como texto en toda la
 * colección.
 *
 * @param MongoDB\Collection $collection
 * @param string $fieldName
 * @return void
 */
function updateCollectionStringIdToObjectId(MongoDB\Collection $collection, string $fieldName)
{
    $collection->updateMany(
        [$fieldName => ['$type' => 'string']],
        [
            ['$set' => [$fieldName => ['$toObjectId' => '$' . $fieldName]]]
        ]
    );
}
