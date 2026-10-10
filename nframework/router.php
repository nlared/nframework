<?php

use Intervention\Image\ImageManager;
use OTPHP\TOTP;
use Endroid\QrCode\Writer\PngWriter;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'include.php';
$loader1 = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$loader2 = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates/panda');
$loader3 = new \Twig\Loader\FilesystemLoader($nframework->include_path . '/i18n/' . $nframework->lang);
$loader = new \Twig\Loader\ChainLoader([$loader1, $loader2, $loader3]);
// Plantillas compiladas en la caché local: compilar en cada petición costaba ~20 ms.
// auto_reload las recompila cuando cambia el .html. $config['twig_cache'] = false la desactiva.
$twig = new \Twig\Environment($loader, [
	'cache' => (($config['twig_cache'] ?? true) !== false ? nfCacheDir('twig') : null) ?? false,
	'debug' => !empty($developermode),
	'auto_reload' => true,
]);

function replaceVarsAtUrl($url, $vars)
{
	foreach ($vars as $clave => $valor) {
		// Reemplaza {clave} por el valor codificado
		$url = str_replace("{" . $clave . "}", urlencode((string) $valor), $url);
	}
	return $url;
}

/**
 * Crea un PHPMailer configurado con los datos SMTP del sitio.
 */
function nfMailer(): PHPMailer
{
	global $config;
	$mail = new PHPMailer(true);
	$mail->isSMTP();
	$mail->CharSet = 'UTF-8';
	$mail->Host = $config['smtp']['host'];
	$mail->SMTPAuth = boolval($config['smtp']['auth']);
	$mail->Username = $config['smtp']['username'];
	$mail->Password = $config['smtp']['password'];
	if (!empty($config['smtp']['secure']) && $config['smtp']['secure'] == 'ssl') {
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
	} elseif (!empty($config['smtp']['secure']) && $config['smtp']['secure'] == 'tls') {
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
	}
	$mail->Port = $config['smtp']['port'];
	$mail->setFrom($config['smtp']['fromemail'], $config['smtp']['fromname']);
	$mail->isHTML(true);
	return $mail;
}

/**
 * Marca la sesión como autenticada (regenerando el id para evitar fijación de sesión)
 * y redirige al destino guardado o al inicio correspondiente.
 */
function nfCompleteLogin(User $user): void
{
	session_regenerate_id(true);
	$_SESSION['user'] = $user->_id;
	unset($_SESSION['tmp_user']);
	$redir = $_SESSION['login_redirect'] ?? '';
	$_SESSION['login_redirect'] = '';
	session_write_close();
	if ($redir != '' && $redir != '/account/login.php' && $redir != '/account/login') {
		$redir = nfSafeRedirect($redir);
		if (strpos($redir, '/') !== 0) {
			$redir .= (str_contains($redir, '?') ? '&' : '?') . 'uid=' . encryptSessionId($user->_id, SESSION_KEY);
		}
		header('Location: ' . $redir);
	} elseif ($user->in('admins')) {
		header('Location: /admin/');
	} else {
		header('Location: /');
	}
	exit();
}

/**
 * Limita el tamaño de imágenes generadas dinámicamente para evitar agotar memoria/CPU.
 */
function nfClampImageSize($size, int $max = 2048): int
{
	return max(1, min($max, (int) $size));
}


//https://github.com/alexdodonov/mezon-router#routing--


$router = new \Mezon\Router\Router();

$router->addRoute('index', function ($route, $variables) {
	global $twig, $nframework, $config;

	$header = nfPage('_header');
	$footer = nfPage('_footer');
	$parallax = nfPage('_parallax');

	$nframework->usecommon = true;
	$template = $twig->load('page.html');

	$page = null;
	if ($config['homepagetype'] == 'page') {
		$page = nfPage('_home');
		$nframework->metas['description'] = $page['description'] ?? null;
		$nframework->metas['title'] = $page['title'] ?? null;
		$nframework->metas['keywords'] = $page['keywords'] ?? null;
	}

	echo $template->render([
		'theme' => $config['theme'],
		'parallaxpage' => $parallax['html'] ?? null,
		'page' => $page['html'] ?? null,
		'header' => renderEmbeddedFunctions((string) ($header['html'] ?? '')),
		'footer' => $footer['html'] ?? null,
		'menu' => nfMetroMenu('_nav'),
		'route' => 'index.php'
	]);
}, 'GET');


$router->addRoute('/main.js', function (string $route, array $p) {
	global $twig, $config, $nframework;
	$js = $twig->load('main.js')->render([
		'publicKey' => $config['notifications']['publicKey']
	]);
	$nframework->serveContent($js, 'text/javascript; charset=utf-8');   // 304 si no cambió
}, 'GET');

