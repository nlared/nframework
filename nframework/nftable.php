<?php


$tabla = $m->{$config['sitedb']}->ntablas->findOne([
    '_id' => tomongoid($p['collection']))
]);


if (!$tabla) {
    echo 'No se encontró la tabla';
}
if (!isset($tabla->campos) || count($tabla->campos) == 0) {
    echo 'La tabla no tiene campos definidos';
}

$headers = '';
foreach ($tabla->campos as $campo) {
    $headers .= '<th>' . $campo->descripcion_corta . '</th>';
    $columns[] = $campo->nombre;
}


$datatable = new Table();
$datatable->Ajax([
    'id' => 'testid',
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
        count($columns) => ['render' => "'<a href=\"/nftables/" . $tabla->nfcollection . "/'+data+'\" class=\"square button small primary\"><span class=\"mif-pencil\"></span></a>'+		
		'<a href=\"javascript:removeid(\\''+data+'\\');\" class=\"square small button alert\"><span class=\"mif-bin\"></span></a>'"], // data $row[0]
    ]
]);


if ($nframework->isAjax()) {
    if ($_POST['op'] == 'delete') {
        $m->{$config['sitedb']}->{$tabla->nfcollection}->deleteOne(['_id' => tomongoid($_POST['_id'])]);
    }
} else {
    $nframework->usecommon = true;
    $javas->addjs("
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
					url: \"$_SERVER[PHP_SELF]\",
					method: 'post',
					cache: false, 
					data:{
						op: 'delete',
						_id: id
					}
				}).done(function() {
					datatable=$('#testid').DataTable();
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
	
	");
?>
    <div class="container p-5">
        <div class=" p-5">
            <h2><?= $tabla->plural ?></h2>
        </div>
        <div class="p-5">
            <?= $datatable; ?>
        </div>
    </div>
<?php
}
