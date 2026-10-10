<?php
// Cada visita sin _id crea un documento nuevo; con _id lo edita.
if (empty($_GET['_id']) || !preg_match('/^[a-f\d]{24}$/i', $_GET['_id'])) {
	header('Location: ?_id=' . new MongoDB\BSON\ObjectId());
	exit();
}
require 'common.php';

$dataset = new dataset([
	'collection' => $m->{$config['sitedb']}->exampledata,
	'_id' => $_GET['_id'],
	'simpleid' => false,        // _id es ObjectId (true = texto libre)
	'nameprefix' => 'data',     // los campos se envían como data[campo]
]);

$texto = new inputText(['dataset' => &$dataset, 'field' => 'text', 'caption' => 'Texto', 'required' => true]);
$anidado = new inputText(['dataset' => &$dataset, 'field' => 'array.text2', 'caption' => 'Campo anidado (array.text2)']);
$numero = new inputNumber(['dataset' => &$dataset, 'field' => 'number', 'caption' => 'Número']);
$fecha = new inputDate(['dataset' => &$dataset, 'field' => 'date', 'caption' => 'Fecha']);
$casilla = new inputCheckbox(['dataset' => &$dataset, 'field' => 'checkbox', 'caption' => 'Activo']);

$mensaje = '';
$accion = '/docs/databinding.php?_id=' . $dataset->_id;
if (($_POST['op'] ?? '') === 'guardar') {
	if (!csrfValidate()) {
		$mensaje = '<div class="remark alert">Formulario caducado, recargue la página.</div>';
	} else {
		$errores = $dataset->save();                   // valida y guarda; false = sin errores
		if ($errores === false) {
			$dataset->modificado = date('Y-m-d H:i:s'); // asignar una propiedad la guarda de inmediato
			$mensaje = '<div class="remark success">Guardado.</div>';
		} else {
			$mensaje = '<div class="remark alert">' . $errores . '</div>';
		}
	}
}
?>
<div class="container">
	<?= docHeader('Databinding', 'Un <code>dataset</code> representa un documento de MongoDB. Los controles creados con <code>\'dataset\' => &$dataset</code> y <code>\'field\'</code> leen su valor del documento y <code>$dataset->save()</code> guarda lo enviado, ya validado y convertido (fechas a <code>UTCDateTime</code>, números a número...).') ?>
	<?= $mensaje ?>
	<div class="card p-4">
		<?= secureform($accion) ?>
			<div class="grid">
				<div class="row">
					<div class="cell-md-6"><?= $texto ?></div>
					<div class="cell-md-6"><?= $anidado ?></div>
				</div>
				<div class="row">
					<div class="cell-md-4"><?= $numero ?></div>
					<div class="cell-md-4"><?= $fecha ?></div>
					<div class="cell-md-4"><?= $casilla ?></div>
				</div>
			</div>
			<button class="button primary secureop" value="guardar">Guardar</button>
			<a class="button" href="datatable.php">Ver todos</a>
		</form>
	</div>

	<h4>Documento en MongoDB</h4>
	<?= docCode(json_encode(mongoToArray($m->{$config['sitedb']}->exampledata->findOne(['_id' => new MongoDB\BSON\ObjectId($dataset->_id)]) ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'json') ?>

	<h3>Código</h3>
	<?= docCode(<<<'PHP'
$dataset = new dataset([
    'collection' => $m->{$config['sitedb']}->exampledata,
    '_id' => $_GET['_id'],
    'nameprefix' => 'data',
]);
$texto = new inputText(['dataset' => &$dataset, 'field' => 'text', 'caption' => 'Texto', 'required' => true]);
$anidado = new inputText(['dataset' => &$dataset, 'field' => 'array.text2']);   // notación con punto
$fecha = new inputDate(['dataset' => &$dataset, 'field' => 'date']);

$accion = '/docs/databinding.php?_id=' . $dataset->_id;
if (($_POST['op'] ?? '') === 'guardar' && csrfValidate()) {
    $errores = $dataset->save();
}
?>
<?= secureform($accion) ?>       <!-- con acción: POST normal (sin AJAX) -->
    <?= $texto ?> <?= $anidado ?> <?= $fecha ?>
    <button class="button primary secureop" value="guardar">Guardar</button>
</form>
PHP) ?>

	<h3>Otras formas de usar <code>dataset</code></h3>
	<?= docCode(<<<'PHP'
// Leer y escribir campos directamente (cada asignación hace un updateOne con upsert).
echo $dataset->text;
$dataset->estado = 'revisado';
unset($dataset->temporal);

// Historial: guarda la versión anterior en nfversions cada vez que cambia el documento.
$dataset = new dataset(['collection' => $coleccion, '_id' => $id, 'nameprefix' => 'data', 'historic' => true]);

// _id de texto (p.ej. un RFC o un folio) en lugar de ObjectId.
$dataset = new dataset(['collection' => $coleccion, '_id' => 'XAXX010101000', 'simpleid' => true, 'nameprefix' => 'data']);

// Dentro de una transacción (requiere replica set).
$sesion = $m->startSession();
$sesion->startTransaction();
$dataset->mongo_session = $sesion;
$errores = $dataset->save();
$errores === false ? $sesion->commitTransaction() : $sesion->abortTransaction();
PHP) ?>
	<p>La versión AJAX de este mismo formulario está en <a href="databindingajax.php">Databinding AJAX</a>.</p>
</div>