$router->addRoute('/account/login', function (string $route, array $p) {
	global $twig, $config, $nframework;
	$msgError = '';
	if (!empty($_POST['login'])) {
		$login = $_POST['login'];
		$user = null;
		if (!nflogAttempt('login:' . $GLOBALS['ip'], 10, 900)) {
			$msgError = 'Demasiados intentos, intente más tarde.';
		} else {
			$user = User::authenticate($login['username'] ?? null, $login['password'] ?? null);
		}

		if ($user !== null && !empty($user->_id)) {
			nflogReset('login:' . $GLOBALS['ip']);
			// active === false: registrada por /account/signup y aún sin confirmar el correo.
			if ((!empty($user->disabled) && $user->disabled == true) || $user->active === false) {
				$msgError = 'La cuenta no está activada.';
				$nframework->usecommon = true;
				$template = $twig->load('login.html');
				$oauths = [
					'google' => $config['google_oauth_client_enable'],
					'facebook' => $config['facebook_oauth_client_enable'],
				];
				echo $template->render([
					'nframework' => [
						'themeSwitcher' => $nframework->themeSwitcher()
					],
					'lng' => $nframework->language(),
					'config' => $config, //TODO: Solo pasar lo necesario
					'oauths' => $oauths,
					'msgError' => $msgError
				]);
				exit();
			}

			if (!empty($user->twofa_secret)) {
				session_regenerate_id(true);
				$_SESSION['tmp_user'] = $user->_id;
				header('location: /account/twofa');
				exit();
			}
			nfCompleteLogin($user);
		}
		if ($msgError == '') {
			$msgError = 'Datos incorrectos';
		}
	}
	if (!empty($_GET['login_redirect']) && is_string($_GET['login_redirect'])) {
		$_SESSION['login_redirect'] = (string) decryptSessionId($_GET['login_redirect'], SESSION_KEY);
	}
	if (!empty($_SESSION['user'])) {
		$current = new User(['_id' => $_SESSION['user']]);
		if (!empty($current->_id)) {
			nfCompleteLogin($current);
		}
	}

	$nframework->usecommon = true;
	$template = $twig->load('login.html');
	$oauths = [
		'google' => $config['google_oauth_client_enable'],
		'facebook' => $config['facebook_oauth_client_enable'],
	];
	echo $template->render([
		'nframework' => [
			'themeSwitcher' => $nframework->themeSwitcher()
		],
		'lng' => $nframework->language(),
		'config' => $config, //TODO: Solo pasar lo necesario
		'oauths' => $oauths,
		'msgError' => $msgError
	]);
	//print_r($config);
}, ['GET', 'POST']);

$router->addRoute('/account/twofa', function (string $route, array $p) {
	global $twig, $config, $nframework;
	if (empty($_SESSION['tmp_user'])) {
		header('location: /account/login');
		exit();
	}
	$msgError = '';
	if (!empty($_POST['code']) && is_string($_POST['code'])) {
		$user = new User([
			'_id' => $_SESSION['tmp_user']
		]);
		if (!nflogAttempt('twofa:' . $GLOBALS['ip'], 10, 900)) {
			$msgError = 'Demasiados intentos, intente más tarde.';
		} elseif (!empty($user->twofa_secret) && TOTP::create($user->twofa_secret)->verify(trim($_POST['code']))) {
			nfCompleteLogin($user);
		} else {
			$msgError = 'Código incorrecto';
		}
	}

	$nframework->usecommon = true;
	$template = $twig->load('twofa.html');
	echo $template->render([
		'nframework' => [
			'themeSwitcher' => $nframework->themeSwitcher()
		],
		'msgError' => $msgError,
		'lng' => $nframework->language(),
	]);
}, ['GET', 'POST']);


$router->addRoute('/account/signup', function (string $route, array $p) {
	global $twig, $config, $nframework, $m;
	$lng = $nframework->language();
	$msgError = '';
	if (!empty($_POST['signup']) && is_array($_POST['signup'])) {
		$signup = array_map(fn($v) => is_string($v) ? $v : '', $_POST['signup'] + ['username' => '', 'name' => '', 'password' => '', 'confirmpassword' => '']);
		if (!nflogAttempt('signup:' . $GLOBALS['ip'], 5, 3600)) {
			$msgError = 'Demasiados intentos, intente más tarde.';
		} elseif ($signup['password'] != $signup['confirmpassword']) {
			$msgError = 'Las contraseñas no coinciden';
		} elseif (strlen($signup['password']) < NF_PASSWORD_MIN_LENGTH) {
			$msgError = $lng['password_too_short'];
		} elseif (empty($signup['username']) || !filter_var($signup['username'], FILTER_VALIDATE_EMAIL)) {
			$msgError = 'Debe indicar un email válido';
		} else {
			$username = strtolower(trim($signup['username']));
			// Sin distinguir mayúsculas: las cuentas anteriores pueden estar guardadas con mayúsculas.
			$user = new User([
				'username' => new MongoDB\BSON\Regex('^' . preg_quote($username, '/') . '$', 'i'),
			]);
			if (!empty($user->_id)) {
				$msgError = 'Ya existe un usuario con ese email';
			} else {
				$token = bin2hex(random_bytes(16));
				$nuser = User::create([
					'username' => $username,
					'name' => trim($signup['name']),
					'password' => $signup['password'],
					'active' => false,
					'created_at' => time(),
					'updated_at' => time(),
					'sessions' => [],
					'activatetoken' => hash('sha256', $token),
					'activatetokenexp' => time() + (60 * 60 * 24),
				]);
				try {
					$mail = nfMailer();
					$mail->addAddress($nuser->username, $nuser->name);
					$link = 'https://' . nfSiteHost() . '/account/activate?token=' . $token . '&user=' . $nuser->_id;
					$name = htmlspecialchars((string) $nuser->name, ENT_QUOTES, 'UTF-8');
					$mail->Subject = $lng['activate_account_subject'];
					$mail->Body    = str_replace(['{name}', '{link}'], [$name, htmlspecialchars($link, ENT_QUOTES, 'UTF-8')], $lng['activate_account_body']);
					$mail->AltBody = str_replace(['{name}', '{link}'], [(string) $nuser->name, $link], $lng['activate_account_altbody']);
					$mail->send();
					$msgError = $lng['activate_account_sent'];
				} catch (Exception $e) {
					error_log('nframework signup mail: ' . $e->getMessage());
					$msgError = $lng['activate_account_error'];
				}
			}
		}
	}
	$nframework->usecommon = true;
	$template = $twig->load('signup.html');
	echo $template->render([
		'nframework' => [
			'themeSwitcher' => $nframework->themeSwitcher()
		],
		'lng' => $nframework->language(),
		'msgError' => $msgError
	]);
}, ['GET', 'POST']);


