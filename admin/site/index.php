<?php
require '../common2.php';
require 'timezonelist.php';
$dataset = new dataset(
	[
		'collection' => $m->{$config['sitedb']}->configs,
		'_id' => 'site',
		'simpleid' => true,
		'nameprefix' => 'data'
	]
);

$themes = [];
foreach (scandir('../../themes/') ?: [] as $themedir) {
	if ($themedir[0] !== '.' && is_dir('../../themes/' . $themedir)) {
		$themes[$themedir] = $themedir;
	}
}

$pdfConverters = [
	'' => 'DOMPDF (integrado, sin dependencias)',
	'unoconv' => 'unoconv (LibreOffice local)',
	'unoconv-server' => 'unoconv server (servicio remoto)'
];

// Límites de longitud: se aplican en el navegador (maxlength) y en el servidor (siteConfigError()).
const SITE_LIMITS = [
	'title' => 120,
	'shortname' => 30,
	'tagline' => 160,
	'description' => 500,
	'url' => 255,
	'image' => 500,
	'email' => 254,
	'passwordmask' => 300,
	'smtp.host' => 253,
	'smtp.username' => 254,
	'smtp.fromname' => 100,
	'smtp.fromemail' => 254,
	'letsencrypt_email' => 254,
	'google-site-verification' => 200,
	'google-captcha-key' => 200,
	'google-captcha-secret' => 200,
	'google-maps-api' => 200,
];
// Todos los patrones aceptan vacío: Metro (requiredMode) evalúa las reglas aunque el campo esté vacío
// y estos campos son opcionales (no todos los sitios usan correo, Google o Let's Encrypt).
const SITE_HOST_PATTERN = '^([A-Za-z0-9]([A-Za-z0-9.\-]*[A-Za-z0-9])?)?$';
const SITE_KEY_PATTERN = '^[A-Za-z0-9_\-]*$';
const SITE_EMAIL_PATTERN = '^([^@\s]+@[^@\s]+\.[^@\s]+)?$';
const SITE_URL_PATTERN = '^(https?://[A-Za-z0-9.\-]+(:[0-9]+)?/?)?$';

/**
 * Opciones comunes de un campo de texto con límite de longitud.
 */
function siteTextOptions(string $field, array $options = []): array
{
	$max = SITE_LIMITS[$field] ?? 255;
	$options['validate'] = trim(($options['validate'] ?? '') . ' maxlength=' . $max);
	$options['tags'] = ($options['tags'] ?? []) + ['maxlength' => $max];
	return $options + ['field' => $field];
}

/**
 * Texto de ayuda bajo un campo.
 */
function siteHelp(string $html): string
{
	return '<small class="d-block fg-gray mt-1 mb-2">' . $html . '</small>';
}

/**
 * Título de sección con icono y descripción.
 */
function siteSection(string $icon, string $title, string $description): string
{
	return '<div class="row mt-6"><div class="cell">' .
		'<h4 class="mb-1"><span class="mif-' . $icon . '"></span>&nbsp;' . $title . '</h4>' .
		'<p class="fg-gray mt-0">' . $description . '</p><hr></div></div>';
}

/**
 * Normaliza el arreglo SMTP enviado por el formulario (data[smtp.*]) a la forma de $config['smtp'].
 */
function siteSmtpFromPost(array $data): array
{
	$smtp = [];
	foreach (['host', 'port', 'username', 'fromname', 'fromemail', 'secure', 'auth'] as $key) {
		$value = $data['smtp.' . $key] ?? '';
		$smtp[$key] = is_string($value) ? trim($value) : '';
	}
	return $smtp;
}

/**
 * Valida lo que no cubre is_valid() de los componentes (longitudes, rangos, listas cerradas y formatos).
 * Devuelve el mensaje de error o ''.
 */
