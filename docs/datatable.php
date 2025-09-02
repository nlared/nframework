<?php
require '../common2.php';
$datatable=new Table();
$datatable->header='<th>Titulo</th><th>Descripcion</th><th>Opciones</th>';
foreach ($m->{$config['sitedb']}->exampledata->find() as $doc) {
    $datatable->data[]=[
        $doc['_id'],
        serialize($doc),
        '<a href="databinding.php?_id='.$doc['_id'].'"><spam class="mif-pencil"></spam></a>
        <a href="?eliminar='.$doc['_id'].'"><spam class="mif-cross"></spam></a>'
        ];
}
?>
<div class="container p-5">
	<div class="bg-cyan fg-white p-3"><h4>DataTable</h4></div>
	<div class="bg-white p-3">
	<a href="databinding.php" class="button"><span class="mif-plus"></span> Nuevo</a>		
	<?=$datatable;?>
</div>
</div>

<pre><code class="html">
<?=tocode(__file__) ?>
</code></pre>