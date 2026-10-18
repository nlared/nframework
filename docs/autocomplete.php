<?php
require 'common.php';
docDemoVentas();

// Endpoint de sugerencias en la misma página (ejemplo "Desde la misma página"): el navegador pide
// ?op=lenguajes&q=texto con X-Requested-With, así que $nframework->isAjax() es true y se responde $result.
if ($nframework->isAjax() && ($_GET['op'] ?? '') === 'lenguajes') {
	$lenguajes = ['PHP' => 1995, 'Python' => 1991, 'JavaScript' => 1995, 'TypeScript' => 2012, 'Java' => 1995, 'C' => 1972,
		'C++' => 1985, 'C#' => 2000, 'Go' => 2009, 'Rust' => 2015, 'Kotlin' => 2011, 'Swift' => 2014, 'Ruby' => 1995, 'Perl' => 1987];
	$q = mb_strtolower(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
	$items = [];
	foreach ($lenguajes as $nombre => $anio) {
		if (str_contains(mb_strtolower($nombre), $q)) {
			$items[] = ['label' => $nombre . ' (' . $anio . ')', 'value' => $nombre];
		}
	}
	$result = ['items' => $items];
	return;
}

$examples = [
	['code' => <<<'PHP'
new inputText([
	'name' => 'fruta',
	'caption' => 'Fruta',
	'suggest' => ['Manzana', 'Mango', 'Mandarina', 'Melón', 'Pera', 'Piña', 'Plátano', 'Uva'],
]);
PHP,
		'title' => 'Lista fija',
		'desc' => 'Un arreglo de textos se filtra en el navegador, sin peticiones. Se puede escribir un valor que no está en la lista.'],

	['code' => <<<'PHP'
new inputText([
	'name' => 'pais',
	'caption' => 'País',
	'suggest' => [
		'collection' => 'countrys',
		'columns' => ['name', 'iso2'],          // dónde se busca lo escrito
		'value' => '$name',                     // lo que se escribe en el campo
		'label' => ['$concat' => ['$name', ' (', '$iso2', ')']],   // lo que se ve en la lista
	],
]);
PHP,
		'title' => 'Desde una colección de MongoDB',
		'desc' => 'Con <code>collection</code> la búsqueda se hace en el servidor (<code>/nframework/select_ajax.php</code>) mientras se escribe. La consulta vive en la sesión: el navegador solo envía el texto. <code>db</code> es opcional (por omisión la del sitio).'],

	['code' => <<<'PHP'
new inputText([
	'name' => 'ciudad',
	'caption' => 'Ciudad',
	'suggest' => [
		'collection' => 'demo_clientes',
		'value' => '$ciudad',
		'distinct' => true,      // un resultado por valor, ordenado
		'minlength' => 0,        // con ↓ muestra la lista sin escribir
	],
]);
PHP,
		'title' => 'Valores ya capturados',
		'desc' => 'Con <code>distinct</code> se sugiere lo que ya existe en la colección (ciudades, colonias, puestos...) para evitar variantes como "Torreon" y "Torreón". Sin <code>columns</code> se busca en el campo de <code>value</code>.'],

	['code' => <<<'PHP'
new inputText([
	'name' => 'cliente',
	'caption' => 'Cliente de esa ciudad',
	'suggest' => [
		'collection' => 'demo_clientes',
		'value' => '$nombre',
		'depends' => ['ciudad' => '#ciudad'],   // campo de la colección => selector del input
	],
]);
PHP,
		'title' => 'Dependiente de otro campo',
		'desc' => '<code>depends</code> envía el valor de otro control y en MongoDB se usa como filtro exacto (si está vacío no filtra). Al cambiar la ciudad se limpia el cliente; use <code>\'clearondepends\' => false</code> para conservarlo.'],

	['code' => <<<'PHP'
new inputText([
	'name' => 'pais_nombre',
	'caption' => 'País',
	'suggest' => [
		'collection' => 'countrys',
		'value' => '$name',
		'fields' => ['iso2' => '$iso2', 'iso3' => '$iso3'],   // datos extra en cada resultado
		'onselect' => "document.getElementById('pais_iso').value = item.iso2 + ' / ' + item.iso3;",
	],
]) . new inputText(['name' => 'pais_iso', 'caption' => 'ISO', 'readonly' => true]);
PHP,
		'title' => 'Llenar otros campos al elegir',
		'desc' => '<code>fields</code> agrega datos a cada sugerencia y <code>onselect</code> es el cuerpo de una función que recibe <code>item</code> e <code>input</code>. Escribir a mano no la dispara.'],

	['code' => <<<'PHP'
new inputText([
	'name' => 'lenguaje',
	'caption' => 'Lenguaje',
	'suggest' => ['url' => '?op=lenguajes'],
]);
PHP,
		'title' => 'Desde la misma página (AJAX)',
		'desc' => 'Con <code>url</code> el navegador hace <code>GET url&amp;q=texto</code> con <code>X-Requested-With</code>: la página responde en su bloque <code>isAjax()</code> con <code>$result</code> (código abajo). Acepta <code>["texto", ...]</code>, <code>[{label, value}, ...]</code> o <code>{items: [...]}</code>; con <code>key</code> se lee otra propiedad.'],
];

// Lista cargada una vez desde una API externa (catálogo de estados de INEGI) y entregada con setItems().
$estado = new inputText(['name' => 'estado_inegi', 'caption' => 'Estado (INEGI)', 'suggest' => ['items' => []]]);
$javas->addjs(<<<'JS'
fetch('https://gaia.inegi.org.mx/wscatgeo/mgee/')
	.then(r => r.json())
	.then(data => document.getElementById('estado_inegi').nfSuggest.setItems(data.datos.map(e => e.nom_agee)));
JS, 'ready');
?>
<div class="container">
	<?= docHeader('Autocompletar', 'La opción <code>suggest</code> de <code>inputText</code> muestra sugerencias mientras se escribe, desde una lista fija, una colección de MongoDB o un endpoint propio. El campo sigue siendo texto libre: para obligar a elegir un valor de un catálogo use un <a href="/docs/inputsajax.php">Select con AJAX</a>.') ?>

	<h3>¿Cuál usar?</h3>
	<table class="table striped compact">
		<thead><tr><th>Necesito</th><th>Use</th></tr></thead>
		<tbody>
			<tr><td>Ayudar a escribir, aceptando cualquier texto</td><td><code>inputText</code> + <code>suggest</code> (esta página)</td></tr>
			<tr><td>Elegir un registro de un catálogo grande (guardar su id/clave)</td><td><code>Select</code> + <code>ajax</code> (Tom Select)</td></tr>
			<tr><td>Elegir de pocas opciones fijas</td><td><code>Select</code> con <code>options</code></td></tr>
			<tr><td>Varios valores como etiquetas</td><td><code>Select</code> con <code>multiple</code></td></tr>
		</tbody>
	</table>

	<?= docExamples($examples) ?>

	<div class="grid">
		<div class="row border-bottom bd-default pb-4 mb-4">
			<div class="cell-12"><h5>Lista cargada desde JavaScript</h5></div>
			<div class="cell-12"><p class="text-muted">Para una API externa que devuelve todo el catálogo de una vez: <code>'suggest' => ['items' => []]</code> y después <code>input.nfSuggest.setItems(lista)</code>. Sustituye al truco de <code>data-autocomplete</code> + <code>input.autocomplete = ...</code> de Metro.</p></div>
			<div class="cell-md-5"><?= $estado ?></div>
			<div class="cell-md-7"><?= docCode(<<<'PHP'
$estado = new inputText(['name' => 'estado_inegi', 'caption' => 'Estado (INEGI)', 'suggest' => ['items' => []]]);
$javas->addjs(<<<'JS'
fetch('https://gaia.inegi.org.mx/wscatgeo/mgee/')
	.then(r => r.json())
	.then(data => document.getElementById('estado_inegi').nfSuggest.setItems(data.datos.map(e => e.nom_agee)));
JS, 'ready');
PHP) ?></div>
		</div>
	</div>

	<h3>Endpoint de la misma página</h3>
	<?= docCode(<<<'PHP'
require 'include.php';

if ($nframework->isAjax() && ($_GET['op'] ?? '') === 'lenguajes') {
	$q = mb_strtolower(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');   // forzar string
	$items = [];
	foreach ($lenguajes as $nombre => $anio) {
		if (str_contains(mb_strtolower($nombre), $q)) {
			$items[] = ['label' => "$nombre ($anio)", 'value' => $nombre];
		}
	}
	$result = ['items' => $items];
	return;   // el framework envía json_encode($result)
}

echo new inputText(['name' => 'lenguaje', 'suggest' => ['url' => '?op=lenguajes']]);
PHP) ?>

	<h3>Con databinding</h3>
	<?= docCode(<<<'PHP'
// Igual que cualquier inputText: se guarda el texto escrito o elegido.
$ciudad = new inputText(['dataset' => &$dataset, 'field' => 'domicilio.ciudad', 'caption' => 'Ciudad', 'suggest' => [
	'collection' => 'clientes', 'value' => '$domicilio.ciudad', 'distinct' => true,
	'pipeline' => [['$match' => ['owner' => $user->_id]]],   // solo lo que capturó este usuario
]]);
PHP) ?>

	<h3>Opciones de <code>suggest</code></h3>
	<table class="table striped compact">
		<thead><tr><th>Clave</th><th>Descripción</th></tr></thead>
		<tbody>
			<tr><td><code>items</code></td><td>Lista fija (textos o <code>{label, value}</code>). Un arreglo simple en <code>suggest</code> equivale a <code>items</code>.</td></tr>
			<tr><td><code>url</code>, <code>param</code>, <code>key</code></td><td>Endpoint propio. Se envía <code>param</code> (por omisión <code>q</code>); <code>key</code> indica la propiedad de la respuesta con la lista (por omisión <code>items</code> si no es arreglo).</td></tr>
			<tr><td><code>collection</code>, <code>db</code></td><td>Búsqueda en MongoDB. <code>db</code> por omisión es <code>$config['sitedb']</code>.</td></tr>
			<tr><td><code>columns</code></td><td>Campos donde se busca el texto (sin distinguir mayúsculas). Por omisión, el campo de <code>value</code>.</td></tr>
			<tr><td><code>value</code>, <code>label</code></td><td>Expresiones de agregación: lo que se escribe en el campo y lo que se ve en la lista (por omisión igual a <code>value</code>).</td></tr>
			<tr><td><code>pipeline</code></td><td>Etapas previas a la búsqueda: filtrar por usuario, por estado...</td></tr>
			<tr><td><code>distinct</code></td><td>Un resultado por valor, ordenado alfabéticamente; omite vacíos.</td></tr>
			<tr><td><code>fields</code></td><td><code>['nombre' => expresión]</code>: datos extra en cada resultado, para <code>onselect</code>.</td></tr>
			<tr><td><code>depends</code>, <code>clearondepends</code></td><td><code>['param' => '#selector']</code>: envía el valor de otros controles. En MongoDB es filtro exacto sobre ese campo (vacío = sin filtro). Al cambiar el otro control se limpia este, salvo <code>clearondepends => false</code>.</td></tr>
			<tr><td><code>minlength</code>, <code>delay</code>, <code>limit</code></td><td>Letras mínimas (1), espera en ms antes de consultar (250) y máximo de sugerencias (20).</td></tr>
			<tr><td><code>onselect</code></td><td>Cuerpo de una función JavaScript con <code>item</code> e <code>input</code>; se ejecuta al elegir una sugerencia.</td></tr>
		</tbody>
	</table>

	<h3>En el navegador</h3>
	<?= docCode(<<<'JS'
const el = document.getElementById('cliente');
el.nfSuggest.search();            // consultar con el texto actual
el.nfSuggest.setItems(['a', 'b']); // reemplazar la lista fija
el.nfSuggest.clearCache();        // olvidar respuestas guardadas
el.addEventListener('nf:suggest-select', e => console.log(e.detail));   // {label, value, ...fields}

// También sirve para inputs propios:
nfSuggest(document.querySelector('#otro'), { url: '/api/buscar', minlength: 2 }, item => console.log(item));
JS, 'javascript') ?>
	<p class="remark info">Teclado: <kbd>↓</kbd>/<kbd>↑</kbd> recorren la lista, <kbd>Enter</kbd> elige y <kbd>Esc</kbd> la cierra. El texto de cada sugerencia se escapa, así que los datos capturados por usuarios se pueden mostrar sin riesgo.</p>

	<?= docSource(__FILE__) ?>
</div>
