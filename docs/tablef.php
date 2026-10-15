<?php
require 'common.php';

docDemoVentas();

// 1) La tabla AJAX con la consulta base (lo que el filtro nunca quita).
$pedidos = new Table();
$pedidos->Ajax([
	'id' => 'pedidosf',
	'db' => $config['sitedb'],
	'collection' => 'demo_pedidos',
	'header' => '<th>Folio</th><th>Fecha</th><th>Importe</th><th>Estado</th><th>Productos</th>',
	'pipeline' => [
		['$match' => ['estado' => ['$ne' => 'cancelado']]],   // filtro fijo
	],
	'columns' => ['folio', 'fecha', 'importe', 'estado', 'productos'],
	'order' => [[0, 'desc']],
]);

// 2) TableF después de Ajax(): toma ese pipeline como base y le agrega el $match del constructor de filtros.
$filtros = new Tablef([
	'table' => &$pedidos,
	'filters' => [
		['id' => 'folio', 'field' => 'folio', 'label' => 'Folio', 'type' => 'integer', 'phptype' => 'number'],
		['id' => 'importe', 'field' => 'importe', 'label' => 'Importe', 'type' => 'double', 'phptype' => 'number'],
		['id' => 'fecha', 'field' => 'fecha', 'label' => 'Fecha (AAAA-MM-DD)', 'type' => 'date', 'phptype' => 'date',
			'operators' => ['less', 'less_or_equal', 'greater', 'greater_or_equal', 'between']],
		['id' => 'estado', 'field' => 'estado', 'label' => 'Estado', 'type' => 'string',
			'input' => 'select', 'values' => ['pagado' => 'Pagado', 'pendiente' => 'Pendiente'], 'operators' => ['equal', 'not_equal']],
		['id' => 'productos', 'field' => 'productos', 'label' => 'Productos', 'type' => 'integer', 'phptype' => 'number'],
	],
]);
?>
<div class="container">
	<?= docHeader('DataTable con filtros (TableF)', '<code>TableF</code> agrega a una <a href="datatableajax.php">DataTable AJAX</a> un constructor visual de filtros (jQuery QueryBuilder). Las reglas se convierten en un <code>$match</code> que se suma al pipeline de la tabla, y el resultado se puede exportar a CSV o Excel. Para que el usuario escriba la pregunta en lenguaje natural vea <a href="tablefia.php">TablefIA</a>.') ?>

	<h3>Ejemplo</h3>
	<p>Agregue reglas (por ejemplo <em>Importe mayor que 5000</em> y <em>Estado igual a Pendiente</em>) y pulse <strong>Filtrar</strong>.</p>
	<div class="card p-4">
		<?= $filtros ?>
		<?= $pedidos ?>
	</div>

	<p>El código completo está al final de la página. En el HTML se imprime primero el constructor de filtros y después la tabla: <code>&lt;?= $filtros ?&gt;</code> <code>&lt;?= $pedidos ?&gt;</code>.</p>

	<h3>Opciones</h3>
	<table class="table striped compact">
		<thead><tr><th>Opción</th><th>Descripción</th></tr></thead>
		<tbody>
			<tr><td><code>table</code></td><td>La <code>Table</code> ya configurada con <code>Ajax()</code> (por referencia). Su <code>pipeline</code> queda como base: el filtro se agrega al final y nunca lo reemplaza.</td></tr>
			<tr><td><code>filters</code></td><td>Campos que se pueden filtrar (ver abajo).</td></tr>
			<tr><td><code>query</code></td><td>Reglas iniciales en formato Mongo (JSON) que se cargan en el constructor.</td></tr>
			<tr><td><code>codeid</code></td><td>Id de un input donde se escribe el <code>$match</code> resultante en JSON (para guardarlo, como en Admin → Reglas).</td></tr>
			<tr><td><code>excelFile</code>, <code>excelCell</code></td><td>Plantilla .xlsx y celda inicial para la exportación a Excel (por defecto un libro vacío desde <code>A1</code>).</td></tr>
		</tbody>
	</table>

	<h4>Cada filtro</h4>
	<table class="table striped compact">
		<thead><tr><th>Clave</th><th>Descripción</th></tr></thead>
		<tbody>
			<tr><td><code>id</code>, <code>field</code></td><td>Campo del documento (notación con punto para subdocumentos). <code>id</code> es el que usa QueryBuilder; normalmente iguales.</td></tr>
			<tr><td><code>label</code></td><td>Texto que ve el usuario.</td></tr>
			<tr><td><code>type</code></td><td>Tipo de QueryBuilder: <code>string</code>, <code>integer</code>, <code>double</code>, <code>date</code>, <code>datetime</code>, <code>boolean</code>. Define los operadores disponibles.</td></tr>
			<tr><td><code>phptype</code></td><td>Conversión en el servidor antes de consultar: <code>number</code> (a float) o <code>date</code> (a <code>UTCDateTime</code> con <code>strtotime()</code>). Sin él el valor se compara como texto, así que los campos numéricos y de fecha lo necesitan.</td></tr>
			<tr><td><code>input</code>, <code>values</code></td><td><code>'select'</code>, <code>'radio'</code> o <code>'checkbox'</code> con sus opciones <code>['valor' => 'Texto']</code>.</td></tr>
			<tr><td><code>operators</code></td><td>Limita los operadores (<code>equal</code>, <code>not_equal</code>, <code>less</code>, <code>greater</code>, <code>between</code>, <code>contains</code>, <code>begins_with</code>…).</td></tr>
			<tr><td><code>valueGetter</code></td><td>Función JS en texto para leer el valor del input, p. ej. <code>'function(rule){return Number(rule.$el.find("input").val());}'</code>.</td></tr>
		</tbody>
	</table>

	<h3>Cómo funciona</h3>
	<ul>
		<li><strong>Filtrar</strong> envía las reglas (<code>getMongo</code>) a <code>/nframework/datatablef.php</code>, que les quita operadores peligrosos con <code>nfSanitizeMongoQuery()</code> (<code>$where</code>, <code>$expr</code>, <code>$lookup</code>…), convierte tipos según <code>phptype</code> y guarda el pipeline en la sesión; la tabla se vuelve a dibujar.</li>
		<li><strong>Csv</strong> y <strong>Excel</strong> llaman a <code>/nframework/datatablee.php</code> con el pipeline filtrado y las <code>columns</code> de la tabla.</li>
		<li>El constructor usa el id fijo <code>#builder-basic</code>: solo puede haber un <code>TableF</code> por página.</li>
		<li>Instáncielo como <code>new Tablef(...)</code>: el autoload busca <code>class.Tablef.php</code> respetando mayúsculas, así que <code>new TableF</code> solo funciona si la clase ya se cargó.</li>
	</ul>

	<?= docSource(__FILE__) ?>
</div>