$router->addRoute('/account/forgot', function (string $route, array $p) {
	global $twig, $config, $nframework, $m;
	$lng = $nframework->language();
	if (!empty($_POST['login']['username']) && is_string($_POST['login']['username'])) {
		$username = trim($_POST['login']['username']);
		$user = null;
		if (nflogAttempt('forgot:' . $GLOBALS['ip'], 5, 3600)) {
			$user = new User([
				'username' => new MongoDB\BSON\Regex('^' . preg_quote($username, '/') . '$', 'i'),
			]);
		}
		// Mismo mensaje exista o no el usuario, para no permitir enumerar cuentas.
		$msgError = $lng['reset_password_sent'];
		if ($user !== null && !empty($user->_id) && $user->username !== 'guest') {
			$token = bin2hex(random_bytes(16));
			$user->resettoken = hash('sha256', $token);
			$user->resettokenexp = time() + (60 * 60);
			try {
				$mail = nfMailer();
				$mail->addAddress($user->username, (string) $user->name);
				$vars = ['host' => nfSiteHost(), 'token' => $token, 'user' => $user->_id];
				$mail->Subject = $lng['reset_password_subject'];
				$mail->Body    = str_replace('{name}', htmlspecialchars((string) $user->name, ENT_QUOTES, 'UTF-8'), replaceVarsAtUrl($lng['reset_password_body'], $vars));
				$mail->AltBody = str_replace('{name}', (string) $user->name, replaceVarsAtUrl($lng['reset_password_altbody'], $vars));
				$mail->send();
			} catch (Exception $e) {
				error_log('nframework reset mail: ' . $e->getMessage());
			}
		}
	} else {
		$msgError = $lng['must_provide_username'];
	}
	$nframework->usecommon = true;
	$template = $twig->load('forgot.html');
	echo $template->render([
		'nframework' => [
			'themeSwitcher' => $nframework->themeSwitcher()
		],
		'lng' => $lng,
		'msgError' => $msgError
	]);
}, ['GET', 'POST']);

