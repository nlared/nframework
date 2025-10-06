<?php
require '../common2.php';
if(!$user->in('admins')){
	//exit();
}
$datatable=new Table();
$datatable->Ajax([
    'id'=>'testid',
    'db'=>$config['sitedb'],
    'collection'=>'users',
    'header'=>'<th>'.$nframework->language['username'].'</th><th>'.$nframework->language['options'].'</th>',
    'pipeline'=>[
    	[
    	'$match'=>[
    		'username'=>['$ne'=>'guest']
    	]
    	]
    ],
    'columns'=>[
        'username','_id',
    ],
    'columnDefs'=>[
		'1'=>['render'=>"'<a href=\"user.php?_id='+data+'\" class=\"button primary btn btn-primary\"><span class=\"bi bi-pencil mif-pencil\"></span></a><a href=\"javascript:eliminar(\''+data+'\')\" class=\"button alert btn btn-danger \"><span class=\"bi-trash mif-cross\"></span</a>'"],
	]
]);

if ($nframework->isAjax()) {
	if (!empty($_GET['eliminar'])) {
    	$m->{$config['sitedb']}->users->deleteOne(['_id'=>tomongoid($_GET['eliminar'])]);
    }
}else{
	$nframework->usecommon=true;
?>
<div class="container p-5">
	<div class="mt-4 mb-4">
		<h1 class="text-weight-10 text-center gradient gr-text-blue"><?=$nframework->language['users']?></h1>
	</div>
	<div class="box shadow-large-extra p-5">
		<a href="user.php" class="button primary "><span class="mif-add-person"></span>&nbsp;<?=$nframework->language['new']?></a>
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
		  datatables["usersgroups"].clearPipeline().draw(false);
		});
	}
</script>
<?}?>