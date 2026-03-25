<?php
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);//*/
$buffers = '';
function out($msg)
{
	global $buffers;
	$buffers .= $msg . '<br>';
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


$errores = [];
function return_bytes($val)
{
	$val = (int)trim($val);
	$last = strtolower($val[strlen($val) - 1]);
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
		$output = @shell_exec("$cmd 2>&1");
		if ($output) {
			out(ok("Ghostscript detectado: $cmd → " . trim($output)));
			return true;
		}
	}
	out(warn("No se detectó Ghostscript en PATH (gs/gswin64c/gswin32c). Imagick no podrá leer PDFs sin Ghostscript."));
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
				out(ok("No se encontró regla de bloqueo explícito para PDF en policy.xml."));
			}
		}
	}
	if (!$found) {
		out(warn("No se encontró policy.xml en rutas comunes. Si hay bloqueo, vendrá de otra ubicación de configuración."));
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
		out(ok("Lectura de PDF OK. Tamaño página 1: {$width}x{$height}px"));

		// Opcional: formato/compresión antes de guardar
		$imagick->setImageFormat('png');
		$imagick->setImageCompressionQuality(90);
		if ($imagick->writeImage($outputFile)) {
			out(ok("Escritura OK: $outputFile"));
		} else {
			out(fail("Imagick no pudo escribir el archivo: $outputFile"));
		}

		$imagick->clear();
		$imagick->destroy();
		return true;
	} catch (ImagickException $e) {
		out(fail("ImagickException al leer/escribir PDF: " . $e->getMessage()));
		// Sugerencias específicas
		if (stripos($e->getMessage(), 'not authorized') !== false) {
			out(fail("Posible bloqueo por policy.xml (\"not authorized\"). Revisa configuración de ImageMagick."));
		}
		if (
			stripos($e->Message ?? '', 'no decode delegate for this image format') !== false ||
			stripos($e->getMessage(), 'no decode delegate') !== false
		) {
			out(fail("Falta delegado para PDF (Ghostscript). Instala/expón Ghostscript en PATH."));
		}
		return false;
	} catch (Throwable $t) {
		out(fail("Error inesperado: " . $t->getMessage()));
		return false;
	}
}
$inipath = php_ini_loaded_file();
$archivo = file_get_contents($inipath);
date_default_timezone_set('America/Monterrey');
$exts = get_loaded_extensions();
if (ini_get('display_errors')) {
	$errores[] = "display_errors=Off";
}


$memory_limit = ini_get('memory_limit');

$inineeds = [
	'display_errors',
	'memory_limit',
	'opcache.jit',
	'auto_append_file',
	'post_max_size',
	'upload_max_filesize'
];



/*$iniPath = php_ini_loaded_file(); // Get path to active php.ini
$directive = 'memory_limit';
$newValue = '512M';

/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//*/

$phpver = number_format((float)phpversion(), 1);
$apts = [];


$depends = [
	'mongodb' => "php$phpver-mongodb",
	'intl' => "php$phpver-intl",
	'gd' => "php$phpver-gd",
	'curl' => "php$phpver-curl",
	'pdo_sqlite' => "php$phpver-sqlite",
	'zip' => "php$phpver-zip",
	'mbstring' => "php$phpver-mbstring",
	'libxml' => "php$phpver-xml",
	'imagick' => 'php-imagick'
];

foreach ($depends as $ext => $command) {
	if (!in_array($ext, $exts)) {
		$apts[] = $command;
	}
}

$classMongoExists = class_exists('MongoDB\\Driver\\Manager');
if (!$classMongoExists) {
	$errores[] = "MongoDB extension no está instalada o no se cargó (clase MongoDB\\Driver\\Manager no encontrada).";
}



if (count($apts) > 0) {
	$errores[] = 'sudo apt-get install ' . implode(' ', $apts);
}



if (!checkGhostscript()) {
	$errores[] = "Instala Ghostscript y asegúrate que el comando 'gs' esté en PATH.";
}
if (detectPolicyBlock()) {
	$errores[] = "Revisa y corrige policy.xml de ImageMagick para permitir manejo de PDFs.";
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
			out(warn("$directive actual: $current (esperado: $expected_value)"));
			$opcache_needs_update = true;
		} else {
			out(ok("$directive configurado correctamente: $current"));
		}
	} else if ($directive === 'opcache.jit') {
		// JIT can be 'disable' or a number
		if ($current === 'disable' || $current != $expected_value) {
			out(warn("$directive actual: $current (esperado: $expected_value)"));
			$opcache_needs_update = true;
		} else {
			out(ok("$directive configurado correctamente: $current"));
		}
	} else {
		// opcache.enable_cli - simple comparison
		if ($current != $expected_value) {
			out(warn("$directive actual: $current (esperado: $expected_value)"));
			$opcache_needs_update = true;
		} else {
			out(ok("$directive configurado correctamente: $current"));
		}
	}
}

