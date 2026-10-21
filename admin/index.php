<?php
require 'common2.php';

/*
 * Diagnóstico del framework (solo lectura): revisa PHP, extensiones, composer, MongoDB, configuración,
 * permisos, programas externos y seguridad, y para cada problema indica cómo resolverlo.
 * Lo que requiere crear datos (usuarios, grupos, índices) lo hace nframework/test.php.
 */

$root = dirname(__DIR__);
$includesDir = $root . '/includes';
$phpVer = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
$fpmService = PHP_SAPI === 'fpm-fcgi' ? "php{$phpVer}-fpm" : 'apache2';
$restartCmd = "sudo systemctl restart $fpmService";

$diag = [];
function diagAdd(string $group, string $status, string $title, string $detail = '', array $fix = []): void
{
    global $diag;
    $diag[$group][] = compact('status', 'title', 'detail', 'fix');
}

/** Ejecuta un comando sin shell y devuelve [código, salida]. */
function diagRun(array $cmd, ?string $cwd = null): array
{
    $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
    if (!is_resource($proc)) {
        return [-1, ''];
    }
    $out = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($proc), trim((string) $out)];
}

function diagWhich(string $bin): ?string
{
    foreach (explode(':', getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin') as $dir) {
        if ($dir !== '' && is_executable("$dir/$bin")) {
            return "$dir/$bin";
        }
    }
    foreach (['/usr/local/bin', '/usr/bin', '/bin'] as $dir) {
        if (is_executable("$dir/$bin")) {
            return "$dir/$bin";
        }
    }
    return null;
}

function diagBytes($val): int
{
    $val = trim((string) $val);
    if ($val === '' || $val === '-1') {
        return -1;
    }
    $num = (int) $val;
    switch (strtolower(substr($val, -1))) {
        case 'g':
            $num *= 1024;
        case 'm':
            $num *= 1024;
        case 'k':
            $num *= 1024;
    }
    return $num;
}

/** Paquete apt que provee una extensión de PHP. */
function diagAptPackage(string $ext): string
{
    global $phpVer;
    $map = [
        'dom' => 'xml', 'simplexml' => 'xml', 'xmlreader' => 'xml', 'xmlwriter' => 'xml', 'libxml' => 'xml', 'xsl' => 'xml',
        'pdo_sqlite' => 'sqlite3', 'sqlite3' => 'sqlite3', 'pdo_mysql' => 'mysql', 'mysqli' => 'mysql',
        'posix' => 'common', 'fileinfo' => 'common', 'iconv' => 'common', 'ctype' => 'common', 'tokenizer' => 'common',
        'sockets' => 'common', 'exif' => 'common', 'ftp' => 'common', 'gettext' => 'common', 'calendar' => 'common',
    ];
    return "php{$phpVer}-" . ($map[$ext] ?? $ext);
}

/** Como diagRun() pero junta stdout y stderr (nginx -v y apache2 -v escriben la versión en stderr). */
function diagRunAll(array $cmd): string
{
    $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        return '';
    }
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return trim($out);
}

/** Primer ejecutable que exista entre rutas absolutas o nombres en PATH (incluye sbin, donde están nginx y apache2). */
function diagFindBin(string ...$names): ?string
{
    foreach ($names as $name) {
        $found = diagWhich($name);
        if ($found !== null) {
            return $found;
        }
        foreach (['/usr/sbin', '/usr/local/sbin', '/sbin'] as $dir) {
            if (is_executable("$dir/$name")) {
                return "$dir/$name";
            }
        }
    }
    return null;
}

function diagSize(float $bytes): string
{
    return $bytes >= 1073741824 ? round($bytes / 1073741824, 1) . ' GB' : round($bytes / 1048576) . ' MB';
}

// ---------------------------------------------------------------------------------------------------
// Sistema
// ---------------------------------------------------------------------------------------------------
$g = 'Sistema';
$osRelease = @parse_ini_file('/etc/os-release') ?: [];
$osName = $osRelease['PRETTY_NAME'] ?? trim(php_uname('s') . ' ' . php_uname('r'));
diagAdd($g, 'ok', 'Sistema operativo', htmlspecialchars($osName)
    . (!empty($osRelease['ID_LIKE']) ? ' <span class="text-muted">(basado en ' . htmlspecialchars($osRelease['ID_LIKE']) . ')</span>' : ''));
diagAdd($g, 'ok', 'Kernel', htmlspecialchars(php_uname('s') . ' ' . php_uname('r') . ' · ' . php_uname('m')) . ' · equipo <b>' . htmlspecialchars((string) gethostname()) . '</b>');

// Servidor web: SERVER_SOFTWARE puede venir sin versión (server_tokens off); el binario da la exacta.
$software = (string) ($_SERVER['SERVER_SOFTWARE'] ?? '');
$webDetail = [];
if ($software !== '') {
    $webDetail[] = 'Responde como <code>' . htmlspecialchars($software) . '</code>';
}
$isApache = stripos($software, 'apache') !== false || PHP_SAPI === 'apache2handler';
if ($isApache) {
    $bin = diagFindBin('apache2', 'httpd');
    if ($bin && preg_match('/Server version:\s*(.+)/', diagRunAll([$bin, '-v']), $mm)) {
        $webDetail[] = 'binario: ' . htmlspecialchars(trim($mm[1]));
    }
} elseif ($bin = diagFindBin('nginx')) {
    if (preg_match('/nginx version:\s*(.+)/', diagRunAll([$bin, '-v']), $mm)) {
        $webDetail[] = 'binario: ' . htmlspecialchars(trim($mm[1]));
    }
}
$webDetail[] = 'PHP por <code>' . htmlspecialchars(PHP_SAPI) . '</code>' . (PHP_SAPI === 'fpm-fcgi' ? " (servicio <code>$fpmService</code>)" : '');
diagAdd($g, $software === '' && count($webDetail) === 1 ? 'warn' : 'ok', 'Servidor web', implode(' · ', $webDetail));