function siteConfigError(array $data, array $timezones, array $themes, array $pdfConverters): string
{
	$errors = [];
	$text = static fn(string $key): string => is_string($data[$key] ?? null) ? trim($data[$key]) : '';

	foreach (SITE_LIMITS as $field => $max) {
		if (mb_strlen($text($field)) > $max) {
			$errors[] = "«{$field}» admite como máximo {$max} caracteres.";
		}
	}
	foreach (['title' => 'El nombre del sitio', 'shortname' => 'El nombre corto', 'description' => 'La descripción'] as $field => $label) {
		if ($text($field) === '') {
			$errors[] = $label . ' es obligatorio.';
		}
	}
	$url = $text('url');
	if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://[^/]+/?$#i', $url))) {
		$errors[] = 'La URL pública debe ser solo esquema y dominio, p. ej. https://www.ejemplo.com';
	}
	foreach (['email' => 'El correo del webmaster', 'smtp.fromemail' => 'El correo del remitente', 'letsencrypt_email' => 'El correo de Let\'s Encrypt'] as $field => $label) {
		if ($text($field) !== '' && !filter_var($text($field), FILTER_VALIDATE_EMAIL)) {
			$errors[] = $label . ' no es una dirección válida.';
		}
	}
	if ($text('timezone') !== '' && !isset($timezones[$text('timezone')])) {
		$errors[] = 'Zona horaria desconocida.';
	}
	if ($text('theme') !== '' && !isset($themes[$text('theme')])) {
		$errors[] = 'El tema seleccionado no existe en /themes.';
	}
	if (!in_array($text('homepagetype'), ['', 'page', 'blog'], true)) {
		$errors[] = 'Tipo de página de inicio inválido.';
	}
	foreach (['word_pdf_converter', 'excel_pdf_converter'] as $field) {
		if (!isset($pdfConverters[$text($field)])) {
			$errors[] = 'Convertidor a PDF inválido.';
		}
	}
	if ($text('passwordmask') !== '' && @preg_match($text('passwordmask'), '') === false) {
		$errors[] = 'La máscara de contraseña no es una expresión regular válida (incluya los delimitadores, p. ej. /^.{8,}$/).';
	}
	foreach (['google-site-verification', 'google-captcha-key', 'google-captcha-secret', 'google-maps-api'] as $field) {
		if ($text($field) !== '' && !preg_match('/' . SITE_KEY_PATTERN . '/', $text($field))) {
			$errors[] = "«{$field}» solo admite letras, números, guion y guion bajo.";
		}
	}
	if ($text('letsencrypt_use') !== '' && $text('letsencrypt_email') === '') {
		$errors[] = 'Para usar Let\'s Encrypt indique el correo de la cuenta.';
	}

	$smtp = siteSmtpFromPost($data);
	if ($smtp['host'] !== '') {
		if (!preg_match('/' . SITE_HOST_PATTERN . '/', $smtp['host'])) {
			$errors[] = 'El servidor SMTP debe ser un nombre de host o IP, sin protocolo ni puerto.';
		}
		if (!ctype_digit($smtp['port']) || (int) $smtp['port'] < 1 || (int) $smtp['port'] > 65535) {
			$errors[] = 'El puerto SMTP debe ser un número entre 1 y 65535 (habitualmente 25, 465 o 587).';
		}
		if ($smtp['fromemail'] === '') {
			$errors[] = 'Indique el correo del remitente: sin él los servidores rechazan los mensajes.';
		}
		if ($smtp['auth'] !== '' && $smtp['username'] === '') {
			$errors[] = 'Con autenticación SMTP activada el usuario es obligatorio.';
		}
	}
	if (!in_array($smtp['secure'], ['', 'ssl', 'tls'], true)) {
		$errors[] = 'Tipo de cifrado SMTP inválido.';
	}

	return implode('<br>', $errors);
}

/**
 * Envía un correo de prueba con la configuración SMTP indicada.
 * Devuelve ['ok' => bool, 'message' => string, 'log' => string] con la conversación SMTP sin credenciales.
 */
