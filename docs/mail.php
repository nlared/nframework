<?php
require 'common.php';

$smtp = (array) ($config['smtp'] ?? []);
$destino = $user->isLoggedIn() && filter_var($user->username, FILTER_VALIDATE_EMAIL) ? $user->username : '';

if ($nframework->isAjax()) {
	if (!csrfValidate()) {
		$result = ['error' => 'Formulario caducado, recargue la página.'];
	} elseif (($_POST['op'] ?? '') === 'probar') {
		if ($destino === '') {
			$result = ['error' => 'Inicie sesión con un usuario cuyo nombre sea un correo.'];
		} elseif (!nflogAttempt('docsmail:' . $user->_id, 5, 3600)) {
			$result = ['error' => 'Demasiados envíos de prueba; intente en una hora.'];
		} else {
			try {
				$mail = nfMailer();
				$mail->addAddress($destino);
				$mail->Subject = 'Prueba de correo de nframework';
				$mail->Body = '<p>Este correo se envió desde <strong>' . htmlspecialchars(nfSiteHost()) . '</strong> con <code>nfMailer()</code>.</p>';
				$mail->AltBody = 'Este correo se envió desde ' . nfSiteHost() . ' con nfMailer().';
				$mail->send();
				$result = ['error' => false, 'ids' => ['resultadoCorreo' => 'Enviado a ' . htmlspecialchars($destino) . ' a las ' . date('H:i:s')]];
			} catch (PHPMailer\PHPMailer\Exception $e) {
				$result = ['error' => 'No se pudo enviar: ' . $e->getMessage()];
			}
		}
	}
	return;
}

$mostrar = [];
foreach (['host', 'port', 'secure', 'auth', 'username', 'fromemail', 'fromname', 'timeout'] as $k) {
	$mostrar[$k] = $smtp[$k] ?? null;
}
?>
<div class="container">
	<?= docHeader('Correo (nfMailer)', '<code>nfMailer()</code> devuelve un <a href="https://github.com/PHPMailer/PHPMailer" target="_blank">PHPMailer</a> listo para enviar con el servidor SMTP del sitio. Está en <code>includes/functions.php</code>: funciona en páginas, rutas y <a href="jobs.php">jobs</a>.') ?>

	<h3>Enviar un correo</h3>
	<?= docCode(<<<'PHP'
$mail = nfMailer();                         // SMTP, UTF-8, HTML y remitente ya configurados
$mail->addAddress($cliente['correo'], $cliente['nombre']);
$mail->addCC('ventas@ejemplo.com');
$mail->addReplyTo('soporte@ejemplo.com');
$mail->Subject = 'Su pedido ' . $folio;
$mail->Body = '<p>Hola ' . htmlspecialchars($cliente['nombre']) . ', adjuntamos su factura.</p>';
$mail->AltBody = 'Hola ' . $cliente['nombre'] . ', adjuntamos su factura.';   // versión en texto
$mail->addAttachment('/var/data/facturas/' . basename($xml));                // adjunto desde disco
$mail->addStringAttachment($pdf, 'factura.pdf', 'base64', 'application/pdf'); // adjunto generado
$mail->send();                              // lanza PHPMailer\PHPMailer\Exception si falla

// Las URL de los correos se arman con nfSiteHost() / $config['url'], nunca con $_SERVER['HTTP_HOST'].
$liga = 'https://' . nfSiteHost() . '/pedidos/' . urlencode($folio);
PHP) ?>
	<p>Para no hacer esperar al usuario (o reintentar si el servidor SMTP falla), envíe desde un <a href="jobs.php">Job</a> en lugar de hacerlo en la petición.</p>

	<h3>Configuración</h3>
	<p>Se toma de <code>$config['smtp']</code>: Admin → Sitio (guardado en MongoDB, con botón de prueba) o <code>includes/config.php</code>.</p>
	<table class="table striped compact">
		<thead><tr><th>Clave</th><th>Descripción</th><th>En este sitio</th></tr></thead>
		<tbody>
			<?php
			$desc = [
				'host' => 'Servidor SMTP.',
				'port' => 'Puerto (25 por defecto; 465 con ssl, 587 con tls).',
				'secure' => '<code>ssl</code> (SMTPS) o <code>tls</code> (STARTTLS); vacío para sin cifrado.',
				'auth' => '<code>true</code> si el servidor pide usuario y contraseña.',
				'username' => 'Usuario SMTP (<code>password</code> va junto; no se muestra aquí).',
				'fromemail' => 'Correo del remitente.',
				'fromname' => 'Nombre del remitente.',
				'timeout' => 'Segundos de espera de la conexión (opcional).',
			];
			foreach ($mostrar as $k => $v) {
				echo '<tr><td><code>' . $k . '</code></td><td>' . $desc[$k] . '</td><td><code>' . htmlspecialchars(var_export($v, true)) . '</code></td></tr>';
			}
			?>
		</tbody>
	</table>
	<?= docCode(<<<'PHP'
// Otro servidor o remitente para un envío concreto (no toca $config['smtp']):
$mail = nfMailer([
    'host' => 'smtp.office365.com', 'port' => 587, 'secure' => 'tls', 'auth' => true,
    'username' => 'facturas@ejemplo.com', 'password' => $clave,
    'fromemail' => 'facturas@ejemplo.com', 'fromname' => 'Facturación', 'timeout' => 15,
]);
PHP) ?>

	<h3>Prueba</h3>
	<?php if ($destino === '') { ?>
		<p class="text-muted">Inicie sesión para enviarse un correo de prueba.</p>
	<?php } else { ?>
		<?= secureform() ?>
			<button class="button primary secureop" value="probar"><span class="mif-mail"></span> Enviarme un correo de prueba</button>
			<span id="resultadoCorreo" class="ml-2 text-muted">Se enviará a <?= htmlspecialchars($destino) ?> (máximo 5 por hora).</span>
		</form>
	<?php } ?>

	<?= docSource(__FILE__) ?>
</div>