$router->addRoute('/account/reset', function (string $route, array $p) {
	global $twig, $config, $nframework;
	$lng = $nframework->language();
	$msgError = '';
	if (!empty($_GET['token']) && is_string($_GET['token']) && isValidObjectId($_GET['user'] ?? null)) {
		// Los valores se fuerzan a string: un arreglo como token[$ne]=x permitiría saltarse la validación.
		$user = new User([
			'_id' => toMongoId($_GET['user']),
			'resettoken' => hash('sha256', $_GET['token']),
			'resettokenexp' => ['$gt' => time()]
		]);
		if (!empty($user->_id)) {
			$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
			$confirm = is_string($_POST['confirmpassword'] ?? null) ? $_POST['confirmpassword'] : '';
			if ($password !== '' && $confirm !== '') {
				if ($password !== $confirm) {
					$msgError = $lng['passwords_do_not_match'];
				} elseif (strlen($password) < NF_PASSWORD_MIN_LENGTH) {
					$msgError = $lng['password_too_short'];
				} else {
					$user->password = nfPasswordHash($password);
					$user->resettoken = null;
					$user->resettokenexp = null;
					$user->sessions = [];
					header('location: /account/login');
					exit();
				}
			}
		} else {
			$msgError = $lng['invalid_token'];
		}
	} else {
		$msgError = $lng['no_token_provided'];
	}

	$nframework->usecommon = true;
	$template = $twig->load('reset.html');
	echo $template->render([
		'nframework' => [
			'themeSwitcher' => $nframework->themeSwitcher()
		],
		'lng' => $lng,
		'msgError' => $msgError
	]);
}, ['GET', 'POST']);
$router->addRoute('/account/activate/', function (string $route, array $p) {
	global $twig, $config, $nframework, $javas;
	$nframework->usecommon = true;
	$msgError = '';
	if (!empty($_GET['token']) && is_string($_GET['token']) && isValidObjectId($_GET['user'] ?? null)) {
		// token se fuerza a string: token[$ne]=x iniciaría sesión como cualquier usuario.
		// Se acepta el token con hash (actual) o en claro (enlaces enviados antes del cambio).
		$user = new User([
			'_id' => toMongoId($_GET['user']),
			'activatetoken' => ['$in' => [hash('sha256', $_GET['token']), $_GET['token']]],
			'activatetokenexp' => ['$gt' => time()],
		]);
		if (!empty($user->_id)) {
			$user->activatetoken = null;
			$user->activatetokenexp = null;
			$user->active = true;
			session_regenerate_id(true);
			$_SESSION['user'] = $user->_id;
			session_write_close();
			header('location: /');
			exit();
		} else {
			$user = new User([
				'_id' => toMongoId($_GET['user']),
			]);
			if (!empty($user->_id) && empty($user->activatetoken)) {
				$msgError = 'La cuenta ya está activada';
				$javas->addjs(
					<<<addjs
let timeLeft = 10;
        const timerElement = document.getElementById('timer');

        const countdown = setInterval(() => {
            timeLeft--;
            timerElement.textContent = timeLeft;
            if (timeLeft <= 0) {
                clearInterval(countdown);
                window.location.href = "/"; // Change to your target URL
            }
        }, 1000);
addjs
				);
			} else {
				$msgError = 'Token inválido o caducado';
			}
		}
	} else {
		$msgError = 'No token provided';
	}
	$nframework->usecommon = true;
	$template = $twig->load('messages.html');
	echo $template->render([
		'nframework' => [
			'themeSwitcher' => $nframework->themeSwitcher()
		],
		'lng' => $nframework->language(),
		'msgError' => $msgError
	]);
}, ['GET']);

$router->addRoute('/account/totp-setup', function ($route, $arg) {
	global $user, $m, $config, $nframework;
	$user->requireAuth();

	// Genera el secreto y guárdalo en la base de datos del usuario
	if (empty($user->totp_secret)) {
		$totp = TOTP::create();
		$secret = $totp->getSecret();
		$user->totp_secret = $secret;
		//$user->save();
	} else {
		$secret = $user->totp_secret;
		$totp = TOTP::create($secret);
	}

	$totp->setLabel($user->username);
	$totp->setIssuer($config['title']);
	$uri = $totp->getProvisioningUri();

	// Genera el QR
	$qr = new Endroid\QrCode\QrCode($uri);
	$writer = new PngWriter();
	$result = $writer->write($qr);

	header('Content-Type: image/png');
	echo $result->getString();
}, 'GET');


$router->addRoute('/account/profile', function (string $route, array $p) {
	global $twig, $config, $nframework, $user;
	$user->requireAuth();
	$nframework->usecommon = true;
	$template = $twig->load('profile.html');
	echo $template->render([
		'nframework' => [
			'themeSwitcher' => $nframework->themeSwitcher()
		],
		'lng' => $nframework->language(),
		'user' => $user
	]);
}, ['GET', 'POST']);
$router->addRoute('/account/sessions', function (string $route, array $p) {
	global $twig, $config, $nframework, $user;
	$user->requireAuth();
	$nframework->usecommon = true;
	$template = $twig->load('sessions.html');
	echo $template->render([
		'nframework' => [
			'themeSwitcher' => $nframework->themeSwitcher()
		],
		'lng' => $nframework->language(),
		'user' => $user
	]);
}, 'GET');
$router->addRoute('/account/apitokens', function (string $route, array $p) {
	global $twig, $config, $nframework, $user;
	$user->requireAuth();
	$nframework->usecommon = true;
	$template = $twig->load('apitokens.html');
	echo $template->render([
		'nframework' => [
			'themeSwitcher' => $nframework->themeSwitcher()
		],
		'lng' => $nframework->language(),
		'user' => $user
	]);
}, 'GET');



$router->addRoute('/account/logout', function (string $route, array $p) {
	global $twig, $config, $nframework, $user, $m;
	$tmp = (array)$user->sessions;
	//unset($tmp[session_id()]);
	$tmp = array_diff($tmp, [session_id()]);

	$m->{$config['sitedb']}->endpoints->deleteOne(['_id' => (string)session_id()]);
	$user->sessions = $tmp;

	unset($user);
	unset($_SESSION['user']);
	unset($_SESSION['emisor']);
	unset($_SESSION['primerinicio']);
	session_regenerate_id(true);
	if (session_status() == PHP_SESSION_NONE) {
		session_start();
	}
	session_destroy();
	header('Location: ' . nfSafeRedirect($_GET['to'] ?? '/'));
	exit();
}, ['GET', 'POST']);

$router->addRoute('/login-google/oauth', function (string $route, array $p) {
	global $twig, $config, $nframework, $m;
	require 'google/oauth.php';
}, ['GET', 'POST']);

$router->addRoute('/login-facebook/oauth', function (string $route, array $p) {
	global $twig, $config, $nframework, $m;
	require 'facebook/oauth.php';
}, ['GET', 'POST']);

