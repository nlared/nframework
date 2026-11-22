<?php

/**
 * Migraciones versionadas: actualiza la base de datos (y revisa el código) de cada sitio cuando cambia el
 * framework.
 *
 * - La versión del framework está en el archivo VERSION de su raíz (año.número, igual que las etiquetas de git:
 *   2026.1, 2026.2...).
 * - Cada migración es un archivo upgrades/AAAAMMDD_nombre.php que devuelve un arreglo (ver load()). Las del
 *   framework están en <framework>/upgrades/; un sitio puede tener las suyas en <docroot>/upgrades/ (o en
 *   $config['upgrades_dir']) con el mismo formato.
 * - Lo aplicado se registra por sitio en la colección nfmigrations: una migración se ejecuta una sola vez, en
 *   orden (primero las del framework, luego las del sitio), y la primera que falla detiene las siguientes.
 * - Las reglas 'code' no se aplican solas: scanCode() lista las coincidencias en el código del sitio y
 *   applyCode() reescribe solo las que tienen 'replace', con respaldo de cada archivo.
 *
 * Se ejecuta desde Admin → Actualizar (pestaña Migraciones, y en automático después de Pull / Forzar) o en CLI:
 *
 *   php nframework/upgrade.php --host=www.ejemplo.com [--docroot=/var/www/sitio] [--dry-run] [--code]
 *
 *   $up = new nfUpgrader($m->{$config['sitedb']}, nfUpgrader::defaultDirs($docroot), $docroot);
 *   $estado = $up->status();          // versiones, pendientes y aplicadas
 *   $resultado = $up->run();          // aplica las pendientes
 */
class nfUpgrader
{
    /** Colección donde cada sitio registra sus migraciones aplicadas. */
    public const COLLECTION = 'nfmigrations';
    /** Documentos de control dentro de la colección. */
    public const STATE_ID = '_estado';
    public const LOCK_ID = '_candado';
    /** Segundos tras los que un candado abandonado (proceso que murió) deja de bloquear. */
    public const LOCK_TTL = 900;
    /** Nombre de archivo de una migración: fecha, guion bajo y nombre en minúsculas. */
    public const FILE_PATTERN = '/^(\d{8})_([a-z0-9_]+)\.php$/';
    /** Carpetas que scanCode() nunca recorre. */
    public const SKIP_DIRS = ['vendor', 'node_modules', '.git', '.nfupgrade', 'tmp', 'cache'];
    /** Carpetas del framework: se omiten cuando el sitio y el framework comparten carpeta (instalación simple). */
    public const FRAMEWORK_DIRS = ['includes', 'nframework', 'admin', 'docs', 'sat', 'tests', 'upgrades', 'src', 'stubs', 'css', 'img'];
    /** Archivos más grandes no se revisan. */
    public const MAX_FILE = 2097152;

    /** @var MongoDB\Database */
    private $db;
    /** @var array<string, string> origen => carpeta de migraciones */
    private $dirs;
    /** @var string */
    private $siteRoot;
    /** @var array<string, mixed> Datos extra que reciben las migraciones ($ctx). */
    public $context = [];

    /**
     * @param MongoDB\Database $db Base del sitio ($m->{$config['sitedb']}).
     * @param array<string, string> $dirs origen => carpeta (ver defaultDirs()).
     * @param string $siteRoot Document root del sitio (para scanCode()).
     */
    public function __construct($db, array $dirs, string $siteRoot = '')
    {
        $this->db = $db;
        $this->dirs = $dirs;
        $this->siteRoot = $siteRoot;
    }

    /**
     * Raíz del framework (carpeta padre de includes/).
     *
     * @return string
     */
    public static function frameworkRoot(): string
    {
        return dirname(__DIR__);
    }

    /**
     * Versión instalada del framework (archivo VERSION); '0' si no existe.
     *
     * @param string|null $root
     * @return string
     */
    public static function frameworkVersion(?string $root = null): string
    {
        $file = ($root ?? self::frameworkRoot()) . '/VERSION';
        $version = is_file($file) ? trim((string) file_get_contents($file)) : '';
        return preg_match('/^\d+(\.\d+){0,3}$/', $version) ? $version : '0';
    }

