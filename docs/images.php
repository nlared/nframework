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

// Registrar la carpeta: habilita /images/resize/galeria/... y /images/pngtowebp/galeria/...
$_SESSION['imagesresize']['galeria'] = [
	'src' => $carpeta,                      // originales
	'dst' => $carpeta . '/cache',           // tamaños generados (se reutilizan mientras el original no cambie)
	'default' => $muestra,                  // si el archivo pedido no existe
];
?>
<div class="container">
	<?= docHeader('Imágenes', 'Rutas que generan imágenes al vuelo y las guardan en caché con <code>ETag</code>: miniaturas, conversión a WebP, páginas de PDF y el logo del sitio. Las carpetas se registran en la sesión, así que el navegador solo conoce un identificador.') ?>

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

	<h3>PNG a WebP</h3>
	<p><img src="/images/pngtowebp/galeria/240/150/muestra.webp" alt="webp" class="border bd-default"></p>
	<?= docCode('<img src="/images/pngtowebp/galeria/240/150/muestra.webp">   <!-- busca muestra.png en src -->', 'html') ?>

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
<!-- Página 2 a 600 px de ancho, en png, jpg o webp -->
<img src="/images/frompdf/contrato/600/0/2.png">
PHP) ?>
	<p>Vea un ejemplo funcionando al subir un PDF en <a href="files.php">Archivos</a>.</p>

	<h3>Logo del sitio</h3>
	<p>El logo configurado en Admin → Sitio se sirve en cualquier tamaño, p.ej. para el manifiesto de la aplicación:</p>
	<?= docCode(<<<'HTML'
<img src="/images/config/192/logo.png">        <!-- cuadrado 192x192 -->
<img src="/images/config/1200/628/logo.png">   <!-- ancho x alto -->
HTML, 'html') ?>

	<h3>Foto de perfil</h3>
	<?= docCode('<img src="<?= $user->gravatar() ?>">   <!-- /images/pngtowebp/users/32/32/{_id}.webp -->', 'html') ?>
</div>