function siteSmtpTest(array $smtp, string $to, string $siteTitle): array
{
	$log = [];
	try {
		$mail = nfMailer($smtp + ['timeout' => 15]);
		$mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_CONNECTION;
		$mail->Debugoutput = static function ($line) use (&$log) {
			$line = trim((string) $line);
			// Del cliente solo se muestran los comandos; AUTH, usuario, contraseña y cuerpo se omiten.
			if (str_starts_with($line, 'CLIENT -> SERVER:')) {
				$command = trim(substr($line, 17));
				if (preg_match('/^AUTH\s+\S+/i', $command, $match)) {
					$line = 'CLIENT -> SERVER: ' . $match[0] . ' [credenciales ocultas]';
				} elseif (!preg_match('/^((EHLO|HELO|STARTTLS|MAIL FROM|RCPT TO|DATA|QUIT|RSET)\b|\.$)/i', $command)) {
					return;
				}
			}
			$log[] = $line;
		};
		$mail->addAddress($to);
		$mail->Subject = 'Correo de prueba - ' . $siteTitle;
		$mail->Body = '<p>Este es un correo de prueba enviado desde <b>Administración → Sitio</b> de ' .
			htmlspecialchars($siteTitle) . '.</p><p>Si lo recibe, la configuración SMTP funciona.</p>' .
			'<p><small>Servidor: ' . htmlspecialchars($smtp['host'] . ':' . $smtp['port']) .
			' · Cifrado: ' . htmlspecialchars($smtp['secure'] ?: 'ninguno') .
			' · Fecha: ' . date('Y-m-d H:i:s T') . '</small></p>';
		$mail->AltBody = 'Correo de prueba de ' . $siteTitle . '. Si lo recibe, la configuración SMTP funciona.';
		$mail->send();
		return ['ok' => true, 'message' => 'Correo enviado a ' . $to . '. Revise también la carpeta de spam.', 'log' => implode("\n", $log)];
	} catch (\Throwable $e) {
		$message = isset($mail) && $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
		return ['ok' => false, 'message' => $message, 'log' => implode("\n", $log)];
	}
}

/* ---------- Identidad ---------- */
$title = new inputText(siteTextOptions('title', ['dataset' => &$dataset, 'caption' => 'Nombre del sitio:', 'required' => true, 'placeholder' => 'Mi Empresa S.A. de C.V.']));
$shortname = new inputText(siteTextOptions('shortname', ['dataset' => &$dataset, 'caption' => 'Nombre corto (app / pestaña):', 'required' => true, 'placeholder' => 'MiEmpresa']));
$tagline = new inputText(siteTextOptions('tagline', ['dataset' => &$dataset, 'caption' => 'Lema o subtítulo:', 'placeholder' => 'Soluciones para su negocio']));
// Dominio público para enlaces de correos, sitemap, robots y manifiesto (ver nfSiteHost()).
$siteurl = new inputText(siteTextOptions('url', ['dataset' => &$dataset, 'caption' => 'URL pública del sitio:', 'placeholder' => 'https://www.ejemplo.com', 'pattern' => SITE_URL_PATTERN]));
$image = new inputText(siteTextOptions('image', ['dataset' => &$dataset, 'caption' => 'Imagen para redes sociales (ruta o URL):', 'placeholder' => 'https://www.ejemplo.com/img/portada.png']));
$description = new textarea(siteTextOptions('description', ['dataset' => &$dataset, 'caption' => 'Descripción del sitio (meta description):', 'required' => true, 'charscounter' => '#description_counter']));

$logo = new inputfile([
	'id' => 'logo',
	'name' => 'logo',
	'dir' => $_SERVER['DOCUMENT_ROOT'] . '/img/nf/',
	'path' => $_SERVER['DOCUMENT_ROOT'] . '/img/nf/logo.png',
	'accept' => '.png',
	'drop' => false,
	'onDone' => <<<js
	if (data.files.length==1){
		$("#img-container").html('<img width="256" src="/images/config/256/logo.png?time='+Date.now()+'">');
	}
js
]);

