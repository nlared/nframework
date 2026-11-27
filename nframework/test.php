<?php
/*
 * Diagnóstico e instalador: crea/actualiza la base de datos (usuarios guest/admin, grupos, índices)
 * y ajusta php.ini.
 *
 * Acceso:
 *  - CLI y localhost: siempre.
 *  - Cualquier host mientras el sitio NO esté instalado (no existe el grupo 'admins' con usuarios).
 *    Si $config['install_key'] está definido en config.php, se exige ?key=<install_key>.
 *  - Una vez instalado: solo usuarios del grupo 'admins' con sesión iniciada.
 *
 * Uso en CLI (el host elige el bloque de includes/config.php, es decir, la base de datos):
 *   php nframework/test.php --host=www.ejemplo.com
 *   php nframework/test.php www.ejemplo.com
 *   php /opt/nframework/nframework/test.php --host=www.ejemplo.com --docroot=/var/www/www.ejemplo.com   (multisitio)
 */
if (PHP_SAPI === 'cli') {
	$cliHost = null;
	$cliDocroot = dirname(__DIR__);
	foreach (array_slice($argv, 1) as $arg) {
		if ($arg === '-h' || $arg === '--help') {
			echo "Uso: php " . $argv[0] . " --host=<dominio>\n"
				. "  --host=<dominio>  Dominio del sitio a instalar/revisar (elige el bloque de includes/config.php).\n"
				. "                    También se acepta el dominio como primer argumento.\n"
				. "  --docroot=<dir>   Document root del sitio (multisitio: la carpeta del vhost con las ligas a admin/,\n"
				. "                    nframework/ y router.php). Por omisión, la carpeta del framework.\n";
			exit(0);
		}
		if (str_starts_with($arg, '--host=')) {
			$cliHost = substr($arg, 7);
		} elseif (str_starts_with($arg, '--docroot=')) {
			$cliDocroot = realpath(substr($arg, 10));
			if ($cliDocroot === false || !is_dir($cliDocroot)) {
				fwrite(STDERR, "--docroot no es una carpeta: " . substr($arg, 10) . "\n");
				exit(1);
			}
		} elseif ($arg !== '' && $arg[0] !== '-' && $cliHost === null) {
			$cliHost = $arg;
		}
	}
	if ($cliHost === null) {
		fwrite(STDERR, "Aviso: no se indicó --host=<dominio>; se usa 'localhost' (bloque default de config.php).\n");
		$cliHost = 'localhost';
	}
	if (!preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $cliHost)) {
		fwrite(STDERR, "Host inválido: $cliHost\n");
		exit(1);
	}
	// Variables que config.php e include.php esperan de una petición web.
	$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = $cliHost;
	$_SERVER['REQUEST_URI'] ??= '/nframework/test.php';
	$_SERVER['REQUEST_METHOD'] ??= 'GET';
	$_SERVER['HTTP_USER_AGENT'] ??= 'nframework-cli';
	$_SERVER['DOCUMENT_ROOT'] = $cliDocroot;
	// En CLI el include_path puede no tener includes/ (se configura en el php.ini de FPM).
	set_include_path(dirname(__DIR__) . '/includes' . PATH_SEPARATOR . get_include_path());
	echo "Host: $cliHost\n";
	if ($cliDocroot !== dirname(__DIR__)) {
		echo "Document root: $cliDocroot\n";
	}
}
if (PHP_SAPI !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
	@include 'config.php';
	$nfInstalled = false;
	try {
		// Sin dependencias no se puede saber si está instalado: se trata como no instalado (aplica install_key)
		// para que el diagnóstico diga qué falta en lugar de morir con un error fatal.
		if (!nfTestVendorUsable(dirname(__DIR__) . '/includes') || !extension_loaded('mongodb')) {
			throw new RuntimeException('Faltan dependencias');
		}
		require_once 'vendor/autoload.php';
		$nfInstallClient = new MongoDB\Client($config['mongo_connection_string'] ?? 'mongodb://127.0.0.1');
		$nfInstalled = !empty($config['sitedb']) && !empty($nfInstallClient->{$config['sitedb']}->usersgroups->findOne(
			['name' => 'admins', 'users.0' => ['$exists' => true]]
		));
	} catch (Throwable $e) {
		// Sin conexión: se deja pasar para que el diagnóstico muestre el problema.
	}
	if ($nfInstalled) {
		require_once 'include.php';
		if (!$user->in('admins')) {
			http_response_code(403);
			exit('El sitio ya está instalado: inicie sesión como administrador para usar esta página.');
		}
	} elseif (!empty($config['install_key']) && !hash_equals((string) $config['install_key'], (string) ($_GET['key'] ?? ''))) {
		http_response_code(403);
		exit('Se requiere la clave de instalación (?key=).');
	}
}
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);//*/
$buffers = '';
$errores = [];
$nfTestPrinted = false;
$nfTestStage = '';
function out($msg)
{
	global $buffers;
	$buffers .= $msg . '<br>';
}

