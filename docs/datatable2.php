<?php
require 'common.php';
$datatable=new MetroDataTable();
$datatable->header='<th>Titulo</th><th>Descripcion</th><th>Opciones</th>';
foreach ($m->{$config['sitedb']}->exampledata->find() as $doc) {
    $datatable->data[]=[
        $doc['_id'],
        serialize($doc),
        '<button onclick="editar();"><spam class="mif-pencil"></spam></button>'
        ];
}
?>
<div class="container p-5">
	<div class="bg-cyan fg-white p-3"><h4>DataTable</h4></div>
	<div class="bg-white p-3">
		<a href="prueba.php" class="button"><span class="mif-plus"></span> Nuevo</a>		
		<?=$datatable;?>
	</div>
</div>
<script>
function editar(id){
	var content="";
	$.ajax({
	    url : 'databindingajax2.php',
	    data : { _id : id },
	    type : 'GET',
	    success : function(data) {
	        Metro.dialog.create({
		        title: "Editar",
		        content: data,
		        actions: [
		            {
		                caption: "<span class=\"mif-floppy-disk\"></span>&nbsp;Aceptar",
		                cls: "js-dialog-close alert",
		                onclick: function(){
		                    alert("You clicked Agree action");
		                }
		            },
		            {
		                caption: "<span class=\"mif-exit\"></span>&nbsp;Cancelar",
		                cls: "js-dialog-close",
		                onclick: function(){
		                    alert("You clicked Disagree action");
		                }
		            }
		        ]
		    });
	    }
	});
	
}
</script>
<pre><code class="html">
<?=tocode(__file__) ?>
</code></pre>