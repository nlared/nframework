<?php
if (empty($_GET['_id']) || !preg_match('/^[a-f\d]{24}$/i', $_GET['_id'])) {
	header('Location: ?_id=' . new MongoDB\BSON\ObjectId());
	exit();
}
$noobfuscate = true;
require 'common.php';

// Geocerca fija que hace de límite de la zona (en un sitio real vendría de una colección: municipios, clientes...).
$municipio = ['type' => 'Polygon', 'coordinates' => [[
	[-100.995, 25.405], [-100.925, 25.405], [-100.925, 25.460], [-100.950, 25.460],
	[-100.950, 25.435], [-100.970, 25.435], [-100.970, 25.460], [-100.995, 25.460], [-100.995, 25.405],
]]];

$dataset = new dataset([
	'collection' => $m->{$config['sitedb']}->demo_geocercas,
	'_id' => $_GET['_id'],
	'nameprefix' => 'data',
]);
$nombre = new inputText(['dataset' => &$dataset, 'field' => 'nombre', 'caption' => 'Nombre', 'required' => true]);
$zona = new mapshape([
	'dataset' => &$dataset,
	'field' => 'zona',
	'caption' => 'Zona de reparto (dentro del área punteada)',
	'required' => true,
	'limit' => $municipio,
	'height' => 380,
	'onchange' => 'mapshapes["data_ruta"].setLimit(geometry);',   // la zona es el límite de la ruta
]);
$ruta = new mapshape([
	'dataset' => &$dataset,
	'field' => 'ruta',
	'caption' => 'Ruta (dentro de la zona)',
	'shape' => mapshape::POLYLINE,
	'limit' => $dataset->zona,      // la zona guardada
	'color' => '#43a047',
	'height' => 380,
]);

if ($nframework->isAjax()) {
	if (!csrfValidate()) {
		$result = ['error' => 'Formulario caducado, recargue la página.'];
	} elseif (($_POST['op'] ?? '') === 'guardar') {
		// La ruta se valida contra la zona que llega en el mismo envío (si es válida), no contra la anterior.
		$zonaPost = $_POST['data']['zona'] ?? null;
		if ($zona->is_valid($zonaPost)) {
			$ruta->limit = $zona->__toMongo($zonaPost);
		}
		$errores = $dataset->save();
		if ($errores) {
			$errores .= implode('<br>', array_filter([$zona->error, $ruta->error]));
		}
		$dataset->refresh();
		$result = ['error' => $errores, 'ids' => ['documento' => htmlspecialchars(json_encode(mongoToArray($dataset->info), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))]];
	}
	return;
}