// Muestra los pendientes ($errores) y todo lo acumulado en $buffers. Se llama al final o, si algo termina el
// script antes (error fatal, exit de config.php), desde el manejador de cierre: el reporte nunca se pierde.
function nfTestReport(): void
{
	global $buffers, $errores, $nfTestPrinted;
	if ($nfTestPrinted) {
		return;
	}
	$nfTestPrinted = true;
	if (count($errores) > 0) {
		out('<br><b>Pendientes</b>');
		foreach ($errores as $err) {
			out("👉 $err");
		}
	} else {
		out('No se encontraron errores de configuración');
	}
	$errores = [];
	if (PHP_SAPI === 'cli') {
		echo html_entity_decode(strip_tags(preg_replace('/(<br\s*\/?>\s*)+/i', "\n", $buffers))), "\n";
	} else {
		echo $buffers;
	}
}
register_shutdown_function(function () {
	global $errores, $nfTestPrinted, $nfTestStage;
	if ($nfTestPrinted) {
		return;
	}
	$e = error_get_last();
	if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
		$errores[] = 'Error fatal' . ($nfTestStage ? " ($nfTestStage)" : '') . ': '
			. htmlspecialchars($e['message']) . ' en ' . htmlspecialchars($e['file']) . ':' . $e['line'];
	} elseif ($nfTestStage === 'config.php') {
		$errores[] = 'config.php terminó la ejecución: el dominio ' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? '')
			. ' no tiene bloque en includes/config.php (en CLI indique --host=&lt;dominio&gt;).';
	} elseif ($nfTestStage !== '') {
		$errores[] = "La ejecución terminó durante: $nfTestStage";
	}
	if (PHP_SAPI !== 'cli') {
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
	}
	nfTestReport();
});

// Versión mínima de PHP que exige vendor/ (platform_check.php de Composer); sin vendor, la del framework.
function nfTestPhpMin(string $includesDir): string
{
	$check = @file_get_contents("$includesDir/vendor/composer/platform_check.php");
	if ($check && preg_match('/PHP_VERSION_ID >= (\d+)(\d\d)(\d\d)\)/', $check, $mm)) {
		return (int) $mm[1] . '.' . (int) $mm[2] . '.' . (int) $mm[3];
	}
	return '8.3.0';
}

// vendor/autoload.php existe y se puede incluir sin que platform_check.php termine con un error fatal.
function nfTestVendorUsable(string $includesDir): bool
{
	return is_file("$includesDir/vendor/autoload.php")
		&& version_compare(PHP_VERSION, nfTestPhpMin($includesDir), '>=');
}

// Paquete apt (Debian/Ubuntu, nombres de los repositorios ondrej/sury) que trae una extensión.
function nfTestExtPackage(string $ext, string $phpver): string
{
	$ext = strtolower($ext);
	$map = [
		'dom' => 'xml', 'simplexml' => 'xml', 'xmlreader' => 'xml', 'xmlwriter' => 'xml', 'xml' => 'xml', 'xsl' => 'xml',
		'pdo_sqlite' => 'sqlite3', 'sqlite3' => 'sqlite3', 'pdo_mysql' => 'mysql', 'mysqli' => 'mysql',
		'ctype' => 'common', 'fileinfo' => 'common', 'iconv' => 'common', 'phar' => 'common', 'tokenizer' => 'common',
		'exif' => 'common', 'ftp' => 'common', 'gettext' => 'common', 'sockets' => 'common', 'calendar' => 'common',
		'opcache' => 'opcache',
	];
	return "php$phpver-" . ($map[$ext] ?? $ext);
}
function fail($msg)
{
	out("❌ $msg");
}
function ok($msg)
{
	out("✅ $msg");
}
function warn($msg)
{
	out("⚠️ $msg");
}


function return_bytes($val)
{
	$val = trim((string) $val);
	$last = strtolower(substr($val, -1));
	$val = (int) $val;
	switch ($last) {
		// The 'G' modifier is available since PHP 5.1.0
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
		case 'k':
			$val *= 1024;
	}

	return $val;
}


function checkGhostscript()
{
	// Try multiple common names: gs, gswin64c, gswin32c (Windows)
	$candidates = ['gs --version', 'gswin64c --version', 'gswin32c --version'];
	foreach ($candidates as $cmd) {
		$output = function_exists('shell_exec') ? @shell_exec("$cmd 2>&1") : null;
		if ($output) {
			ok("Ghostscript detectado: $cmd → " . trim($output));
			return true;
		}
	}
	warn("No se detectó Ghostscript en PATH (gs/gswin64c/gswin32c). Imagick no podrá leer PDFs sin Ghostscript.");
	return false;
}

