<?php
require 'common.php';

// Mismo formato que guarda Admin → Menús (árbol de jsTree).
$nodos = [
	['text' => 'Inicio', 'data' => ['link' => '/', 'icon' => 'mif-home']],
	['text' => 'Documentación', 'children' => [
		['text' => 'Primeros pasos', 'data' => ['link' => '/docs/intro.php']],
		['text' => 'Rutas', 'data' => ['link' => '/docs/routes.php']],
	]],
	['text' => 'Solo administradores', 'data' => ['link' => '/admin/', 'onfunction' => '$user->in("admins")']],
];
$menuHtml = buildMetroMenu($nodos, 'h-menu');

$plantilla = 'Hoy es {{ date(d/m/Y) }} · {{ strtoupper(nframework) }}';
$header = nfPage('_header');
?>
<div class="container">
	<?= docHeader('Páginas y menús del CMS', 'Admin → Páginas y Admin → Menús guardan contenido en MongoDB (<code>pages</code> y <code>menus</code>). El router lo publica y cualquier página <code>.php</code> puede usarlo con <code>nfPage()</code>, <code>nfMetroMenu()</code> y <code>renderEmbeddedFunctions()</code>.') ?>

	<h3>Páginas: <code>nfPage($path)</code></h3>
	<p>Devuelve el documento de <code>pages</code> con ese <code>path</code> como arreglo (desde la caché local) o <code>null</code>. Campos que captura Admin → Páginas: <code>path</code>, <code>title</code>, <code>description</code>, <code>keywords</code>, <code>lang</code>, <code>background</code>, <code>robots</code> y <code>html</code>.</p>
	<ul>
		<li>Un <code>path</code> normal (<code>/nosotros</code>) es una ruta pública: el router la sirve con la plantilla Twig <code>page.html</code>, el menú <code>_nav</code> y los fragmentos <code>_header</code> y <code>_footer</code>.</li>
		<li>Un <code>path</code> que empieza con <code>_</code> es un <strong>fragmento</strong> y no tiene URL: <code>_header</code>, <code>_footer</code>, <code>_home</code> (portada si <code>homepagetype</code> es <code>page</code>), <code>_parallax</code> y <code>_404</code>.</li>
	</ul>
	<?= docCode(<<<'PHP'
// En cualquier página del sitio: reutilizar el pie de página editable desde el panel.
$footer = nfPage('_footer');
echo $footer['html'] ?? '';

// Una página propia con el contenido de Admin → Páginas
$page = nfPage('/aviso-de-privacidad');
if ($page === null) {
    http_response_code(404);
} else {
    $nframework->metas['title'] = $page['title'];
    echo $page['html'];
}
PHP) ?>
	<p class="text-muted">En este sitio <code>nfPage('_header')</code> <?= $header ? 'existe (' . strlen((string) ($header['html'] ?? '')) . ' caracteres de HTML).' : 'no existe todavía.' ?></p>

	<h3>Menús: <code>nfMetroMenu($nombre, $clase = 'h-menu')</code></h3>
	<p>Lee el menú <code>$nombre</code> de la colección <code>menus</code> (campo <code>code</code>, el JSON del árbol) y lo convierte en un menú de Metro UI con <code>buildMetroMenu()</code>. El router usa <code>nfMetroMenu('_nav')</code> en todas las páginas del CMS.</p>
	<div class="card p-4 mb-4"><?= $menuHtml ?></div>
	<?= docCode(<<<'PHP'
echo nfMetroMenu('_nav');                 // menú guardado en Admin → Menús
echo nfMetroMenu('lateral', 'v-menu');    // otro menú, vertical

// Sin base de datos: el mismo formato de nodos que guarda el panel.
echo buildMetroMenu([
    ['text' => 'Inicio', 'data' => ['link' => '/']],
    ['text' => 'Documentación', 'children' => [
        ['text' => 'Primeros pasos', 'data' => ['link' => '/docs/intro.php']],
    ]],
    ['text' => 'Solo administradores', 'data' => ['link' => '/admin/', 'onfunction' => '$user->in("admins")']],
], 'h-menu');
PHP) ?>
	<p>Cada nodo tiene <code>text</code> y en <code>data</code>: <code>link</code>, <code>icon</code> (clase de Metro Icons, p. ej. <code>mif-home</code>), <code>target</code> (<code>_blank</code> abre otra pestaña) y <code>onfunction</code>. Los menús que guarda el panel pasan por <code>nfNormalizeMenuNodes()</code>, que descarta todo lo demás.</p>
	<p>La <strong>condición</strong> (<code>onfunction</code>, «Condición» en el editor) es código PHP evaluado con <code>nfMenuCondition()</code>: el nodo se muestra solo si da verdadero. Puede ser una expresión (<code>$user->in('admins')</code>, <code>!$user->isLoggedIn()</code>) o código con <code>return</code>; <code>$user</code>, <code>$config</code>, <code>$m</code> y <code>$nframework</code> ya están disponibles. Un error en ese código oculta el nodo y queda en el log.</p>

	<h3>Funciones incrustadas: <code>renderEmbeddedFunctions($html)</code></h3>
	<p>Reemplaza cada <code>{{ funcion(arg1, arg2) }}</code> del HTML por lo que devuelve esa función de PHP. Los argumentos son texto (se les quitan las comillas). El router lo aplica al fragmento <code>_header</code>.</p>
	<table class="table compact">
		<tr><th>HTML</th><td><code><?= htmlspecialchars($plantilla) ?></code></td></tr>
		<tr><th>Resultado</th><td><?= htmlspecialchars(renderEmbeddedFunctions($plantilla)) ?></td></tr>
	</table>

	<div class="remark alert">
		<strong>Seguridad.</strong> <code>renderEmbeddedFunctions()</code> llama a <em>cualquier</em> función de PHP que exista (incluidas <code>system</code>, <code>file_get_contents</code> o <code>unlink</code>), y el <em>OnFunction</em> de los menús ejecuta PHP arbitrario. Quien pueda editar páginas o menús puede ejecutar código en el servidor: dé acceso a Admin → Páginas y Admin → Menús solo a personas de total confianza, y nunca pase a <code>renderEmbeddedFunctions()</code> HTML escrito por usuarios.
	</div>

	<?= docSource(__FILE__) ?>
</div>