    /**
     * Carpetas de migraciones: las del framework y, si existen, las del sitio.
     *
     * @global class_config|array<string, mixed> $config
     * @param string $docroot Document root del sitio.
     * @return array<string, string>
     */
    public static function defaultDirs(string $docroot = ''): array
    {
        global $config;
        $dirs = ['framework' => self::frameworkRoot() . '/upgrades'];
        $site = (string) ($config['upgrades_dir'] ?? '');
        if ($site === '' && $docroot !== '') {
            $site = rtrim($docroot, '/') . '/upgrades';
        }
        $realSite = $site !== '' ? realpath($site) : false;
        if ($realSite !== false && is_dir($realSite) && $realSite !== realpath($dirs['framework'])) {
            $dirs['sitio'] = $realSite;
        }
        return $dirs;
    }

    /**
     * Lee y valida una migración. El archivo devuelve:
     *
     *   return [
     *       'version' => '2026.1',                    // versión del framework que la trae
     *       'description' => 'Qué hace',
     *       'up' => function (MongoDB\Database $db, callable $log, array $ctx): void { ... },   // opcional
     *       'code' => [                               // opcional: reglas para el código de los sitios
     *           ['description' => '...', 'pattern' => '/regex/', 'replace' => '...' (opcional), 'files' => ['php']],
     *       ],
     *   ];
     *
     * 'up' debe ser idempotente (si se interrumpe se vuelve a ejecutar completa).
     *
     * @param string $file
     * @param string $origin 'framework' o 'sitio'.
     * @return array{id: string, origin: string, file: string, version: string, description: string, up: callable|null, code: list<array{description: string, pattern: string, replace: string|null, files: list<string>}>}
     * @throws InvalidArgumentException Si el archivo no tiene el formato esperado.
     */
    public static function load(string $file, string $origin): array
    {
        $name = basename($file);
        if (!preg_match(self::FILE_PATTERN, $name)) {
            throw new InvalidArgumentException("$name: el nombre debe ser AAAAMMDD_nombre.php");
        }
        $def = (static fn() => require $file)();
        if (!is_array($def)) {
            throw new InvalidArgumentException("$name: debe devolver un arreglo");
        }
        if (isset($def['up']) && !is_callable($def['up'])) {
            throw new InvalidArgumentException("$name: 'up' debe ser una función");
        }
        $code = [];
        foreach ((array) ($def['code'] ?? []) as $i => $rule) {
            if (!is_array($rule) || !is_string($rule['pattern'] ?? null) || @preg_match($rule['pattern'], '') === false) {
                throw new InvalidArgumentException("$name: la regla de código $i no tiene un 'pattern' válido");
            }
            $code[] = [
                'description' => (string) ($rule['description'] ?? ''),
                'pattern' => $rule['pattern'],
                'replace' => isset($rule['replace']) ? (string) $rule['replace'] : null,
                'files' => array_values(array_map('strtolower', (array) ($rule['files'] ?? ['php']))),
            ];
        }
        if (!isset($def['up']) && $code === []) {
            throw new InvalidArgumentException("$name: necesita 'up' o 'code'");
        }
        return [
            'id' => $origin . ':' . substr($name, 0, -4),
            'origin' => $origin,
            'file' => $file,
            'version' => (string) ($def['version'] ?? '0'),
            'description' => (string) ($def['description'] ?? ''),
            'up' => $def['up'] ?? null,
            'code' => $code,
        ];
    }