function detectPolicyBlock()
{
	// Common policy.xml paths
	$paths = [
		'/etc/ImageMagick-6/policy.xml',
		'/etc/ImageMagick/policy.xml',
		'/usr/local/etc/ImageMagick/policy.xml',
		// On some distros or IM7: /etc/ImageMagick-7/policy.xml
		'/etc/ImageMagick-7/policy.xml',
	];
	$found = false;
	foreach ($paths as $path) {
		if (file_exists($path)) {
			$found = true;
			$xml = @file_get_contents($path);
			out("🔎 policy.xml: $path");
			if ($xml === false) {
				warn("No se pudo leer policy.xml (permiso denegado).");
				continue;
			}
			// Look for domain=coder and pattern=PDF with rights="none"
			if (preg_match('/<policy\s+domain="coder"\s+rights="none"\s+pattern="PDF"\s*\/>/', $xml)) {
				fail("policy.xml bloquea el manejo de PDFs (rights=\"none\").");
				out("👉 Cambia a: <policy domain=\"coder\" rights=\"read|write\" pattern=\"PDF\" /> y reinicia el servicio (apache/php-fpm).");
				return true; // blocked
			} else {
				ok("No se encontró regla de bloqueo explícito para PDF en policy.xml.");
			}
		}
	}
	if (!$found) {
		warn("No se encontró policy.xml en rutas comunes. Si hay bloqueo, vendrá de otra ubicación de configuración.");
	}
	return false; // not blocked or not found
}

function tryReadWrite($pdfPath, $outputDir)
{
	$outputFile = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'probe_page0.png';
	try {
		$imagick = new Imagick();
		// DPI razonable para pruebas
		$imagick->setResolution(150, 150);
		// Importante: especificar la primera página [0]
		$imagick->readImage($pdfPath . '[0]');
		$width = $imagick->getImageWidth();
		$height = $imagick->getImageHeight();
		ok("Lectura de PDF OK. Tamaño página 1: {$width}x{$height}px");

		// Opcional: formato/compresión antes de guardar
		$imagick->setImageFormat('png');
		$imagick->setImageCompressionQuality(90);
		if ($imagick->writeImage($outputFile)) {
			ok("Escritura OK: $outputFile");
		} else {
			fail("Imagick no pudo escribir el archivo: $outputFile");
		}

		$imagick->clear();
		$imagick->destroy();
		return true;
	} catch (ImagickException $e) {
		fail("ImagickException al leer/escribir PDF: " . $e->getMessage());
		// Sugerencias específicas
		if (stripos($e->getMessage(), 'not authorized') !== false) {
			fail("Posible bloqueo por policy.xml (\"not authorized\"). Revisa configuración de ImageMagick.");
		}
		if (
			stripos($e->Message ?? '', 'no decode delegate for this image format') !== false ||
			stripos($e->getMessage(), 'no decode delegate') !== false
		) {
			fail("Falta delegado para PDF (Ghostscript). Instala/expón Ghostscript en PATH.");
		}
		return false;
	} catch (Throwable $t) {
		fail("Error inesperado: " . $t->getMessage());
		return false;
	}
}
date_default_timezone_set('America/Monterrey');
$inipath = php_ini_loaded_file();
if (ini_get('display_errors')) {
	$errores[] = 'Desactive display_errors en ' . ($inipath ?: 'php.ini') . ': <code>display_errors=Off</code>';
}

// ---------------------------------------------------------------------------------------------------------------
// Requisitos: PHP, extensiones, dependencias de Composer y config.php. Se revisan antes de cargar vendor/,
// conectarse a MongoDB o incluir include.php; si falta algo indispensable se muestra qué instalar y cómo, y el
// script termina aquí en lugar de morir con un error fatal sin reporte.
// ---------------------------------------------------------------------------------------------------------------
$nfIncludesDir = dirname(__DIR__) . '/includes';
$phpver = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
$criticos = 0;
out('<b>Requisitos</b> (PHP ' . PHP_VERSION . ' ' . PHP_SAPI . ', php.ini: ' . ($inipath ?: 'ninguno') . ')');
if (PHP_SAPI === 'cli') {
	out("ℹ️ CLI y PHP-FPM cargan php.ini y extensiones distintos: revise también /nframework/test.php desde el navegador.");
}

// PHP
$phpMin = nfTestPhpMin($nfIncludesDir);
if (version_compare(PHP_VERSION, $phpMin, '<')) {
	fail("PHP " . PHP_VERSION . ": las dependencias requieren PHP $phpMin o superior");
	$criticos++;
	$errores[] = "Instale PHP $phpMin o superior. Ubuntu: <code>sudo add-apt-repository ppa:ondrej/php && sudo apt-get update"
		. " && sudo apt-get install php" . implode('.', array_slice(explode('.', $phpMin), 0, 2)) . "-fpm php"
		. implode('.', array_slice(explode('.', $phpMin), 0, 2)) . "-cli</code> (Debian: repositorio packages.sury.org/php)";
} else {
	ok("PHP " . PHP_VERSION . " (mínimo $phpMin)");
}