if ($opcache_needs_update) {
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
		if (file_put_contents($inipath, $contents)) {
			ok("php.ini actualizado con configuración de opcache");
			$errores[] = "Reinicia PHP para aplicar cambios: sudo systemctl restart php" . number_format((float)phpversion(), 1) . "-fpm";
		} else {
			out(fail("No se pudo escribir en php.ini (permisos insuficientes)"));
			$errores[] = "Edita manualmente $inipath y agrega: <br><textarea>" . $contents . "</textarea>";
		}
	}
}





include('config.php');
if (!isset($config)) {
	$includepaths[] = explode(':', $includepath);
	//TODO:buscar path

	$errores[] = 'config.php not found OR include_path = "' . $includespath . '"';
}

if ((include 'vendor/autoload.php') != TRUE) {
	$errores[] = 'composer update --ignore-platform-reqs';
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
} else {
	$errores[] = 'Imagick extension is not loaded';
}

if ($config['sitedb'] == '') {
	$errores[] = '$config[sitedb] no configurada';
} else {
	try {
		$m = new MongoDB\Client($config['mongo_connection_string']);



		$guest = $m->{$config['sitedb']}->users->findOne(['username' => 'guest']);
		if (empty($guest)) {
			$errores[] = "guest no existe";
			$m->{$config['sitedb']}->users->insertOne(['username' => 'guest']);
			$errores[] = "guest creado error solucionado actualiza la pagina";
			//$m->{$config['sitedb']}->users->createIndex(["username" => 1], ['unique' => true]);
		}

		$admin = $m->{$config['sitedb']}->users->findOne(['username' => 'admin']);
		if (empty($admin)) {
			$errores[] = "admin no existe";


			$adminid = new  MongoDB\BSON\ObjectID();
			$m->{$config['sitedb']}->users->insertOne([
				'username' => 'admin',
				'_id' => $adminid,
				'password' => 'c7ad44cbad762a5da0a452f9e854fdc1e0e7a52a38015f23f3eab1d80b931dd472634dfac71cd34ebc35d16ab7fb8a90c81f975113d6c7538dc69dd8de9077ec'
			]);
			$errores[] = "admin creado error solucionado actualiza la pagina";
		} else {
			$adminid = $admin->_id;
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
			if (count($gadmin->users) == 0) {
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
		}

		/*$rules = $m->{$config['sitedb']}->securityrules->findOne([]);
		if (empty($rules)) {
			$tmprules = json_decode(file_get_contents('includes/default_security_rules.php'), true);
			foreach ($tmprules as $rule) {
				$m->{$config['sitedb']}->securityrules->insertOne($rule);
			}
		}
		*/
	} catch (Exception $e) {
		$errores[] = 'Excepción capturada: ' .  $e->getMessage();
	}

	$indexes = [
		['colletion' => 'users', 'key' => ['username' => 1], 'options' => ['unique' => true]],
		['colletion' => 'pages', 'key' => ['path' => 1]],
		['colletion' => 'usergroups', 'key' => ['name' => 1]],
		['colletion' => 'nfuristats', 'key' => ['ip' => 1, 'created_at' => 1]],
		['colletion' => 'nfuristats', 'key' => ['created_at' => 1, 'ip' => 1]],
		['colletion' => 'nfsecurityrules', 'key' => ['enabled' => 1]],
	];

	foreach ($indexes as $idx) {
		if (!isset($idx['options'])) {
			$idx['options'] = [];
		}
		try {
			$result = $m->{$config['sitedb']}->{$idx['colletion']}->createIndex(
				$idx['key'],
				$idx['options']
			);
			out("Index created: $result\n");
		} catch (MongoDB\Driver\Exception\CommandException $e) {
			// If the index already exists → don’t stop
			if (str_contains($e->getMessage(), 'already exists')) {
				out("Index already exists, skipping...\n");
				continue;
			}

			// For other errors, rethrow (to avoid hiding real problems)
			throw $e;
		}
	}
}

use Nlared\MongoSessionHandler;

$sessions = $m->{$config['sitedb']}->sessions;
$handler = new MongoSessionHandler($sessions);
session_set_save_handler($handler);
session_name(str_replace('.', '_', $config['cookie_domain']));
session_set_cookie_params(0, '/', $config['cookie_domain'], $nframework->https, false);
session_start();


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
if (count($errores) > 0) {
	foreach ($errores  as $errs) {
		out($errs . '<br>');
	}
} else {
	out("No se encontraron errores de configuración");
}
require 'include.php';
out("sid: " . session_id() . '<br>Lenguaje: ' . $_SESSION['nf']['browser']['language']);
echo $buffers;