$uptime = (float) explode(' ', (string) @file_get_contents('/proc/uptime'))[0];
$load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
$cpus = (int) (diagRunAll(['nproc']) ?: 1);
if ($uptime > 0 || $load) {
    $detail = $uptime > 0 ? 'Encendido hace ' . floor($uptime / 86400) . ' d ' . floor(fmod($uptime, 86400) / 3600) . ' h' : '';
    if ($load) {
        $detail .= ($detail ? ' · ' : '') . 'carga ' . implode(' / ', array_map(fn ($l) => number_format($l, 2), $load)) . " (1/5/15 min, $cpus CPU)";
    }
    $busy = $load && $load[1] > $cpus * 1.5;
    diagAdd($g, $busy ? 'warn' : 'ok', 'Actividad', $detail, $busy ? ['La carga de los últimos 5 minutos supera la capacidad de los CPU: revise procesos con <code>top</code> (workers, importaciones, PHP-FPM).'] : []);
}

if (preg_match('/MemTotal:\s+(\d+)/', (string) @file_get_contents('/proc/meminfo'), $mt)
    && preg_match('/MemAvailable:\s+(\d+)/', (string) @file_get_contents('/proc/meminfo'), $ma)) {
    $total = $mt[1] * 1024;
    $avail = $ma[1] * 1024;
    $low = $avail < $total * 0.1;
    diagAdd($g, $low ? 'warn' : 'ok', 'Memoria', diagSize($avail) . ' disponibles de ' . diagSize($total), $low ? ['Queda menos del 10 % de memoria: reduzca <code>pm.max_children</code> de PHP-FPM o agregue memoria.'] : []);
}

$diskFree = @disk_free_space($root);
$diskTotal = @disk_total_space($root);
if ($diskFree !== false && $diskTotal) {
    $low = $diskFree < $diskTotal * 0.1 || $diskFree < 2 * 1073741824;
    diagAdd($g, $low ? 'warn' : 'ok', 'Disco', diagSize($diskFree) . ' libres de ' . diagSize($diskTotal) . ' en la partición del sitio', $low ? [
        'Libere espacio: logs (<code>/var/log</code>, <code>*.log</code> del sitio), respaldos viejos, <code>comprobantes/xmls</code> o la caché en ' . htmlspecialchars((string) (nfCacheDir() ?? sys_get_temp_dir())) . '.',
    ] : []);
}