// Extensiones: las que usa el framework + las que exigen los paquetes de vendor/ (sin los de desarrollo).
$extsRequeridas = [
	'mongodb' => 'núcleo, MongoDB', 'intl' => 'idioma/formatos', 'gd' => 'imágenes', 'curl' => 'HTTP, SAT',
	'pdo_sqlite' => 'caché local', 'zip' => 'Excel/Word', 'mbstring' => 'texto', 'openssl' => 'e.firma, cifrado',
	'dom' => 'XML', 'simplexml' => 'XML', 'xmlreader' => 'Excel', 'xmlwriter' => 'Excel/Word', 'xsl' => 'CFDI',
	'imagick' => 'PDF a imagen',
];
$extsMinimas = [];
$installed = @json_decode((string) @file_get_contents("$nfIncludesDir/vendor/composer/installed.json"), true);
if (is_array($installed)) {
	$devPackages = $installed['dev-package-names'] ?? [];
	foreach ($installed['packages'] ?? $installed as $pkg) {
		if (!is_array($pkg) || in_array($pkg['name'] ?? '', $devPackages, true)) {
			continue;
		}
		foreach ($pkg['require'] ?? [] as $req => $constraint) {
			if (!str_starts_with($req, 'ext-')) {
				continue;
			}
			$ext = strtolower(substr($req, 4));
			$extsRequeridas[$ext] ??= $pkg['name'];
			if (preg_match('/(\d+\.\d+(?:\.\d+)?)/', (string) $constraint, $mm)
				&& version_compare($mm[1], $extsMinimas[$ext] ?? '0', '>')) {
				$extsMinimas[$ext] = $mm[1];
			}
		}
	}
}
ksort($extsRequeridas);
$apts = [];
$enmods = [];
$faltanExt = [];
foreach ($extsRequeridas as $ext => $uso) {
	if (!extension_loaded($ext)) {
		$faltanExt[] = $ext;
		// Paquete instalado pero extensión deshabilitada: basta phpenmod.
		if (is_file("/etc/php/$phpver/mods-available/$ext.ini")) {
			$enmods[] = $ext;
		} else {
			$apts[] = nfTestExtPackage($ext, $phpver);
		}
		fail("Extensión $ext no cargada ($uso)");
		if ($ext === 'mongodb') {
			$criticos++;
		}
	} elseif (isset($extsMinimas[$ext]) && version_compare((string) phpversion($ext), $extsMinimas[$ext], '<')) {
		fail("Extensión $ext " . phpversion($ext) . " es antigua (se requiere {$extsMinimas[$ext]} o superior)");
		$errores[] = "Actualice la extensión $ext: <code>sudo apt-get install --only-upgrade " . nfTestExtPackage($ext, $phpver)
			. "</code> o <code>sudo pecl upgrade $ext</code>";
		if ($ext === 'mongodb') {
			$criticos++;
		}
	}
}
if ($faltanExt) {
	if ($apts) {
		$errores[] = 'Instale las extensiones de PHP: <code>sudo apt-get install ' . implode(' ', array_unique($apts)) . '</code>';
	}
	if ($enmods) {
		$errores[] = 'Habilite las extensiones ya instaladas: <code>sudo phpenmod -v ' . $phpver . ' ' . implode(' ', $enmods) . '</code>';
	}
	if (in_array("php$phpver-mongodb", $apts, true)) {
		$errores[] = "Si php$phpver-mongodb no existe en sus repositorios: <code>sudo apt-get install php$phpver-dev php-pear"
			. " && sudo pecl install mongodb && echo 'extension=mongodb.so' | sudo tee /etc/php/$phpver/mods-available/mongodb.ini"
			. " && sudo phpenmod -v $phpver mongodb</code>";
	}
	$errores[] = "Después reinicie PHP: <code>sudo systemctl restart php$phpver-fpm</code>";
} else {
	ok(count($extsRequeridas) . ' extensiones de PHP cargadas');
}

// Dependencias de Composer (includes/vendor)
// Se usa includes/composer.phar (el mismo que Admin → Actualizar); --ignore-platform-reqs instala aunque el CLI
// no tenga todas las extensiones: lo que falte se reporta arriba.
$composerCmd = 'cd ' . $nfIncludesDir . ' && php composer.phar install --ignore-platform-reqs';
$composerComoInstalar = is_file("$nfIncludesDir/composer.phar") ? '' : ' Primero descargue composer.phar en includes/:'
	. ' <code>' . dirname(__DIR__) . '/install.sh --no-apt</code> (lo instala y corre composer) o'
	. ' <code>cd ' . $nfIncludesDir . ' && php -r "copy(\'https://getcomposer.org/installer\', \'composer-setup.php\');"'
	. ' && php composer-setup.php && rm composer-setup.php</code> (vea https://getcomposer.org/download/).';
