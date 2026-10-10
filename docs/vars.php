<?php
require 'common.php';
?>
<div class="container">
	<?= docHeader('Variables especiales', 'Variables globales y propiedades de <code>$nframework</code> que cambian cómo se procesa y entrega la página.') ?>

	<h3>Antes de <code>require 'include.php'</code></h3>
	<p>Estas se leen mientras el framework arranca; definirlas después no tiene efecto.</p>
	<table class="table striped compact">
		<thead><tr><th>Variable</th><th>Efecto</th></tr></thead>
		<tbody>
			<tr><td><code>$requiresession = true</code></td><td>Si no hay usuario con sesión, redirige a <code>/</code> y termina.</td></tr>
			<tr><td><code>$nfshutdowndisable = true</code></td><td>No registra el cierre del framework: no hay plantilla, ni JSON automático de <code>$result</code>, ni estadísticas. Para descargas o endpoints que escriben su propia salida.</td></tr>
			<tr><td><code>$nfjavaobfuscatedisable = true</code></td><td>No captura la salida: los <code>&lt;script&gt;</code> se quedan donde se imprimen. Normalmente va junto con la anterior.</td></tr>
		</tbody>
	</table>
	<?= docCode(<<<'PHP'
<?php
// Endpoint que transmite un archivo grande sin pasar por el buffer del framework.
$nfshutdowndisable = true;
$nfjavaobfuscatedisable = true;
require 'include.php';
$user->requireAuth();
$nframework->downloadfrom('/var/data/reportes/' . basename($_GET['f'] ?? ''));
PHP) ?>

	<h3>En cualquier punto de la página</h3>
	<table class="table striped compact">
		<thead><tr><th>Variable</th><th>Efecto</th></tr></thead>
		<tbody>
			<tr><td><code>$developermode = true</code></td><td>Los errores se muestran en una tabla al final de la página en lugar de solo registrarse en la colección <code>errorlog</code>. Se activa solo para el grupo <code>developers</code>; no lo fuerce en producción.</td></tr>
			<tr><td><code>$noobfuscate = true</code></td><td>El JavaScript de la página se entrega tal cual, sin comprimir/ofuscar. Útil para depurar.</td></tr>
			<tr><td><code>$result</code></td><td>En peticiones AJAX es la respuesta: el framework envía <code>json_encode($result)</code>. Ver <a href="intro.php">Primeros pasos</a>.</td></tr>
		</tbody>
	</table>

	<h3>Propiedades de <code>$nframework</code></h3>
	<table class="table striped compact">
		<thead><tr><th>Propiedad</th><th>Efecto</th></tr></thead>
		<tbody>
			<tr><td><code>usecommon</code></td><td><code>true</code> envuelve la salida en la plantilla HTML completa.</td></tr>
			<tr><td><code>metas['title' | 'description' | 'url']</code></td><td>Título y metadatos (también Open Graph y Twitter).</td></tr>
			<tr><td><code>csss['clave']</code>, <code>jss['clave']</code></td><td>Hojas de estilo y scripts. Se ordenan por clave: <code>'001'</code> carga antes que <code>'100'</code>. Ver <a href="ui.php">Interfaz</a>.</td></tr>
			<tr><td><code>html_addtag</code>, <code>body_addtag</code></td><td>Atributos extra para <code>&lt;html&gt;</code> y <code>&lt;body&gt;</code>, p.ej. <code>' class="dark"'</code>.</td></tr>
			<tr><td><code>docend[]</code></td><td>HTML que se agrega al final del <code>&lt;body&gt;</code>.</td></tr>
			<tr><td><code>etag</code>, <code>lastmodified</code> + <code>testcache()</code></td><td>Responde <code>304 Not Modified</code> si el navegador ya tiene esa versión. Con solo estos dos, la respuesta se envía con <code>Cache-Control: private, no-cache</code>: el navegador la guarda y la revalida cada vez.</td></tr>
			<tr><td><code>expiretime</code></td><td>Timestamp hasta el que el navegador usa la respuesta sin preguntar (<code>max-age</code>).</td></tr>
			<tr><td><code>cachepublic</code></td><td><code>true</code> envía <code>public</code> en lugar de <code>private</code> (proxies y CDN pueden guardarla). Solo para respuestas iguales para todos los usuarios.</td></tr>
			<tr><td><code>lang</code>, <code>langshort</code>, <code>language</code></td><td>Idioma detectado (<code>es-MX</code>, <code>es</code>) y sus textos de <code>includes/i18n</code>.</td></tr>
		</tbody>
	</table>

	<h4>Caché HTTP: <code>serveFile()</code> y <code>serveContent()</code></h4>
	<p>Envían la respuesta directamente (sin pasar por la plantilla ni cargar el archivo en memoria) con <code>ETag</code>, <code>Last-Modified</code> y 304 automático.</p>
	<?= docCode(<<<'PHP'
<?php
require 'include.php';

// Archivo: ETag a partir de fecha y tamaño. Tercer parámetro = segundos sin revalidar:
//   0 (por defecto) revalida siempre, 3600 una hora, null sin caché. Cuarto: public.
$nframework->serveFile(__DIR__ . '/catalogo.json', 'application/json', 3600);

// Contenido generado: el ETag es el hash del contenido.
$xml = generarFeed();
$nframework->serveContent($xml, 'application/rss+xml; charset=utf-8');

// A mano, para una página normal:
$nframework->lastmodified = $ultimaModificacion;
$nframework->etag = md5($id . $ultimaModificacion);
$nframework->testcache();      // termina con 304 si el navegador ya la tiene
PHP) ?>

	<h4>Caché local del servidor</h4>
	<p>La configuración del sitio, las reglas de seguridad, las rutas y fragmentos de páginas (<code>nfPage()</code>) y los menús se guardan <code>cache_ttl</code> segundos (60 por defecto) en una carpeta local, en lugar de consultarse a MongoDB en cada petición. Cualquier POST de un administrador a <code>/admin/</code> la vacía. Use la misma caché para sus propios datos:</p>
	<?= docCode(<<<'PHP'
$categorias = nfCacheRemember('categorias', 300, function () use ($m, $config) {
    return $m->{$config['sitedb']}->categorias->find([], ['sort' => ['nombre' => 1]])->toArray();
});
nfCacheForget('categorias');   // después de modificarlas
nfCacheForget();               // todo el sitio
PHP) ?>

	<h4>Valores de esta petición</h4>
	<?= docCode(var_export([
		'lang' => $nframework->lang,
		'langshort' => $nframework->langshort,
		'isAjax' => $nframework->isAjax(),
		'https' => $nframework->https,
		'ip' => $ip,
	], true)) ?>
</div>
