<?php
require 'common.php';

// 1. Datos generados en PHP.
$ventas = [
	['Norte', 'Ana', 1250.5, '2026-06-01'],
	['Sur', 'Luis', 980, '2026-06-02'],
	['Centro', 'María <b>', 2310.75, '2026-06-02'],
	['Norte', 'Pedro', 450, '2026-06-03'],
];
$tabla = new Table([
	'header' => '<th>Región</th><th>Vendedor</th><th>Importe</th><th>Fecha</th>',
	'foot' => '<tr><th colspan="2">Total</th><th>' . number_format(array_sum(array_column($ventas, 2)), 2) . '</th><th></th></tr>',
	'order' => [[2, 'desc']],     // ordenar por importe, mayor primero
	'compact' => true,
]);
foreach ($ventas as [$region, $vendedor, $importe, $fecha]) {
	// Las celdas son HTML: escape todo lo que venga del usuario o de la base de datos.
	$tabla->data[] = [htmlspecialchars($region), htmlspecialchars($vendedor), number_format($importe, 2), $fecha];
}

// 2. Columna calculada en el navegador con columnDefs.
$estados = new Table([
	'header' => '<th>Folio</th><th>Avance</th>',
	'data' => [['A-001', 25], ['A-002', 80], ['A-003', 100]],
	'columnDefs' => [
		// render es una expresión JavaScript; recibe data, type, row y meta.
		1 => ['render' => "'<div data-role=\"progress\" data-value=\"' + data + '\"></div>' + data + '%'"],
	],
]);

// 3. Documentos de MongoDB.
$documentos = new Table(['header' => '<th>_id</th><th>Texto</th><th>Número</th><th></th>']);
foreach ($m->{$config['sitedb']}->exampledata->find([], ['limit' => 50]) as $doc) {
	$documentos->data[] = [
		(string) $doc['_id'],
		htmlspecialchars((string) ($doc['text'] ?? '')),
		htmlspecialchars((string) ($doc['number'] ?? '')),
		'<a class="button small" href="databinding.php?_id=' . $doc['_id'] . '"><span class="mif-pencil"></span></a>',
	];
}
?>
<div class="container">
	<?= docHeader('DataTable', '<code>Table</code> genera una tabla con <a href="https://datatables.net" target="_blank">DataTables</a> (búsqueda, orden y paginación en el navegador). Para colecciones grandes use la <a href="datatableajax.php">versión AJAX</a>, que pagina en el servidor.') ?>

	<h3>Datos en PHP</h3>
	<?= $tabla ?>
	<?= docCode(<<<'PHP'
$tabla = new Table([
    'header' => '<th>Región</th><th>Vendedor</th><th>Importe</th><th>Fecha</th>',
    'foot' => '<tr><th colspan="2">Total</th><th>' . $total . '</th><th></th></tr>',
    'order' => [[2, 'desc']],
    'compact' => true,
]);
foreach ($ventas as [$region, $vendedor, $importe, $fecha]) {
    $tabla->data[] = [htmlspecialchars($region), htmlspecialchars($vendedor), number_format($importe, 2), $fecha];
}
echo $tabla;
PHP) ?>

	<h3>Columnas calculadas</h3>
	<?= $estados ?>
	<?= docCode(<<<'PHP'
$estados = new Table([
    'header' => '<th>Folio</th><th>Avance</th>',
    'data' => [['A-001', 25], ['A-002', 80], ['A-003', 100]],
    'columnDefs' => [
        1 => ['render' => "'<div data-role=\"progress\" data-value=\"' + data + '\"></div>' + data + '%'"],
    ],
]);
PHP) ?>

	<h3>Desde MongoDB</h3>
	<a href="databinding.php" class="button primary mb-2"><span class="mif-plus"></span> Nuevo</a>
	<?= $documentos ?>
	<?= docCode(<<<'PHP'
$documentos = new Table(['header' => '<th>_id</th><th>Texto</th><th>Número</th><th></th>']);
foreach ($m->{$config['sitedb']}->exampledata->find([], ['limit' => 50]) as $doc) {
    $documentos->data[] = [
        (string) $doc['_id'],
        htmlspecialchars((string) ($doc['text'] ?? '')),
        htmlspecialchars((string) ($doc['number'] ?? '')),
        '<a class="button small" href="databinding.php?_id=' . $doc['_id'] . '">Editar</a>',
    ];
}
PHP) ?>

	<h3>Opciones</h3>
	<table class="table striped compact">
		<thead><tr><th>Opción</th><th>Descripción</th></tr></thead>
		<tbody>
			<tr><td><code>header</code>, <code>foot</code></td><td>HTML del encabezado y pie.</td></tr>
			<tr><td><code>data</code></td><td>Filas: arreglo de arreglos con el HTML de cada celda (no se escapa).</td></tr>
			<tr><td><code>order</code></td><td>Orden inicial, p.ej. <code>[[0, 'asc']]</code>.</td></tr>
			<tr><td><code>columnDefs</code></td><td><code>[columna => ['render' => 'expresión JS']]</code>.</td></tr>
			<tr><td><code>striped</code>, <code>compact</code>, <code>rowhover</code>, <code>cellborder</code>, <code>nowrap</code></td><td>Estilo de la tabla.</td></tr>
			<tr><td><code>lengthMenu</code>, <code>scrollX</code>, <code>responsive</code>, <code>stateSave</code></td><td>Paginación, desplazamiento horizontal, columnas plegables y recordar el estado.</td></tr>
			<tr><td><code>id</code></td><td>El objeto JavaScript queda en <code>datatables["id"]</code>.</td></tr>
		</tbody>
	</table>
</div>