if (!is_file("$nfIncludesDir/vendor/autoload.php")) {
	fail("No existen las dependencias de Composer ($nfIncludesDir/vendor)");
	$criticos++;
	$errores[] = "Instale las dependencias: <code>$composerCmd</code>." . $composerComoInstalar;
} else {
	$composerJson = @json_decode((string) @file_get_contents("$nfIncludesDir/composer.json"), true);
	$instalados = [];
	foreach (is_array($installed) ? ($installed['packages'] ?? $installed) : [] as $pkg) {
		if (is_array($pkg) && isset($pkg['name'])) {
			$instalados[strtolower($pkg['name'])] = true;
		}
	}
	$faltanPkg = [];
	foreach (array_keys($composerJson['require'] ?? []) as $pkg) {
		if ($pkg !== 'php' && !str_starts_with($pkg, 'ext-') && !isset($instalados[strtolower($pkg)])) {
			$faltanPkg[] = $pkg;
		}
	}
	if ($faltanPkg) {
		fail('Paquetes de composer.json sin instalar: ' . htmlspecialchars(implode(', ', $faltanPkg)));
		$errores[] = "Actualice las dependencias: <code>$composerCmd</code> (si el lock no los incluye: <code>php composer.phar update --ignore-platform-reqs</code>)."
			. $composerComoInstalar;
	} else {
		ok('Dependencias de Composer instaladas');
	}
}

// config.php
$configFile = stream_resolve_include_path('config.php');
if ($configFile === false) {
	fail('No se encontró config.php en el include_path (' . htmlspecialchars(get_include_path()) . ')');
	$criticos++;
	$errores[] = "Cree la configuración: <code>cp $nfIncludesDir/config.example.php $nfIncludesDir/config.php</code> y edite"
		. " mongo_connection_string, sitedb, cookie_domain y url del bloque de su dominio.";
} else {
	ok("config.php: $configFile");
}

// Herramientas externas (no indispensables)
if (!checkGhostscript()) {
	$errores[] = "Instale Ghostscript (PDF a imagen): <code>sudo apt-get install ghostscript</code>";
}
if (detectPolicyBlock()) {
	$errores[] = "Revisa y corrige policy.xml de ImageMagick para permitir manejo de PDFs.";
}

if ($criticos > 0) {
	out('<br>❌ <b>Faltan requisitos indispensables</b>: se omitieron la instalación de la base de datos y el resto del diagnóstico.'
		. ' Instale lo indicado abajo y vuelva a ejecutar este script.');
	nfTestReport();
	exit(1);
}


$includespath = get_include_path();
/*
$data = explode(PHP_EOL, file_get_contents("/proc/meminfo"));
print_r($data);
$meminfo = array();
foreach ($data as $line) {
    list($key, $val) = explode(":", $line);
    $meminfo[$key] = trim($val);
}
//*/

// Check and configure opcache settings
$opcache_settings = [
	'opcache.enable_cli' => '1',
	'opcache.jit_buffer_size' => '500000000',
	'opcache.jit' => '1235'
];

$opcache_needs_update = false;
foreach ($opcache_settings as $directive => $expected_value) {
	$current = ini_get($directive);

	if ($directive === 'opcache.jit_buffer_size') {
		// Convert to bytes for comparison
		$current_bytes = return_bytes($current);
		$expected_bytes = (int)$expected_value;
		if ($current_bytes != $expected_bytes) {
			warn("$directive actual: $current (esperado: $expected_value)");
			$opcache_needs_update = true;
		} else {
			ok("$directive configurado correctamente: $current");
		}
	} else if ($directive === 'opcache.jit') {
		// JIT can be 'disable' or a number
		if ($current === 'disable' || $current != $expected_value) {
			warn("$directive actual: $current (esperado: $expected_value)");
			$opcache_needs_update = true;
		} else {
			ok("$directive configurado correctamente: $current");
		}
	} else {
		// opcache.enable_cli - simple comparison
		if ($current != $expected_value) {
			warn("$directive actual: $current (esperado: $expected_value)");
			$opcache_needs_update = true;
		} else {
			ok("$directive configurado correctamente: $current");
		}
	}
}

if ($opcache_needs_update && !$inipath) {
	$errores[] = 'PHP no cargó ningún php.ini (<code>php --ini</code>): agregue opcache.enable_cli=1, opcache.jit_buffer_size=500000000'
		. ' y opcache.jit=1235 al php.ini de su instalación.';
} elseif ($opcache_needs_update) {
	$contents = file_get_contents($inipath);
	$updated = false;

	foreach ($opcache_settings as $directive => $value) {
		// Check if directive exists in file
		if (preg_match("/^;?\s*$directive\s*=.*$/m", $contents)) {
			// Replace existing (commented or not)
			$contents = preg_replace("/^;?\s*$directive\s*=.*$/m", "$directive=$value", $contents);
			$updated = true;
		} else {
			// Add new directive
			$contents .= "\n$directive=$value\n";
			$updated = true;
		}
	}

	if ($updated) {
		if (@file_put_contents($inipath, $contents)) {
			ok("php.ini actualizado con configuración de opcache");
			$errores[] = "Reinicia PHP para aplicar cambios: sudo systemctl restart php" . number_format((float)phpversion(), 1) . "-fpm";
		} else {
			fail("No se pudo escribir en php.ini (permisos insuficientes)");
			$directives = [];
			foreach ($opcache_settings as $directive => $value) {
				$directives[] = "$directive=$value";
			}
			$errores[] = "Edita manualmente $inipath y agrega:<br><code>" . implode('<br>', $directives) . "</code>";
		}
	}
}

