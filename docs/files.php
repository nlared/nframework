<?php
require 'common.php';

$directory = __DIR__ . '/tmp/';
$limit = strtotime('+30 minutes');

// 1. Un archivo con ruta fija: al subir, reemplaza al anterior.
$imagen = new inputFile([
	'name' => 'imagen',
	'caption' => 'Imagen de producto',
	'dir' => $directory,
	'path' => $directory . 'producto.png',
	'accept' => 'image/*',
	'delete' => true,
	'preview' => true,
	'download' => true,
	'limit_time_end' => $limit,
	// JavaScript que se ejecuta al terminar la carga (recibe `data`).
	'onDone' => <<<'JS'
		$("#vistaImagen").html('<img class="mt-2" height="160" src="/docs/tmp/producto.png?t=' + Date.now() + '">');
	JS,
]);

// 2. Varios archivos con límite de cantidad y tamaño, y funciones del servidor al subir/borrar.
$fotos = new inputFiles([
	'name' => 'fotos',
	'caption' => 'Galería (máximo 5, 2 MB c/u)',
	'dir' => $directory . 'galeria/',
	'create_dir' => true,
	'accept' => 'image/*',
	'countlimit' => 5,
	'sizelimit' => 2 * 1024 * 1024,
	'extension' => __DIR__ . '/upload_extension.php',
	'onupload' => 'subir',
	'ondelete' => 'borrar',
	'limit_time_end' => $limit,
]);

