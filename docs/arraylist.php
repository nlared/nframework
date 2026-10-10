<?php
if (empty($_GET['_id']) || !preg_match('/^[a-f\d]{24}$/i', $_GET['_id'])) {
	header('Location: ?_id=' . new MongoDB\BSON\ObjectId());
	exit();
}
$noobfuscate = true;
require 'common.php';

$dataset = new dataset(
	[
		'collection' => $m->{$config['sitedb']}->exampledata,
		'_id' => $_GET['_id'],
		'simpleid' => false,
		'nameprefix' => 'data'
	]
);
// Diálogo con el formulario de un elemento de la lista.
$dialog = new Dialog([
	'title' => 'title',
]);
$arrayf = new embededArray([
	//'action'=>'items.php?_id='.$dataset->_id,
	'dataset' => &$dataset,
	'field' => 'texts',
	'containerid' => 'list',
	'dialogid' => $dialog->id,
	'template' => <<<T
	{% if items|length > 0 %}
    	<div class="grid">
        {% for key,item in items %}
            <div class="row">
            	<div class="cell">{{ item.text|e }}</div>
    	    	<div class="cell">
            		<div class="button primary" onclick="javascript:{{function_get}}('{{key}}')"><span class="mif-pencil"></span></div>
					<div class="button alert" onclick="javascript:{{function_delete}}('{{key}}')"><span class="mif-cross"></span></div>
				</div>
            </div>
        {% endfor %}
    	</div>
	{% else %}
	no hay
	{% endif %}
T
]);

$txt = new inputtext(['nfembeded' => &$arrayf, 'field' => 'text']);
$dialog->content = <<<FORM
	<div class="grid">
		<div class="row">
			$txt
		</div>
	</div>
FORM;
echo $dialog;
echo $arrayf;



?>
<style>
	dialog {
		width: 800px;
	}
</style>
<div class="container">
	<?= docHeader('Arreglos embebidos', '<code>embededArray</code> edita una lista de subdocumentos (<code>texts: [{text: ...}, ...]</code>) dentro de un documento: agregar, editar y borrar elementos sin recargar. Cada elemento se edita en un <a href="dialog.php">Dialog</a> con controles ligados mediante <code>\'nfembeded\' => &$arrayf</code>, y la lista se dibuja con una plantilla Twig.') ?>
	<div class="card p-4">
		<div class="button primary" onclick="<?= htmlspecialchars($arrayf->function_new(), ENT_QUOTES) ?>"><span class="mif-plus"></span> Agregar</div>
		<div id="<?= htmlspecialchars($arrayf->containerid, ENT_QUOTES) ?>" class="mt-2"></div>
	</div>
	<h3>Variables de la plantilla</h3>
	<ul>
		<li><code>items</code>: los elementos del arreglo; <code>key</code> es su posición.</li>
		<li><code>function_get</code>, <code>function_delete</code>: nombres de las funciones JS para editar o borrar un elemento por <code>key</code>.</li>
		<li><code>$arrayf->function_new()</code>: llamada JS que abre el diálogo vacío.</li>
	</ul>
	<?= docSource(__FILE__) ?>
</div>