$guardado = mongoToArray($dataset->info);
?>
<div class="container">
	<?= docHeader('Geocercas y rutas', '<code>mapshape</code> dibuja un polígono (geocerca) o una polilínea sobre un mapa Leaflet y lo guarda como GeoJSON. Con <code>limit</code> la figura debe quedar dentro de otra geocerca: el navegador no deja salir del límite y <code>is_valid()</code> lo vuelve a comprobar en el servidor.') ?>

	<p>Clic en el mapa agrega un vértice; arrastre un vértice para moverlo; clic derecho o doble clic sobre él lo borra; el punto tenue a la mitad de un lado inserta un vértice nuevo.</p>

	<div class="grid">
		<div class="row">
			<div class="cell-md-8">
				<div class="card p-4">
					<?= secureform() ?>
						<?= $nombre ?>
						<div class="mt-4"><?= $zona ?></div>
						<div class="mt-4"><?= $ruta ?></div>
						<button class="button primary secureop mt-4" value="guardar">Guardar</button>
					</form>
				</div>
			</div>
			<div class="cell-md-4">
				<h5>Documento en <code>demo_geocercas</code></h5>
				<pre id="documento" class="border bd-default p-2" style="font-size:12px; white-space:pre-wrap; max-height:820px; overflow:auto"><?= htmlspecialchars(json_encode($guardado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
			</div>
		</div>
	</div>

	<?= docCode(<<<'PHP'
$zona = new mapshape([
    'dataset' => &$dataset,
    'field' => 'zona',                 // {type: 'Polygon', coordinates: [[[lng, lat], ...]]}
    'required' => true,
    'limit' => $municipio,             // Polygon, MultiPolygon, Feature o FeatureCollection
    'onchange' => 'mapshapes["data_ruta"].setLimit(geometry);',
]);
$ruta = new mapshape([
    'dataset' => &$dataset,
    'field' => 'ruta',                 // {type: 'LineString', coordinates: [[lng, lat], ...]}
    'shape' => mapshape::POLYLINE,
    'limit' => $dataset->zona,         // otra geocerca guardada sirve de límite
]);

// Al guardar: si la zona cambió en el mismo envío, la ruta se valida contra la nueva.
if ($zona->is_valid($_POST['data']['zona'] ?? null)) {
    $ruta->limit = $zona->__toMongo($_POST['data']['zona']);
}
$errores = $dataset->save();           // 'Error en:ruta' si sale del límite; el motivo en $ruta->error
PHP) ?>

	<h3>Consultas con la geocerca</h3>
	<p>Lo guardado es GeoJSON válido para un índice <code>2dsphere</code>. Para saber en PHP si un punto cae dentro de una geocerca sin consultar MongoDB está <code>mapshape::contains()</code>.</p>
	<table class="table compact">
		<thead><tr><th>Punto (lat, lng)</th><th><code>mapshape::contains($municipio, $lat, $lng)</code></th></tr></thead>
		<tbody>
			<?php foreach ([[25.42, -100.98, ''], [25.45, -100.96, ' (en la muesca)'], [25.50, -100.96, ' (al norte)']] as [$lat, $lng, $nota]) { ?>
				<tr><td><?= $lat ?>, <?= $lng ?><?= $nota ?></td><td><code><?= var_export(mapshape::contains($municipio, $lat, $lng), true) ?></code></td></tr>
			<?php } ?>
		</tbody>
	</table>
	<?= docCode(<<<'PHP'
$geo->createIndex(['zona' => '2dsphere']);
// Zonas que contienen un punto (p. ej. la ubicación de un mapmarker GeoJSON):
$geo->find(['zona' => ['$geoIntersects' => ['$geometry' => ['type' => 'Point', 'coordinates' => [$lng, $lat]]]]]);
// Clientes dentro de una zona:
$clientes->find(['ubicacion' => ['$geoWithin' => ['$geometry' => $dataset->zona]]]);
PHP) ?>

	<h3>En el navegador</h3>
	<?= docCode(<<<'JS'
mapshapes['data_zona'].getGeoJSON();      // GeoJSON o null si faltan puntos
mapshapes['data_zona'].getPoints();       // [[lat, lng], ...]
mapshapes['data_zona'].setGeoJSON(g);     // false si no cumple el límite
mapshapes['data_zona'].setLimit(g);       // cambia el límite (null lo quita); el servidor usa su propio 'limit'
mapshapes['data_zona'].undo(); mapshapes['data_zona'].clear(); mapshapes['data_zona'].fit();
mapshapes['data_zona'].map;               // L.Map

document.addEventListener('mapshape:change', function (e) {
    console.log(e.target.id, e.detail.geometry);
});
JS, 'javascript') ?>

	<h3>Opciones</h3>
	<table class="table striped compact">
		<thead><tr><th>Opción</th><th>Descripción</th></tr></thead>
		<tbody>
			<tr><td><code>shape</code></td><td><code>mapshape::POLYGON</code> (por defecto, geocerca) o <code>mapshape::POLYLINE</code>.</td></tr>
			<tr><td><code>limit</code></td><td>Geocerca dentro de la que debe quedar la figura: <code>Polygon</code> (con huecos), <code>MultiPolygon</code>, <code>Feature</code> o <code>FeatureCollection</code>, como arreglo, BSON o JSON. Se exige que todos los vértices estén dentro, que ningún lado cruce el borde y que un polígono no encierre un hueco del límite.</td></tr>
			<tr><td><code>dataset</code>, <code>field</code></td><td>Se guarda GeoJSON (<code>Polygon</code> cerrado o <code>LineString</code>) con <code>[lng, lat]</code> en double. Un polígono cuyos lados se cruzan se rechaza.</td></tr>
			<tr><td><code>required</code>, <code>maxpoints</code></td><td>Exigir figura (3 puntos un polígono, 2 una línea) y máximo de vértices (500).</td></tr>
			<tr><td><code>onchange</code></td><td>JavaScript en cada cambio, con la variable <code>geometry</code>. También se lanza <code>mapshape:change</code> sobre <code>#&lt;id&gt;_wrap</code>.</td></tr>
			<tr><td><code>startpoint</code>, <code>zoom</code></td><td>Vista inicial cuando no hay figura ni límite (si los hay, el mapa se ajusta a ellos).</td></tr>
			<tr><td><code>color</code>, <code>limitcolor</code>, <code>height</code></td><td>Colores de la figura y del límite; alto del mapa en píxeles.</td></tr>
			<tr><td><code>toolbar</code></td><td><code>false</code> oculta los botones Deshacer/Limpiar/Ajustar y la línea con puntos, longitud y área.</td></tr>
			<tr><td><code>readonly</code>, <code>disabled</code></td><td>Solo muestra la figura.</td></tr>
		</tbody>
	</table>
	<p>Los cálculos de límite son planos sobre longitud/latitud: correctos para geocercas de ciudad o región, no para figuras que crucen el antimeridiano o los polos.</p>
	<?= docSource(__FILE__) ?>
</div>
