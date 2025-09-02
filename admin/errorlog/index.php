<?php
require '../common2.php';
if(!$user->in('admins')){
	exit();
}
$datatable=new Table();
$datatable->Ajax([
    'id'=>'errorlog',
    'db'=>$config['sitedb'],
    'collection'=>'errorlog',
    'header'=>'
    <th>Type</th>
    <th>Description</th>
    <th>Last time</th>
    <th>Tries</th>
    <th>Opciones</th>',
    'pipeline'=>[
    	[
    	'$addFields'=>[
    		'number'=>['$toString'=>'$number']
    		]
    	],
    	[
    	'$addFields'=>[
    		'desc'=>['$concat'=>['$file',' ','$number','<br>','$desc']]
    		]
    	]
    	],
    'columns'=>[
        'type','desc','lasttime','tries','_id'
    ],
    'columnDefs'=>[
		'4'=>['render'=>"'<a href=\"user.php?_id='+data+'\" class=\"button\"><span class=\"mif-pencil\"></span></a><a href=\"javascript:eliminar(\''+data+'\')\" class=\"button\"><span class=\"mif-cross\"></span</a>'"],
	]
]);

if ($nframework->isAjax()) {
	if (!empty($_GET['eliminar'])) {
    	$m->{$config['sitedb']}->errorlog->deleteOne(['_id'=>tomongoid($_GET['eliminar'])]);
    }
}else{
	$nframework->usecommon=true;
?>
<div class="container p-5">
	<div class="bg-cyan fg-white p-5"><h2>Usuarios</h2></div>
	<div class="bg-white p-5">
		<a href="user.php" class="button"><span class="mif-user-plus"></span>&nbsp;Nuevo</a>
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
		  datatables["errorlog"].clearPipeline().draw(false);
		});
	}
</script>
<?}?>