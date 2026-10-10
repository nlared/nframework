<?php
require 'common.php';

$examples = [
	['code' => "new select([
	'name' => 'pais1',
	'caption' => 'País (escriba para buscar)',
	'ajax' => [
		'db' => \$config['sitedb'],
		'collection' => 'countrys',
		'columns' => ['name', 'iso2'],   // campos donde se busca lo que se escribe
		'label' => '\$name',             // expresión de agregación para el texto visible
		'value' => '\$iso2',             // expresión para el valor que se envía
		'pipeline' => [],
	],
]);",
		'title' => 'Búsqueda simple',
		'desc' => 'Mientras el usuario escribe, el navegador consulta <code>/nframework/select_ajax.php</code>, que busca en <code>columns</code> sin distinguir mayúsculas.'],

	['code' => "new select([
	'name' => 'pais2',
	'caption' => 'Con valor inicial',
	'value' => 'MX',
	'ajax' => [
		'db' => \$config['sitedb'],
		'collection' => 'countrys',
		'columns' => ['name'],
		'label' => ['\$concat' => ['\$name', ' (', '\$iso2', ')']],
		'value' => '\$iso2',
		'pipeline' => [],
	],
]);",
		'title' => 'Valor inicial y etiqueta compuesta',
		'desc' => 'Con <code>value</code> el control carga la etiqueta correspondiente al abrir la página. <code>label</code> acepta cualquier expresión de agregación.'],

	['code' => "new select([
	'name' => 'pais3',
	'caption' => 'Solo países con bandera, ordenados',
	'ajax' => [
		'db' => \$config['sitedb'],
		'collection' => 'countrys',
		'columns' => ['name'],
		'label' => '\$name',
		'value' => '\$iso3',
		'pipeline' => [
			['\$match' => ['flag' => ['\$ne' => null]]],
			['\$sort' => ['name' => 1]],
			['\$limit' => 20],
		],
	],
]);",
		'title' => 'Filtrar con pipeline',
		'desc' => '<code>pipeline</code> se ejecuta antes de la búsqueda: sirve para filtrar por usuario, ordenar o limitar resultados.'],
];
?>
<div class="container">
	<?= docHeader('Select con AJAX', 'Un <code>select</code> con la opción <code>ajax</code> se convierte en un buscador (Tom Select) que consulta una colección de MongoDB. La consulta se guarda en la sesión del usuario: el navegador solo envía el texto buscado.') ?>
	<?= docExamples($examples) ?>

	<h3>Opciones de <code>ajax</code></h3>
	<table class="table striped compact">
		<thead><tr><th>Clave</th><th>Descripción</th></tr></thead>
		<tbody>
			<tr><td><code>db</code>, <code>collection</code></td><td>Base y colección donde buscar.</td></tr>
			<tr><td><code>columns</code></td><td>Campos donde se busca el texto escrito.</td></tr>
			<tr><td><code>label</code>, <code>value</code></td><td>Expresiones de agregación (<code>'$campo'</code> o <code>['$concat' => ...]</code>) para el texto y el valor de cada opción.</td></tr>
			<tr><td><code>pipeline</code></td><td>Etapas previas (<code>$match</code>, <code>$sort</code>, <code>$limit</code>...). Obligatorio; use <code>[]</code> si no hay.</td></tr>
			<tr><td><code>args</code> + <code>adduri</code></td><td><code>args</code> lista parámetros GET que se agregan como filtro exacto; <code>adduri</code> (p.ej. <code>'&amp;estado=COA'</code>) los envía.</td></tr>
			<tr><td><code>load</code></td><td>Función JavaScript propia de carga, en lugar de la consulta estándar.</td></tr>
		</tbody>
	</table>
	<p class="remark warning">Use un arreglo para <code>ajax</code>. La clase <code>SelectAjaxOptions</code> todavía no es compatible con el control.</p>

	<h3>Valores de tipo ObjectId</h3>
	<?= docCode(<<<'PHP'
// Elegir un documento por _id y guardarlo como ObjectId con databinding.
$cliente = new select([
    'dataset' => &$dataset,
    'field' => 'cliente_id',
    'caption' => 'Cliente',
    'format' => Select::formatMongoID,         // convierte el valor a ObjectId al guardar
    'ajax' => [
        'db' => $config['sitedb'],
        'collection' => 'clientes',
        'columns' => ['nombre', 'rfc'],
        'label' => ['$concat' => ['$nombre', ' - ', '$rfc']],
        'value' => ['$toString' => '$_id'],
        'pipeline' => [['$match' => ['activo' => true]]],
    ],
]);
PHP) ?>
</div>