// include_path con includes/ del framework para CLI, FPM y Apache de cada versión de PHP instalada.
// Se escribe conf.d/99-nframework.ini (se lee después de php.ini, así que gana) en lugar de editar php.ini.
$nfIncludes = realpath(dirname(__DIR__) . '/includes');
foreach (glob('/etc/php/*/{cli,fpm,apache2}', GLOB_BRACE | GLOB_ONLYDIR) ?: [] as $sapidir) {
	$sapi = basename($sapidir);
	$v = basename(dirname($sapidir));
	// Valor efectivo: el último include_path entre php.ini y conf.d/*.ini (en orden alfabético, como PHP).
	$actual = '.:/usr/share/php';
	foreach (array_merge([$sapidir . '/php.ini'], glob($sapidir . '/conf.d/*.ini') ?: []) as $inifile) {
		$ini = @parse_ini_file($inifile, false, INI_SCANNER_RAW);
		if (isset($ini['include_path'])) {
			$actual = trim($ini['include_path'], '"\'');
		}
	}
	$paths = array_values(array_filter(array_map(fn($p) => rtrim($p, '/') ?: $p, explode(':', $actual)), 'strlen'));
	if (in_array($nfIncludes, $paths, true)) {
		ok("include_path de PHP $v $sapi incluye $nfIncludes");
		continue;
	}
	$linea = 'include_path = "' . implode(':', array_unique(array_merge(['.', $nfIncludes], $paths))) . '"';
	$dropin = "$sapidir/conf.d/99-nframework.ini";
	if (@file_put_contents($dropin, "; Generado por nframework/test.php\n$linea\n") !== false) {
		ok("include_path agregado en $dropin");
		if ($sapi === 'fpm') {
			$errores[] = "Reinicia PHP para aplicar cambios: sudo systemctl restart php$v-fpm";
		} elseif ($sapi === 'apache2') {
			$errores[] = "Reinicia Apache para aplicar cambios: sudo systemctl restart apache2";
		}
	} else {
		warn("include_path de PHP $v $sapi no incluye $nfIncludes");
		$errores[] = "Agrega includes/ al include_path de PHP $v $sapi:<br><code>"
			. htmlspecialchars("echo '$linea' | sudo tee $dropin") . '</code>';
	}
}





$nfTestStage = 'config.php';
require_once $configFile;
$nfTestStage = 'vendor/autoload.php';
require_once $nfIncludesDir . '/vendor/autoload.php';
$nfTestStage = '';
foreach (['mongo_connection_string', 'sitedb', 'cookie_domain', 'url'] as $clave) {
	if (empty($config[$clave])) {
		$errores[] = "Falta \$config['$clave'] en el bloque de " . htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') . " de $configFile";
	}
}

if (ini_get('auto_append_file') == '') {
	$errores[] = 'auto_append_file =/var/www/html/includes/append_file.php';
}
if (extension_loaded('imagick')) {
	ok('Imagick extension is loaded');
	$imagick = new Imagick();
	out("Memory limit: " . $imagick->getResourceLimit(Imagick::RESOURCETYPE_MEMORY) . " MB\n");
	out("Map limit: " . $imagick->getResourceLimit(Imagick::RESOURCETYPE_MAP) . " MB\n");
	out("Disk limit: " . $imagick->getResourceLimit(Imagick::RESOURCETYPE_DISK) . " MB\n");
	out("Thread limit: " . $imagick->getResourceLimit(Imagick::RESOURCETYPE_THREAD) . "\n");
}

