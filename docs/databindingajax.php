<?php
if (empty($_GET['_id']) || !preg_match('/^[a-f\d]{24}$/i', $_GET['_id'])) {
	header('Location: ?_id=' . new MongoDB\BSON\ObjectId());
	exit();
}
require 'common.php';

$dataset = new dataset([
	'collection' => $m->{$config['sitedb']}->exampledata,
	'_id' => $_GET['_id'],
	'simpleid' => false,
	'nameprefix' => 'data',
]);

$texto = new inputText(['dataset' => &$dataset, 'field' => 'text', 'caption' => 'Texto', 'required' => true]);
$numero = new inputNumber(['dataset' => &$dataset, 'field' => 'number', 'caption' => 'Número']);
$fecha = new inputDate(['dataset' => &$dataset, 'field' => 'date', 'caption' => 'Fecha', 'prepend' => 'Día']);
$fechaHora = new inputDateTime(['dataset' => &$dataset, 'field' => 'datetime', 'caption' => 'Fecha y hora']);
$casilla = new inputCheckbox(['dataset' => &$dataset, 'field' => 'checkbox', 'caption' => 'Activo']);
$etiquetas = new inputCheckboxs(['dataset' => &$dataset, 'field' => 'checkboxs', 'caption' => 'Etiquetas', 'options' => ['urgente' => 'Urgente', 'revisar' => 'Revisar']]);
$prioridad = new inputRadios(['dataset' => &$dataset, 'field' => 'radios1', 'caption' => 'Prioridad', 'options' => ['baja' => 'Baja', 'alta' => 'Alta']]);
$notas = new textarea(['dataset' => &$dataset, 'field' => 'textarea', 'caption' => 'Notas']);

if ($nframework->isAjax()) {
	if (!csrfValidate()) {
		$result = ['error' => 'Formulario caducado, recargue la página.'];
	} elseif (($_POST['op'] ?? '') === 'save') {
		try {
			$errores = $dataset->save();
			$result = [
				'error' => $errores,                                       // false = éxito
				'ids' => ['guardadoEn' => date('H:i:s')],                  // actualiza #guardadoEn
			];
		} catch (Exception $e) {
			$result = ['error' => $e->getMessage()];
		}
	}
	return;
}
?>
<div class="container">
	<?= docHeader('Databinding AJAX', 'El mismo patrón que <a href="databinding.php">Databinding</a>, pero el formulario se guarda sin recargar: <code>secureform()</code> envía por AJAX y <code>$result</code> es la respuesta.') ?>
	<div class="card p-4">
		<?= secureform() ?>
			<div class="grid">
				<div class="row">
					<div class="cell-md-6"><?= $texto ?></div>
					<div class="cell-md-6"><?= $numero ?></div>
				</div>
				<div class="row">
					<div class="cell-md-6"><?= $fecha ?></div>
					<div class="cell-md-6"><?= $fechaHora ?></div>
				</div>
				<div class="row">
					<div class="cell-md-4"><?= $casilla ?></div>
					<div class="cell-md-4"><?= $etiquetas ?></div>
					<div class="cell-md-4"><?= $prioridad ?></div>
				</div>
				<div class="row">
					<div class="cell"><?= $notas ?></div>
				</div>
				<div class="row">
					<div class="cell">Último guardado: <span id="guardadoEn">—</span></div>
				</div>
				<div class="row">
					<div class="cell-md-2 offset-md-8"><a href="datatableajax.php" class="button w-100"><span class="mif-exit"></span> Cerrar</a></div>
					<div class="cell-md-2"><button class="button success secureop w-100" value="save"><span class="mif-floppy-disk"></span> Guardar</button></div>
				</div>
			</div>
		</form>
	</div>
	<?= docSource(__FILE__) ?>
</div>
