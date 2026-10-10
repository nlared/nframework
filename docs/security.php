<?php
require 'common.php';

$redirecciones = ['/admin/', '//otro-sitio.com/', 'https://otro-sitio.com/robar', 'javascript:alert(1)', 'https://' . nfSiteHost() . '/cuenta'];
$filasRedir = '';
foreach ($redirecciones as $url) {
	$filasRedir .= '<tr><td><code>' . htmlspecialchars($url) . '</code></td><td><code>' . htmlspecialchars(nfSafeRedirect($url)) . '</code></td></tr>';
}

$consulta = ['nombre' => 'Ana', '$where' => 'sleep(5000)', 'edad' => ['$gt' => 18, '$function' => ['body' => '...']]];
?>
<div class="container">
	<?= docHeader('Seguridad', 'Qué hace el framework automáticamente y qué le toca a cada página.') ?>

	<h3>Automático</h3>
	<ul>
		<li><b>CSRF por origen</b>: todo POST/PUT/PATCH/DELETE cuyo <code>Origin</code> (o <code>Referer</code>) sea de otro dominio recibe 403 antes de llegar a la página. Excepciones en <code>csrf_trusted_origins</code> y <code>csrf_exempt_paths</code> de la configuración.</li>
		<li><b>Sesión</b>: cookie <code>HttpOnly</code>, <code>SameSite=Lax</code> y <code>Secure</code> en HTTPS; el id se regenera al iniciar sesión.</li>
		<li><b>Bloqueo de IP</b>: más de 10 peticiones marcadas por las reglas de Admin → Seguridad en la ventana configurada bloquean la IP.</li>
		<li><b>Cabeceras</b>: <code>X-Content-Type-Options: nosniff</code>, <code>X-Frame-Options: SAMEORIGIN</code> y HSTS opcional (<code>hsts_max_age</code>).</li>
		<li><b>Archivos subidos</b>: <code>uploadfile.php</code> rechaza extensiones ejecutables y nombres ocultos; la carpeta destino nunca la decide el navegador.</li>
	</ul>

	<h3>Formularios: token CSRF</h3>
	<p><code>secureform()</code> incluye un token ligado a la sesión, al navegador y a la acción. Valídelo antes de modificar datos:</p>
	<?= docCode(<<<'PHP'
if ($nframework->isAjax()) {
    if (!csrfValidate()) {
        $result = ['error' => 'Formulario caducado, recargue la página.'];
        return;
    }
    // ... guardar
}
echo secureform();          // AJAX a la misma página
echo secureform($accion);   // POST normal a $accion (use la misma URL con la que se abre la página)
PHP) ?>

	<h3>Escapar la salida</h3>
	<?= docCode(<<<'PHP'
echo htmlspecialchars($doc['nombre']);                      // en HTML
echo '<a href="?id=' . urlencode($id) . '">';              // en URLs
$javas->addjs('const datos = ' . json_encode($datos) . ';'); // en JavaScript
// Las celdas de Table y el contenido de Dialog son HTML: escape antes de pasarlos.
PHP) ?>

	<h3>Datos del usuario en consultas MongoDB</h3>
	<p>PHP convierte <code>?usuario[$ne]=x</code> en un arreglo, que MongoDB interpreta como operador. Fuerce el tipo de todo lo que llegue en <code>$_GET</code>/<code>$_POST</code>:</p>
	<?= docCode(<<<'PHP'
// Mal: ?usuario[$ne]=nadie devuelve el primer usuario.
$m->{$config['sitedb']}->users->findOne(['username' => $_GET['usuario']]);

// Bien:
$usuario = is_string($_GET['usuario'] ?? null) ? $_GET['usuario'] : '';
$m->{$config['sitedb']}->users->findOne(['username' => $usuario]);

// ObjectId desde la URL:
if (!isValidObjectId($_GET['_id'] ?? null)) { http_response_code(404); exit(); }
$doc = $coleccion->findOne(['_id' => toMongoId($_GET['_id'])]);

// Búsqueda por texto: escape la expresión regular.
$filtro = ['nombre' => new MongoDB\BSON\Regex(preg_quote($texto), 'i')];
PHP) ?>
	<p>Si un filtro completo viene del cliente, <code>nfSanitizeMongoQuery()</code> quita los operadores que ejecutan código (<code>$where</code>, <code>$function</code>, <code>$accumulator</code>, <code>$expr</code>, <code>$lookup</code>, <code>$merge</code>...). No quita <code>$ne</code> ni <code>$gt</code>:</p>
	<div class="grid">
		<div class="row">
			<div class="cell-md-6"><b>Entrada</b><?= docCode(var_export($consulta, true)) ?></div>
			<div class="cell-md-6"><b>nfSanitizeMongoQuery()</b><?= docCode(var_export(nfSanitizeMongoQuery($consulta), true)) ?></div>
		</div>
	</div>

	<h3>Redirecciones</h3>
	<p>Nunca redirija a una URL que venga del usuario sin pasarla por <code>nfSafeRedirect()</code>: solo deja rutas propias y los hosts de <code>allowed_redirect_hosts</code>.</p>
	<table class="table striped compact">
		<thead><tr><th><code>nfSafeRedirect($url)</code></th><th>Resultado</th></tr></thead>
		<tbody><?= $filasRedir ?></tbody>
	</table>
	<?= docCode("header('Location: ' . nfSafeRedirect(\$_GET['volver'] ?? '/'));") ?>

	<h3>Limitar intentos</h3>
	<?= docCode(<<<'PHP'
// Máximo 5 intentos por IP cada 15 minutos; al pasarse queda bloqueado ese tiempo.
if (!nflogAttempt('cupon:' . $ip, 5, 900)) {
    $result = ['error' => 'Demasiados intentos, intente más tarde.'];
    return;
}
if (cuponValido($codigo)) {
    nflogReset('cupon:' . $ip);   // éxito: reinicia el contador
}
PHP) ?>

	<h3>Contraseñas</h3>
	<?= docCode(<<<'PHP'
$hash = nfPasswordHash($clave);                  // password_hash() con el algoritmo por defecto de PHP
if (nfPasswordVerify($clave, $hash)) { /* ... */ } // también acepta los hash sha512 antiguos
// Mínimo de longitud del framework: NF_PASSWORD_MIN_LENGTH (8).
PHP) ?>
</div>