// ---------------------------------------------------------------------------------------------------
// Rutas de include
// ---------------------------------------------------------------------------------------------------
$g = 'Rutas de include';
$realIncludes = realpath($includesDir) ?: $includesDir;
$loadedInclude = '';
foreach (get_included_files() as $file) {
    if (basename($file) === 'include.php' && is_file(dirname($file) . '/class.Base.php')) {
        $loadedInclude = $file;
        break;
    }
}
diagAdd($g, 'ok', 'Framework en uso', 'Núcleo <code>' . htmlspecialchars($loadedInclude ?: $realIncludes . '/include.php') . '</code>'
    . ' · raíz del sitio (DOCUMENT_ROOT) <code>' . htmlspecialchars((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')) . '</code>');

// Clases del framework: un class.X.php con el mismo nombre en otra ruta nunca se carga (el autoload busca primero aquí).
$frameworkClasses = array_map('basename', glob($realIncludes . '/class.*.php') ?: []);
$entries = array_values(array_filter(explode(PATH_SEPARATOR, get_include_path()), fn ($e) => $e !== ''));
$coreFoundAt = null;
foreach ($entries as $i => $entry) {
    $n = $i + 1;
    if ($entry === '.') {
        diagAdd($g, 'ok', "$n. .", 'Carpeta del script que se ejecuta (y la actual): permite <code>require \'common2.php\'</code> sin ruta.');
        continue;
    }
    $real = realpath($entry);
    if ($real === false || !is_dir($real)) {
        diagAdd($g, 'warn', "$n. " . $entry, 'La carpeta no existe: PHP la revisa en cada <code>require</code> sin encontrar nada.', [
            'Quítela del <code>include_path</code> (vea «Definido en» abajo).',
        ]);
        continue;
    }
    $parts = [];
    if ($real !== $entry) {
        $parts[] = 'enlace a <code>' . htmlspecialchars($real) . '</code>';
    }
    $classes = array_map('basename', glob($real . '/class.*.php') ?: []);
    $status = 'ok';
    $fix = [];
    if (is_file($real . '/include.php') && is_file($real . '/class.Base.php')) {
        if ($coreFoundAt === null) {
            $coreFoundAt = $real;
            $parts[] = $real === $realIncludes
                ? '<b>núcleo de este framework</b>: <code>require \'include.php\'</code> carga este'
                : '<b>núcleo de nframework en otra ruta</b>: <code>require \'include.php\'</code> de los sitios carga este, no el de este panel';
            if ($real !== $realIncludes) {
                $status = 'warn';
                $fix[] = 'Los sitios que usan este <code>include_path</code> corren con el framework de <code>' . htmlspecialchars($real) . '</code>. Si debe ser el de <code>' . htmlspecialchars($realIncludes) . '</code>, cambie el <code>include_path</code>.';
            }
        } else {
            $status = 'warn';
            $parts[] = 'otro núcleo de nframework que <b>no se usa</b> (gana <code>' . htmlspecialchars($coreFoundAt) . '</code>, que va antes)';
        }
    } elseif ($classes) {
        $parts[] = count($classes) . ' clase(s) <code>class.*.php</code> disponibles para el autoload';
        $shadowed = $real !== $realIncludes ? array_values(array_intersect($classes, $frameworkClasses)) : [];
        if ($shadowed) {
            $status = 'warn';
            $parts[] = 'con el mismo nombre que clases del framework: ' . htmlspecialchars(implode(', ', $shadowed));
            $fix[] = 'Esas versiones nunca se cargan: el autoload busca primero en <code>' . htmlspecialchars($realIncludes) . '</code>. Renómbrelas o elimínelas.';
        }
    } else {
        $parts[] = is_file($real . '/vendor/autoload.php') ? 'bibliotecas (tiene vendor/)' : 'sin clases del framework (p. ej. PEAR del sistema)';
    }
    diagAdd($g, $status, "$n. " . $entry, implode(' · ', $parts), $fix);
}
if ($coreFoundAt === null) {
    diagAdd($g, 'warn', 'Núcleo en include_path', 'Ninguna ruta del <code>include_path</code> contiene <code>include.php</code> del framework: este panel funciona porque se carga con ruta relativa, pero los sitios que hacen <code>require \'include.php\'</code> fallarán.', [
        'Agregue <code>' . htmlspecialchars($realIncludes) . '</code> al <code>include_path</code> del php.ini de FPM.',
    ]);
}

// De dónde sale el valor: php.ini global, o un pool de FPM / .user.ini / PHP_VALUE de nginx que lo sobrescribe.
$sources = [];
foreach (array_filter(array_merge([php_ini_loaded_file()], array_map('trim', explode(',', (string) php_ini_scanned_files())))) as $iniFile) {
    if (is_readable($iniFile) && preg_match('/^\s*include_path\s*=/m', (string) file_get_contents($iniFile))) {
        $sources[] = $iniFile;
    }
}
foreach (glob("/etc/php/$phpVer/fpm/pool.d/*.conf") ?: [] as $pool) {
    if (is_readable($pool) && preg_match('/^\s*php_(admin_)?value\[include_path\]/m', (string) file_get_contents($pool))) {
        $sources[] = $pool;
    }
}
$globalIncludePath = ini_get_all('core', true)['include_path']['global_value'] ?? '';
$overridden = $globalIncludePath !== get_include_path();
diagAdd($g, 'ok', 'Definido en', ($sources ? implode(', ', array_map(fn ($s) => '<code>' . htmlspecialchars($s) . '</code>', $sources)) : 'valor predeterminado de PHP')
    . ($overridden ? ' · <b>sobrescrito</b> para este sitio (pool de FPM, <code>.user.ini</code> o <code>PHP_VALUE</code> de nginx); valor global: <code>' . htmlspecialchars($globalIncludePath) . '</code>' : ''));

// Los workers y scripts de los sitios corren con el PHP de línea de comandos, que tiene su propio php.ini.
$cliBin = PHP_BINDIR . "/php$phpVer";
if (is_executable($cliBin)) {
    [$code, $cliPath] = diagRun([$cliBin, '-r', 'echo get_include_path();']);
    if ($code === 0) {
        $cliReal = array_map(fn ($e) => realpath($e) ?: $e, explode(PATH_SEPARATOR, $cliPath));
        if (in_array($realIncludes, $cliReal, true) || in_array($coreFoundAt, $cliReal, true)) {
            diagAdd($g, 'ok', 'include_path en CLI', '<code>' . htmlspecialchars($cliPath) . '</code>');
        } else {
            diagAdd($g, 'warn', 'include_path en CLI', '<code>' . htmlspecialchars($cliPath) . '</code>: no incluye el framework. Los scripts de los sitios que se ejecutan por consola (workers, cron) y hacen <code>require \'include.php\'</code> fallarán; <code>includes/job_worker.php</code> sí funciona porque está junto a <code>include.php</code>.', [
                "Agréguelo en <code>/etc/php/$phpVer/cli/php.ini</code>: <code>include_path = \".:" . htmlspecialchars($coreFoundAt ?? $realIncludes) . ":/usr/share/php\"</code>",
                'O ejecute esos scripts con <code>php -d include_path=.:' . htmlspecialchars($coreFoundAt ?? $realIncludes) . ' script.php</code>.',
            ]);
        }
    }
}

// ---------------------------------------------------------------------------------------------------
// PHP
// ---------------------------------------------------------------------------------------------------
$g = 'PHP';
$minPhp = null;
if (is_file($includesDir . '/vendor/composer/platform_check.php')
    && preg_match('/PHP_VERSION_ID >= (\d+)/', (string) file_get_contents($includesDir . '/vendor/composer/platform_check.php'), $mm)) {
    $minPhp = (int) $mm[1];
}
if ($minPhp !== null && PHP_VERSION_ID < $minPhp) {
    $need = intdiv($minPhp, 10000) . '.' . intdiv($minPhp % 10000, 100);
    diagAdd($g, 'error', 'Versión de PHP', 'Se ejecuta PHP ' . PHP_VERSION . "; las dependencias de composer exigen $need o mayor.", [
        "Instale PHP $need y su FPM, y apunte el sitio al nuevo socket: <code>sudo apt install php$need-fpm</code>",
        'O reinstale dependencias compatibles con esta versión desde <a href="/admin/update/">Actualización → Composer</a>.',
    ]);
} else {
    diagAdd($g, 'ok', 'Versión de PHP', 'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')' . ($minPhp ? '; las dependencias piden ≥ ' . intdiv($minPhp, 10000) . '.' . intdiv($minPhp % 10000, 100) : ''));
}

$cliPhp = is_executable(PHP_BINDIR . "/php$phpVer") ? PHP_BINDIR . "/php$phpVer" : null;
if ($cliPhp === null) {
    diagAdd($g, 'warn', 'PHP de línea de comandos', "No existe " . PHP_BINDIR . "/php$phpVer. Composer y los workers de la cola usarían otra versión de PHP que el sitio.", [
        "<code>sudo apt install php{$phpVer}-cli</code>",
    ]);
} else {
    diagAdd($g, 'ok', 'PHP de línea de comandos', "$cliPhp (misma versión que el sitio; la usan composer y los workers)");
}

$memory = diagBytes(ini_get('memory_limit'));
if ($memory !== -1 && $memory < 256 * 1024 * 1024) {
    diagAdd($g, 'warn', 'memory_limit', 'Valor actual: ' . ini_get('memory_limit') . '. Exportar Excel/PDF grandes puede fallar.', [
        "En <code>/etc/php/$phpVer/" . (PHP_SAPI === 'fpm-fcgi' ? 'fpm' : 'apache2') . "/php.ini</code> ponga <code>memory_limit = 512M</code> y reinicie: <code>$restartCmd</code>",
    ]);
} else {
    diagAdd($g, 'ok', 'memory_limit', ini_get('memory_limit'));
}

$upload = min(diagBytes(ini_get('upload_max_filesize')), diagBytes(ini_get('post_max_size')));
$uploadText = 'upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size');
if ($upload !== -1 && $upload < 16 * 1024 * 1024) {
    diagAdd($g, 'warn', 'Tamaño máximo de subida', "$uploadText. Archivos de más de " . round($upload / 1048576, 1) . ' MB serán rechazados.', [
        "En php.ini ponga <code>upload_max_filesize = 64M</code> y <code>post_max_size = 64M</code>, y reinicie: <code>$restartCmd</code>",
        'Si usa nginx, revise también <code>client_max_body_size</code>.',
    ]);
} else {
    diagAdd($g, 'ok', 'Tamaño máximo de subida', $uploadText);
}

if (!filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN)) {
    diagAdd($g, 'warn', 'OPcache', 'OPcache está desactivado: cada petición recompila el código.', [
        "<code>sudo apt install php{$phpVer}-opcache</code> y en php.ini <code>opcache.enable = 1</code>; luego <code>$restartCmd</code>",
    ]);
} else {
    diagAdd($g, 'ok', 'OPcache', 'Activo');
}

if (extension_loaded('xdebug')) {
    diagAdd($g, 'warn', 'Xdebug cargado', 'Xdebug hace más lento cada petición y, si no hay depurador escuchando, llena los logs con "Could not connect to debugging client". No debe estar en producción.', [
        "Desactívelo en producción: <code>sudo phpdismod -v $phpVer xdebug && $restartCmd</code>",
        'En desarrollo, use <code>xdebug.start_with_request = trigger</code> para que solo actúe cuando se pida.',
    ]);
}

if (filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN) && PHP_SAPI !== 'cli') {
    diagAdd($g, 'warn', 'display_errors', 'Los errores de PHP se muestran al visitante (pueden revelar rutas y datos).', [
        "En producción ponga <code>display_errors = Off</code> en php.ini y reinicie: <code>$restartCmd</code>",
    ]);
}