// Conexión a MongoDB: sin ella no se instala la base de datos ni se carga include.php.
$dbOk = false;
if (!empty($config['sitedb']) && !empty($config['mongo_connection_string'])) {
	try {
		$m = new MongoDB\Client($config['mongo_connection_string'], [], ['serverSelectionTimeoutMS' => 5000]);
		$m->selectDatabase($config['sitedb'])->command(['ping' => 1]);
		ok('Conexión a MongoDB (base ' . htmlspecialchars($config['sitedb']) . ')');
		$dbOk = true;
	} catch (Throwable $e) {
		fail('No se pudo conectar a MongoDB: ' . htmlspecialchars($e->getMessage()));
		$errores[] = 'Revise que MongoDB esté instalado y en ejecución (<code>sudo systemctl status mongod</code>; instalación:'
			. ' https://www.mongodb.com/docs/manual/administration/install-on-linux/) y que mongo_connection_string de'
			. ' config.php sea correcta (usuario, contraseña, authSource).';
	}
}
if ($dbOk) {
	try {
		$guest = $m->{$config['sitedb']}->users->findOne(['username' => 'guest']);
		if (empty($guest)) {
			$errores[] = "guest no existe";
			$m->{$config['sitedb']}->users->insertOne(['username' => 'guest']);
			$errores[] = "guest creado error solucionado actualiza la pagina";
			//$m->{$config['sitedb']}->users->createIndex(["username" => 1], ['unique' => true]);
		}

		$admin = $m->{$config['sitedb']}->users->findOne(['username' => 'admin']);
		$adminCreado = empty($admin);
		if ($adminCreado) {
			$errores[] = "admin no existe";


			// Contraseña aleatoria (antes era siempre "admin"). Se muestra una sola vez.
			$adminPassword = substr(strtr(base64_encode(random_bytes(18)), '+/', 'Kx'), 0, 20);
			$adminid = new  MongoDB\BSON\ObjectID();
			$m->{$config['sitedb']}->users->insertOne([
				'username' => 'admin',
				'_id' => $adminid,
				'password' => password_hash($adminPassword, PASSWORD_DEFAULT)
			]);
			$errores[] = "admin creado. Usuario: <b>admin</b> Contraseña: <b><code>" . htmlspecialchars($adminPassword) . "</code></b> — guárdela ahora, no se volverá a mostrar.";
		} else {
			$adminid = $admin->_id;
			$adminHash = (string) ($admin->password ?? '');
			if ($adminHash === hash('sha512', 'admin') || (password_get_info($adminHash)['algo'] && password_verify('admin', $adminHash))) {
				$errores[] = '<span style="color:red">El usuario admin aún tiene la contraseña predeterminada "admin". Cámbiela en Admin → Usuarios.</span>';
			}
		}
		$gadmin = $m->{$config['sitedb']}->usersgroups->findOne(['name' => 'admins']);
		if (empty($gadmin)) {
			$m->{$config['sitedb']}->usersgroups->insertOne([
				'name' => 'admins',
				'description' => 'administrators',
				'users' => [$adminid]
			]);
			//$m->{$config['sitedb']}->usersgroups->createIndex(["name" => 1], ['unique' => true]);
		} else {
			// Grupo vacío, o admin recreado (el grupo conserva el _id del admin borrado): se agrega el nuevo.
			if ($adminCreado || count($gadmin->users ?? []) == 0) {
				$m->{$config['sitedb']}->usersgroups->updateOne(['name' => 'admins'], [
					'$addToSet' => [
						'users' => $adminid
					]
				]);
			}
		}


		$gadmin = $m->{$config['sitedb']}->usersgroups->findOne(['name' => 'developers']);
		if (empty($gadmin)) {
			$m->{$config['sitedb']}->usersgroups->insertOne([
				'name' => 'developers',
				'description' => 'developers',
				'users' => [$adminid]
			]);
		} elseif ($adminCreado) {
			$m->{$config['sitedb']}->usersgroups->updateOne(['name' => 'developers'], ['$addToSet' => ['users' => $adminid]]);
		}

		/*$rules = $m->{$config['sitedb']}->securityrules->findOne([]);
		if (empty($rules)) {
			$tmprules = json_decode(file_get_contents('includes/default_security_rules.php'), true);
			foreach ($tmprules as $rule) {
				$m->{$config['sitedb']}->securityrules->insertOne($rule);
			}
		}
		*/
	} catch (Throwable $e) {
		$errores[] = 'Excepción capturada: ' .  htmlspecialchars($e->getMessage());
	}

	// Lista única con el diagnóstico de Admin → Inicio (includes/class.nfIndexes.php).
	require_once 'class.nfIndexes.php';
	$indexes = nfIndexes::required($config);
	// Migraciones de datos (upgrades/*.php del framework y del sitio): las que falten, una sola vez cada una.
	try {
		// En web esto corre antes de include.php (sin el autoload del framework).
		require_once 'class.nfUpgrader.php';
		$nfUp = new nfUpgrader($m->{$config['sitedb']}, nfUpgrader::defaultDirs($_SERVER['DOCUMENT_ROOT'] ?? ''), $_SERVER['DOCUMENT_ROOT'] ?? '');
		$nfUpRun = $nfUp->run();
		foreach ($nfUpRun['log'] as $line) {
			out(htmlspecialchars($line) . "\n");
		}
		if (!$nfUpRun['ok']) {
			$errores[] = 'Migración fallida: ' . htmlspecialchars((string) ($nfUpRun['failed'] ?? '')) . ' (revise Admin → Actualizar → Migraciones)';
		}
	} catch (Throwable $e) {
		$errores[] = 'Migraciones: ' . htmlspecialchars($e->getMessage());
	}
	foreach ($indexes as $idx) {
		try {
			$result = $m->{$config['sitedb']}->{$idx['collection']}->createIndex(
				$idx['key'],
				$idx['options']
			);
			out("Index created: $result\n");
		} catch (MongoDB\Driver\Exception\CommandException $e) {
			// TTL ya existente con otros días (IndexOptionsConflict): createIndex no lo cambia, collMod sí.
			if ($e->getCode() === 85 && isset($idx['options']['expireAfterSeconds'])) {
				try {
					$m->{$config['sitedb']}->command(['collMod' => $idx['collection'], 'index' => [
						'keyPattern' => $idx['key'],
						'expireAfterSeconds' => $idx['options']['expireAfterSeconds'],
					]]);
					out('Index TTL updated: ' . $idx['collection'] . ' ' . $idx['options']['expireAfterSeconds'] . "s\n");
					continue;
				} catch (Throwable $e2) {
					$e = $e2;
				}
			}
			out("Error on Index " . $e->getMessage() . "\n");
			continue;
		}
	}
}

