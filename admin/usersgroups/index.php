<?php
require '../common2.php';
if(!$user->in('admins')){
	//exit();
}


if ($nframework->isAjax()) {
	if (!empty($_GET['eliminar'])) {
    	$m->{$config['sitedb']}->usersgroups->deleteOne(['_id'=>tomongoid($_GET['eliminar'])]);
    	$result='ll';
    }
}else{
	$nframework->usecommon=true;
	$datatable=new Table();
$datatable->Ajax([
    'id'=>'usersgroups',
    'db'=>$config['sitedb'],
    'collection'=>'usersgroups',
    'header'=>'<th>Name</th><th>Description</th><th>Opciones</th>',
    /*'pipeline'=>[
    	[
    	'$match'=>[
    		'username'=>['$ne'=>'guest']
    	]
    	]
    ],*/
    'columns'=>[
        'name','description','_id',
    ],
    'columnDefs'=>[
		'2'=>['render'=>"'<a href=\"group.php?_id='+data+'\" class=\"button primary\"><span class=\"mif-pencil bi-pencil\"></span></a><a href=\"javascript:eliminar(\''+data+'\')\" class=\"button alert\"><span class=\"mif-cross bi-trash\"></span</a>'"],
	]
]);
	
	
	
	
	
?>
<div class="container p-5">
	<div class="bg-cyan fg-white p-5"><h2>Grupos</h2></div>
	<div class="bg-white p-5">
		<a href="group.php" class="button primary btn btn-primary"><span class="mif-users bi-people-fill"></span>&nbsp;Nuevo</a>
		<?=$datatable;?>
	</div>
</div>
<script>
	function eliminar(el){
		$.ajax({
		  url: "index.php",
		  data:{
		  	eliminar:el
		  }
		}).done(function(data) {
		  toast(data);
//		  datatables["usersgroups"].ajax.reload();
		  datatables["usersgroups"].clearPipeline().draw(false);
		});
	}
</script>
<?}?>