$router->addRoute('/login-microsoft/oauth', function (string $route, array $p) {
	global $twig, $config, $nframework, $m;
	require 'ms/oauth.php';
}, ['GET', 'POST']);

$router->addRoute('/.well-known/microsoft-identity-association.json', function (string $route, array $p) {
	global $config;
	header('Content-Type: application/json; charset=utf-8');
	echo '{
  "associatedApplications": [
    {
      "applicationId": "' . $config['microsoft_oauth_client_id'] . '"
    }
  ]
}';
}, ['GET']);

//TODO:favicon.ico

$router->addRoute('/robots.txt', function ($route, $variables) {
	global $_SERVER;
	header('Content-Type: text/plain');
	echo 'User-agent: *
Disallow: 
Disallow: /nframework/
Disallow: /account/
Sitemap: https://' . nfSiteHost() . '/sitemap.xml';
});


$router->addRoute('/sitemap.xml', function ($route, $variables) {
	global $m, $config;
	header("Content-type: text/xml; charset=utf-8");
	$urls = [];
	$base = 'https://' . nfSiteHost();
	foreach ($m->{$config['sitedb']}->pages->distinct('path') as $path) {
		if (!is_string($path) || $path === '' || $path[0] === '_') {
			continue;
		}
		$urls[] = '<url>
  <loc>' . htmlspecialchars($base . '/' . ltrim($path, '/'), ENT_XML1, 'UTF-8') . '</loc>
</url>';
	}
	echo '<?xml version="1.0" encoding="UTF-8"?>
<urlset
      xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
            http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
<!-- created by nframework5 -->
' . implode("\n", $urls) . '

</urlset>';
}, 'GET');


// Validación HTTP-01 de Let's Encrypt: el cliente ACME (certbot --webroot, acme.sh, ...) deja el
// token en $config['acme_challenge_dir'] y aquí solo se sirve. Sin esa opción responde 404.
$router->addRoute('/.well-known/acme-challenge/[s:filename]', function ($route, $variables) {
	global $config;
	$dir = rtrim((string) ($config['acme_challenge_dir'] ?? ''), '/');
	$file = (string) $variables['filename'];
	if ($dir === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $file) || !is_file($dir . '/' . $file)) {
		http_response_code(404);
		return;
	}
	header('Content-Type: text/plain');
	readfile($dir . '/' . $file);
}, 'GET');


$router->addRoute('/images/config/[i:size]/logo.png', function (string $route, array $p) {
	global $m, $config, $nframework;
	$logo = $_SERVER['DOCUMENT_ROOT'] . '/img/nf/logo.png';
	if (!is_file($logo)) {
		http_response_code(404);
		return;
	}
	$dir = 'img/nf/config/';
	$p['size'] = nfClampImageSize($p['size']);
	$dst = $dir . '/logo_' . $p['size'] . '.png';
	if (!file_exists($dst) || filemtime($dst) < filemtime($logo)) {
		if (!file_exists($dir)) {
			mkdir($dir, 0777, true);
		}
		$manager = new ImageManager(array('driver' => 'gd'));
		$img = $manager->make($logo);
		$img->fit($p['size'], $p['size'], function ($constraint) {
			$constraint->aspectRatio();
			// $constraint->upsize();
		});
		$img->save($dst);
	}
	// El logo es igual para todos: el navegador y los proxies lo reutilizan un día.
	$nframework->serveFile($dst, 'image/png', 86400, true);
}, 'GET');

/**
 * Sirve la página $page de un PDF registrado en $_SESSION['frompdf'][$id] como imagen.
 * La imagen se guarda en $options['directory'] y se regenera si el PDF cambia; con
 * 'deletefile' se borra tras enviarla y con 'deletedirectory' se elimina la carpeta si queda vacía.
 */
function nfServePdfPage(string $id, int $width, int $page, string $format): void
{
	global $nframework;
	$formats = [
		'png' => [\Spatie\PdfToImage\Enums\OutputFormat::Png, 'image/png'],
		'jpg' => [\Spatie\PdfToImage\Enums\OutputFormat::Jpg, 'image/jpeg'],
		'webp' => [\Spatie\PdfToImage\Enums\OutputFormat::Webp, 'image/webp'],
	];
	$options = $_SESSION['frompdf'][$id] ?? null;
	if (!is_array($options) || empty($options['filename']) || !is_file($options['filename']) || $page < 1) {
		http_response_code(404);
		return;
	}
	$dir = rtrim((string) ($options['directory'] ?? ''), '/');
	if ($dir === '') {
		$dir = sys_get_temp_dir() . '/nffrompdf_' . md5($options['filename']);
	}
	$width = nfClampImageSize($width);
	$dst = $dir . '/' . $page . '_' . $width . '.' . $format;
	if (!is_file($dst) || filemtime($dst) < filemtime($options['filename'])) {
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}
		try {
			$pdf = new \Spatie\PdfToImage\Pdf($options['filename']);
			$pdf->format($formats[$format][0]);
			if ($format === 'jpg') {
				$pdf->resolution(150);
			}
			$pdf->selectPage($page)->size($width)->save($dst);
		} catch (\Throwable $e) {
			// Página fuera de rango o PDF dañado.
			error_log('nframework frompdf: ' . $e->getMessage());
			http_response_code(404);
			return;
		}
	}
	$deleteAfter = !empty($options['deletefile']);
	// Si la imagen se borra al enviarla no tiene sentido que el navegador la revalide.
	$nframework->serveFile($dst, $formats[$format][1], $deleteAfter ? null : 0);
	if ($deleteAfter) {
		unlink($dst);
	}
	if (!empty($options['deletedirectory'])) {
		@rmdir($dir);
	}
}

