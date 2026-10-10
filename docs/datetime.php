<?php
require_once 'common.php';

$examples = [
	['title' => 'inputDate', 'desc' => 'Formato <code>Y-m-d</code>. Por defecto se guarda en MongoDB como <code>UTCDateTime</code>, lo que permite ordenar y filtrar por rango.'],
	['code' => "new inputDate(['name' => 'fecha1', 'caption' => 'Fecha']);"],
	['code' => "new inputDate(['name' => 'fecha2', 'caption' => 'Con valor', 'value' => '2026-06-03']);"],
	['code' => "new inputDate(['name' => 'fecha3', 'caption' => 'Obligatoria', 'required' => true]);"],
	['code' => "new inputDate(['name' => 'fecha4', 'caption' => 'Rango permitido', 'min' => date('Y-m-d'), 'max' => date('Y-m-d', strtotime('+30 days'))]);",
		'desc' => '<code>min</code> y <code>max</code> pasan como atributos HTML del input.'],
	['code' => "new inputDate(['name' => 'fecha5', 'caption' => 'Guardar como texto', 'storagetype' => inputDate::ST_STRING]);",
		'desc' => 'Con <code>ST_STRING</code> se guarda el texto <code>2026-06-03</code> tal cual.'],

	['title' => 'inputTime'],
	['code' => "new inputTime(['name' => 'hora1', 'caption' => 'Hora']);"],
	['code' => "new inputTime(['name' => 'hora2', 'caption' => 'Con valor', 'value' => '14:30']);"],

	['title' => 'inputDateTime'],
	['code' => "new inputDateTime(['name' => 'fechahora1', 'caption' => 'Fecha y hora']);"],
	['code' => "new inputDateTime(['name' => 'fechahora2', 'caption' => 'Con prepend y valor', 'prepend' => 'Programar', 'value' => '2026-06-03T14:30']);"],
];

// Cómo se convierte lo que llega del navegador al guardarlo con dataset->save().
$conversiones = [
	"(new inputDate([]))->__toMongo('2026-06-03')" => (new inputDate([]))->__toMongo('2026-06-03'),
	"(new inputDate(['storagetype' => inputDate::ST_STRING]))->__toMongo('2026-06-03')" => (new inputDate(['storagetype' => inputDate::ST_STRING]))->__toMongo('2026-06-03'),
	"(new inputTime([]))->__toMongo('14:30')" => (new inputTime([]))->__toMongo('14:30'),
	"(new inputDate([]))->__toPHP(new MongoDB\\BSON\\UTCDateTime(strtotime('2026-06-03 UTC') * 1000))" => (new inputDate([]))->__toPHP(new MongoDB\BSON\UTCDateTime(strtotime('2026-06-03 UTC') * 1000)),
];
$tabla = '';
foreach ($conversiones as $codigo => $valor) {
	$tabla .= '<tr><td><code>' . htmlspecialchars($codigo) . '</code></td><td><code>'
		. htmlspecialchars($valor instanceof MongoDB\BSON\UTCDateTime ? 'UTCDateTime(' . $valor->toDateTime()->format('c') . ')' : var_export($valor, true))
		. '</code></td></tr>';
}
?>
<div class="container">
	<?= docHeader('Fechas y horas', 'Los controles de fecha convierten entre el texto del navegador y <code>MongoDB\BSON\UTCDateTime</code> automáticamente cuando se usan con <a href="databinding.php">dataset</a>.') ?>
	<?= docExamples($examples) ?>

	<h3>Conversión al guardar y al leer</h3>
	<table class="table striped compact">
		<thead><tr><th>Llamada</th><th>Resultado</th></tr></thead>
		<tbody><?= $tabla ?></tbody>
	</table>

	<h3>Consultar por rango</h3>
	<?= docCode(<<<'PHP'
// Documentos con fecha en junio de 2026 (campo guardado con inputDate).
$desde = new MongoDB\BSON\UTCDateTime(strtotime('2026-06-01 UTC') * 1000);
$hasta = new MongoDB\BSON\UTCDateTime(strtotime('2026-07-01 UTC') * 1000);
$docs = $m->{$config['sitedb']}->exampledata->find(['date' => ['$gte' => $desde, '$lt' => $hasta]]);

// Mostrar una fecha guardada en la zona horaria del sitio:
echo mongoDateToReadable($doc['date'], 'd/m/Y');
PHP) ?>
</div>
