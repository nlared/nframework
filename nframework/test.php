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
 */
if (PHP_SAPI === 'cli') {
	$cliHost = null;
	foreach (array_slice($argv, 1) as $arg) {
		if ($arg === '-h' || $arg === '--help') {
			echo "Uso: php " . $argv[0] . " --host=<dominio>\n"
				. "  --host=<dominio>  Dominio del sitio a instalar/revisar (elige el bloque de includes/config.php).\n"
				. "                    También se acepta el dominio como primer argumento.\n";
			exit(0);
		}
		if (str_starts_with($arg, '--host=')) {
			$cliHost = substr($arg, 7);
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
	$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
	// En CLI el include_path puede no tener includes/ (se configura en el php.ini de FPM).
	set_include_path(dirname(__DIR__) . '/includes' . PATH_SEPARATOR . get_include_path());
	echo "Host: $cliHost\n";
}
if (PHP_SAPI !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
	@include 'config.php';
	require_once 'vendor/autoload.php';
	$nfInstalled = false;
	try {
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
		if (@file_put_contents($inipath, $contents)) {
			ok("php.ini actualizado con configuración de opcache");
			$errores[] = "Reinicia PHP para aplicar cambios: sudo systemctl restart php" . number_format((float)phpversion(), 1) . "-fpm";
		} else {
			out(fail("No se pudo escribir en php.ini (permisos insuficientes)"));
			$directives = [];
			foreach ($opcache_settings as $directive => $value) {
				$directives[] = "$directive=$value";
			}
			$errores[] = "Edita manualmente $inipath y agrega:<br><code>" . implode('<br>', $directives) . "</code>";
		}
	}
}





include('config.php');
if (!isset($config)) {
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
		// User::in() consulta usersgroups por usuario y nombre en casi cada página.
		['colletion' => 'usersgroups', 'key' => ['users' => 1, 'name' => 1]],
		['colletion' => 'nf_attempts', 'key' => ['ip' => 1]],
	];
	// Opcional: borrar automáticamente las estadísticas de peticiones con más de N días.
	if (!empty($config['nfuristats_ttl_days'])) {
		$indexes[] = ['colletion' => 'nfuristats', 'key' => ['createdAt' => 1], 'options' => ['expireAfterSeconds' => (int) $config['nfuristats_ttl_days'] * 86400]];
	}

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
			out("Error on Index " . $e->getMessage());
			continue;
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
if (count($errores) > 0) {
	foreach ($errores  as $errs) {
		out($errs . '<br>');
	}
} else {
	out("No se encontraron errores de configuración");
}
require_once 'include.php';
out("sid: " . session_id() . '<br>Lenguaje: ' . $_SESSION['nf']['browser']['language']);
if (PHP_SAPI === 'cli') {
	echo html_entity_decode(strip_tags(preg_replace('/(<br\s*\/?>\s*)+/i', "\n", $buffers))), "\n";
} else {
	echo $buffers;
}