foreach (['png', 'jpg', 'webp'] as $nfPdfFormat) {
	$router->addRoute('/images/frompdf/[s:id]/[i:w]/[i:h]/[i:p].' . $nfPdfFormat, function (string $route, array $p) use ($nfPdfFormat) {
		nfServePdfPage((string) $p['id'], (int) $p['w'], (int) $p['p'], $nfPdfFormat);
	}, 'GET');
}

$router->addRoute('/images/frompdf/[s:id]/info.json', function (string $route, array $p) {
	global $nframework;
	$nframework->isAjax = false;
	$options = $_SESSION['frompdf'][$p['id']] ?? null;
	if (!is_array($options) || empty($options['filename']) || !is_file($options['filename'])) {
		http_response_code(404);
		return;
	}
	// Ghostscript cuenta páginas mucho más rápido que Imagick; si falla se usa pageCount().
	$cmd = 'gs -q -dNODISPLAY --permit-file-read=' . escapeshellarg($options['filename']) . ' -c '
		. escapeshellarg('(' . addcslashes($options['filename'], '()\\') . ') (r) file runpdfbegin pdfpagecount = quit') . ' 2>&1';
	$out = trim(shell_exec($cmd) ?? '');
	if (ctype_digit($out)) {
		$count = (int) $out;
	} else {
		$count = (new \Spatie\PdfToImage\Pdf($options['filename']))->pageCount();
	}
	header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
	header("Pragma: no-cache");
	header('Content-Type: application/json');
	echo json_encode(['numberOfPages' => $count]);
}, 'GET');

// Vista previa (primera página) del PDF de un control inputFile con 'preview' habilitado.
$router->addRoute('/images/[s:id]/[i:w]/[i:h]/preview.png', function (string $route, array $p) {
	$upload = $_SESSION['uploads4'][$p['id']] ?? null;
	$filename = $upload['extensioninfo']['path'] ?? '';
	if (empty($upload['preview']) || !is_string($filename) || !is_file($filename)
		|| strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
		http_response_code(404);
		return;
	}
	$dst = tempnam(sys_get_temp_dir(), 'pdftopng');
	try {
		$pdf = new \Spatie\PdfToImage\Pdf($filename);
		$pdf->format(\Spatie\PdfToImage\Enums\OutputFormat::Png);
		$pdf->selectPage(1)->size(nfClampImageSize($p['w']))->save($dst . '.png');
	} catch (\Throwable $e) {
		error_log('nframework preview: ' . $e->getMessage());
		@unlink($dst);
		http_response_code(404);
		return;
	}
	global $nframework;
	$nframework->serveFile($dst . '.png', 'image/png', null);
	unlink($dst . '.png');
	unlink($dst);
}, 'GET');

$router->addRoute('/images/config/[i:w]/[i:h]/logo.png', function (string $route, array $p) {
	global $m, $config, $nframework;
	$dir = 'img/nf/config/';
	$p['w'] = nfClampImageSize($p['w']);
	$p['h'] = nfClampImageSize($p['h']);
	$dst = $dir . '/logo_' . $p['w'] . 'x' . $p['h'] . '.png';
	// $config['image'] puede ser una URL: filemtime() fallaría y el logo se regeneraría en cada petición.
	$src = (!empty($config['image']) && is_file($config['image'])) ? $config['image'] : $_SERVER['DOCUMENT_ROOT'] . '/img/nf/logo.png';
	if (!is_file($src)) {
		http_response_code(404);
		return;
	}
	if (!file_exists($dst) || filemtime($dst) < filemtime($src)) {
		if (!file_exists($dir)) {
			mkdir($dir, 0777, true);
		}
		$manager = new ImageManager(array('driver' => 'gd'));
		$img = $manager->make($src);
		$img->fit($p['w'], $p['h'], function ($constraint) {
			$constraint->aspectRatio();
			//$constraint->upsize();
		});
		$img->save($dst);
	}
	$nframework->serveFile($dst, 'image/png', 86400, true);
}, 'GET');