if (ini_get('date.timezone') === '' || ini_get('date.timezone') === false) {
    diagAdd($g, 'warn', 'Zona horaria', 'date.timezone no está definida; PHP usa ' . date_default_timezone_get() . '.', [
        "En php.ini ponga <code>date.timezone = America/Mexico_City</code> (o la que corresponda) y reinicie: <code>$restartCmd</code>",
    ]);
} else {
    diagAdd($g, 'ok', 'Zona horaria', ini_get('date.timezone'));
}

// ---------------------------------------------------------------------------------------------------
// Extensiones
// ---------------------------------------------------------------------------------------------------
$g = 'Extensiones de PHP';
// Las que usa el propio framework.
$needed = [
    'mongodb' => ['framework (base de datos y sesiones)'],
    'gd' => ['framework (imágenes, logos, miniaturas)'],
    'mbstring' => ['framework'],
    'intl' => ['framework (idiomas, formatos)'],
    'curl' => ['framework (OAuth, APIs)'],
    'openssl' => ['framework (cifrado, SAT)'],
    'zip' => ['Excel/Word (xlsx, docx)'],
    'xml' => ['Excel/Word, SAT'],
    'dom' => ['Excel/Word, SAT'],
    'fileinfo' => ['subida de archivos'],
    'posix' => ['caché y procesos'],
];
// Y las que exigen los paquetes instalados.
$installedJson = json_decode((string) @file_get_contents($includesDir . '/vendor/composer/installed.json'), true);
$devPackages = array_flip($installedJson['dev-package-names'] ?? []);
foreach ($installedJson['packages'] ?? [] as $pkg) {
    if (isset($devPackages[$pkg['name']])) {
        continue;
    }
    foreach (array_keys($pkg['require'] ?? []) as $req) {
        if (str_starts_with($req, 'ext-')) {
            $needed[strtolower(substr($req, 4))][] = $pkg['name'];
        }
    }
}
ksort($needed);
$missing = [];
foreach ($needed as $ext => $by) {
    $by = array_unique($by);
    if (extension_loaded($ext)) {
        diagAdd($g, 'ok', $ext, 'Usada por: ' . htmlspecialchars(implode(', ', $by)));
    } else {
        $missing[] = $ext;
        diagAdd($g, 'error', $ext, 'No está cargada. La necesita: ' . htmlspecialchars(implode(', ', $by)) . '. Esas funciones fallarán.', [
            '<code>sudo apt install ' . diagAptPackage($ext) . " && $restartCmd</code>",
        ]);
    }
}

if (extension_loaded('gd')) {
    $gdInfo = gd_info();
    $gdMissing = [];
    foreach (['PNG Support' => 'PNG', 'JPEG Support' => 'JPEG', 'WebP Support' => 'WebP', 'FreeType Support' => 'FreeType'] as $k => $name) {
        if (empty($gdInfo[$k])) {
            $gdMissing[] = $name;
        }
    }
    if ($gdMissing) {
        diagAdd($g, 'warn', 'Formatos de GD', 'GD no soporta: ' . implode(', ', $gdMissing) . '. Las miniaturas en esos formatos fallarán.', [
            "Reinstale GD con las bibliotecas del sistema: <code>sudo apt install --reinstall php{$phpVer}-gd libwebp-dev libfreetype6 && $restartCmd</code>",
        ]);
    } else {
        diagAdd($g, 'ok', 'Formatos de GD', 'PNG, JPEG, WebP y FreeType');
    }
}

