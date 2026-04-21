<?php
$tabla = $m->{$config['sitedb']}->nftables->findOne([
	'nfcollection' => $p['collection']
]);


if (!$tabla) {
	echo 'No se encontró la tabla' . $config['sitedb'] . '.nftables con nfcollection ' . $p['collection'];
}
if (!isset($tabla->nffields) || count($tabla->nffields) == 0) {
	echo 'La tabla no tiene campos definidos';
}

$headers = '';
$columns = [];
$filtertypes = [
	'string' => 'string',
	'integer' => 'integer',
	'date' => 'date',

];

foreach ($tabla->nffields as $field) {
	$headers .= '<th>' . $field->short_description . '</th>';
	$columns[] = $field->field;
	$filters[] = [
		'id' => $field->field,
		'field' => $field->field,
		'label' => $field->short_description,
		'type' => $filtertypes[$field->type],
	];
}
$columns[] = '_id';
$headers .= '<th>Acciones</th>';

$datatable = new Table();
$datatable->Ajax([
	'id' => 'nftable_' . $tabla->nfcollection,
	'db' => $config['sitedb'],
	'collection' => $tabla->nfcollection,
	'header' => $headers,
	/*'pipeline'=>[[
    	'$addFields'=>[
    		'addfield'=>'add'
    		]
    	]],*/
	'columns' => $columns,
	'columnDefs' => [
		count($columns) - 1 => ['render' => "'<a href=\"/nftables/" . $tabla->nfcollection . "/'+data+'\" class=\"square button small primary\"><span class=\"mif-pencil\"></span></a>'+		
		'<a href=\"javascript:removeid(\\''+data+'\\');\" class=\"square small button alert\"><span class=\"mif-bin\"></span></a>'"], // data $row[0]
	]
]);

$tablef = new Tablef([
	//'excelFile'=>__DIR__.'/alumnos.xlsx',
	//'excelCell'=>'A2',
	'table' => &$datatable,
	'filters' => $filters
]);


if ($nframework->isAjax()) {
	if ($_POST['op'] == 'delete') {
		$m->{$config['sitedb']}->{$tabla->nfcollection}->deleteOne(['_id' => tomongoid($_POST['_id'])]);
	}
} else {
	$nframework->usecommon = true;
	$javas->addjs(
		<<<jss
	function removeid(id){
		Swal.fire({
			title: 'Estas seguro?',
			text: 'No podras deshacer esto!',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#3085d6',
			cancelButtonColor: '#d33',
			confirmButtonText: 'Si, borrar!'
		}).then((result) => {
			if (result.isConfirmed) {
				$.ajax({
					url: "/nftables/{$tabla->nfcollection}/"+id,
					method: 'DELETE',
					cache: false 					
				}).done(function() {
					datatable=$('#nftable_{$tabla->nfcollection}').DataTable();
					datatable.clearPipeline();
					datatable.draw();
					Swal.fire(
	    				'Borrado!',
		    			'El registro ha sido eliminado.',
	    				'success'
	    			)
				});
			}
		})
	}
jss
	);
?>
	<div class="container p-5">
		<div class="box shadow-large">
			<div class="box-title"><?= $tabla->plural ?></div>
			<a href="/nftables/<?= $tabla->nfcollection ?>/create" class="button"><span class="mif-plus"></span> Nuevo</a>
			<?= $tablef; ?>
			<?= $datatable; ?>
		</div>
	</div>
<?php
}