/* ---------- Regional y apariencia ---------- */
$timezone = new select(['dataset' => &$dataset, 'field' => 'timezone', 'caption' => 'Zona horaria del servidor:', 'options' => $timezones]);
$theme = new select(['dataset' => &$dataset, 'field' => 'theme', 'caption' => 'Tema visual:', 'options' => $themes]);
$homepagetype = new inputradios(['dataset' => &$dataset, 'field' => 'homepagetype', 'caption' => 'La página de inicio muestra:<br>', 'options' => [
	'page' => 'Una página fija (_home)',
	'blog' => 'Las últimas entradas (blog)'
]]);

/* ---------- Cuentas ---------- */
$email = new inputText(siteTextOptions('email', ['dataset' => &$dataset, 'pattern' => SITE_EMAIL_PATTERN, 'tags' => ['inputmode' => 'email'], 'caption' => 'Correo del webmaster (opcional):', 'placeholder' => 'webmaster@ejemplo.com']));
$canregister = new inputcheckbox(['dataset' => &$dataset, 'field' => 'canregister', 'caption' => 'Permitir que los visitantes creen su propia cuenta']);
$passwordmask = new inputText(siteTextOptions('passwordmask', ['dataset' => &$dataset, 'caption' => 'Expresión regular para contraseñas:', 'default' => '/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/', 'required' => true]));

/* ---------- Correo saliente (SMTP) ---------- */
$mailhost = new inputText(siteTextOptions('smtp.host', ['dataset' => &$dataset, 'caption' => 'Servidor SMTP:', 'placeholder' => 'smtp.ejemplo.com', 'pattern' => SITE_HOST_PATTERN]));
$mailport = new inputNumber(['dataset' => &$dataset, 'field' => 'smtp.port', 'caption' => 'Puerto:', 'placeholder' => '587', 'validate' => 'integer max=65535', 'data_validate' => 'integer', 'min' => 1, 'max' => 65535, 'step' => 1]);
$mailsmtpauth = new inputcheckbox(['dataset' => &$dataset, 'field' => 'smtp.auth', 'caption' => 'El servidor requiere usuario y contraseña']);
$mailusername = new inputText(siteTextOptions('smtp.username', ['dataset' => &$dataset, 'caption' => 'Usuario SMTP:', 'placeholder' => 'usuario@ejemplo.com']));
// La contraseña no se liga al dataset para no imprimirla en el HTML: vacía = conservar la guardada.
$mailpassword = new inputText([
	'name' => 'smtp_password',
	'type' => 'password',
	'caption' => 'Contraseña SMTP:',
	'autocomplete' => 'new-password',
	'placeholder' => empty($config['smtp']['password']) ? 'Sin contraseña guardada' : '•••••••• (guardada; deje vacío para conservarla)',
	'validate' => 'maxlength=255',
	'tags' => ['maxlength' => 255],
]);
$mailcrypt = new inputradios(['dataset' => &$dataset, 'field' => 'smtp.secure', 'caption' => 'Cifrado de la conexión:<br>', 'options' => [
	'' => 'Ninguno (puerto 25)',
	'tls' => 'STARTTLS (puerto 587)',
	'ssl' => 'SSL/TLS implícito (puerto 465)'
]]);
$mailfromname = new inputText(siteTextOptions('smtp.fromname', ['dataset' => &$dataset, 'caption' => 'Nombre del remitente:', 'placeholder' => 'Mi Empresa']));
$mailfromemail = new inputText(siteTextOptions('smtp.fromemail', ['dataset' => &$dataset, 'pattern' => SITE_EMAIL_PATTERN, 'tags' => ['inputmode' => 'email'], 'caption' => 'Correo del remitente (From):', 'placeholder' => 'no-responder@ejemplo.com']));