// Composer y los workers corren con el PHP de línea de comandos, que tiene su propio php.ini.
if ($cliPhp) {
    [$code, $cliMods] = diagRun([$cliPhp, '-m']);
    if ($code === 0) {
        $cliMods = array_map('strtolower', array_map('trim', explode("\n", $cliMods)));
        $cliMissing = array_values(array_filter(array_keys($needed), fn ($e) => extension_loaded($e) && !in_array($e, $cliMods, true)));
        if ($cliMissing) {
            diagAdd($g, 'warn', 'Extensiones en PHP CLI', 'El sitio las tiene pero el PHP de línea de comandos no: ' . implode(', ', $cliMissing) . '. Composer pedirá ignorarlas y los workers fallarán si las usan.', [
                "Actívelas también para CLI: <code>sudo phpenmod -v $phpVer -s cli " . implode(' ', $cliMissing) . '</code>',
            ]);
        } else {
            diagAdd($g, 'ok', 'Extensiones en PHP CLI', 'El PHP de línea de comandos tiene las mismas extensiones requeridas.');
        }
    }
}

// ---------------------------------------------------------------------------------------------------
// Composer
// ---------------------------------------------------------------------------------------------------
$g = 'Composer (includes/)';
$updLink = '<a href="/admin/update/">Actualización → Composer</a>';
if (!is_file($includesDir . '/vendor/autoload.php')) {
    diagAdd($g, 'error', 'Dependencias instaladas', 'No existe includes/vendor/autoload.php.', ["Ejecute <b>Install</b> en $updLink."]);
} else {
    diagAdd($g, 'ok', 'Dependencias instaladas', count($installedJson['packages'] ?? []) . ' paquetes en vendor/');
}

$composerJson = json_decode((string) @file_get_contents($includesDir . '/composer.json'), true);
$composerLock = json_decode((string) @file_get_contents($includesDir . '/composer.lock'), true);
if (!$composerLock) {
    diagAdd($g, 'warn', 'composer.lock', 'No existe composer.lock: cada instalación puede resolver versiones distintas.', ["Ejecute <b>Update todo</b> en $updLink para generarlo."]);
} elseif (is_array($composerJson)) {
    // Mismo cálculo que Composer\Package\Locker::getContentHash().
    $relevant = [];
    foreach (['name', 'version', 'require', 'require-dev', 'conflict', 'replace', 'provide', 'minimum-stability', 'prefer-stable', 'repositories', 'extra'] as $k) {
        if (isset($composerJson[$k])) {
            $relevant[$k] = $composerJson[$k];
        }
    }
    if (isset($composerJson['config']['platform'])) {
        $relevant['config']['platform'] = $composerJson['config']['platform'];
    }
    ksort($relevant);
    if (md5(json_encode($relevant, 0)) !== ($composerLock['content-hash'] ?? '')) {
        diagAdd($g, 'warn', 'composer.lock al día', 'composer.json cambió después de generar composer.lock (se agregaron, quitaron o cambiaron restricciones).', ["Ejecute <b>Update todo</b> en $updLink."]);
    } else {
        diagAdd($g, 'ok', 'composer.lock al día', 'Coincide con composer.json');
    }

    $locked = [];
    foreach (array_merge($composerLock['packages'] ?? [], $composerLock['packages-dev'] ?? []) as $p) {
        $locked[$p['name']] = $p['version'];
    }
    $installed = [];
    foreach ($installedJson['packages'] ?? [] as $p) {
        $installed[$p['name']] = $p['version'];
    }
    $diff = [];
    foreach ($composerLock['packages'] ?? [] as $p) {
        if (!isset($installed[$p['name']])) {
            $diff[] = $p['name'] . ' (falta)';
        } elseif ($installed[$p['name']] !== $p['version']) {
            $diff[] = $p['name'] . ' ' . $installed[$p['name']] . ' → ' . $p['version'];
        }
    }
    foreach ($installed as $name => $v) {
        if (!isset($locked[$name])) {
            $diff[] = "$name (sobra)";
        }
    }
    if ($diff) {
        diagAdd($g, 'warn', 'vendor/ coincide con composer.lock', count($diff) . ' diferencia(s): ' . htmlspecialchars(implode(', ', array_slice($diff, 0, 8))) . (count($diff) > 8 ? '…' : ''), ["Ejecute <b>Install</b> en $updLink."]);
    } else {
        diagAdd($g, 'ok', 'vendor/ coincide con composer.lock');
    }
}

$webUser = function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'www-data') : get_current_user();
$notWritable = [];
foreach (['composer.json', 'composer.lock', 'vendor', 'vendor/composer'] as $f) {
    if (file_exists("$includesDir/$f") && !is_writable("$includesDir/$f")) {
        $notWritable[] = $f;
    }
}
foreach (glob($includesDir . '/vendor/*/*', GLOB_ONLYDIR) ?: [] as $dir) {
    if (!is_writable($dir)) {
        $notWritable[] = substr($dir, strlen($includesDir) + 1);
    }
}
if ($notWritable) {
    diagAdd($g, 'warn', 'Permisos para actualizar desde el panel', "El usuario del servidor web ($webUser) no puede modificar " . count($notWritable) . ' ruta(s), p. ej. ' . htmlspecialchars(implode(', ', array_slice($notWritable, 0, 4))) . ". Install/Update desde el panel fallarán con «Could not delete».", [
        "<code>sudo chown -R $webUser: $includesDir/vendor $includesDir/composer.json $includesDir/composer.lock</code>",
        "Si ejecuta composer en la terminal, hágalo como $webUser: <code>sudo -u $webUser php$phpVer composer.phar install</code>",
    ]);
} else {
    diagAdd($g, 'ok', 'Permisos para actualizar desde el panel', "$webUser puede modificar vendor/ y composer.*");
}

