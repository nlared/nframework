<?php
require 'common.php';

$carpeta = __DIR__ . '/tmp/imagenes';
$muestra = $carpeta . '/muestra.png';
if (!is_file($muestra) && function_exists('imagecreatetruecolor')) {
	// Imagen de ejemplo de 800x500 generada con GD.
	@mkdir($carpeta, 0777, true);
	$img = imagecreatetruecolor(800, 500);
	for ($y = 0; $y < 500; $y++) {
		imageline($img, 0, $y, 800, $y, imagecolorallocate($img, 27, (int) (161 - $y / 5), 226));
	}
	imagefilledellipse($img, 400, 250, 300, 300, imagecolorallocate($img, 255, 255, 255));
	imagestring($img, 5, 340, 242, 'nframework', imagecolorallocate($img, 27, 100, 226));
	imagepng($img, $muestra);
	imagedestroy($img);
}

// Registrar la carpeta: habilita /images/resize/galeria/... y /images/pngtoavif/galeria/...
$_SESSION['imagesresize']['galeria'] = [
	'src' => $carpeta,                      // originales
	'dst' => $carpeta . '/cache',           // tamaños generados (se reutilizan mientras el original no cambie)
	'default' => $muestra,                  // si el archivo pedido no existe
];
?>
<div class="container">
	<?= docHeader('Imágenes', 'Rutas que generan imágenes al vuelo y las guardan en caché con <code>ETag</code>: miniaturas, conversión a AVIF, páginas de PDF y el logo del sitio. Las carpetas se registran en la sesión, así que el navegador solo conoce un identificador.') ?>

	<h3>Miniaturas</h3>
	<p>
		<img src="/images/resize/galeria/400/250/muestra.png" alt="400x250" class="border bd-default">
		<img src="/images/resize/galeria/160/160/muestra.png" alt="160x160" class="border bd-default">
		<img src="/images/resize/galeria/80/80/muestra.png" alt="80x80" class="border bd-default">
	</p>
	<?= docCode(<<<'PHP'
$_SESSION['imagesresize']['galeria'] = [
    'src' => __DIR__ . '/tmp/imagenes',          // carpeta de originales
    'dst' => __DIR__ . '/tmp/imagenes/cache',    // caché de tamaños generados
    'default' => __DIR__ . '/img/sin-foto.png',  // si el archivo no existe
];
?>
<img src="/images/resize/galeria/400/250/muestra.png">   <!-- /{id}/{ancho}/{alto}/{archivo} -->
PHP) ?>
	<p>La imagen se ajusta y recorta al tamaño pedido conservando la proporción. Ancho y alto se limitan a 2048 px. El navegador la guarda y la revalida con <code>ETag</code> (respuesta 304 vacía si no cambió); con <code>'maxage' => 3600</code> en la configuración la reutiliza una hora sin preguntar.</p>

	<h3>PNG a AVIF o WebP según el navegador</h3>
	<?php $fmt = nfImageFormat(); ?>
	<p><img src="/images/pngto<?= $fmt ?>/galeria/240/150/muestra.<?= $fmt ?>" alt="<?= $fmt ?>" class="border bd-default"> Este navegador recibe <b><?= strtoupper($fmt) ?></b>.</p>
	<?= docCode(<<<'PHP'
<?php $fmt = nfImageFormat(); ?>   <!-- 'avif' o 'webp' -->
<img src="/images/pngto<?= $fmt ?>/galeria/240/150/muestra.<?= $fmt ?>">   <!-- busca muestra.png en src -->
PHP, 'html') ?>
	<p><code>/images/pngtoavif/</code> y <code>/images/pngtowebp/</code> convierten el mismo PNG. <code>nfImageFormat()</code> elige: <code>include.php</code> guarda en <code>$_SESSION['nf']['browser']['avif']</code> si el navegador envía <code>image/avif</code> en el encabezado <code>Accept</code> (Chrome, Edge y Firefox lo hacen al pedir la página; Safari solo al pedir imágenes, así que pasa a AVIF tras su primera imagen). Si GD no tiene soporte AVIF se usa WebP; pedir directamente un formato que GD no genera responde 501.</p>

	<h3>Páginas de un PDF</h3>
	<?= docCode(<<<'PHP'
$_SESSION['frompdf']['contrato'] = [
    'filename' => '/var/data/contratos/123.pdf',
    'directory' => '/var/data/contratos/123_paginas/',   // caché de imágenes
    // 'deletefile' => true,       // borrar cada imagen después de enviarla
    // 'deletedirectory' => true,  // borrar la carpeta si queda vacía
];
?>
<!-- Número de páginas -->
GET /images/frompdf/contrato/info.json          → {"numberOfPages": 3}
<!-- Página 2 a 600 px de ancho, en png, jpg, webp o avif -->
<img src="/images/frompdf/contrato/600/0/2.png">
<img src="/images/frompdf/contrato/600/0/2.avif">
PHP) ?>
	<p>Vea un ejemplo funcionando al subir un PDF en <a href="files.php">Archivos</a>.</p>

	<h3>Logo del sitio</h3>
	<p>El logo configurado en Admin → Sitio se guarda en <code>$config['logo']</code> (por omisión <code>img/nf/logo.png</code> del sitio; se cambia en
	<code>includes/config.php</code>) y se sirve en cualquier tamaño, p.ej. para el manifiesto de la aplicación.
	Si ese archivo no existe se usa el de nframework (<code>img/nlarednf.png</code>). En PHP la ruta del archivo vigente es <code>nfLogoPath()</code>.</p>
	<?= docCode(<<<'HTML'
<img src="/images/config/192/logo.png">        <!-- cuadrado 192x192 -->
<img src="/images/config/1200/628/logo.png">   <!-- ancho x alto -->
HTML, 'html') ?>

	<h3>Foto de perfil</h3>
	<p><img src="<?= htmlspecialchars($user->gravatar(64)) ?>" alt="foto" class="border bd-default"></p>
	<?= docCode(<<<'HTML'
<img src="<?= $user->gravatar() ?>">      <!-- /images/pngto{avif|webp}/users/32/32/{_id}.{avif|webp} -->
<img src="<?= $user->gravatar(120) ?>">   <!-- 120×120; gravatar(ancho, alto) -->
HTML, 'html') ?>
	<p>La carpeta <code>users</code> ya viene registrada: la foto es <code>{_id}.png</code> en <code>$config['users_pictures']</code> (por omisión <code>$DOCUMENT_ROOT/img/nf/users</code>); si el usuario no tiene foto se muestra una silueta genérica. Los tamaños generados se guardan en la caché local (<code>nfCacheDir('avatars')</code>) y se regeneran cuando cambia la foto. Un sitio puede usar otra carpeta registrando su propio <code>$_SESSION['imagesresize']['users']</code>.</p>
</div>
