<?php
if (empty($_GET['_id']) || !preg_match('/^[a-f\d]{24}$/i', $_GET['_id'])) {
	header('Location: ?_id=' . new MongoDB\BSON\ObjectId());
	exit();
}
require 'common.php';

/**
 * Tipo BSON y valor en Extended JSON (relaxed) de un valor PHP, tal como quedaría en MongoDB.
 */
function bsonFormato($valor): array
{
	$tipo = match (true) {
		$valor === null => 'null',
		is_bool($valor) => 'bool',
		is_int($valor) => 'int',
		is_float($valor) => 'double',
		is_string($valor) => 'string',
		$valor instanceof MongoDB\BSON\UTCDateTime => 'date',
		$valor instanceof MongoDB\BSON\ObjectId => 'objectId',
		$valor instanceof MongoDB\BSON\Decimal128 => 'decimal',
		is_array($valor) && array_is_list($valor) => 'array',
		default => 'object',
	};
	$json = MongoDB\BSON\Document::fromPHP(['v' => $valor])->toRelaxedExtendedJSON();
	return [$tipo, substr($json, 7, -2)];   // quita el envoltorio { "v" : ... }
}

/**
 * Documento guardado en Extended JSON con sangría (los tipos se ven como $date, $oid...).
 */
function bsonDocumento($collection, $id): string
{
	$doc = $collection->findOne(['_id' => $id], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
	if (!$doc) {
		return '(todavía no hay documento: llene el formulario y guarde)';
	}
	$json = json_decode(MongoDB\BSON\Document::fromPHP($doc)->toRelaxedExtendedJSON());
	return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$tz = new DateTimeZone('America/Mexico_City');
$coleccion = $m->{$config['sitedb']}->exampledata;
$dataset = new dataset([
	'collection' => $coleccion,
	'_id' => $_GET['_id'],
	'nameprefix' => 'data',
]);

// Un control por formato. El nombre del campo indica el tipo con que se guarda.
$texto = new inputText(['dataset' => &$dataset, 'field' => 'texto', 'caption' => 'Texto → string']);
$entero = new inputNumber(['dataset' => &$dataset, 'field' => 'entero', 'caption' => 'Entero → int', 'validate' => 'integer']);
$decimal = new inputNumber(['dataset' => &$dataset, 'field' => 'decimal', 'caption' => 'Decimal → double', 'validate' => 'float', 'step' => '0.01']);
$cantidad = new inputSpinner(['dataset' => &$dataset, 'field' => 'cantidad', 'caption' => 'Spinner → int', 'validate' => 'integer']);
$fecha = new inputDate(['dataset' => &$dataset, 'field' => 'fecha', 'caption' => 'Fecha → date', 'timezone' => $tz]);
$fechaTexto = new inputDate(['dataset' => &$dataset, 'field' => 'fecha_texto', 'caption' => 'Fecha → string', 'storagetype' => inputDate::ST_STRING]);
$hora = new inputTime(['dataset' => &$dataset, 'field' => 'hora', 'caption' => 'Hora → date']);
$cita = new inputDateTime(['dataset' => &$dataset, 'field' => 'cita', 'caption' => 'Fecha y hora → date', 'timezone' => $tz]);
$activo = new inputCheckBox(['dataset' => &$dataset, 'field' => 'activo', 'caption' => 'bool']);
$activoEntero = new inputCheckBox(['dataset' => &$dataset, 'field' => 'activo_int', 'caption' => 'int 1/0', 'storeagetype' => inputCheckBox::ST_INTEGER]);
$activoTexto = new inputCheckBox(['dataset' => &$dataset, 'field' => 'activo_texto', 'caption' => "'true'/'false'", 'storeagetype' => inputCheckBox::ST_STRING]);
$activoPropio = new inputCheckBox(['dataset' => &$dataset, 'field' => 'activo_sn', 'caption' => "'S'/'N'", 'storeagetype' => inputCheckBox::ST_CUSTOM, 'customtrue' => 'S', 'customfalse' => 'N']);
$permisos = new inputCheckBoxs(['dataset' => &$dataset, 'field' => 'permisos', 'caption' => 'Casillas → objeto {clave: true}', 'options' => ['leer' => 'Leer', 'escribir' => 'Escribir', 'borrar' => 'Borrar']]);
$estado = new Select(['dataset' => &$dataset, 'field' => 'estado', 'caption' => 'Select → string', 'options' => ['' => '', 'abierto' => 'Abierto', 'cerrado' => 'Cerrado']]);
// Opciones con _id de ejemplo; en un sitio real vendrían de una colección (o de 'ajax').
$clientes = ['' => '', '65a000000000000000000001' => 'Cliente A', '65a000000000000000000002' => 'Cliente B'];
$cliente = new Select(['dataset' => &$dataset, 'field' => 'cliente', 'caption' => 'Select mongoid → objectId', 'format' => Select::formatMongoID, 'options' => $clientes]);
$contactos = new Select(['dataset' => &$dataset, 'field' => 'contactos', 'caption' => 'Select múltiple mongoid → [objectId]', 'format' => Select::formatMongoID, 'multiple' => true, 'options' => $clientes]);
$ubicacion = new mapmarker(['dataset' => &$dataset, 'field' => 'ubicacion', 'type' => mapmarker::GeoJSON, 'height' => 250]);

if ($nframework->isAjax()) {
	if (!csrfValidate()) {
		$result = ['error' => 'Formulario caducado, recargue la página.'];
	} elseif (($_POST['op'] ?? '') === 'save') {
		$errores = $dataset->save();
		$result = [
			'error' => $errores,
			'ids' => ['documento' => htmlspecialchars(bsonDocumento($coleccion, toMongoId($dataset->_id)))],
		];
	}
	return;
}

// Conversión que hace cada control: lo que llega del navegador (texto) → lo que se guarda.
$conversiones = [
	"(new inputText([]))->__toMongo('Hola')",
	"(new inputNumber(['validate' => 'integer']))->__toMongo('42')",
	"(new inputNumber(['validate' => 'float']))->__toMongo('1,234.50')",
	"(new inputNumber([]))->__toMongo('')",
	"(new inputSpinner(['validate' => 'integer']))->__toMongo('3')",
	"(new inputDate([]))->__toMongo('2026-06-03')",
	"(new inputDate(['timezone' => new DateTimeZone('America/Mexico_City')]))->__toMongo('2026-06-03')",
	"(new inputDate(['storagetype' => inputDate::ST_STRING]))->__toMongo('2026-06-03')",
	"(new inputTime([]))->__toMongo('14:30')",
	"(new inputDateTime(['timezone' => new DateTimeZone('America/Mexico_City')]))->__toMongo('2026-06-03T14:30')",
	"(new inputCheckBox([]))->__toMongo('1')",
	"(new inputCheckBox([]))->__toMongo(null)",
	"(new inputCheckBox(['storeagetype' => inputCheckBox::ST_INTEGER]))->__toMongo('1')",
	"(new inputCheckBox(['storeagetype' => inputCheckBox::ST_STRING]))->__toMongo(null)",
	"(new inputCheckBox(['storeagetype' => inputCheckBox::ST_CUSTOM, 'customtrue' => 'S', 'customfalse' => 'N']))->__toMongo('1')",
	"(new inputCheckBoxs([]))->__toMongo(['leer' => 'on', 'borrar' => 'on'])",
	"(new Select([]))->__toMongo('abierto')",
	"(new Select(['format' => Select::formatMongoID]))->__toMongo('65a000000000000000000001')",
	"(new Select(['format' => Select::formatMongoID, 'multiple' => true]))->__toMongo(['65a000000000000000000001', '65a000000000000000000002'])",
	"(new mapmarker(['type' => mapmarker::GeoJSON]))->__toMongo(['lat' => '25.4332803', 'lng' => '-100.9604797'])",
	"(new mapmarker([]))->__toMongo(['lat' => '25.4332803', 'lng' => '-100.9604797'])",
];
$tabla = '';
foreach ($conversiones as $codigo) {
	[$tipo, $valor] = bsonFormato(eval('return ' . $codigo . ';'));
	$tabla .= '<tr><td><code>' . htmlspecialchars($codigo) . '</code></td><td><span class="badge inline">' . $tipo
		. '</span></td><td><code>' . htmlspecialchars($valor) . '</code></td></tr>';
}
?>
<div class="container">
	<?= docHeader('Formatos en MongoDB', 'Al guardar con <a href="databinding.php">dataset</a>, cada control convierte el texto que envía el navegador al tipo BSON adecuado (<code>__toMongo()</code>) y al leer lo regresa al formato del input (<code>__toPHP()</code>). Así se puede ordenar, sumar y filtrar por rango directamente en MongoDB.') ?>

	<h3>Qué guarda cada control</h3>
	<p>Resultado real de <code>__toMongo()</code> con el valor que llegaría en <code>$_POST</code>. La última columna es el valor en Extended JSON, como lo muestra <code>mongosh</code> o Compass.</p>
	<div class="table-component">
		<table class="table striped compact">
			<thead><tr><th>Llamada</th><th>Tipo BSON</th><th>Se guarda</th></tr></thead>
			<tbody><?= $tabla ?></tbody>
		</table>
	</div>
	<ul>
		<li><code>inputNumber</code>/<code>inputSpinner</code>: con <code>validate</code> <code>integer</code> o <code>digits</code> se guarda <code>int</code>; con cualquier otro (<code>float</code>, <code>number</code>) <code>double</code>. Acepta separador de miles.</li>
		<li>Las fechas se interpretan en <code>timezone</code> (UTC si no se indica) y se guardan como instante UTC; al leer se muestran en la misma zona. <code>inputTime</code> guarda la hora (UTC) sobre la fecha del día en que se guardó: al leer solo se usa <code>H:i</code>.</li>
		<li><code>Select</code> con <code>format => Select::formatMongoID</code> convierte a <code>ObjectId</code> solo los valores de 24 hexadecimales; los demás quedan como texto.</li>
		<li><code>mapmarker</code> con <code>type => mapmarker::GeoJSON</code> guarda un <code>Point</code> con <code>[lng, lat]</code> como <code>double</code>, listo para un índice <code>2dsphere</code>.</li>
		<li>Fíjese en el nombre de la propiedad de <code>inputCheckBox</code>: <code>storeagetype</code> (con <em>e</em>), a diferencia de <code>storagetype</code> en las fechas.</li>
	</ul>

	<h3>Pruébelo: formulario y documento guardado</h3>
	<p>Guarde el formulario y a la derecha verá el documento tal como quedó en la colección <code>exampledata</code>, en Extended JSON: <code>$date</code> es una fecha, <code>$oid</code> un ObjectId, <code>42</code> un entero y <code>1234.5</code> (o <code>1234.0</code>) un double.</p>
	<div class="grid">
		<div class="row">
			<div class="cell-lg-7">
				<div class="card p-4">
					<?= secureform() ?>
						<div class="grid">
							<div class="row">
								<div class="cell-md-6"><?= $texto ?></div>
								<div class="cell-md-6"><?= $estado ?></div>
							</div>
							<div class="row">
								<div class="cell-md-4"><?= $entero ?></div>
								<div class="cell-md-4"><?= $decimal ?></div>
								<div class="cell-md-4"><?= $cantidad ?></div>
							</div>
							<div class="row">
								<div class="cell-md-6"><?= $fecha ?></div>
								<div class="cell-md-6"><?= $fechaTexto ?></div>
							</div>
							<div class="row">
								<div class="cell-md-6"><?= $hora ?></div>
								<div class="cell-md-6"><?= $cita ?></div>
							</div>
							<div class="row">
								<div class="cell-md-3"><?= $activo ?></div>
								<div class="cell-md-3"><?= $activoEntero ?></div>
								<div class="cell-md-3"><?= $activoTexto ?></div>
								<div class="cell-md-3"><?= $activoPropio ?></div>
							</div>
							<div class="row">
								<div class="cell"><?= $permisos ?></div>
							</div>
							<div class="row">
								<div class="cell-md-6"><?= $cliente ?></div>
								<div class="cell-md-6"><?= $contactos ?></div>
							</div>
							<div class="row">
								<div class="cell"><label>Ubicación → GeoJSON Point</label><?= $ubicacion ?></div>
							</div>
							<div class="row">
								<div class="cell-md-4 offset-md-8"><button class="button success secureop w-100" value="save"><span class="mif-floppy-disk"></span> Guardar</button></div>
							</div>
						</div>
					</form>
				</div>
			</div>
			<div class="cell-lg-5">
				<pre class="stay-on"><code id="documento"><?= htmlspecialchars(bsonDocumento($coleccion, toMongoId($dataset->_id))) ?></code></pre>
			</div>
		</div>
	</div>

	<h3>Campos vacíos y notación con punto</h3>
	<ul>
		<li>Si ningún campo del formulario usa punto, <code>save()</code> escribe el documento completo con <code>$set</code>: un número o fecha vacíos quedan en <code>null</code>, un texto vacío en <code>''</code> y una casilla sin marcar en su valor falso (<code>false</code>, <code>0</code>, <code>'false'</code> o <code>customfalse</code>).</li>
		<li>Si algún campo usa punto (<code>'field' => 'direccion.cp'</code>), solo se envían los campos del formulario: los que llegan con valor van en <code>$set</code> (convertidos) y los vacíos en <code>$unset</code>, es decir, se eliminan del documento.</li>
	</ul>

	<h3>Guardar a mano con el tipo correcto</h3>
	<p>Cuando el valor no pasa por un control (cálculos, APIs, rutas), conviértalo usted antes de escribir. Nunca guarde números o fechas como texto si va a ordenar, sumar o filtrar por ellos.</p>
	<?= docCode(<<<'PHP'
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\Decimal128;

$col = $m->{$config['sitedb']}->pedidos;
$cantidad = is_string($_POST['cantidad'] ?? null) ? $_POST['cantidad'] : '';
$cliente = is_string($_POST['cliente'] ?? null) && isValidObjectId($_POST['cliente']) ? $_POST['cliente'] : null;
$etiquetas = array_filter((array) ($_POST['etiquetas'] ?? []), fn($v) => is_string($v) && isValidObjectId($v));

$col->updateOne(['_id' => toMongoId($_GET['_id'])], ['$set' => [
	'cantidad'  => (int) $cantidad,                          // int
	'precio'    => nfnumbertomongo('1,234.50', 'float'),     // double 1234.5 (mismo criterio que inputNumber)
	'total'     => new Decimal128('1234.50'),                // decimal exacto, recomendado para dinero
	'cliente'   => $cliente ? toMongoId($cliente) : null,    // ObjectId (toMongoId lanza excepción si no es válido)
	'etiquetas' => toMongoIds(array_values($etiquetas)),     // [ObjectId, ...]
	'creado'    => new UTCDateTime(),                        // fecha y hora actuales
	'entrega'   => readableToMongoDate('2026-06-03'),        // texto → UTCDateTime (zona horaria de PHP)
	'pagado'    => filter_var($_POST['pagado'] ?? false, FILTER_VALIDATE_BOOLEAN),   // bool
	'direccion' => ['calle' => 'Juárez', 'cp' => '25000'],   // subdocumento; el CP es texto (ceros a la izquierda)
]]);

// Con dataset, asignar una propiedad hace updateOne inmediato con el valor tal cual (ya convertido):
$dataset->revisado = new UTCDateTime();
$dataset->intentos = ($dataset->intentos ?? 0) + 1;
PHP) ?>

	<h3>Leer y consultar por tipo</h3>
	<?= docCode(<<<'PHP'
$doc = $m->{$config['sitedb']}->exampledata->findOne(['_id' => toMongoId($id)]);

echo mongoDateToReadable($doc['fecha'], 'd/m/Y');     // UTCDateTime → texto (en UTC)
echo $doc['fecha']->toDateTime()->setTimezone(new DateTimeZone('America/Mexico_City'))->format('d/m/Y H:i');
echo (string) $doc['cliente'];                        // ObjectId → '65a0...'
echo number_format($doc['decimal'], 2);               // double
echo (string) $doc['total'];                          // Decimal128 → '1234.50'

// Rangos y orden funcionan porque el tipo es nativo:
$col->find(['entero' => ['$gte' => 10]], ['sort' => ['fecha' => -1]]);
$col->find(['cita' => ['$gte' => new MongoDB\BSON\UTCDateTime(strtotime('2026-06-01') * 1000)]]);
$col->find(['ubicacion' => ['$near' => ['$geometry' => ['type' => 'Point', 'coordinates' => [-100.96, 25.43]], '$maxDistance' => 5000]]]);

// Encontrar documentos que quedaron con el tipo equivocado (p. ej. números guardados como texto):
$col->find(['entero' => ['$type' => 'string']]);
PHP) ?>

	<?= docSource(__FILE__) ?>
</div>