// ---------------------------------------------------------------------------------------------------
// MongoDB
// ---------------------------------------------------------------------------------------------------
$g = 'MongoDB';
$sitedb = $config['sitedb'] ?? '';
try {
    $build = $m->selectDatabase('admin')->command(['buildInfo' => 1])->toArray()[0];
    diagAdd($g, 'ok', 'Conexión', 'MongoDB ' . htmlspecialchars((string) $build['version']) . ', base de datos <b>' . htmlspecialchars($sitedb) . '</b>');

    $db = $m->{$sitedb};
    $missingIdx = [];
    $wanted = [
        'users' => ['username' => 1],
        'pages' => ['path' => 1],
        'usersgroups' => ['users' => 1, 'name' => 1],
        'nfuristats' => ['ip' => 1, 'created_at' => 1],
        'nf_attempts' => ['ip' => 1],
    ];
    foreach ($wanted as $coll => $key) {
        $found = false;
        foreach ($db->{$coll}->listIndexes() as $idx) {
            if ((array) $idx->getKey() == $key) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $missingIdx[] = $coll . '(' . implode(', ', array_keys($key)) . ')';
        }
    }
    if ($missingIdx) {
        diagAdd($g, 'warn', 'Índices', 'Faltan índices: ' . htmlspecialchars(implode('; ', $missingIdx)) . '. Las páginas y el inicio de sesión serán más lentos conforme crezcan los datos.', [
            "Ejecute el instalador, que los crea: <code>php$phpVer " . $root . '/nframework/test.php --host=' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost') . '</code>',
        ]);
    } else {
        diagAdd($g, 'ok', 'Índices', 'Presentes');
    }

    $admins = $db->usersgroups->findOne(['name' => 'admins']);
    if (empty($admins) || count($admins['users'] ?? []) === 0) {
        diagAdd($g, 'error', 'Grupo admins', 'No existe el grupo admins o no tiene usuarios.', [
            "Ejecute el instalador: <code>php$phpVer $root/nframework/test.php --host=" . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost') . '</code>',
        ]);
    } else {
        diagAdd($g, 'ok', 'Grupo admins', count($admins['users']) . ' usuario(s)');
    }
} catch (Throwable $e) {
    diagAdd($g, 'error', 'Conexión', 'No se pudo consultar MongoDB: ' . htmlspecialchars($e->getMessage()), [
        'Verifique <code>mongo_connection_string</code> en includes/config.php y que el servicio esté activo: <code>sudo systemctl status mongod</code>',
    ]);
}

// ---------------------------------------------------------------------------------------------------
// Configuración
// ---------------------------------------------------------------------------------------------------
$g = 'Configuración';
foreach (['mongo_connection_string', 'sitedb', 'cookie_domain'] as $k) {
    if (empty($config[$k])) {
        diagAdd($g, 'error', $k, 'Falta en includes/config.php (obligatoria).', ['Agréguela siguiendo <code>includes/config.example.php</code>.']);
    }
}
if (empty($config['url'])) {
    diagAdd($g, 'warn', 'url', 'No está definida: los correos de activación, sitemap y manifiesto toman el dominio de la petición.', [
        'Defínala en <a href="/admin/site">Sitio</a> o en includes/config.php, p. ej. <code>\'url\' => \'https://www.ejemplo.com\'</code>.',
    ]);
} else {
    diagAdd($g, 'ok', 'url', htmlspecialchars((string) $config['url']));
}
$smtp = $config['smtp'] ?? [];
$smtpMissing = array_filter(['host', 'port', 'fromemail'], fn ($k) => empty($smtp[$k]));
if ($smtpMissing) {
    diagAdd($g, 'warn', 'Correo (SMTP)', 'Falta: ' . implode(', ', $smtpMissing) . '. No se podrán enviar correos (activación, recuperar contraseña, notificaciones).', [
        'Configúrelo en <a href="/admin/site">Sitio</a> → correo.',
    ]);
} else {
    diagAdd($g, 'ok', 'Correo (SMTP)', htmlspecialchars($smtp['host'] . ':' . $smtp['port']));
}

// ---------------------------------------------------------------------------------------------------
// Archivos y permisos
// ---------------------------------------------------------------------------------------------------
$g = 'Archivos y permisos';
$imgDir = ($_SERVER['DOCUMENT_ROOT'] ?? $root) . '/img';
if (!is_dir($imgDir) || !is_writable($imgDir)) {
    diagAdd($g, 'error', 'img/', "El servidor web no puede escribir en $imgDir: logos y miniaturas no se generarán.", [
        "<code>sudo mkdir -p $imgDir && sudo chown -R $webUser: $imgDir</code>",
    ]);
} else {
    diagAdd($g, 'ok', 'img/', 'Escribible (logos y miniaturas)');
}
$cacheDir = function_exists('nfCacheDir') ? nfCacheDir() : null;
if (!empty($config['cache_ttl']) || !isset($config['cache_ttl'])) {
    if ($cacheDir === null) {
        diagAdd($g, 'warn', 'Caché local', 'No se pudo usar el directorio de caché: cada petición consulta la configuración a MongoDB.', [
            'Defina <code>\'cache_dir\' => \'/var/cache/nframework\'</code> en config.php y créelo: <code>sudo install -d -o ' . $webUser . ' -m 700 /var/cache/nframework</code>',
        ]);
    } else {
        diagAdd($g, 'ok', 'Caché local', htmlspecialchars($cacheDir));
    }
}
$tmp = sys_get_temp_dir();
if (!is_writable($tmp)) {
    diagAdd($g, 'error', 'Directorio temporal', "$tmp no es escribible: exportes PDF/Excel y subidas fallarán.", ["Revise permisos de $tmp o defina <code>sys_temp_dir</code> en php.ini."]);
} else {
    diagAdd($g, 'ok', 'Directorio temporal', $tmp);
}