/* ---------- Let's Encrypt ---------- */
$letsencryptuse = new inputcheckbox(['dataset' => &$dataset, 'field' => 'letsencrypt_use', 'caption' => 'Usar certificados de Let\'s Encrypt']);
$letsencryptemail = new inputText(siteTextOptions('letsencrypt_email', ['dataset' => &$dataset, 'pattern' => SITE_EMAIL_PATTERN, 'tags' => ['inputmode' => 'email'], 'caption' => 'Correo de la cuenta ACME (avisos de caducidad):', 'placeholder' => 'ssl@ejemplo.com']));

/* ---------- Google ---------- */
$keyOptions = ['dataset' => &$dataset, 'pattern' => SITE_KEY_PATTERN, 'autocomplete' => 'off'];
$google_site_verification = new inputText(siteTextOptions('google-site-verification', $keyOptions + ['caption' => 'Verificación de Search Console:']));
$google_maps_api = new inputText(siteTextOptions('google-maps-api', $keyOptions + ['caption' => 'Llave de API de Google Maps:', 'placeholder' => 'AIza...']));
$google_captcha_key = new inputText(siteTextOptions('google-captcha-key', $keyOptions + ['caption' => 'reCAPTCHA: llave del sitio (pública):']));
$google_captcha_secret = new inputText(siteTextOptions('google-captcha-secret', $keyOptions + ['caption' => 'reCAPTCHA: llave secreta:']));

/* ---------- Conversión a PDF ---------- */
$wordtopdfconvertion = new select(['dataset' => &$dataset, 'field' => 'word_pdf_converter', 'caption' => 'Convertir Word a PDF con:', 'options' => $pdfConverters]);
$exceltopdfconvertion = new select(['dataset' => &$dataset, 'field' => 'excel_pdf_converter', 'caption' => 'Convertir Excel a PDF con:', 'options' => $pdfConverters]);

if ($nframework->isAjax()) {
	$op = is_string($_POST['op'] ?? null) ? $_POST['op'] : '';
	$data = is_array($_POST['data'] ?? null) ? $_POST['data'] : [];
	if (!csrfValidate()) {
		$result = ['error' => 'Formulario caducado, recargue la página.'];
	} elseif ($op === 'save') {
		$error = siteConfigError($data, $timezones, $themes, $pdfConverters);
		if ($error === '') {
			try {
				$error = $dataset->save();
				$password = is_string($_POST['smtp_password'] ?? null) ? $_POST['smtp_password'] : '';
				if ($error === false && $password !== '') {
					$m->{$config['sitedb']}->configs->updateOne(['_id' => 'site'], ['$set' => ['smtp.password' => $password]], ['upsert' => true]);
				}
			} catch (Exception $e) {
				$error = $e->getMessage();
			}
		}
		$result = ['error' => $error === '' ? false : $error];
	} elseif ($op === 'testmail') {
		// Prueba los valores del formulario (aunque no estén guardados); el envío real limita abusos.
		$to = is_string($_POST['to'] ?? null) ? trim($_POST['to']) : '';
		$smtp = siteSmtpFromPost($data);
		$password = is_string($_POST['smtp_password'] ?? null) ? $_POST['smtp_password'] : '';
		$smtp['password'] = $password !== '' ? $password : ($config['smtp']['password'] ?? '');
		if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
			$result = ['ok' => false, 'message' => 'Escriba una dirección de destino válida.'];
		} elseif ($smtp['host'] === '') {
			$result = ['ok' => false, 'message' => 'Indique el servidor SMTP.'];
		} elseif (($error = siteConfigError($data, $timezones, $themes, $pdfConverters)) !== '') {
			$result = ['ok' => false, 'message' => strip_tags(str_replace('<br>', "\n", $error))];
		} elseif (!nflogAttempt('smtptest:' . $user->_id, 10, 900)) {
			$result = ['ok' => false, 'message' => 'Demasiadas pruebas seguidas; espere 15 minutos.'];
		} else {
			$siteTitle = is_string($data['title'] ?? null) && $data['title'] !== '' ? $data['title'] : (string) ($config['title'] ?? 'nframework');
			$result = siteSmtpTest($smtp, $to, $siteTitle);
		}
	}
	return;
}

