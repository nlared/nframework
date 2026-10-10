<?php
require 'common.php';

$campos = [
	'nombre' => new inputText(['name' => 'nombre', 'caption' => 'Nombre (required)', 'required' => true]),
	'correo' => new inputText(['name' => 'correo', 'caption' => 'Correo (validate=email)', 'validate' => 'email']),
	'rfc' => new inputText(['name' => 'rfc', 'caption' => 'RFC (pattern)', 'pattern' => '^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$', 'uppercase' => true, 'placeholder' => 'XAXX010101000']),
	'edad' => new inputNumber(['name' => 'edad', 'caption' => 'Edad (validate=integer)', 'validate' => 'integer', 'min' => 0, 'max' => 120]),
	'cita' => new inputDate(['name' => 'cita', 'caption' => 'Fecha (formato Y-m-d)']),
	'hora' => new inputTime(['name' => 'hora', 'caption' => 'Hora (HH:MM)']),
];

if ($nframework->isAjax()) {
	// Validación en el servidor: nunca confíe solo en la del navegador.
	$errores = [];
	foreach ($campos as $nombre => $campo) {
		if (!$campo->is_valid($_POST[$nombre] ?? null)) {
			$errores[] = htmlspecialchars($campo->caption);
		}
	}
	$result = $errores
		? ['error' => 'Revise: ' . implode(', ', $errores)]
		: ['error' => false, 'ids' => ['resultado' => '<span class="fg-green">Todos los campos son válidos.</span>']];
	return;
}
?>
<div class="container">
	<?= docHeader('Validaciones', 'Cada control valida dos veces: en el navegador (Metro UI, atributo <code>data-validate</code>) y en el servidor con <code>is_valid()</code>, que es lo que usa <code>dataset->save()</code> antes de guardar.') ?>

	<h3>Pruébelo</h3>
	<p>«Validar» revisa primero en el navegador. «Enviar sin validar» manda los datos directo al servidor para ver qué rechaza <code>is_valid()</code>.</p>
	<div class="card p-4">
		<?= secureform('', false, 'formValidacion') ?>
			<div class="grid">
				<div class="row">
					<?php foreach ($campos as $campo) { ?>
						<div class="cell-md-6"><?= $campo ?></div>
					<?php } ?>
				</div>
			</div>
			<button class="button primary secureop" value="validar">Validar</button>
			<button type="button" class="button" id="sinCliente">Enviar sin validar</button>
		</form>
		<div id="resultado" class="mt-3"></div>
	</div>
	<script>
		// Envía el formulario sin pasar por el validador de Metro UI; la respuesta se procesa igual.
		$('#sinCliente').on('click', function () {
			var form = $('#formValidacion');
			form.find('input[name="op"]').val('validar');
			$.post(location.pathname, form.serialize(), nAjaxFormDone, 'json');
		});
	</script>

	<h3>Reglas</h3>
	<table class="table striped compact">
		<thead><tr><th>Opción</th><th>Navegador</th><th>Servidor (<code>is_valid</code>)</th></tr></thead>
		<tbody>
			<tr><td><code>'required' => true</code></td><td>Sí</td><td>Sí</td></tr>
			<tr><td><code>'pattern' => '^...$'</code></td><td>Sí</td><td>Sí</td></tr>
			<tr><td><code>'validate' => 'email'</code></td><td>Sí</td><td>Sí</td></tr>
			<tr><td><code>'validate' => 'number'</code> / <code>'integer'</code> / <code>'float'</code></td><td>Sí</td><td>Sí</td></tr>
			<tr><td><code>'validate' => 'minlength=5'</code> (y otras reglas de Metro UI)</td><td>Sí</td><td>No: valide en su código</td></tr>
			<tr><td><code>inputDate</code>, <code>inputTime</code></td><td>Formato del control</td><td><code>Y-m-d</code> / <code>HH:MM</code></td></tr>
			<tr><td><code>select</code>, <code>inputCheckboxs</code></td><td>—</td><td>No comprueba que el valor esté en <code>options</code>: hágalo con <code>array_key_exists()</code> si importa.</td></tr>
		</tbody>
	</table>
	<p>Varias reglas se separan con espacios: <code>'validate' => 'required email'</code>.</p>

	<h3>Con databinding</h3>
	<p><code>dataset->save()</code> valida todos los controles ligados y devuelve <code>false</code> si guardó, o un texto con los campos inválidos. Ese texto puede ir directo a <code>$result['error']</code>:</p>
	<?= docCode(<<<'PHP'
if ($nframework->isAjax() && ($_POST['op'] ?? '') === 'save') {
    $errores = $dataset->save();               // false = guardado
    if ($errores === false && mb_strlen($_POST['data']['descripcion'] ?? '') < 20) {
        $errores = 'La descripción debe tener al menos 20 caracteres';   // regla propia
    }
    $result = ['error' => $errores];
}
PHP) ?>
</div>
