<?
require 'common2.php';
if (empty($_GET['_id'])) {
	$newid = new MongoDB\BSON\ObjectID();
	header('Location: ?_id=' . $newid);
	exit();
}

$nframework->usecommon = true;
$dataset = new dataset(
	[
		'collection' => $m->{$config['sitedb']}->exampledata,
		'_id' => $_GET['_id'],
		'simpleid' => false,
		'nameprefix' => 'data'
	]
);
$noobfuscate = true;
$developermode = true;


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
<div class="container p-5">
	<div class="box shadow-large">
		<div class="box-title">Embeded Data</div>
		<div class="button" onclick="<?= $arrayf->function_new() ?>">Agregar</div>
		<div id="list">

		</div>
	</div>
</div>