$router->addRoute('/images/resize/[s:id]/[i:w]/[i:h]/[s:file]', function (string $route, array $p) {
	global $nframework;
	if (isset($_SESSION['imagesresize'][$p['id']])) {
		$conf = $_SESSION['imagesresize'][$p['id']];
		$filename = basename($p['file']);
		if ($filename === '' || $filename[0] === '.') {
			http_response_code(404);
			return;
		}
		$p['w'] = nfClampImageSize($p['w']);
		$p['h'] = nfClampImageSize($p['h']);
		$actualizar = false;
		$pos = strrpos($filename, '.');
		$name = substr($filename, 0, $pos);
		$ext = substr($filename, $pos);
		$dst = $conf['dst'] . '/' . $name . '_' . $p['w'] . 'x' . $p['h'] . $ext;
		$src = $conf['src'] . '/' . $filename;
		//echo "$name  $ext $dst";


		if (!file_exists($dst)) {
			if (!file_exists($conf['dst'])) {
				mkdir($conf['dst'], 0777, true);
			}
			$actualizar = true;
		} else {
			$lasttimedst = filemtime($dst);
			$lasttimesrc = filemtime($src);
			if ($lasttimedst < $lasttimesrc) {
				$actualizar = true;
			}
		}
		if ($actualizar) {
			$manager = new ImageManager(array('driver' => 'gd'));
			if (!file_exists($src)) {
				$src = $conf['default'];
			}
			$img = $manager->make($src);
			$img->fit($p['w'], $p['h'], function ($constraint) {
				$constraint->aspectRatio();
				//$constraint->upsize();
			});
			$img->save($dst);
			$lasttimedst = filemtime($dst);
		}

		// 'maxage' en la configuración de la sesión: segundos sin revalidar (por defecto revalida siempre).
		$nframework->serveFile($dst, 'image/png', (int) ($conf['maxage'] ?? 0));
	}
}, 'GET');
$router->addRoute('/images/pngtowebp/[s:id]/[i:w]/[i:h]/[s:file]', function (string $route, array $p) {
	global $nframework;
	if (isset($_SESSION['imagesresize'][$p['id']])) {
		$conf = $_SESSION['imagesresize'][$p['id']];
		$filename = basename($p['file']);
		if ($filename === '' || $filename[0] === '.') {
			http_response_code(404);
			return;
		}
		$p['w'] = nfClampImageSize($p['w']);
		$p['h'] = nfClampImageSize($p['h']);
		$actualizar = false;
		$pos = strrpos($filename, '.');
		$name = substr($filename, 0, $pos);
		$ext = substr($filename, $pos);
		$dst = $conf['dst'] . '/' . $name . '_' . $p['w'] . 'x' . $p['h'] . '.webp';
		$src = $conf['src'] . '/' . $name . '.png';
		//echo "$name  $ext $dst";
		if (!file_exists($dst)) {
			if (!file_exists($conf['dst'])) {
				mkdir($conf['dst'], 0777, true);
			}
			$actualizar = true;
		} else {
			$lasttimedst = filemtime($dst);
			$lasttimesrc = filemtime($src);
			if ($lasttimedst < $lasttimesrc) {
				$actualizar = true;
			}
		}
		if ($actualizar) {
			$manager = new ImageManager(array('driver' => 'gd'));
			if (!file_exists($src)) {
				$src = $conf['default'];
			}
			$img = $manager->make($src);
			$img->fit($p['w'], $p['h'], function ($constraint) {
				$constraint->aspectRatio();
				//$constraint->upsize();
			});
			$img->encode('webp');
			$img->save($dst);
			$lasttimedst = filemtime($dst);
		}

		$nframework->serveFile($dst, 'image/webp', (int) ($conf['maxage'] ?? 0));
	}
}, 'GET');



