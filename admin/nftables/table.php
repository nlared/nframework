<?php
if (empty($_GET['_id'])) {
    $newid = new MongoDB\BSON\ObjectID();
    header('Location: ?_id=' . $newid);
    exit();
}
require 'include.php';
$dataset = new dataset(
    [
        'collection' => $m->{$config['sitedb']}->ntablas,
        '_id' => $_GET['_id'],
        'simpleid' => false,
        'nameprefix' => 'data'
    ]
);



$tabla_singular = new inputtext(['dataset' => &$dataset, 'field' => 'singular', 'caption' => 'Singular:', 'required' => true]);
$tabla_plural = new inputtext(['dataset' => &$dataset, 'field' => 'plural', 'caption' => 'Plural:', 'required' => true]);
$tabla_descripcion = new textarea(['dataset' => &$dataset, 'field' => 'nfdescription', 'caption' => 'Descripción:', 'required' => true]);
$tabla_coleccion = new inputtext(['dataset' => &$dataset, 'field' => 'nfcollection', 'caption' => 'collection:', 'required' => true]);



$dialogCampo = new Dialog([
    'title' => 'Detalles de campo',

]);
$arrayCampos = new embededArray([
    //'action'=>'items.php?_id='.$dataset->_id,
    'dataset' => &$dataset,
    'field' => 'campos',
    'containerid' => 'lista_campos',
    'onchange' => 'refrescarColumnas()',
    'dialogid' => 'dialogs_0',
    'template' => <<<T
	{% if items|length > 0 %}
        <table class="table">
		<thead>
			<tr>
            	<th>Nombre</th>
            	<th>Descripción</th>
				<th>Tipo</th>
            	<th>Opciones</th>
            </tr>
		</thead>
		<tbody>
        {% for key,item in items %}
            <tr>
            	<td>{{ item.field|e }}</td>            	
            	<td>{{ item.short_description|e }}</td>
				<td>{{ item.type|e }}</td>
            	<td>
            		<div class="button primary" onclick="javascript:{{function_get}}('{{key}}')"><span class="mif-pencil"></span></div>
					<div class="button alert" onclick="javascript:{{function_delete}}('{{key}}')"><span class="mif-cross"></span></div>
            	</td>
            </tr>
        {% endfor %}
		</tbody>
        </table>
	{% else %}
	Sin Hechos
	{% endif %}
T
]);

$campo_nombre = new inputtext(['nfembeded' => &$arrayCampos, 'field' => 'field', 'caption' => 'Nombre del campo:', 'required' => true]);
$campo_descripcion_corta = new inputtext(['nfembeded' => &$arrayCampos, 'field' => 'short_description', 'caption' => 'Descripcion corta:']);
$campo_descripcion_larga = new textarea(['nfembeded' => &$arrayCampos, 'field' => 'long_description', 'caption' => 'Descripcion larga:']);
$campo_tipo = new select(['nfembeded' => &$arrayCampos, 'field' => 'type', 'caption' => 'Tipo:', 'options' => [
    'inputtext' => 'Texto',
    'textarea' => 'Text area',
    'inputnumber' => 'Número',
    'inputdate' => 'Fecha',
    'inputdatetime' => 'Fecha y Hora',
    'select' => 'Opciones',
    'inputcheckbox' => 'Cuadro',
    'inputcheckboxes' => 'Multicuadro',
    'archivo' => 'Archivo',
    'mark' => 'Mapa'
]]);
$campo_requerido = new inputcheckbox(['nfembeded' => &$arrayCampos, 'field' => 'required', 'caption' => 'Requerido']);

$dialogCampo->content = <<<FORM
	<div class="row">
		<div class="cell">
			$campo_nombre
		</div>
		<div class="cell">
			$campo_tipo
		</div>
		<div class="cell">
			$campo_descripcion_corta
		</div>
		<div class="cell">
			$campo_descripcion_larga
		</div>
	</div>
FORM;


if ($nframework->isAjax()) {
    if ($_POST['op'] == 'save') {
        try {
            $result = [
                'error' => $dataset->save(),
            ];
        } catch (Exception $e) {
            $result = [
                'error' => $e->getMessage()
            ];
        }
    }
} else {
    $nframework->csss['900'] = 'https://cdn.datatables.net/2.3.7/css/dataTables.dataTables.css';
    $nframework->jss['900'] = 'https://cdn.datatables.net/2.3.7/js/dataTables.js';



    $nframework->usecommon = true;
    echo $dialogCampo;
    echo $arrayCampos;
    $javas->addjs(
        <<<addjs
	function refrescarTabla(){
		$('#tablepreview').DataTable().ajax.reload();
	}
	function refrescarColumnas(){
		$.ajax({
			url:'tabla_preview.php?_id={$dataset->_id}',
			success:function(data){
				var html='<table><thead><tr><th>'+
				data.columns.join('</th><th>')+
				'</th><th>Opciones</th></tr></thead></table>';
				
				$('#tablepreview').html(html);
				$('#tablepreview table').DataTable();
			}
		});
	}
	refrescarColumnas();			
addjs
    );

?>

    <div class="container p-5">
        <div class="box shadow-large">
            <div class="box-title">Tabla</div>
            <?= secureform() ?>
            <div class="grid">
                <div class="row">
                    <div class="cell-6">
                        <div class="row">
                            <div class="cell"><?= $tabla_coleccion ?></div>
                        </div>
                        <div class="row">
                            <div class="cell-6"><?= $tabla_singular ?></div>
                            <div class="cell-6"><?= $tabla_plural ?></div>
                        </div>
                        <div class="row">
                            <div class="cell"><?= $tabla_descripcion ?></div>
                        </div>
                    </div>
                    <div class="cell-6">
                        <div class="row">
                            <div class="cell">Campos</div>
                        </div>
                        <div class="row"">
							<div class=" cell" id="lista_campos"></div>
                        <div class="button" onclick="<?= $arrayCampos->function_new() ?>">Agregar</div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div id="tablepreview"></div>
                </div>
            </div>
            <div class="row">
                <div class="cell-md-2 offset-md-8"><a href="./" class="button primary btn btn-primary w-100"><span class="mif-exit"></span>&nbsp;<?= $nframework->language['close'] ?></a></div>
                <div class="cell-md-2"><button class="button success btn btn-success secureop  w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;<?= $nframework->language['save'] ?></button></div>
            </div>
        </div>
        </form>
    </div>
    </div>
    <style>
        #dialogs_0 {
            width: 800px;
        }
    </style>
<? } ?>