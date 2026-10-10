<?php
require 'common.php';
?>
<div class="container">
	<?= docHeader('Rutas', 'Los archivos .php se sirven directamente. Toda URL que no corresponde a un archivo llega a <code>/router.php</code>, que resuelve en este orden: rutas integradas de <code>nframework/router.php</code>, páginas creadas en Admin → Páginas, y las rutas propias de <code>crouter.php</code>. Si nada coincide, se muestra la página <code>_404</code> con código 404.') ?>

	<h3>Rutas propias: <code>crouter.php</code></h3>
	<p>Cree <code>crouter.php</code> en la raíz del sitio (junto a <code>router.php</code>). Ahí ya existen <code>$router</code> (<a href="https://github.com/alexdodonov/mezon-router" target="_blank">Mezon Router</a>), <code>$m</code>, <code>$config</code> y <code>$user</code>; dentro de cada función declárelos con <code>global</code>.</p>
	<?= docCode(<<<'PHP'
<?php
// crouter.php

// Página HTML con la plantilla del sitio.
$router->addRoute('/productos', function (string $route, array $p) {
    global $m, $config, $nframework;
    $nframework->usecommon = true;
    $nframework->metas['title'] = 'Productos';
    foreach ($m->{$config['sitedb']}->productos->find(['activo' => true]) as $producto) {
        echo '<h3>' . htmlspecialchars($producto['nombre']) . '</h3>';
    }
}, 'GET');

// Parámetros: [i:...] entero, [s:...] texto sin "/", [a:...] letras, números, _ . - @,
// [date:...] AAAA-MM-DD, [fp:...] decimal, [il:...] lista de enteros "1,2,3".
$router->addRoute('/productos/[i:id]', function (string $route, array $p) {
    global $m, $config;
    $producto = $m->{$config['sitedb']}->productos->findOne(['folio' => (int) $p['id']]);
    if (!$producto) {
        http_response_code(404);
        return;
    }
    echo htmlspecialchars($producto['nombre']);
}, 'GET');

// Reporte por fecha: /ventas/2026-06-03
$router->addRoute('/ventas/[date:dia]', function (string $route, array $p) {
    echo 'Ventas del ' . htmlspecialchars($p['dia']);
}, 'GET');
PHP) ?>

	<h3>API JSON</h3>
	<?= docCode(<<<'PHP'
// Un mismo path con varios métodos.
$router->addRoute('/api/notas', function () {
    global $m, $config, $user;
    $user->requireAuth();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($m->{$config['sitedb']}->notas->find(['owner' => $user->_id])->toArray());
}, 'GET');

$router->addRoute('/api/notas', function () {
    global $m, $config, $user;
    $user->requireAuth();
    $body = json_decode(file_get_contents('php://input'), true);
    $texto = is_string($body['texto'] ?? null) ? $body['texto'] : '';   // forzar tipos (ver Seguridad)
    $r = $m->{$config['sitedb']}->notas->insertOne(['owner' => $user->_id, 'texto' => $texto]);
    http_response_code(201);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['_id' => (string) $r->getInsertedId()]);
}, 'POST');

$router->addRoute('/api/notas/[s:id]', function (string $route, array $p) {
    global $m, $config, $user;
    $user->requireAuth();
    if (!isValidObjectId($p['id'])) { http_response_code(400); return; }
    $m->{$config['sitedb']}->notas->deleteOne(['_id' => toMongoId($p['id']), 'owner' => $user->_id]);
    http_response_code(204);
}, 'DELETE');
PHP) ?>
	<p>Un POST/PUT/DELETE desde otro dominio se rechaza con 403 (CSRF). Para recibir llamadas de un servicio externo con navegador agregue su dominio a <code>csrf_trusted_origins</code>; los webhooks entre servidores no envían <code>Origin</code> y pasan sin cambios.</p>

	<h3>Rutas integradas</h3>
	<table class="table striped compact">
		<thead><tr><th>Ruta</th><th>Qué hace</th></tr></thead>
		<tbody>
			<tr><td><code>/</code></td><td>Página de inicio (<code>_home</code> si <code>homepagetype = 'page'</code>).</td></tr>
			<tr><td><code>/account/login</code>, <code>/signup</code>, <code>/forgot</code>, <code>/reset</code>, <code>/activate/</code>, <code>/twofa</code>, <code>/logout</code></td><td>Cuentas de usuario (ver <a href="user.php">Usuarios</a>).</td></tr>
			<tr><td><code>/account/profile</code>, <code>/sessions</code>, <code>/apitokens</code>, <code>/totp-setup</code></td><td>Perfil, sesiones abiertas, tokens y QR para 2FA.</td></tr>
			<tr><td><code>/login-google/oauth</code>, <code>/login-facebook/oauth</code>, <code>/login-microsoft/oauth</code></td><td>Inicio de sesión con proveedores externos.</td></tr>
			<tr><td><code>/images/config/{tamaño}/logo.png</code>, <code>/images/config/{ancho}/{alto}/logo.png</code></td><td>Logo del sitio redimensionado.</td></tr>
			<tr><td><code>/images/resize/…</code>, <code>/images/pngtowebp/…</code>, <code>/images/frompdf/…</code></td><td>Imágenes generadas (ver <a href="images.php">Imágenes</a>).</td></tr>
			<tr><td><code>/robots.txt</code>, <code>/sitemap.xml</code>, <code>/nf.webmanifest</code>, <code>/sw.js</code></td><td>SEO y aplicación instalable (PWA).</td></tr>
			<tr><td><code>/privacy</code>, <code>/terms</code>, <code>/righttoforget</code></td><td>Textos legales (página propia o plantilla por idioma).</td></tr>
			<tr><td><code>/nftables/{colección}/…</code></td><td>Tablas administrables (solo grupo <code>admins</code>).</td></tr>
			<tr><td><code>/.well-known/acme-challenge/{token}</code></td><td>Validación de Let's Encrypt desde <code>acme_challenge_dir</code>.</td></tr>
		</tbody>
	</table>

	<h3>Páginas de Admin → Páginas</h3>
	<p>Cada página con <code>path</code> (p.ej. <code>/nosotros</code>) se publica en esa URL dentro de <code>page.html</code>, con <code>_header</code> y <code>_footer</code>. Los paths que empiezan con <code>_</code> son fragmentos y no son públicos.</p>
</div>
