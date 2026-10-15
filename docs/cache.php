<?php
require 'common.php';

if ($nframework->isAjax()) {
	if (!csrfValidate()) {
		$result = ['error' => 'Formulario caducado, recargue la página.'];
	} elseif (($_POST['op'] ?? '') === 'olvidar') {
		nfCacheForget('docs:hora');
		$result = ['error' => false, 'js' => 'location.reload();'];
	}
	return;
}

// Ejemplo: el "cálculo" se ejecuta como mucho una vez cada 30 segundos.
$calculado = false;
$hora = nfCacheRemember('docs:hora', 30, function () use (&$calculado) {
	$calculado = true;
	return date('H:i:s');
});
$carpeta = nfCacheDir();
?>
<div class="container">
	<?= docHeader('Caché', 'Dos cachés distintas: la <strong>local del servidor</strong> (<code>nfCacheRemember()</code>), que evita repetir consultas costosas, y la <strong>HTTP</strong> (<code>serveFile()</code>, <code>serveContent()</code>, <code>testcache()</code>), que evita volver a enviar al navegador lo que ya tiene.') ?>

	<h3>Caché local: <code>nfCacheRemember($clave, $segundos, $funcion)</code></h3>
	<p>Devuelve el valor guardado bajo <code>$clave</code>; si no existe o caducó, ejecuta <code>$funcion</code>, guarda lo que devuelve y lo regresa.</p>
	<div class="card p-4 mb-4">
		<p class="mb-2">Valor en caché: <strong><?= htmlspecialchars($hora) ?></strong> — <?= $calculado ? '<span class="fg-orange">se calculó en esta petición</span>' : '<span class="fg-green">se leyó de la caché</span>' ?>. Recargue la página: durante 30 segundos verá la misma hora.</p>
		<?= secureform() ?>
			<button class="button small secureop" value="olvidar"><span class="mif-bin"></span> nfCacheForget('docs:hora')</button>
		</form>
	</div>
	<?= docCode(<<<'PHP'
$categorias = nfCacheRemember('categorias', 300, function () use ($m, $config) {
    // toArray(): un cursor de MongoDB no se puede guardar, sus documentos sí.
    return $m->{$config['sitedb']}->categorias->find([], ['sort' => ['nombre' => 1]])->toArray();
});

nfCacheForget('categorias');   // al modificarlas
nfCacheForget();               // todo el sitio

// Usar el mismo tiempo que el framework (cache_ttl):
$reglas = nfCacheRemember('mis-reglas', nfCacheTtl(), fn() => cargarReglas());
PHP) ?>
	<ul>
		<li>Se guarda en archivos con <code>serialize()</code>: el valor debe ser serializable (arreglos, escalares, documentos BSON; no cursores, conexiones ni closures).</li>
		<li>Use claves con prefijo (<code>'ventas:resumen:' . $empresaId</code>). Si el valor depende del usuario, inclúyalo en la clave.</li>
		<li>La carpeta es local a cada servidor: con varios servidores, un <code>nfCacheForget()</code> solo limpia el actual y los demás ven el cambio al caducar.</li>
		<li>Con <code>$segundos</code> en 0, o si la carpeta no se puede usar, siempre ejecuta la función.</li>
	</ul>

	<h4>Lo que el framework ya guarda</h4>
	<p>La configuración del sitio (<code>configs</code>), las reglas de seguridad, las rutas y fragmentos de páginas (<code>nfPage()</code>), los menús (<code>nfMetroMenu()</code>) y los campos que <a href="tablefia.php">TablefIA</a> lee de una muestra. Duran <code>cache_ttl</code> segundos y un POST de un administrador a <code>/admin/</code> vacía todo.</p>
	<table class="table striped compact">
		<thead><tr><th>config.php</th><th>Descripción</th><th>En este sitio</th></tr></thead>
		<tbody>
			<tr><td><code>cache_ttl</code></td><td>Segundos de la caché del framework (<code>nfCacheTtl()</code>); 0 la desactiva.</td><td><code><?= nfCacheTtl() ?></code></td></tr>
			<tr><td><code>cache_dir</code></td><td>Carpeta base. Por defecto <code>{tmp}/nframework_cache_{uid}</code>, con permisos 0700 y una subcarpeta por <code>sitedb</code>.</td><td><code><?= htmlspecialchars($carpeta ?? 'no disponible') ?></code></td></tr>
		</tbody>
	</table>

	<h3>Caché HTTP</h3>
	<p>Para archivos y contenido generado: envían <code>ETag</code> y <code>Last-Modified</code>, y responden <code>304 Not Modified</code> si el navegador ya tiene esa versión. La respuesta sale directa (sin plantilla ni buffer). Detalle de cabeceras en <a href="vars.php">Variables especiales</a>.</p>
	<?= docCode(<<<'PHP'
// Archivo: tercer parámetro = segundos que el navegador lo usa sin preguntar
// (0 revalida siempre, null sin caché); cuarto = public (proxies/CDN).
$nframework->serveFile('/var/data/catalogo.pdf', 'application/pdf', 3600);

// Contenido generado: el ETag es el hash del contenido.
$nframework->serveContent(json_encode($datos), 'application/json');

// Página normal: si no cambió desde la última visita, 304 y termina.
$nframework->lastmodified = $doc['modificado']->toDateTime()->getTimestamp();
$nframework->etag = md5((string) $doc['_id'] . $nframework->lastmodified);
$nframework->testcache();
PHP) ?>

	<?= docSource(__FILE__) ?>
</div>
