<?php

function assignArrayByPath(&$arr, $path, $value, $separator = '.')
{
    $keys = explode($separator, $path);
    foreach ($keys as $key) {
        $arr = &$arr[$key];
    }
    $arr = $value;
}
function remove_trailing_separator($path)
{
    return rtrim($path, DIRECTORY_SEPARATOR);
}
function clean_filename($filename)
{
    return preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
}
/**
 * Versión de /main.js para la URL (?v=): cambia si cambia la plantilla o la llave pública de notificaciones,
 * que son lo único que define su contenido.
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
function addVarToGarbage($key, $time)
{
    $_SESSION['_gc_tracker'][$key] = $time;
}
function removeVarFromGarbage($key)
{
    unset($_SESSION['_gc_tracker'][$key]);
}

function ifset($array, $key): mixed
{
    return isset($array[$key]) ? $array[$key] : null;
}

/**
 * Limpia el árbol de un menú (formato de Admin → Menús / jstree) y deja solo lo que usa buildMetroMenu():
 * [['text' => ..., 'data' => ['link', 'icon', 'onfunction', 'target'], 'children' => [...]], ...].
 * Descarta nodos sin texto y lo que exceda $maxDepth niveles. Lanza InvalidArgumentException si $nodes no
 * es una lista.
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
 */
function nfPage(string $path): ?array
{
    global $m, $config;
    return nfCacheRemember('page:' . $path, nfCacheTtl(), function () use ($m, $config, $path) {
        $doc = $m->{$config['sitedb']}->pages->findOne(['path' => $path]);
        return $doc ? mongoToArray($doc) : null;
    });
}

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
 * @param mixed $doc 
 * @param mixed $query 
 * @return bool 
 *  True if the document matches the query, false otherwise
 * 
 * Supported operators:
 *  - $and
 *  - $or
 *  - $nor
 *  - $gt
 *  - $lt
 *  - $gte
 *  - $lte
 *  - $eq
 *  - $ne
 *  - $in
 *  - $nin
 *  - $regex
 *  - $exists
 *  - $size
 *  - $type (only basic types: string, integer, array, object, boolean, double)
 *  - $options (for regex)      
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

function nflogReset($key): void
{
    global $m, $config;
    $m->{$config['sitedb']}->nf_attempts->deleteOne(['ip' => $key]);
}

function encryptSessionId($sessionId, $key)
{
    // AES-256-GCM (autenticado): 'g' . IV(12) . TAG(16) . datos
    $iv = random_bytes(12);
    $tag = '';
    $encrypted = openssl_encrypt((string) $sessionId, 'aes-256-gcm', hash('sha256', (string) $key, true), OPENSSL_RAW_DATA, $iv, $tag);
    return rtrim(strtr(base64_encode('g' . $iv . $tag . $encrypted), '+/', '-_'), '=');
}

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
 */
function nfCacheTtl(): int
{
    global $config;
    return isset($config['cache_ttl']) ? max(0, (int) $config['cache_ttl']) : 60;
}

/**
 * Normaliza una opción de configuración tipo lista: acepta arreglo, BSONArray o texto
 * separado por saltos de línea, comas o espacios (como se captura en el panel).
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
 * Protección CSRF para peticiones que modifican estado (POST/PUT/PATCH/DELETE): si el navegador
 * envía Origin (o en su defecto Referer), debe apuntar a este sitio, a un host de
 * $config['allowed_redirect_hosts'] o de $config['csrf_trusted_origins']. Las llamadas
 * servidor a servidor (webhooks, callbacks) no envían esas cabeceras y no se ven afectadas.
 * Las rutas en $config['csrf_exempt_paths'] (prefijos) se omiten.
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

function isValidObjectId($id): bool
{
    return (is_string($id) && preg_match('/^[a-f\d]{24}$/i', $id) === 1) || $id instanceof MongoDB\BSON\ObjectId;
}

/**
 * Corta la petición si el usuario actual no pertenece a alguno de los grupos indicados.
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

function nfPasswordHash(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Crea un PHPMailer configurado con los datos SMTP del sitio ($config['smtp']).
 * Vive aquí y no en nframework/router.php para que también funcione en páginas y en job_worker.php.
 * $smtp permite usar otra configuración (p. ej. probar la del formulario de Admin → Sitio antes de guardarla).
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

function delTree($dir)
{
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}


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

function formatSize($bytes, $decimals = 2)
{
    $size = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $factor = (int) floor((strlen((string) $bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ($size[$factor] ?? '');
}

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
function mongoDateToReadable($mongoDate, $format = 'Y-m-d H:i:s')
{
    if ($mongoDate instanceof MongoDB\BSON\UTCDateTime) {
        $dateTime = $mongoDate->toDateTime();
        return $dateTime->format($format);
    }
    return null;
}
function readableToMongoDate($dateString)
{
    $dateTime = new DateTime($dateString);
    $milliseconds = ($dateTime->getTimestamp() * 1000) + (int) ($dateTime->format('v'));
    return new MongoDB\BSON\UTCDateTime($milliseconds);
}
function updateCollectionStringIdToObjectId(MongoDB\Collection $collection, string $fieldName)
{
    $collection->updateMany(
        [$fieldName => ['$type' => 'string']],
        [
            ['$set' => [$fieldName => ['$toObjectId' => '$' . $fieldName]]]
        ]
    );
}
