<?php
require '../common2.php';
$developermode=true;


if ($nframework->isAjax()) {
	if ($_POST['op']=='delete') {
		$m->{$config['sitedb']}->pages->deleteone(['_id'=>tomongoid($_POST['_id'])]); 
	}
	
}

$nframework->usecommon=true;
$datatable=new Table();
$datatable->header='<th>'.$nframework->language['title'].'</th><th>'.$nframework->language['path'].'</th><th>'.$nframework->language['language'].'</th><th>'.$nframework->language['options'].'</th>';
foreach ($m->{$config['sitedb']}->pages->find() as $doc) {
    $datatable->data[]=[
        $doc['title'],
        $doc['path'],
        $doc['lang'],
        '<a href="page.php?_id='.$doc['_id'].'" class="button primary"><spam class="mif-pencil"></spam></a>
        <a href="javascript:removeid(\''.$doc['_id'].'\')" class="button alert"><spam class="mif-cross"></spam></a>'
        ];
}


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
			//	url: \"datatableajax.php\",
				method: 'post',
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
	<div class="bg-cyan fg-white p-3"><h4><?=$nframework->language['pages']?></h4></div>
	<div class="bg-white p-3">
	<a href="page.php" class="button"><span class="mif-plus"></span>&nbsp;<?=$nframework->language['new']?></a>		
	<?=$datatable;?>
</div>
</div>
