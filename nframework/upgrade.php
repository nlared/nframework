<?php
/**
 * Migraciones de un sitio desde la terminal (ver includes/class.nfUpgrader.php y docs/upgrades.php).
 *
 *   php nframework/upgrade.php --host=www.ejemplo.com                      aplica las pendientes
 *   php nframework/upgrade.php --host=www.ejemplo.com --docroot=/var/www/x  multisitio
 *   php nframework/upgrade.php --host=... --dry-run                        solo lista lo pendiente
 *   php nframework/upgrade.php --host=... --code                           revisa el código del sitio
 *   php nframework/upgrade.php --host=... --apply-code                     aplica las correcciones automáticas
 *
 * Código de salida: 0 = todo bien, 1 = error de uso o de conexión, 2 = una migración falló.
 * Solo funciona en CLI: Admin → Actualizar lo invoca así después de Pull / Forzar, para que corra con el
 * código recién descargado y no con el que ya estaba cargado en el proceso web.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$host = null;
$docroot = dirname(__DIR__);
$dryRun = $scanCode = $applyCode = $json = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '-h' || $arg === '--help') {
        echo "Uso: php {$argv[0]} --host=<dominio> [--docroot=<dir>] [--dry-run] [--code] [--apply-code] [--json]\n";
        exit(0);
    } elseif (str_starts_with($arg, '--host=')) {
        $host = substr($arg, 7);
    } elseif (str_starts_with($arg, '--docroot=')) {
        $docroot = realpath(substr($arg, 10));
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif ($arg === '--code') {
        $scanCode = true;
    } elseif ($arg === '--apply-code') {
        $applyCode = true;
    } elseif ($arg === '--json') {
        $json = true;
    } else {
        fwrite(STDERR, "Argumento desconocido: $arg\n");
        exit(1);
    }
}
if ($host === null || !preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) {
    fwrite(STDERR, "Indique --host=<dominio> (elige el bloque de includes/config.php).\n");
    exit(1);
}
if ($docroot === false || !is_dir($docroot)) {
    fwrite(STDERR, "--docroot no es una carpeta.\n");
    exit(1);
}

$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = $host;
$_SERVER['DOCUMENT_ROOT'] = $docroot;
$includes = dirname(__DIR__) . '/includes';
set_include_path($includes . PATH_SEPARATOR . get_include_path());
require $includes . '/vendor/autoload.php';
require_once $includes . '/functions.php';
// Las clases del framework se cargan como en las páginas (class.<Nombre>.php en el include_path).
spl_autoload_register(function ($class) {
    $file = stream_resolve_include_path('class.' . $class . '.php');
    if ($file !== false) {
        require_once $file;
    }
});
$config = null;
require $includes . '/config.php';
if (!is_array($config) || empty($config['sitedb'])) {
    fwrite(STDERR, "config.php no tiene un bloque para $host.\n");
    exit(1);
}

$out = ['host' => $host, 'log' => []];
$status = 0;
try {
    $m = new MongoDB\Client($config['mongo_connection_string'] ?? 'mongodb://127.0.0.1');
    $up = new nfUpgrader($m->{$config['sitedb']}, nfUpgrader::defaultDirs($docroot), $docroot);
    $up->context = ['host' => $host, 'docroot' => $docroot, 'config' => $config, 'client' => $m];
    $before = $up->status();
    $out['log'][] = "Framework {$before['framework']} · sitio " . ($before['site'] ?: '(sin registrar)') . ' · ' . count($before['pending']) . ' pendiente(s)';
    $run = $up->run($dryRun);
    $out['log'] = array_merge($out['log'], $run['log']);
    $out['ok'] = $run['ok'];
    if (!$run['ok']) {
        $status = 2;
    }
    if ($scanCode || $applyCode) {
        $migrations = nfUpgrader::discover(nfUpgrader::defaultDirs($docroot));
        if ($applyCode && !$dryRun) {
            $applied = nfUpgrader::applyCode($migrations, $docroot);
            $out['log'] = array_merge($out['log'], $applied['log'] ?: ['No hubo correcciones automáticas que aplicar.']);
        }
        $matches = nfUpgrader::scanCode($migrations, $docroot);
        $out['code'] = $matches;
        $out['log'][] = count($matches) . ' coincidencia(s) en el código del sitio' . ($matches ? ':' : '.');
        foreach ($matches as $hit) {
            $out['log'][] = "  {$hit['file']}:{$hit['line']}  " . ($hit['fixable'] ? '[automática] ' : '') . "{$hit['description']}\n      {$hit['text']}";
        }
    }
} catch (Throwable $e) {
    $out['ok'] = false;
    $out['log'][] = 'Error: ' . $e->getMessage();
    $status = $status ?: 1;
}
echo $json ? json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n" : implode("\n", $out['log']) . "\n";
exit($status);