// 3. PDF: al subirlo se muestran sus páginas como imágenes (ver images.php).
$_SESSION['frompdf']['docspdf'] = [
	'filename' => $directory . 'documento.pdf',
	'directory' => $directory . 'documento_paginas/',
];
$pdf = new inputFile([
	'name' => 'pdf',
	'caption' => 'Documento PDF',
	'dir' => $directory,
	'path' => $directory . 'documento.pdf',
	'accept' => 'application/pdf',
	'limit_time_end' => $limit,
	'onDone' => <<<'JS'
		$.getJSON({url: "/images/frompdf/docspdf/info.json", cache: false}).done(function (info) {
			var html = '';
			for (var i = 1; i <= info.numberOfPages; i++) {
				html += '<img class="m-1 border bd-default" height="200" src="/images/frompdf/docspdf/200/282/' + i + '.png?t=' + Date.now() + '">';
			}
			$("#vistaPdf").html(html);
		});
	JS,
]);
?>
<div class="container">
	<?= docHeader('Archivos', '<code>inputFile</code> maneja un archivo con ruta fija; <code>inputFiles</code> una carpeta con varios archivos. La subida va a <code>/nframework/uploadfile.php</code>, que solo acepta los controles registrados en la sesión y rechaza extensiones ejecutables (<code>.php</code>, <code>.phtml</code>, <code>.htaccess</code>...) salvo las que el control permita con <a href="#extensiones"><code>allowext</code></a>.') ?>

	<h3>Un archivo (<code>inputFile</code>)</h3>
	<?= $imagen ?>
	<div id="vistaImagen"></div>
	<?= docCode(<<<'PHP'
$imagen = new inputFile([
    'name' => 'imagen',
    'caption' => 'Imagen de producto',
    'dir' => __DIR__ . '/tmp/',
    'path' => __DIR__ . '/tmp/producto.png',   // nombre final, sin importar el que traiga el usuario
    'accept' => 'image/*',
    'delete' => true, 'preview' => true, 'download' => true,   // botones disponibles
    'limit_time_end' => strtotime('+30 minutes'),  // después de esto ya no se acepta la carga
    'onDone' => '$("#vistaImagen").html(\'<img src="/docs/tmp/producto.png?t=\' + Date.now() + \'">\');',
]);
echo $imagen;
PHP) ?>

	<h3>Varios archivos (<code>inputFiles</code>)</h3>
	<?= $fotos ?>
	<?= docCode(<<<'PHP'
$fotos = new inputFiles([
    'name' => 'fotos',
    'dir' => __DIR__ . '/tmp/galeria/',
    'create_dir' => true,
    'accept' => 'image/*',
    'countlimit' => 5,                  // máximo de archivos en la carpeta
    'sizelimit' => 2 * 1024 * 1024,     // bytes por archivo
    'extension' => __DIR__ . '/upload_extension.php',  // archivo con las funciones de abajo
    'onupload' => 'subir',              // subir($ruta, $opciones) después de guardar
    'ondelete' => 'borrar',             // borrar($ruta, $opciones) REEMPLAZA el borrado: debe hacer unlink()
]);
PHP) ?>
	<h5>upload_extension.php</h5>
	<?= docCode(file_get_contents(__DIR__ . '/upload_extension.php')) ?>

	<h3>PDF con vista de páginas</h3>
	<?= $pdf ?>
	<div id="vistaPdf" class="mt-2"></div>
	<p>Registrar el PDF en <code>$_SESSION['frompdf']</code> habilita las rutas <code>/images/frompdf/{id}/info.json</code> (número de páginas) y <code>/images/frompdf/{id}/{ancho}/{alto}/{página}.png</code>. Ver <a href="images.php">Imágenes</a>.</p>
	<?= docCode(<<<'PHP'
$_SESSION['frompdf']['docspdf'] = [
    'filename' => __DIR__ . '/tmp/documento.pdf',
    'directory' => __DIR__ . '/tmp/documento_paginas/',   // caché de las imágenes generadas
];
PHP) ?>

	<h3 id="extensiones">Extensiones protegidas</h3>
	<p>Por seguridad, <code>/nframework/uploadfile.php</code> rechaza los archivos con extensiones ejecutables o de configuración en <strong>cualquier</strong> parte del nombre (<code>x.php.jpg</code>), sin distinguir mayúsculas:
		<code>php</code> (y <code>php5</code>, <code>php7</code>…), <code>phtml</code>, <code>pht</code>, <code>phar</code>, <code>phps</code>, <code>pgif</code>, <code>inc</code>, <code>shtml</code>, <code>htaccess</code>, <code>htpasswd</code>, <code>ini</code>, <code>asp</code>, <code>aspx</code>, <code>ascx</code>, <code>jsp</code>, <code>jspx</code>, <code>cgi</code>, <code>pl</code>, <code>py</code>, <code>rb</code>, <code>exe</code>, <code>bat</code>, <code>cmd</code>, <code>com</code>, <code>sh</code>, <code>bash</code>, <code>ps1</code>, <code>vbs</code>, <code>scr</code>, <code>msi</code>, <code>dll</code>, <code>so</code>.</p>
	<p>Si un control realmente necesita recibir alguno de esos archivos (scripts, instaladores), indíquelo con <code>allowext</code>. La lista se guarda en la sesión del servidor; el navegador no la puede modificar.</p>
	<?= docCode(<<<'PHP'
$scripts = new inputFiles([
    'name' => 'scripts',
    'caption' => 'Scripts de migración',
    'dir' => '/var/data/migraciones/',     // fuera del document root
    'accept' => '.py,.sh',                 // solo filtra el selector del navegador
    'allowext' => ['py', 'sh'],            // el servidor acepta estas extensiones protegidas
]);
PHP) ?>
	<ul>
		<li>Cada extensión protegida del nombre debe estar en la lista: con <code>['py']</code>, <code>x.py.exe</code> se sigue rechazando.</li>
		<li>Las que ejecuta PHP (<code>php</code>, <code>phtml</code>, <code>pht</code>, <code>phar</code>, <code>phps</code>, <code>pgif</code>, <code>inc</code>, <code>shtml</code>) solo se aceptan si la carpeta destino está <strong>fuera del document root</strong>; dentro, cualquiera podría ejecutarlas abriendo su URL.</li>
		<li>Los archivos ocultos (<code>.htaccess</code>, <code>.user.ini</code>), las rutas (<code>../</code>) y los caracteres especiales se rechazan siempre.</li>
		<li>Guarde estos archivos en una carpeta sin acceso web y entréguelos con <code>download</code> (que los sirve como descarga), nunca enlazándolos directamente.</li>
	</ul>

	<h3>Opciones</h3>
	<table class="table striped compact">
		<thead><tr><th>Opción</th><th>Descripción</th></tr></thead>
		<tbody>
			<tr><td><code>dir</code></td><td>Carpeta destino. <code>create_dir</code> la crea si no existe.</td></tr>
			<tr><td><code>path</code></td><td>Solo <code>inputFile</code>: ruta completa del archivo final.</td></tr>
			<tr><td><code>allowext</code></td><td>Extensiones protegidas que este control sí acepta, p. ej. <code>['py', 'sh']</code>. Ver <a href="#extensiones">Extensiones protegidas</a>.</td></tr>
			<tr><td><code>accept</code></td><td>Tipos permitidos en el selector (<code>image/*</code>, <code>application/pdf</code>, <code>.xlsx</code>).</td></tr>
			<tr><td><code>delete</code>, <code>preview</code>, <code>download</code></td><td>Botones de borrar, ver y descargar (activos por defecto).</td></tr>
			<tr><td><code>mode</code></td><td><code>input</code> (por defecto), <code>drop</code> o <code>button</code>.</td></tr>
			<tr><td><code>countlimit</code>, <code>sizelimit</code></td><td>Solo <code>inputFiles</code>: máximo de archivos y bytes por archivo.</td></tr>
			<tr><td><code>limit_time_start</code>, <code>limit_time_end</code></td><td>Ventana (timestamps) en la que se aceptan cargas. Por defecto 30 minutos.</td></tr>
			<tr><td><code>extension</code> + <code>onupload</code>, <code>ondelete</code></td><td>Archivo PHP y funciones del servidor. <code>onupload</code> se llama después de guardar; <code>ondelete</code> sustituye al borrado estándar, así que debe eliminar el archivo. Lo que devuelven llega al navegador en <code>onresult</code>.</td></tr>
			<tr><td><code>onDone</code></td><td>JavaScript que se ejecuta en el navegador al terminar la carga.</td></tr>
		</tbody>
	</table>
</div>
