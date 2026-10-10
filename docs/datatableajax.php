<?php
require 'common.php';

$datatable = new Table();
$datatable->Ajax([
	'id' => 'testid',
	'db' => $config['sitedb'],
	'collection' => 'exampledata',
	'mark' => true,                                   // resalta el texto buscado
	'header' => '<th>Texto</th><th>Número</th><th>Fecha</th><th>Activo</th><th>Calculado</th><th></th>',
	'pipeline' => [
		// ['$match' => ['owner' => $user->_id]],     // filtro fijo: por usuario, por estado...
		['$addFields' => ['calculado' => ['$multiply' => [['$ifNull' => ['$number', 0]], 2]]]],
	],
	'columns' => ['text', 'number', 'date', 'checkbox', 'calculado', '_id'],
	'columnDefs' => [
		'5' => ['render' => "'<a href=\"databindingajax.php?_id='+data+'\" class=\"square button small primary\"><span class=\"mif-pencil\"></span></a>'+
		'<a href=\"arraylist.php?_id='+data+'\" class=\"square small button primary\"><span class=\"mif-list-bulleted\"></span></a>'+
		'<a href=\"javascript:removeid(\\''+data+'\\');\" class=\"square small button alert\"><span class=\"mif-bin\"></span></a>'"],
	],
]);

if ($nframework->isAjax()) {
	if (($_POST['op'] ?? '') === 'delete' && isValidObjectId($_POST['_id'] ?? null)) {
		$m->{$config['sitedb']}->exampledata->deleteOne(['_id' => toMongoId($_POST['_id'])]);
		$result = ['error' => false];
	}
	return;
}

$javas->addjs(<<<'JS'
function removeid(id) {
	Swal.fire({
		title: '¿Está seguro?',
		text: 'No podrá deshacerlo.',
		icon: 'warning',
		showCancelButton: true,
		confirmButtonText: 'Sí, borrar'
	}).then((r) => {
		if (!r.isConfirmed) return;
		$.post(location.pathname, {op: 'delete', _id: id}, function () {
			datatables['testid'].clearPipeline();   // descarta la caché de páginas
			datatables['testid'].draw();
			Swal.fire('Borrado', 'El registro se eliminó.', 'success');
		}, 'json');
	});
}
JS);
?>
<div class="container">
	<?= docHeader('DataTable AJAX', 'Con <code>$table->Ajax([...])</code> la búsqueda, el orden y la paginación se hacen en MongoDB a través de <code>/nframework/datatable.php</code>. La consulta se guarda en la sesión: el navegador no puede cambiar la colección ni el filtro.') ?>
	<a href="databindingajax.php" class="button primary mb-2"><span class="mif-plus"></span> Nuevo</a>
	<?= $datatable ?>

	<h3>Opciones de <code>Ajax()</code></h3>
	<table class="table striped compact">
		<thead><tr><th>Opción</th><th>Descripción</th></tr></thead>
		<tbody>
			<tr><td><code>db</code>, <code>collection</code></td><td>Origen de los datos.</td></tr>
			<tr><td><code>columns</code></td><td>Campos, en el orden de las columnas. Se usan también para buscar y ordenar.</td></tr>
			<tr><td><code>pipeline</code></td><td>Etapas de agregación previas: <code>$match</code> para un filtro fijo, <code>$addFields</code>, <code>$lookup</code> a otra colección, etc.</td></tr>
			<tr><td><code>columnDefs</code></td><td>Render en JavaScript por columna; útil para botones con el <code>_id</code>.</td></tr>
			<tr><td><code>mark</code></td><td>Resalta en la tabla el texto buscado.</td></tr>
		</tbody>
	</table>
	<?= docSource(__FILE__) ?>
</div>
