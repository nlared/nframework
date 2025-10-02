<?php
require '../common2.php';
$developermode=true;


if(!empty($_GET['eliminar'])){
	$m->{$config['sitedb']}->menus->deleteone(['_id'=>tomongoid($_GET['eliminar'])]); 
}

$nframework->usecommon=true;
$datatable=new Table();
$datatable->header='<th>Title</th><th>Path</th><th>Options</th>';
foreach ($m->{$config['sitedb']}->menus->find() as $doc) {
    $datatable->data[]=[
        $doc['name'],
        $doc['path'],
        '<a href="menu.php?_id='.$doc['_id'].'"><spam class="mif-pencil"></spam></a>
        <a href="?eliminar='.$doc['_id'].'"><spam class="mif-cross"></spam></a>'
        ];
}
?>
<div class="container p-5">
	<div class="bg-cyan fg-white p-3"><h4>Menus</h4></div>
	<div class="bg-white p-3">
	<a href="menu.php" class="button"><span class="mif-plus"></span> New</a>		
	<?=$datatable;?>
</div>
</div>