// ---------------------------------------------------------------------------------------------------
// Programas externos
// ---------------------------------------------------------------------------------------------------
$g = 'Programas externos';
$wordConv = $config['word_pdf_converter'] ?? '';
$excelConv = $config['excel_pdf_converter'] ?? '';
$bins = [
    'git' => ['Actualización del código desde el panel', 'sudo apt install git', 'warn'],
    'openssl' => ['e.firma y certificados del SAT', 'sudo apt install openssl', 'warn'],
    'gs' => ['Convertir PDF a imagen (spatie/pdf-to-image)', 'sudo apt install ghostscript', 'warn'],
    'unoconv' => ['Word/Excel a PDF con LibreOffice' . ($wordConv === 'unoconv' || $excelConv === 'unoconv' || $wordConv === '' ? ' (configurado como convertidor)' : ' (opcional: el sitio usa Dompdf)'),
        'sudo apt install unoconv libreoffice-writer libreoffice-calc',
        ($wordConv === 'unoconv' || $excelConv === 'unoconv') ? 'error' : 'warn'],
    'pdftk' => ['Rellenar/unir PDFs (mikehaertl/php-pdftk), opcional', 'sudo apt install pdftk-java', 'warn'],
];
foreach ($bins as $bin => [$use, $install, $level]) {
    $path = diagWhich($bin);
    if ($path) {
        diagAdd($g, 'ok', $bin, htmlspecialchars("$path — $use"));
    } else {
        diagAdd($g, $level, $bin, "No está instalado. Se usa para: $use.", ["<code>$install</code>"]);
    }
}
if (extension_loaded('imagick')) {
    $policy = @file_get_contents('/etc/ImageMagick-6/policy.xml') ?: @file_get_contents('/etc/ImageMagick-7/policy.xml');
    if ($policy && preg_match('/<policy[^>]*rights="none"[^>]*pattern="PDF"/i', $policy)) {
        diagAdd($g, 'warn', 'ImageMagick y PDF', 'La política de ImageMagick bloquea leer PDFs: la conversión PDF → imagen fallará.', [
            'En <code>/etc/ImageMagick-6/policy.xml</code> cambie <code>rights="none" pattern="PDF"</code> por <code>rights="read|write" pattern="PDF"</code>.',
        ]);
    }
}

// ---------------------------------------------------------------------------------------------------
// Seguridad
// ---------------------------------------------------------------------------------------------------
$g = 'Seguridad';
if (!$nframework->https) {
    diagAdd($g, 'warn', 'HTTPS', 'El sitio se está usando sin HTTPS: contraseñas y cookies de sesión viajan sin cifrar.', [
        'Obtenga un certificado (p. ej. <code>sudo certbot --nginx -d ' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'su-dominio') . '</code>) y redirija HTTP → HTTPS.',
        'Si está detrás de un proxy con HTTPS, agréguelo a <code>trusted_proxies</code> en <a href="/admin/site/security.php">Seguridad</a>.',
    ]);
} else {
    diagAdd($g, 'ok', 'HTTPS', 'Activo');
}
if (!empty($config['enable_web_terminal'])) {
    diagAdd($g, 'warn', 'Terminal web', 'enable_web_terminal está activo: /admin/terminal.php permite ejecutar comandos en el servidor.', [
        "Desactívelo cuando no lo use: <code>'enable_web_terminal' => false</code> en includes/config.php.",
    ]);
}
try {
    $admin = $m->{$sitedb}->users->findOne(['username' => 'admin'], ['projection' => ['password' => 1]]);
    $hash = (string) ($admin['password'] ?? '');
    if ($admin && ($hash === hash('sha512', 'admin') || (password_get_info($hash)['algo'] && password_verify('admin', $hash)))) {
        diagAdd($g, 'error', 'Contraseña de admin', 'El usuario admin todavía tiene la contraseña predeterminada «admin».', [
            'Cámbiela de inmediato en <a href="/admin/users">Usuarios</a>.',
        ]);
    } elseif ($admin) {
        diagAdd($g, 'ok', 'Contraseña de admin', 'No es la predeterminada');
    }
} catch (Throwable $e) {
    // El error de conexión ya se reportó en MongoDB.
}
if (ini_get('expose_php') && filter_var(ini_get('expose_php'), FILTER_VALIDATE_BOOLEAN)) {
    diagAdd($g, 'warn', 'expose_php', 'PHP anuncia su versión en la cabecera X-Powered-By.', ["En php.ini: <code>expose_php = Off</code> y <code>$restartCmd</code>"]);
}

// ---------------------------------------------------------------------------------------------------
// Cola de trabajos
// ---------------------------------------------------------------------------------------------------
$g = 'Cola de trabajos';
$workers = 0;
foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $f) {
    if (str_contains((string) @file_get_contents($f), 'job_worker.php')) {
        $workers++;
    }
}
try {
    $overdue = $m->{$sitedb}->jobs->countDocuments([
        'failed_at' => null,
        'delay_until' => ['$lt' => new MongoDB\BSON\UTCDateTime((time() - 300) * 1000)],
    ]);
    $failed = $m->{$sitedb}->jobs->countDocuments(['failed_at' => ['$ne' => null]]);
    if ($overdue > 0 && $workers === 0) {
        diagAdd($g, 'error', 'Workers', "Hay $overdue trabajo(s) esperando más de 5 minutos y no hay ningún worker corriendo.", [
            'Configure el worker como servicio (supervisor o systemd); las instrucciones están en <a href="/admin/workers">Workers</a>.',
            "Para procesarlos una vez a mano: <code>sudo -u $webUser php$phpVer $includesDir/job_worker.php default</code>",
        ]);
    } elseif ($workers === 0) {
        diagAdd($g, 'warn', 'Workers', 'No hay ningún worker corriendo. Si el sitio usa trabajos en segundo plano (correos masivos, importaciones), no se procesarán.', [
            'Vea <a href="/admin/workers">Workers</a> para configurarlo con supervisor o systemd.',
        ]);
    } else {
        diagAdd($g, 'ok', 'Workers', "$workers proceso(s) activo(s), $overdue trabajo(s) atrasado(s)");
    }
    if ($failed > 0) {
        diagAdd($g, 'warn', 'Trabajos fallidos', "$failed trabajo(s) agotaron sus reintentos.", [
            'Revise el campo <code>error</code> en la colección <code>jobs</code> y elimínelos o reinícielos cuando esté corregido.',
        ]);
    }
} catch (Throwable $e) {
}