    /**
     * Migraciones de las carpetas, en orden: por origen (como vienen en $dirs) y por nombre de archivo.
     *
     * @param array<string, string> $dirs
     * @param list<string> $errors Archivos que no se pudieron leer.
     * @return list<array<string, mixed>>
     */
    public static function discover(array $dirs, array &$errors = []): array
    {
        $out = [];
        foreach ($dirs as $origin => $dir) {
            $files = is_dir($dir) ? glob(rtrim($dir, '/') . '/*.php') : [];
            sort($files, SORT_STRING);
            foreach ($files as $file) {
                if (!preg_match(self::FILE_PATTERN, basename($file))) {
                    continue;
                }
                try {
                    $out[] = self::load($file, (string) $origin);
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
        return $out;
    }

    /**
     * Migraciones con 'up' que aún no se aplican con éxito.
     *
     * @param list<array<string, mixed>> $migrations
     * @param array<string, bool> $applied id => true si se aplicó con éxito.
     * @return list<array<string, mixed>>
     */
    public static function pending(array $migrations, array $applied): array
    {
        return array_values(array_filter($migrations, fn($mg) => $mg['up'] !== null && empty($applied[$mg['id']])));
    }

    /**
     * @return MongoDB\Collection
     */
    private function collection()
    {
        return $this->db->{self::COLLECTION};
    }

    /**
     * Registro de migraciones aplicadas: id => documento.
     *
     * @return array<string, array<string, mixed>>
     */
    public function applied(): array
    {
        $out = [];
        foreach ($this->collection()->find(['_id' => ['$nin' => [self::STATE_ID, self::LOCK_ID]]]) as $doc) {
            $doc = (array) $doc;
            $out[(string) $doc['_id']] = $doc;
        }
        return $out;
    }

    /**
     * Estado del sitio: versión del framework, versión registrada, pendientes y aplicadas.
     *
     * @return array{framework: string, site: string, pending: list<array{id: string, version: string, description: string}>, applied: list<array<string, mixed>>, errors: list<string>, outdated: bool}
     */
    public function status(): array
    {
        $errors = [];
        $migrations = self::discover($this->dirs, $errors);
        $applied = $this->applied();
        $ok = array_map(fn($d) => !empty($d['ok']), $applied);
        $state = (array) ($this->collection()->findOne(['_id' => self::STATE_ID]) ?? []);
        $pending = self::pending($migrations, $ok);
        $framework = self::frameworkVersion();
        $site = (string) ($state['version'] ?? '');
        return [
            'framework' => $framework,
            'site' => $site,
            'pending' => array_map(fn($mg) => ['id' => $mg['id'], 'version' => $mg['version'], 'description' => $mg['description']], $pending),
            'applied' => array_values(array_map(fn($d) => [
                'id' => (string) $d['_id'],
                'ok' => !empty($d['ok']),
                'fecha' => isset($d['fecha']) && $d['fecha'] instanceof MongoDB\BSON\UTCDateTime ? $d['fecha']->toDateTime()->format('Y-m-d H:i') : '',
                'salida' => (string) ($d['salida'] ?? ''),
            ], $applied)),
            'errors' => $errors,
            'outdated' => $pending !== [] || $site === '' || version_compare($site, $framework, '<'),
        ];
    }

    /**
     * Aplica las migraciones pendientes en orden. Se detiene en la primera que falla (las siguientes pueden
     * depender de ella). Con $dryRun solo dice qué se ejecutaría.
     *
     * @param bool $dryRun
     * @return array{ok: bool, applied: list<string>, failed: string|null, log: list<string>}
     */
    public function run(bool $dryRun = false): array
    {
        $errors = [];
        $migrations = self::discover($this->dirs, $errors);
        $log = array_map(fn($e) => 'Aviso: ' . $e, $errors);
        $ok = array_map(fn($d) => !empty($d['ok']), $this->applied());
        $pending = self::pending($migrations, $ok);
        $result = ['ok' => true, 'applied' => [], 'failed' => null, 'log' => &$log];
        if ($pending === []) {
            $log[] = 'No hay migraciones pendientes.';
        }
        if ($dryRun) {
            foreach ($pending as $mg) {
                $log[] = 'Pendiente: ' . $mg['id'] . ' (' . $mg['version'] . ') ' . $mg['description'];
            }
            return $result;
        }
        if ($pending !== [] && !$this->lock()) {
            $log[] = 'Otra actualización está en curso; intente en unos minutos.';
            $result['ok'] = false;
            return $result;
        }
        try {
            foreach ($pending as $mg) {
                $lines = [];
                $writer = function ($msg) use (&$lines) {
                    $lines[] = (string) $msg;
                };
                $start = microtime(true);
                $error = null;
                try {
                    ($mg['up'])($this->db, $writer, $this->context);
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
                $this->collection()->replaceOne(['_id' => $mg['id']], [
                    '_id' => $mg['id'],
                    'origen' => $mg['origin'],
                    'version' => $mg['version'],
                    'descripcion' => $mg['description'],
                    'ok' => $error === null,
                    'fecha' => new MongoDB\BSON\UTCDateTime(),
                    'segundos' => round(microtime(true) - $start, 2),
                    'salida' => mb_substr(implode("\n", $lines) . ($error !== null ? "\nError: " . $error : ''), 0, 20000),
                    'framework' => self::frameworkVersion(),
                ], ['upsert' => true]);
                $log[] = ($error === null ? 'Aplicada: ' : 'FALLÓ: ') . $mg['id'] . ' — ' . $mg['description'];
                foreach ($lines as $line) {
                    $log[] = '    ' . $line;
                }
                if ($error !== null) {
                    $log[] = '    Error: ' . $error;
                    $result['ok'] = false;
                    $result['failed'] = $mg['id'];
                    break;
                }
                $result['applied'][] = $mg['id'];
            }
            if ($result['ok']) {
                $this->collection()->updateOne(['_id' => self::STATE_ID], ['$set' => [
                    'version' => self::frameworkVersion(),
                    'fecha' => new MongoDB\BSON\UTCDateTime(),
                ]], ['upsert' => true]);
                $log[] = 'Sitio en la versión ' . self::frameworkVersion() . ' del framework.';
            }
        } finally {
            if ($pending !== []) {
                $this->unlock();
            }
        }
        return $result;
    }

    /**
     * Toma el candado del sitio (un solo proceso migra a la vez).
     *
     * @return bool false si otro proceso lo tiene y no ha caducado.
     */
    private function lock(): bool
    {
        $now = time();
        $current = $this->collection()->findOne(['_id' => self::LOCK_ID]);
        if ($current && (int) ($current['hasta'] ?? 0) > $now) {
            return false;
        }
        $this->collection()->updateOne(['_id' => self::LOCK_ID], ['$set' => ['hasta' => $now + self::LOCK_TTL, 'pid' => getmypid()]], ['upsert' => true]);
        return true;
    }

    /**
     * @return void
     */
    private function unlock(): void
    {
        $this->collection()->deleteOne(['_id' => self::LOCK_ID]);
    }

    /**
     * Archivos del sitio que se revisan: recorre $root sin seguir ligas simbólicas (las ligas a admin/,
     * nframework/ del multisitio) y sin SKIP_DIRS; si el sitio es la misma carpeta que el framework omite además
     * FRAMEWORK_DIRS.
     *
     * @param string $root
     * @param list<string> $extensions
     * @return list<string> Rutas relativas a $root.
     */
    public static function siteFiles(string $root, array $extensions = ['php']): array
    {
        $root = rtrim((string) realpath($root), '/');
        if ($root === '' || !is_dir($root)) {
            return [];
        }
        $skipTop = $root === rtrim((string) realpath(self::frameworkRoot()), '/') ? self::FRAMEWORK_DIRS : [];
        $out = [];
        $stack = [''];
        while ($stack) {
            $rel = array_pop($stack);
            $items = @scandir($root . ($rel === '' ? '' : '/' . $rel)) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $rel === '' ? $item : $rel . '/' . $item;
                $abs = $root . '/' . $path;
                if (is_link($abs)) {
                    continue;
                }
                if (is_dir($abs)) {
                    if (!in_array($item, self::SKIP_DIRS, true) && !($rel === '' && in_array($item, $skipTop, true))) {
                        $stack[] = $path;
                    }
                } elseif (in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), $extensions, true) && filesize($abs) <= self::MAX_FILE) {
                    $out[] = $path;
                }
            }
        }
        sort($out, SORT_STRING);
        return $out;
    }

    /**
     * Coincidencias de las reglas 'code' de todas las migraciones en el código del sitio.
     *
     * @param list<array<string, mixed>> $migrations
     * @param string $root Document root del sitio.
     * @param int $max Máximo de coincidencias devueltas.
     * @return list<array{migration: string, rule: int, description: string, file: string, line: int, text: string, fixable: bool}>
     */
    public static function scanCode(array $migrations, string $root, int $max = 500): array
    {
        $found = [];
        $cache = [];
        foreach ($migrations as $mg) {
            foreach ($mg['code'] as $i => $rule) {
                foreach (self::siteFiles($root, $rule['files']) as $file) {
                    $cache[$file] ??= (string) file_get_contents($root . '/' . $file);
                    if (!preg_match_all($rule['pattern'], $cache[$file], $matches, PREG_OFFSET_CAPTURE)) {
                        continue;
                    }
                    foreach ($matches[0] as [$text, $offset]) {
                        $line = substr_count($cache[$file], "\n", 0, $offset) + 1;
                        $lineText = explode("\n", $cache[$file])[$line - 1] ?? $text;
                        $found[] = [
                            'migration' => $mg['id'],
                            'rule' => $i,
                            'description' => $rule['description'],
                            'file' => $file,
                            'line' => $line,
                            'text' => mb_substr(trim($lineText), 0, 200),
                            'fixable' => $rule['replace'] !== null,
                        ];
                        if (count($found) >= $max) {
                            return $found;
                        }
                    }
                }
            }
        }
        return $found;
    }

    /**
     * Aplica las reglas con 'replace' a los archivos del sitio. Antes de escribir copia cada archivo a
     * <root>/.nfupgrade/<fecha>/<ruta> (carpeta oculta: no se sirve).
     *
     * @param list<array<string, mixed>> $migrations
     * @param string $root
     * @param list<string>|null $only Ids "migración#regla" a aplicar; null = todas las que tienen replace.
     * @return array{changed: list<string>, backup: string|null, log: list<string>}
     */
    public static function applyCode(array $migrations, string $root, ?array $only = null): array
    {
        $root = rtrim((string) realpath($root), '/');
        $backup = $root . '/.nfupgrade/' . date('Ymd-His');
        $changed = [];
        $log = [];
        $contents = [];
        foreach ($migrations as $mg) {
            foreach ($mg['code'] as $i => $rule) {
                if ($rule['replace'] === null || ($only !== null && !in_array($mg['id'] . '#' . $i, $only, true))) {
                    continue;
                }
                foreach (self::siteFiles($root, $rule['files']) as $file) {
                    $contents[$file] ??= (string) file_get_contents($root . '/' . $file);
                    $new = preg_replace($rule['pattern'], $rule['replace'], $contents[$file], -1, $count);
                    if ($new !== null && $count > 0) {
                        $contents[$file] = $new;
                        $changed[$file] = true;
                        $log[] = "$file: $count cambio(s) — " . ($rule['description'] ?: $mg['id']);
                    }
                }
            }
        }
        foreach (array_keys($changed) as $file) {
            $target = $backup . '/' . $file;
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true)) {
                throw new RuntimeException('No se pudo crear el respaldo en ' . $backup);
            }
            copy($root . '/' . $file, $target);
            if (file_put_contents($root . '/' . $file, $contents[$file], LOCK_EX) === false) {
                throw new RuntimeException("No se pudo escribir $file");
            }
        }
        if ($changed) {
            $log[] = 'Respaldo de los archivos originales: ' . $backup;
        }
        return ['changed' => array_keys($changed), 'backup' => $changed ? $backup : null, 'log' => $log];
    }
}