$nframework->usecommon = true;
$testTo = filter_var($user->email ?? '', FILTER_VALIDATE_EMAIL) ?: (filter_var($user->username, FILTER_VALIDATE_EMAIL) ?: '');
$javas->addjs(<<<'JS'
$('#smtpTestBtn').on('click', function () {
	var btn = $(this), to = $('#smtpTestTo').val().trim(), log = $('#smtpTestLog');
	if (!to) {
		Swal.fire({icon: 'warning', title: 'Falta el destinatario', text: 'Escriba el correo al que se enviará la prueba.'});
		return;
	}
	var data = $('#siteform').serializeArray().filter(function (f) { return f.name !== 'op'; });
	data.push({name: 'op', value: 'testmail'}, {name: 'to', value: to});
	btn.prop('disabled', true).find('.caption').text('Enviando…');
	$.post(location.pathname, $.param(data)).done(function (r) {
		Swal.fire({icon: r.ok ? 'success' : 'error', title: r.ok ? 'Correo enviado' : 'No se pudo enviar', text: r.message || ''});
		log.text(r.log || '').toggleClass('d-none', !r.log);
	}).fail(function (x) {
		Swal.fire({icon: 'error', title: 'Error', text: 'El servidor respondió ' + x.status + '.'});
	}).always(function () {
		btn.prop('disabled', false).find('.caption').text('Enviar correo de prueba');
	});
});
$('#data_smtp_secure_0, #data_smtp_secure_1, #data_smtp_secure_2').on('change', function () {
	var ports = {'': 25, 'tls': 587, 'ssl': 465}, port = $('#data_smtp_port');
	if (Object.values(ports).indexOf(parseInt(port.val(), 10)) !== -1 || port.val() === '') {
		port.val(ports[this.value]);
	}
});
JS, 'ready');
?>
<div class="container">
	<div class="bo">
		<h1 class="text-weight-10 text-center gradient gr-text-blue">Configuración del sitio</h1>
		<p class="text-center fg-gray">Datos generales, correo saliente y servicios externos. Los cambios se aplican en todo el sitio al guardar.</p>
	</div>
	<div class="box shadow-large-extra p-5">
		<?= secureform('', false, 'siteform') ?>
		<div class="grid">
			<?= siteSection('info', 'Identidad del sitio', 'Cómo se presenta el sitio en el navegador, buscadores, redes sociales y al instalarlo como aplicación.') ?>
			<div class="row">
				<div class="cell-md-6"><?= $title ?><?= siteHelp('Aparece en la pestaña del navegador, en resultados de búsqueda y en los correos. Máx. ' . SITE_LIMITS['title'] . ' caracteres.') ?></div>
				<div class="cell-md-6"><?= $shortname ?><?= siteHelp('Se usa en el manifiesto de la app instalada (ícono en el teléfono). Recomendado 12 caracteres o menos; máx. ' . SITE_LIMITS['shortname'] . '.') ?></div>
			</div>
			<div class="row">
				<div class="cell"><?= $tagline ?><?= siteHelp('Frase breve que acompaña al nombre. Máx. ' . SITE_LIMITS['tagline'] . ' caracteres.') ?></div>
			</div>
			<div class="row">
				<div class="cell-md-6"><?= $siteurl ?></div>
				<div class="cell-md-6"><small>
					Se usa en los enlaces de los correos de activación y de restablecer contraseña, <code>robots.txt</code>, <code>sitemap.xml</code> y el manifiesto.
					Si está vacía se toma la cabecera <code>Host</code> de la petición, que el visitante puede falsificar. Solo esquema y dominio, sin rutas.
					<?php if (empty($config['url'])) { ?><br><span class="fg-red">Sin configurar.</span> Valor sugerido: <code>https://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') ?></code><?php } ?>
				</small></div>
			</div>
			<div class="row">
				<div class="cell"><?= $description ?>
					<?= siteHelp('Resumen que muestran Google y las redes sociales. Lo ideal son 150–160 caracteres; máx. ' . SITE_LIMITS['description'] . '. <span id="description_counter"></span>') ?>
				</div>
			</div>
			<div class="row">
				<div class="cell-md-6">
					<label>Logotipo (PNG cuadrado, de preferencia 512×512 px):</label>
					<?= $logo ?>
					<?= siteHelp('Se generan automáticamente los íconos de la app y del navegador a partir de este archivo.') ?>
					<div id="img-container"></div>
				</div>
				<div class="cell-md-6"><?= $image ?><?= siteHelp('Imagen que se muestra al compartir enlaces (Open Graph). Si se deja vacía se usa el logotipo. Recomendado 1200×630 px.') ?></div>
			</div>

			<?= siteSection('earth', 'Regional y apariencia', 'Hora usada para fechas y registros, y aspecto general del sitio.') ?>
			<div class="row">
				<div class="cell-md-4"><?= $timezone ?><?= siteHelp('Afecta las fechas que muestra y guarda PHP.') ?></div>
				<div class="cell-md-4"><?= $theme ?><?= siteHelp('Carpetas disponibles en <code>/themes</code>.') ?></div>
				<div class="cell-md-4"><?= $homepagetype ?></div>
			</div>

			<?= siteSection('users', 'Cuentas de usuario', 'Registro de visitantes y reglas para las contraseñas.') ?>
			<div class="row">
				<div class="cell-md-6"><?= $email ?><?= siteHelp('Dirección a la que se dirigen avisos técnicos del sitio.') ?></div>
				<div class="cell-md-6"><br><?= $canregister ?><?= siteHelp('Si se desactiva, solo un administrador puede dar de alta usuarios.') ?></div>
			</div>
			<div class="row">
				<div class="cell"><?= $passwordmask ?><?= siteHelp('Expresión regular PHP con delimitadores. Ejemplo: <code>/^(?=.*[A-Za-z])(?=.*\d).{' . NF_PASSWORD_MIN_LENGTH . ',}$/</code> exige letras, números y al menos ' . NF_PASSWORD_MIN_LENGTH . ' caracteres.') ?></div>
			</div>

			<?= siteSection('mail', 'Correo saliente (SMTP)', 'Opcional. Servidor con el que el sitio envía activaciones de cuenta, recuperación de contraseña y notificaciones. Si se deja vacío el sitio no envía correos.') ?>
			<div class="row">
				<div class="cell-md-8"><?= $mailhost ?><?= siteHelp('Solo el nombre del servidor, sin <code>smtp://</code> ni puerto. Ej.: <code>smtp.gmail.com</code>, <code>smtp.office365.com</code>.') ?></div>
				<div class="cell-md-4"><?= $mailport ?><?= siteHelp('1–65535. Normalmente 587 (STARTTLS) o 465 (SSL).') ?></div>
			</div>
			<div class="row">
				<div class="cell"><?= $mailcrypt ?><?= siteHelp('Al cambiar el cifrado se sugiere el puerto correspondiente.') ?></div>
			</div>
			<div class="row">
				<div class="cell"><?= $mailsmtpauth ?></div>
			</div>
			<div class="row">
				<div class="cell-md-6"><?= $mailusername ?><?= siteHelp('Normalmente es la dirección de correo completa.') ?></div>
				<div class="cell-md-6"><?= $mailpassword ?><?= siteHelp('Con Gmail o Microsoft 365 use una contraseña de aplicación.') ?></div>
			</div>
			<div class="row">
				<div class="cell-md-6"><?= $mailfromemail ?><?= siteHelp('Debe pertenecer a un dominio autorizado por el servidor (SPF/DKIM) o los correos llegarán a spam.') ?></div>
				<div class="cell-md-6"><?= $mailfromname ?><?= siteHelp('Nombre que ve el destinatario. Máx. ' . SITE_LIMITS['smtp.fromname'] . ' caracteres.') ?></div>
			</div>
			<div class="row">
				<div class="cell">
					<div class="border bd-default p-4 mt-2">
						<h5 class="mt-0"><span class="mif-paper-plane"></span>&nbsp;Probar la configuración</h5>
						<p class="fg-gray mt-0">Envía un mensaje con los valores escritos arriba, <b>aunque todavía no estén guardados</b>, y muestra la conversación con el servidor (sin credenciales).</p>
						<div class="row">
							<div class="cell-md-8">
								<label for="smtpTestTo">Enviar a:</label>
								<input type="email" id="smtpTestTo" data-role="input" maxlength="254" autocomplete="email" placeholder="usted@ejemplo.com" value="<?= htmlspecialchars($testTo) ?>">
							</div>
							<div class="cell-md-4"><label>&nbsp;</label>
								<button type="button" id="smtpTestBtn" class="button info w-100"><span class="mif-paper-plane"></span>&nbsp;<span class="caption">Enviar correo de prueba</span></button>
							</div>
						</div>
						<pre id="smtpTestLog" class="d-none mt-2 p-2 border bd-default" style="max-height:300px;overflow:auto;white-space:pre-wrap;font-size:.8rem"></pre>
					</div>
				</div>
			</div>

			<?= siteSection('lock', 'Certificados HTTPS (Let\'s Encrypt)', 'Opcional. Cuenta ACME para emitir y renovar certificados SSL gratuitos.') ?>
			<div class="row">
				<div class="cell-md-6"><br><?= $letsencryptuse ?></div>
				<div class="cell-md-6"><?= $letsencryptemail ?><?= siteHelp('Let\'s Encrypt avisa a este correo cuando un certificado está por vencer. Solo es obligatorio si se activa la casilla.') ?></div>
			</div>

			<?= siteSection('google', 'Servicios de Google', 'Opcionales: llene solo las de los servicios que use. Se obtienen en la consola de Google Cloud y en Search Console; solo letras, números, <code>-</code> y <code>_</code>.') ?>
			<div class="row">
				<div class="cell-md-6"><?= $google_site_verification ?><?= siteHelp('Valor del atributo <code>content</code> de la meta etiqueta que entrega Search Console.') ?></div>
				<div class="cell-md-6"><?= $google_maps_api ?><?= siteHelp('Para mapas y autocompletado de direcciones. Restrínjala a su dominio en Google Cloud.') ?></div>
			</div>
			<div class="row">
				<div class="cell-md-6"><?= $google_captcha_key ?><?= siteHelp('Se publica en las páginas con formulario.') ?></div>
				<div class="cell-md-6"><?= $google_captcha_secret ?><?= siteHelp('Solo se usa en el servidor; no la comparta.') ?></div>
			</div>

			<?= siteSection('file-pdf', 'Conversión de documentos a PDF', 'Motor usado por las exportaciones a PDF de Word y Excel.') ?>
			<div class="row">
				<div class="cell-md-6"><?= $wordtopdfconvertion ?></div>
				<div class="cell-md-6"><?= $exceltopdfconvertion ?></div>
			</div>
			<div class="row">
				<div class="cell"><?= siteHelp('DOMPDF no requiere nada adicional pero pierde parte del formato; unoconv conserva el diseño y necesita LibreOffice instalado en el servidor (o un servicio unoconv remoto).') ?></div>
			</div>

			<div class="row mt-6 justify-content-md-right">
				<div class="cell-md-2 offset-md-8"><a href="./" class="btn btn-primary button primary w-100"><span class="mif-exit"></span>&nbsp;<?= $nframework->language['close'] ?></a></div>
				<div class="cell-md-2"><button class="button btn btn-success secureop success w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;<?= $nframework->language['save'] ?></button></div>
			</div>
		</div>
		</form>
	</div>
</div>
