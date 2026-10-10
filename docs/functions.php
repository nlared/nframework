<?php
require 'common.php';

// Cada ejemplo se evalúa y se muestra con su resultado real.
$grupos = [
	'MongoDB' => [
		["toMongoId('64f000000000000000000001')", 'Convierte texto a ObjectId (lanza excepción si no es válido).'],
		["toMongoIds(['64f000000000000000000001', '64f000000000000000000002'])", 'Varios a la vez, p.ej. para un $in.'],
		["isValidObjectId('64f000000000000000000001') && !isValidObjectId('abc')", 'Valide antes de convertir datos del usuario.'],
		["mongoDateToReadable(new MongoDB\\BSON\\UTCDateTime(strtotime('2026-06-03 14:30 UTC') * 1000), 'd/m/Y H:i')", 'UTCDateTime a texto.'],
		["readableToMongoDate('2026-06-03 14:30:00')", 'Texto a UTCDateTime (en la zona horaria del sitio).'],
		["flattenDocument(['cliente' => ['nombre' => 'Ana', 'domicilio' => ['cp' => '25000']]])", 'Aplana un documento a notación con punto.'],
		["matchesQuery(['edad' => 30, 'ciudad' => 'Saltillo'], ['edad' => ['\$gte' => 18], 'ciudad' => ['\$in' => ['Saltillo', 'Torreón']]])", 'Evalúa un filtro estilo MongoDB contra un arreglo PHP.'],
	],
	'Arreglos' => [
		["(function () { \$a = []; assignArrayByPath(\$a, 'nivel1.nivel2', 'valor'); return \$a; })()", 'Asigna un valor por ruta con puntos.'],
		["getNestedValue(['a' => ['b' => ['c' => 5]]], 'a.b.c')", 'Lee un valor por ruta con puntos (null si no existe).'],
		["hasNestedKey(['a' => ['b' => 1]], 'a.x')", '¿Existe la ruta?'],
		["array_diff_recursive(['a' => 1, 'b' => ['c' => 2, 'd' => 3]], ['a' => 1, 'b' => ['c' => 2]])", 'Diferencias entre dos arreglos anidados.'],
		["ifset(['a' => 1], 'b')", 'Valor o null, sin aviso de índice inexistente.'],
		["mongoToArray(new MongoDB\\Model\\BSONDocument(['x' => 1, 'lista' => new MongoDB\\Model\\BSONArray([1, 2])]))", 'Documento de MongoDB a arreglo PHP.'],
	],
	'Archivos y texto' => [
		["clean_filename('Factura #12 (copia).pdf')", 'Deja solo letras, números, punto, guion y guion bajo.'],
		["formatSize(1536000)", 'Bytes legibles.'],
		["formatSize(GetDirectorySize(__DIR__))", 'Tamaño total de una carpeta (recursivo).'],
		["remove_trailing_separator('/var/www/datos/')", 'Quita la barra final.'],
	],
	'Sitio' => [
		["nfSiteHost()", 'Dominio público del sitio (de $config[\'url\'], no de la cabecera Host).'],
		["nfSafeRedirect('https://otro-sitio.com/')", 'Ver <a href="security.php">Seguridad</a>.'],
		["csrfToken('/docs/functions.php') !== ''", 'Token CSRF para una acción; secureform() lo usa.'],
	],
];

$html = '';
foreach ($grupos as $grupo => $ejemplos) {
	$filas = '';
	foreach ($ejemplos as [$codigo, $descripcion]) {
		$valor = eval('return ' . $codigo . ';');
		$texto = $valor instanceof MongoDB\BSON\UTCDateTime ? 'UTCDateTime(' . $valor->toDateTime()->format('c') . ')' : var_export($valor, true);
		$filas .= '<tr><td>' . docCode($codigo) . '<div class="text-small text-muted">' . $descripcion . '</div></td><td>' . docCode($texto) . '</td></tr>';
	}
	$html .= '<h3>' . $grupo . '</h3><table class="table compact"><thead><tr><th style="width:60%">Llamada</th><th>Resultado</th></tr></thead><tbody>' . $filas . '</tbody></table>';
}
?>
<div class="container">
	<?= docHeader('Funciones', 'Funciones auxiliares de <code>includes/functions.php</code> e <code>include.php</code>, con el resultado de ejecutarlas ahora mismo.') ?>
	<?= $html ?>

	<h3>Variables de sesión con caducidad</h3>
	<?= docCode(<<<'PHP'
$_SESSION['exportacion'][$id] = $parametros;
addVarToGarbage('exportacion\\' . $id, time() + 3600);   // se borra sola en una hora
removeVarFromGarbage('exportacion\\' . $id);             // cancelar la caducidad
PHP) ?>
	<p>El framework la usa para las consultas de tablas y selects AJAX guardadas en la sesión. La ruta usa <code>\</code> como separador.</p>

	<h3>Menús</h3>
	<?= docCode(<<<'PHP'
echo nfMetroMenu('_nav');                  // menú guardado en Admin → Menús, como <ul class="h-menu">
echo nfMetroMenu('lateral', 'v-menu');
PHP) ?>
</div>
