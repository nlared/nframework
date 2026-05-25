<?php
require 'common.php';

$datatable=new Table();
$datatable->Ajax([
    'id'=>'testid',
    'db'=>$config['sitedb'],
    'collection'=>'exampledata',
    'header'=>'<th>Text</th><th>Number</th><th>Date</th><th>Checkbox</th><th>addexample</th><th>id</th>',
    'pipeline'=>[[
    	'$addFields'=>[
    		'addfield'=>'add'
    		]
    	]],
    'columns'=>[
        'text','number','date','checkbox','addfield','_id'
    ],
    'columnDefs'=>[
		'5'=>['render'=>"'<a href=\"databindingajax.php?_id='+data+'\" class=\"square button small primary\"><span class=\"mif-pencil\"></span></a>'+
		'<a href=\"arraylist.php?_id='+data+'\" class=\"square small button primary\"><span class=\"mif-list-bulleted\"></span></a>'+
		'<a href=\"javascript:removeid(\\''+data+'\\');\" class=\"square small button alert\"><span class=\"mif-bin\"></span></a>'"],// data $row[0]
	]
]);


if ($nframework->isAjax()) {
	if ($_POST['op']=='delete') {
		$m->{$config['sitedb']}->exampledata->deleteOne(['_id'=>tomongoid($_POST['_id'])]);
	}
}else{
	$nframework->usecommon=true;
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
		<div class="box shadow-large">
			<div class="box-title">Ajax Datatable</div>
			<?= $datatable; ?>
		</div>
	</div>
	<div class="container p-5">
		<pre class="stay-on"><code class="html">
<?= tocode(__file__) ?>
</code></pre>
	</div>
<? } ?>