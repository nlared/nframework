<?php
use Intervention\Image\ImageManager;
use OTPHP\TOTP;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use FontLib\Table\Type\head;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Google\Service\DriveActivity\Create;
use MongoDB\Model\BSONDocument;
use Rogierw\RwAcme\AcmeClient;
require 'include.php';
$loader1 = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$loader2 = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates/panda');
$loader3 = new \Twig\Loader\FilesystemLoader($nframework->include_path . '/i18n/' . $nframework->lang);
$loader = new \Twig\Loader\ChainLoader([$loader1, $loader2, $loader3]);
$twig = new \Twig\Environment($loader, [
	'cache' => false, //__DIR__.'/../compilation_cache',
	'debug' => true,
	'auto_reload' => true,
]);

function replaceVarsAtUrl($url, $vars) {
    foreach ($vars as $clave => $valor) {
        // Reemplaza {clave} por el valor codificado
        $url = str_replace("{" . $clave . "}", urlencode($valor), $url);
    }
    return $url;
}


//https://github.com/alexdodonov/mezon-router#routing--


$router = new \Mezon\Router\Router();

$router->addRoute('index', function ($route, $variables) {
	global $_SERVER, $twig, $nframework, $config, $m;

	$header = $m->{$config['sitedb']}->pages->findOne(['path' => '_header']);
	$footer = $m->{$config['sitedb']}->pages->findOne(['path' => '_footer']);
	$parallax = $m->{$config['sitedb']}->pages->findOne(['path' => '_parallax']);
	$menu = $m->{$config['sitedb']}->menus->findOne(['name' => '_nav']);

	$nframework->usecommon = true;
	$template = $twig->load('page.html');

	if ($config['homepagetype'] == 'page') {
		$page = $m->{$config['sitedb']}->pages->findOne(['path' => '_home']);
		$nframework->metas['description'] = $page->description;
		$nframework->metas['title'] = $page->title;
		$nframework->metas['keywords'] = $page->keywords;
	} else {
	}

	echo $template->render([
		'theme' => $config['theme'],
		'parallaxpage' => $parallax?->html,
		'page' => $page->html,
		'header' => $header->html,
		'footer' => $footer->html,
		'menu' => $menu->code,
		'route' => 'index.php'
	]);
}, 'GET');


$router->addRoute('/main.js', function (string $route, array $p) {
	global $twig, $config;
	header('Content-Type: text/javascript; charset=utf-8');
	$template = $twig->load('main.js');
	echo $template->render([
		'publicKey' => $config['notifications']['publicKey']
	]);
}, 'GET');