// ---------------------------------------------------------------------------------------------------
// Código (git)
// ---------------------------------------------------------------------------------------------------
$g = 'Código';
if (diagWhich('git') && is_dir($root . '/.git')) {
    $gitEnv = ['git', '-c', 'safe.directory=' . $root, '-C', $root];
    [$code, $status] = diagRun(array_merge($gitEnv, ['status', '--porcelain', '--untracked-files=no']));
    [, $branch] = diagRun(array_merge($gitEnv, ['rev-parse', '--abbrev-ref', 'HEAD']));
    [$c2, $behind] = diagRun(array_merge($gitEnv, ['rev-list', '--count', 'HEAD..@{u}']));
    if ($code !== 0) {
        diagAdd($g, 'warn', 'Repositorio', 'git no pudo leer el repositorio (¿permisos?).', ["<code>git config --system --add safe.directory $root</code>"]);
    } else {
        $changed = $status === '' ? 0 : count(explode("\n", $status));
        $detail = 'Rama ' . htmlspecialchars($branch) . ($changed ? ", $changed archivo(s) modificados localmente" : ', sin cambios locales');
        if ($c2 === 0 && (int) $behind > 0) {
            diagAdd($g, 'warn', 'Repositorio', "$detail; hay $behind commit(s) por descargar (según el último fetch).", ['Actualice en <a href="/admin/update/">Actualización → Git</a>.']);
        } elseif ($changed) {
            diagAdd($g, 'warn', 'Repositorio', "$detail. Los cambios locales pueden impedir actualizar.", ['Revíselos en <a href="/admin/update/">Actualización → Git</a>.']);
        } else {
            diagAdd($g, 'ok', 'Repositorio', $detail);
        }
    }
}

// ---------------------------------------------------------------------------------------------------
$totals = ['ok' => 0, 'warn' => 0, 'error' => 0];
foreach ($diag as $items) {
    foreach ($items as $it) {
        $totals[$it['status']]++;
    }
}
$icons = [
    'ok' => '<span class="mif-checkmark fg-green"></span>',
    'warn' => '<span class="mif-warning fg-orange"></span>',
    'error' => '<span class="mif-cross fg-red"></span>',
];
?>
<style>
    .diag-summary { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .diag-tile { flex: 1 1 140px; padding: 12px 16px; border-radius: 6px; color: #fff; }
    .diag-tile b { display: block; font-size: 2em; line-height: 1.1; }
    .diag-table td { vertical-align: top; }
    .diag-table td:first-child { width: 32px; text-align: center; font-size: 1.2em; }
    .diag-table td:nth-child(2) { width: 240px; font-weight: 600; }
    .diag-fix { margin: 6px 0 0; padding-left: 18px; }
    .diag-fix code { word-break: break-all; }
    .diag-only-problems tr.diag-ok, .diag-only-problems .diag-group.diag-clean { display: none; }
</style>
<div class="container p-5" id="diag">
    <div class="box shadow-large">
        <div class="box-title">Diagnóstico del framework</div>
        <div class="diag-summary">
            <div class="diag-tile bg-red"><b><?= $totals['error'] ?></b>Errores</div>
            <div class="diag-tile bg-orange"><b><?= $totals['warn'] ?></b>Advertencias</div>
            <div class="diag-tile bg-green"><b><?= $totals['ok'] ?></b>Correctos</div>
        </div>
        <div class="mb-4">
            <label><input type="checkbox" id="diag-filter" <?= ($totals['error'] + $totals['warn']) ? 'checked' : '' ?>> Mostrar solo problemas</label>
            <a href="/admin/" class="button small ml-4"><span class="mif-loop2"></span> Volver a verificar</a>
        </div>
        <?php foreach ($diag as $group => $items):
            $clean = !array_filter($items, fn ($i) => $i['status'] !== 'ok'); ?>
            <div class="diag-group<?= $clean ? ' diag-clean' : '' ?>">
                <h5 class="mt-4"><?= htmlspecialchars($group) ?></h5>
                <table class="table compact diag-table">
                    <?php foreach ($items as $it): ?>
                        <tr class="diag-<?= $it['status'] ?>">
                            <td><?= $icons[$it['status']] ?></td>
                            <td><?= htmlspecialchars($it['title']) ?></td>
                            <td>
                                <?= $it['detail'] ?>
                                <?php if ($it['fix']): ?>
                                    <ol class="diag-fix">
                                        <?php foreach ($it['fix'] as $step): ?><li><?= $step ?></li><?php endforeach; ?>
                                    </ol>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endforeach; ?>
        <?php if (!$totals['error'] && !$totals['warn']): ?>
            <p class="fg-green mt-4">Todo en orden.</p>
        <?php endif; ?>
    </div>
</div>
<script>
    $(function () {
        function apply() {
            $('#diag').toggleClass('diag-only-problems', $('#diag-filter').is(':checked'));
        }
        $('#diag-filter').on('change', apply);
        apply();
    });
</script>