$router->addRoute('/nf.webmanifest', function (string $route, array $p) {
	global $config, $nframework;
	$icons = [];
	foreach ([72, 96, 144, 192, 256, 384, 512, 1024] as $size) {
		$icons[] = [
			'src' => 'https://' . nfSiteHost() . '/images/config/' . $size . '/logo.png',
			'sizes' => $size . 'x' . $size,
			'type' => 'image/png',
			'purpose' => 'any',
		];
	}
	$nframework->serveContent(json_encode([
		'name' => (string) $config['title'],
		'short_name' => (string) $config['shortname'],
		'id' => (string) $config['shortname'],
		'theme_color' => $config['manifest']['theme_color'],
		'background_color' => $config['manifest']['background_color'],
		'display' => 'standalone',
		'scope' => '/',
		'start_url' => '/',
		'description' => str_replace(["\n", "\r"], '', (string) $config['description']),
		'orientation' => 'any',
		'launch_handler' => ['client_mode' => 'auto'],
		'edge_side_panel' => ['preferred_width' => 1],
		'categories' => ['education'],
		'dir' => 'auto',
		'lang' => 'es',
		'prefer_related_applications' => false,
		'iarc_rating_id' => '16+',
		'icons' => $icons,
	], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'application/manifest+json; charset=utf-8', 3600, true);
	//72, 96, 144, 192, 256, 384, 512

}, 'GET');

$router->addRoute('/getPayload', function (string $route, array $p) {
	global $m, $config;
	if (!empty($_GET['endpoint']) && is_string($_GET['endpoint'])) {
		$endpoint = json_decode($_GET['endpoint'], true);
		if (is_array($endpoint) && !empty($endpoint['endpoint']) && str_starts_with((string) $endpoint['endpoint'], 'https://')) {
			$m->{$config['sitedb']}->endpoints->updateOne(
				['_id' => (string)session_id()],
				['$set' => ['endpoint' => nfSanitizeMongoQuery($endpoint)]],
				['upsert' => true]
			);
		} else {
			$m->{$config['sitedb']}->endpoints->deleteOne(['_id' => (string)session_id()]);
		}
		echo "ok";
	}
});

$router->addRoute('/privacy', function (string $route, array $p) {
	global $nframework, $twig, $config, $m;
	$nframework->usecommon = true;
	$page = $m->{$config['sitedb']}->pages->findOne(['title' => 'Privacidad']);
	if (empty($page)) {
		$template = $twig->load('privacy.html');
		$body = $template->render(['config' => $config]);
		echo $body;
	} else {
		echo $page['html'];
	}
}, 'GET');

$router->addRoute('/terms', function (string $route, array $p) {
	global $nframework, $twig, $config, $m;
	$nframework->usecommon = true;
	$page = $m->{$config['sitedb']}->pages->findOne(['title' => 'Terms']);
	if (empty($page)) {
		$template = $twig->load('terms.html');
		$body = $template->render(['config' => $config]);
		echo $body;
	} else {
		echo $page['html'];
	}
}, 'GET');

$router->addRoute('/righttoforget', function (string $route, array $p) {
	global $nframework, $twig, $config, $m;
	$nframework->usecommon = true;
	$page = $m->{$config['sitedb']}->pages->findOne(['title' => 'righttoforget']);
	if (empty($page)) {
		$template = $twig->load('righttoforget.html');
		$body = $template->render(['config' => $config]);
		echo $body;
	} else {
		echo $page['html'];
	}
}, 'GET');



$router->addRoute('/privacidad', function (string $route, array $p) {
	global $nframework, $twig, $config, $m;
	$page = $m->{$config['sitedb']}->pages->findOne(['title' => 'Privacidad']);
	echo $page['html'];
}, 'GET');

$router->addRoute('/sw.js', function (string $route, array $p) {
	global $nframework, $twig, $config;
	$js = $twig->load('sw.js')->render([
		'publicKey' => $config['notifications']['publicKey'],
		'tocache' => array_values(array_merge($nframework->csss, $nframework->jss)),
		'csss' => implode("','", $nframework->csss),
		'jss' => implode("','", $nframework->jss)
	]);
	// El navegador revisa el service worker al navegar; con ETag la respuesta es un 304 vacío.
	$nframework->serveContent($js, 'application/javascript; charset=utf-8');
}, 'GET');

// Rutas de las páginas de Admin → Páginas, en la caché local (se vacía al guardar desde el panel).
$nfPagePaths = nfCacheRemember('pagepaths', nfCacheTtl(), fn() => array_values(array_filter(
	iterator_to_array($m->{$config['sitedb']}->pages->distinct('path'), false),
	'is_string'
)));
foreach ($nfPagePaths as $d) {
	// Las páginas que empiezan con '_' (_header, _footer, _home, _404...) son fragmentos, no rutas públicas.
	if (!is_string($d) || $d === '' || $d[0] === '_') {
		continue;
	}
	$router->addRoute($d, function ($route, $arg) use ($d) {
		global $config, $nframework, $twig;
		$page = nfPage($d);
		$nframework->metas['description'] = $page['description'] ?? null;
		$nframework->metas['title'] = $page['title'] ?? null;
		$nframework->metas['keywords'] = $page['keywords'] ?? null;

		$header = nfPage('_header');
		$footer = nfPage('_footer');
		$nframework->usecommon = true;
		$template = $twig->load('page.html');

		echo $template->render([
			'theme' => $config['theme'],
			'page' => $page['html'] ?? null,
			'header' => renderEmbeddedFunctions((string) ($header['html'] ?? '')),
			'footer' => $footer['html'] ?? null,
			'menu' => nfMetroMenu('_nav'),
			'route' => $route,
		]);
	}, 'GET');
}
$router->addRoute('/nftables/[s:collection]/', function (string $route, array $p) {
	global $m, $config, $nframework, $javas, $user;
	requireGroup('admins', 'nftables');

	require 'nftable.php';
}, ['GET', 'POST']);

$router->addRoute('/nftables/[s:collection]/import', function (string $route, array $p) {
	global $m, $config, $nframework, $javas, $result, $user;
	requireGroup('admins', 'nftables');
	require 'nfimport.php';
}, ['GET', 'POST']);

$router->addRoute('/nftables/[s:collection]/[s:id]', function (string $route, array $p) {
	global $m, $config, $nframework, $javas, $result, $user;
	requireGroup('admins', 'nftables');
	require 'nfdialog.php';
}, ['GET', 'POST']);
$router->addRoute('/nftables/[s:collection]/[s:id]', function (string $route, array $p) {
	global $m, $config, $user;
	requireGroup('admins', 'nftables');
	$tabla = $m->{$config['sitedb']}->nftables->findOne(['nfcollection' => $p['collection']]);
	if ($tabla && isValidObjectId($p['id'])) {
		$m->{$config['sitedb']}->{$tabla->nfcollection}->deleteOne(['_id' => tomongoid($p['id'])]);
	}
},  'DELETE');