$router->addRoute('/account/login', function (string $route, array $p) {
	global $twig, $config, $nframework;

	if (!empty($_POST['login'])) {
		$login = $_POST['login'];
		$user = new User([
			'username' => ['$regex' => trim($login['username']), '$options' => 'i'],
			'password' => trim($login['password'])
		]);

		if (!empty($user->_id)) {
			if (!empty($user->twofa_secret)) {
				$_SESSION['tmp_user'] = $user->_id;
				header('location: /account/twofa');
				exit();
			}
			$_SESSION['user'] = $user->_id;
			session_write_close();
			if ($_SESSION['nframework']['loginpage'] != '' && $_SESSION['nframework']['loginpage'] != '/account/login.php') {
				header('location: ' . $_SESSION['nframework']['logiopage']);
			} else {
				if ($user->in('admins')) {
					header('location: /admin/');
				} else {
					header('location: /');
				}
			}
			exit();
		}
		$msgError = 'Datos incorrectos';
	}

	if (!empty($_SESSION['nframework']['loginpage'])) {
		$_SESSION['nframework']['loginerror'] = 'Datos incorrectos';
		header('location: ' . $_SESSION['nframework']['loginpage']);
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
	if (!empty($_POST['code'])) {
		$user = new User([
			'_id' => $_SESSION['tmp_user']
		]);
		$totp = TOTP::create($user->twofa_secret);
		if ($totp->verify($_POST['code'])) {
			$_SESSION['user'] = $user->_id;
			unset($_SESSION['tmp_user']);
			session_write_close();
			if ($_SESSION['nframework']['loginpage'] != '' && $_SESSION['nframework']['loginpage'] != '/account/login.php') {
				header('location: ' . $_SESSION['nframework']['loginpage']);
			} else {
				if ($user->in('admins')) {
					header('location: /admin/');
				} else {
					header('location: /');
				}
			}
			exit();
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
	if (!empty($_POST['signup'])) {
		$signup = $_POST['signup'];
		if ($signup['password'] != $signup['confirmpassword']) {
			$msgError = 'Las contraseñas no coinciden';
		} elseif (strlen($signup['password']) < 6) {
			$msgError = 'La contraseña debe tener al menos 6 caracteres';
		} elseif (empty($signup['username']) || !filter_var($signup['username'], FILTER_VALIDATE_EMAIL)) {
			$msgError = 'Debe indicar un email válido';
		} else {
			$user = new User([
				'username' => trim($signup['username']),
			]);
			if (!empty($user->_id)) {
				$msgError = 'Ya existe un usuario con ese email';
			} else {
				$token = bin2hex(random_bytes(16));
				$nuser = User::create([
					'username' => trim($signup['username']),
					'name' => trim($signup['name']),
					'password' => trim($signup['password'], PASSWORD_DEFAULT),
					'active' => false,
					'created_at' => time(),
					'updated_at' => time(),					
					'sessions' => [],
					'activatetoken' => $token,
					'activatetokenexp' => time() + (60 * 60 * 24),
				]);
				$mail = new PHPMailer();				
				try {					
					$mail->isSMTP();
					$mail->CharSet = 'UTF-8';                                            // Send using SMTP
					$mail->Host       = $config['smtp']['host'];               // Set the SMTP server to send through
					$mail->SMTPAuth   = boolval($config['smtp']['auth']);                                   // Enable SMTP authentication
					$mail->Username   = $config['smtp']['username'];            // SMTP username
					$mail->Password   = $config['smtp']['password'];            // SMTP password
					if (!empty($config['smtp']['secure']) && $config['smtp']['secure'] == 'ssl') {
						$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
					} elseif (!empty($config['smtp']['secure']) && $config['smtp']['secure'] == 'tls') {
						$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;        // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
					}
					$mail->Port       = $config['smtp']['port'];               // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

					//Recipients
					$mail->setFrom($config['smtp']['fromemail'], $config['smtp']['fromname']);
					$mail->addAddress($nuser->username, $nuser->name);     // Add a recipient
					// Content
					$mail->isHTML(true);                                  // Set email format to HTML
					$mail->Subject = $lng['activate_account_subject'];
					$mail->Body    = $lng['activate_account_body'] . replaceVarsAtUrl('https://' . $_SERVER['HTTP_HOST'] . '/account/activate?token=' . $token . '&user=' . $nuser->_id, [
						'token' => $token,
						'user' => $nuser->_id
					]);
					$mail->AltBody = $lng['activate_account_altbody'] . replaceVarsAtUrl('https://' . $_SERVER['HTTP_HOST'] . '/account/activate?token=' . $token . '&user=' . $nuser->_id, [
						'token' => $token,
						'user' => $nuser->_id
					]);

					$c =$mail->send();					
					if ($c) {
						$msgError = $lng['activate_account_sent'];
					} else {
						$msgError = $lng['activate_account_error'] . $mail->ErrorInfo;
					}
					//header('location: /account/login');
					//exit();

				} catch (Exception $e) {
					$msgError = $lng['activate_account_error'] . $e->getMessage();
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
	if (!empty($_POST['login'])) {
		$login = $_POST['login'];
		$user = new User([
			'username' => ['$regex' => trim($login['username']), '$options' => 'i'],
		]);
		if (!empty($user->_id)) {
			$token = bin2hex(random_bytes(16));
			$user->resettoken = $token;
			$user->resettokenexp = time() + (60 * 60);
			//$user->save();
			//Enviar email
			$mail = new PHPMailer();
			$mail->CharSet = 'UTF-8';
			try {
				//Server settings
				$mail->isSMTP();                                            // Send using SMTP
				$mail->Host       = $config['smtp']['host'];               // Set the SMTP server to send through
				$mail->SMTPAuth   = $config['smtp']['auth'];                                   // Enable SMTP authentication
				$mail->Username   = $config['smtp']['username'];            // SMTP username
				$mail->Password   = $config['smtp']['password'];            // SMTP password
				if (!empty($config['smtp']['secure']) && $config['smtp']['secure'] == 'ssl') {
					$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
				} elseif (!empty($config['smtp']['secure']) && $config['smtp']['secure'] == 'tls') {
					$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;        // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
				}
				$mail->Port       = $config['smtp']['port'];               // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

				//Recipients
				$mail->setFrom($config['smtp']['fromemail'], $config['smtp']['fromname']);
				$mail->addAddress($user->username, $user->name);     // Add a recipient		
				// Content			
				$mail->isHTML(true);                                  // Set email format to HTML
				$mail->Subject = $lng['reset_password_subject'];
				$mail->Body    =  replaceVarsAtUrl($lng['reset_password_body'], ['host' => $_SERVER['HTTP_HOST'], 'token' => $token, 'user' => $user->_id]);
				$mail->AltBody = replaceVarsAtUrl($lng['reset_password_altbody'], [
					'host' => $_SERVER['HTTP_HOST'], 'token' => $token, 'user' => $user->_id]);
				$mail->send();
				$msgError = $lng['reset_password_sent'];
			} catch (Exception $e) {
				$msgError = $lng['reset_password_error'] . $mail->ErrorInfo;
			}
		} else {
			$msgError = $lng['user_not_found'];
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
	if (!empty($_GET['token']) && !empty($_GET['user'])) {
		$user = new User([
			'_id' => toMongoId($_GET['user']),
			'resettoken' => $_GET['token'],
			'resettokenexp' => ['$gt' => time()]
		]);
		if (!empty($user->_id)) {
			if (!empty($_POST['password']) && !empty($_POST['confirmpassword'])) {
				if ($_POST['password'] != $_POST['confirmpassword']) {
					$msgError = $lng['passwords_do_not_match'];
				} elseif (strlen($_POST['password']) < 6) {
					$msgError = $lng['password_too_short'];
				} else {
					$user->password = trim($_POST['password']);
					$user->resettoken = null;
					$user->resettokenexp = null;
					//$user->save();
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
		'lng' => $lng
	]);


}, ['GET', 'POST']);
$router->addRoute('/account/activate/', function (string $route, array $p) {
	global $twig, $config, $nframework;
	$nframework->usecommon = true;	
	if (!empty($_GET['token'])) {
		$user = new User([
			'_id' => toMongoId($_GET['user']),
			'activatetoken' => $_GET['token'],
			'activatetokenexp' => ['$gt' => time()]
		]);
		if (!empty($user->_id)) {
			$user->activatetoken = null;
			$user->activatetokenexp = null;
			$user->active = true;
			//$user->save();
			$_SESSION['user'] = $user->_id;
			session_write_close();	
			header('location: /');
			exit();
		} else {		
			$msgError = 'Token invalid';
		}			
	}else{
		$msgError='No token provided';
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
    global $user, $m, $config,$nframework;

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
	header('Location: ' . (isset($_GET['to']) ? $_GET['to'] : '/'));
	$nframework->usecommon = true;
	$template = $twig->load('logout.html');
	echo $template->render([
		'lng' => $nframework->language()
	]);
}, 'GET');

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
Sitemap: http://' . $_SERVER['HTTP_HOST'] . '/sitemap.xml';
});


$router->addRoute('/sitemap.xml', function ($route, $variables) {
	global $m, $config;
	header("Content-type: text/xml; charset=utf-8");
	$urls = [];
	foreach ($m->{$config['sitedb']} as $url) {
		$urls[] = '<url>
  <loc>' . $url['url'] . '</loc>
  <lastmod>' . $url['lastmod'] . '</lastmod>
  <priority>' . $url['prioridad'] . '</priority>
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


$router->addRoute('/.well-known/acme-challenge/[s:filename]', function ($route, $variables) {
	global $m, $config;
	$client = new Api($config->letsencrypt_email, __DIR__ . '/__account');
	$account = $client->account()->get();


	try {
		$client->domainValidation()->start($account, $validationStatus[0], AuthorizationChallengeEnum::HTTP);
		$privateKey = \Rogierw\RwAcme\Support\OpenSsl::generatePrivateKey();
		$csr = \Rogierw\RwAcme\Support\OpenSsl::generateCsr(['example.com'], $privateKey);
		if ($order->isReady() && $client->domainValidation()->allChallengesPassed($order)) {
			$client->order()->finalize($order, $csr);
		}
		if ($order->isFinalized()) {
			$certificateBundle = $client->certificate()->getBundle($order);
		}
		$config->letsencryptvalidtruh = strtotime('+90 days');
	} catch (DomainValidationException $exception) {
		// The local HTTP challenge test has been failed...
	}
	foreach ($validationData as $vd) {
		if ($vd['identifier'] == $_SERVER['HTTP_HOST'] && $vd['filename'] == $variables['filename']) {
			echo $vd['content'];
			exit();
		}
	}
});


$router->addRoute('/images/config/[i:size]/logo.png', function (string $route, array $p) {
	global $m, $config;
	$logo = $_SERVER['DOCUMENT_ROOT'] . '/img/nf/logo.png';
	$dir = 'img/nf/config/';
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
	header('Content-Length: ' . filesize($dst));
	header('Content-Type: image/png');
	echo file_get_contents($dst);
}, 'GET');

$router->addRoute('/images/frompdf/[s:id]/[i:w]/[i:h]/[i:p].png', function (string $route, array $p) {
	$options = $_SESSION['frompdf'][$p['id']];
	if (file_exists($options['filename'])) {
		$pdf = new \Spatie\PdfToImage\Pdf($options['filename']);
		mkdir($options['directory']);
		$pdf->format(\Spatie\PdfToImage\Enums\OutputFormat::Png);
		$pdf->selectPage($p['p'])->size($p['w'])->save($options['directory'] . $p['p'] . '.png');
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		header('Content-Length: ' . filesize($options['directory'] . '/' . $p['p'] . '.png'));
		header('Content-Type: image/png');
		echo file_get_contents($options['directory'] . '/' . $p['p'] . '.png');
		if (!empty($options['deletefile']) && $options['deletefile'] == true) {
			unlink($options['directory'] . '/' . $p['p'] . '.png');
		}
		if (!empty($options['deletedirectory']) && $options['deletedirectory'] == true) {
			rmdir($options['directory']);
		}
	}
}, 'GET');

$router->addRoute('/images/frompdf/[s:id]/info.json', function (string $route, array $p) {
	global $nframework;
	$nframework->isAjax = false;
	$options = $_SESSION['frompdf'][$p['id']];
	if (file_exists($options['filename'])) {
		$pdf = new \Spatie\PdfToImage\Pdf($options['filename']);
		$size = $pdf->getSize();
		$result = [
			'numberOfPages' => $pdf->pageCount(),
			'width' => $size->width,
			'height' => $size->height,
		];
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		header('Content-Type: application/json');
		echo json_encode($result);
	}
}, 'GET');

$router->addRoute('/images/[s:id]/[i:w]/[i:h]/preview.png', function (string $route, array $p) {
	$upload = $_SESSION['uploads4'][$p['id']];
	$filename = $upload['extensioninfo']['path'];
	$extension = pathinfo($filename, PATHINFO_EXTENSION);

	$dst = sys_get_temp_dir() . '/' . uniqid('pdftopng', true);
	mkdir($dst);
	$pdf = new \Spatie\PdfToImage\Pdf($filename);
	$pdf->format(\Spatie\PdfToImage\Enums\OutputFormat::Png);
	$pdf->selectPage($p['p'])->size($p['w'])->save($dst);

	/*	header('dst:'.$dst);
	header('dstf:'.$filename);
	header('dstp:'.$p['p']);//*/
	header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache");
	header('Content-Length: ' . filesize($dst . '/' . $p['p'] . '.png'));
	header('Content-Type: image/png');
	echo file_get_contents($dst . '/' . $p['p'] . '.png');
	unlink($dst . '/' . $p['p'] . '.png');
	rmdir($dst);
}, 'GET');

$router->addRoute('/images/config/[i:w]/[i:h]/logo.png', function (string $route, array $p) {
	global $m, $config;
	$dir = 'img/nf/config/';
	$dst = $dir . '/logo_' . $p['w'] . 'x' . $p['h'] . '.png';
	if (!file_exists($dst) || filemtime($dst) < filemtime($config['image'])) {
		if (!file_exists($dir)) {
			mkdir($dir, 0777, true);
		}
		$manager = new ImageManager(array('driver' => 'gd'));
		$img = $manager->make($config['image']);
		$img->fit($p['w'], $p['h'], function ($constraint) {
			$constraint->aspectRatio();
			//$constraint->upsize();
		});
		$img->save($dst);
	}
	header('Content-Length: ' . filesize($dst));
	header('Content-Type: image/png');
	echo file_get_contents($dst);
}, 'GET');

$router->addRoute('/images/resize/[s:id]/[i:w]/[i:h]/[s:file]', function (string $route, array $p) {
	global $nframework;
	if (isset($_SESSION['imagesresize'][$p['id']])) {
		$conf = $_SESSION['imagesresize'][$p['id']];
		$filename = $p['file'];
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

		$toetag = $dst . $lasttimedst;
		$nframework->lastmodified = $lasttimedst;
		$nframework->etag = md5($toetag);

		//$nframework->expiretime=time() + (60 * 60 * 24);


		if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
			$id = trim($_SERVER['HTTP_IF_NONE_MATCH']);
			if (substr($id, 0, 2) == "W/") {
				$id = substr($id, 2);
			}
			$id = str_replace('"', '', $id);
			if ($id == $toetag) {
				header('ncache: 304');
				http_response_code(304);

				die();
			}
		}

		header('Content-Length: ' . filesize($dst));
		header('Content-Type: image/png');
		echo file_get_contents($dst); //*/
	}
}, 'GET');



$router->addRoute('/nf.webmanifest', function (string $route, array $p) {
	global $config;
	header('Content-Type: application/manifest+json; charset=utf-8');
	echo '{
    "name": "' . $config['title'] . '",
    "short_name": "' . $config['shortname'] . '",
    "id": "' . $config['shortname'] . '",
    "theme_color": "' . $config['manifest']['theme_color'] . '",
    "background_color": "' . $config['manifest']['background_color'] . '",
    "display": "standalone",
    "scope": "/",
    "start_url": "/",
    "description": "' . str_replace(array("\n", "\r"), '', $config['description']) . '",
    "orientation": "any",
    "launch_handler": {
    	"client_mode": "auto"
	},
    "edge_side_panel": {
    	"preferred_width": 1
	},
	"categories": [
    "education"
  ],
  "dir": "auto",
  "lang": "es",
  "prefer_related_applications": false,
  "iarc_rating_id": "16+",
    "icons": [
        {
            "src": "https://' . $_SERVER['HTTP_HOST'] . '/images/config/72/logo.png",
            "sizes": "72x72",
            "type": "image/png",
            "purpose":"any"
        },
        {
            "src": "https://' . $_SERVER['HTTP_HOST'] . '/images/config/96/logo.png",
            "sizes": "96x96",
            "type": "image/png",
            "purpose":"any"
        },
        {
            "src": "https://' . $_SERVER['HTTP_HOST'] . '/images/config/144/logo.png",
            "sizes": "144x144",
            "type": "image/png",
            "purpose":"any"
        },
        {
            "src": "https://' . $_SERVER['HTTP_HOST'] . '/images/config/192/logo.png",
            "sizes": "192x192",
            "type": "image/png",
            "purpose":"any"
        },
        {
            "src": "https://' . $_SERVER['HTTP_HOST'] . '/images/config/256/logo.png",
            "sizes": "256x256",
            "type": "image/png",
            "purpose":"any"
        },
        {
            "src": "https://' . $_SERVER['HTTP_HOST'] . '/images/config/384/logo.png",
            "sizes": "384x384",
            "type": "image/png",
            "purpose":"any"
        },
        {
            "src": "https://' . $_SERVER['HTTP_HOST'] . '/images/config/512/logo.png",
            "sizes": "512x512",
            "type": "image/png",
            "purpose":"any"
        },
        {
            "src": "https://' . $_SERVER['HTTP_HOST'] . '/images/config/1024/logo.png",
            "sizes": "1024x1024",
            "type": "image/png",
            "purpose":"any"
        }
    ]
}';
	//72, 96, 144, 192, 256, 384, 512

}, 'GET');

$router->addRoute('/getPayload', function (string $route, array $p) {
	global $m, $config;
	if (!empty($_GET['endpoint'])) {
		if ($_GET['endpoint'] != 'null') {
			$m->{$config['sitedb']}->endpoints->updateOne(
				[['_id' => (string)session_id()]],
				[
					'$set' => [
						'endpoint' => json_decode($_GET['endpoint'])
					]
				],
				['upsert' => true]
			);
		} else {
			$m->{$config['sitedb']}->endpoints->deleteOne([['_id' => (string)session_id()]]);
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
	$template = $twig->load('sw.js');
	header('Content-Type: application/javascript; charset=utf-8');
	echo $template->render([
		'publicKey' => $config['notifications']['publicKey'],
		'tocache' => array_values(array_merge($nframework->csss, $nframework->jss)),
		'csss' => implode("','", $nframework->csss),
		'jss' => implode("','", $nframework->jss)
	]);
}, 'GET');

foreach ($m->{$config['sitedb']}->pages->distinct('path') as $d) {
	if (!empty($d)) {
		$router->addRoute($d, function ($route, $arg) {
			global $m, $config, $nframework, $twig;
			$page = $m->{$config['sitedb']}->pages->findOne(['path' => $route]);
			$nframework->metas['description'] = $page->description;
			$nframework->metas['title'] = $page->title;
			$nframework->metas['keywords'] = $page->keywords;

			$menud = $m->{$config['sitedb']}->menus->findOne(['name' => '_nav']);
			if ($menud) {
				$menu = nfMetroMenu($menud->code);
			}

			$header = $m->{$config['sitedb']}->pages->findOne(['path' => '_header']);
			$footer = $m->{$config['sitedb']}->pages->findOne(['path' => '_footer']);
			$nframework->usecommon = true;
			$template = $twig->load('page.html');


			echo $template->render([
				'theme' => $config['theme'],
				'page' => $page->html,
				'header' => renderEmbeddedFunctions($header->html),
				'footer' => $footer->html,
				'route' => $route,
			]);
			//echo $page->html;
		}, 'GET'); // this handler will be called for POST requests
	}
}
$router->addRoute('/cachetest.png', function ($route, $arg) {
	global $m;
	//$developermode=true;
	$cache = new cache(__DIR__ . '/profilepict.png');
	$cache->contentType = 'image/png';
	$cache->cache();
});