// Archivos que no deben descargarse por web (.git, includes/, *.log, composer.*, ocultos): se piden al propio
// sitio ($config['url']) y cualquier respuesta 200 es un error. Ver nfIsBlockedPath() y docs/security.php.
function nfTestHttpStatus(string $url): int
{
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_NOBODY => true,
		CURLOPT_RETURNTRANSFER => true,
		// http → https y similares: cuenta la respuesta final. Solo se lee el estado, no se verifica el certificado.
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 3,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => 0,
		CURLOPT_TIMEOUT => 5,
		CURLOPT_USERAGENT => 'nframework-test',
	]);
	curl_exec($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	curl_close($ch);
	return $status;
}
// Sin 'url' en config.php, el host de la petición (en CLI, el de --host), como nfSiteHost().
$siteUrl = rtrim((string) ($config['url'] ?? ''), '/');
if ($siteUrl === '' && preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $_SERVER['HTTP_HOST'] ?? '')) {
	$siteUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
}
if ($siteUrl === '' || !function_exists('curl_init')) {
	warn("No se revisaron las rutas protegidas: falta \$config['url'] o la extensión curl");
} else {
	$docroot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
	$protegidas = ['/includes/config.php', '/includes/composer.json', '/includes/vendor/autoload.php', '/composer.json'];
	if (is_dir($docroot . '/.git')) {
		$protegidas[] = '/.git/HEAD';
	}
	// Archivos de prueba en el document root: un .txt de control (debe responder 200; si no, la URL no sirve
	// este directorio y la prueba no dice nada), uno oculto y un .log. Se borran al terminar.
	$probe = 'nfprobe-' . bin2hex(random_bytes(4));
	$probes = ["/$probe.txt" => 'control', "/.$probe" => 'oculto', "/$probe.log" => 'log'];
	$creados = [];
	foreach ($probes as $file => $tipo) {
		if (@file_put_contents($docroot . $file, "nframework test\n") !== false) {
			$creados[] = $docroot . $file;
		}
	}
	try {
		if (count($creados) === count($probes)) {
			$control = nfTestHttpStatus($siteUrl . "/$probe.txt");
			if ($control === 200) {
				$protegidas["/.$probe"] = 'archivo oculto de prueba';
				$protegidas["/$probe.log"] = 'archivo .log de prueba';
			} else {
				warn("$siteUrl/$probe.txt respondió HTTP $control (se esperaba 200): $siteUrl no sirve $docroot, no se probaron archivos ocultos ni .log");
			}
		} else {
			warn("No se pudieron crear archivos de prueba en $docroot: no se probaron archivos ocultos ni .log");
		}
		$expuestas = [];
		foreach ($protegidas as $path => $nombre) {
			if (is_int($path)) {
				$path = $nombre;
			}
			$status = nfTestHttpStatus($siteUrl . $path);
			if ($status === 0) {
				warn("No se pudo conectar a $siteUrl$path");
			} elseif ($status >= 200 && $status < 300) {
				fail("$nombre se puede descargar (HTTP $status)");
				$expuestas[] = $nombre;
			} else {
				ok("$nombre bloqueado (HTTP $status)");
			}
		}
		if ($expuestas) {
			$errores[] = 'Rutas protegidas accesibles por web: ' . htmlspecialchars(implode(', ', $expuestas))
				. '. Agregue a nginx el bloque de docs/security.php ("Servidor web").';
		}
	} finally {
		foreach ($creados as $file) {
			@unlink($file);
		}
	}
}

// La sesión la inicia include.php (al final).

$a = ini_get('post_max_size');
$b = ini_get('upload_max_filesize');
out(date("Y-m-d H:i:s") . '<br>
Capacidad de post_max_size:' . $a . '<br>
Capacidad de upload_max_filesize:' . $b . '<br>
Tu capacidad de subida es de:' .
	(return_bytes($a) < return_bytes($b) ?
		$a . '<br>Determinada por post_max_size' :
		$b . '<br>Determinada por upload_max_filesize') .
	'<br>');
if ($dbOk) {
	$nfTestStage = 'include.php';
	require_once 'include.php';
	$nfTestStage = '';
	out("sid: " . session_id() . '<br>Lenguaje: ' . ($_SESSION['nf']['browser']['language'] ?? ''));
}
nfTestReport();
