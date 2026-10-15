<?php
require 'common.php';

// Base SQLite de ejemplo; en un sitio real sería new PDO('mysql:host=...;dbname=...;charset=utf8mb4', $usuario, $clave).
$pdo = new PDO('sqlite:' . __DIR__ . '/tmp/datasetpdo.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE IF NOT EXISTS contactos (id TEXT PRIMARY KEY, nombre TEXT, correo TEXT, edad INTEGER, alta TEXT, activo INTEGER)');

// El id llega en la URL; sin él se crea uno nuevo (igual que con dataset y ObjectId).
$id = is_string($_GET['id'] ?? null) && preg_match('/^[a-z0-9]{1,20}$/', $_GET['id']) ? $_GET['id'] : '';
if ($id === '' && !$nframework->isAjax()) {
	header('Location: ?id=' . bin2hex(random_bytes(6)));
	exit();
}

$contacto = new datasetpdo([
	'pdo' => $pdo,
	'table' => 'contactos',
	'key' => 'id',
	'_id' => $id,
	'nameprefix' => 'data',
]);
$nombre = new inputText(['dataset' => &$contacto, 'field' => 'nombre', 'caption' => 'Nombre', 'required' => true]);
$correo = new inputText(['dataset' => &$contacto, 'field' => 'correo', 'caption' => 'Correo', 'type' => 'email']);
$edad = new inputNumber(['dataset' => &$contacto, 'field' => 'edad', 'caption' => 'Edad', 'validate' => 'integer']);
$alta = new inputDate(['dataset' => &$contacto, 'field' => 'alta', 'caption' => 'Alta', 'storagetype' => inputDate::ST_STRING]);
$activo = new inputCheckBox(['dataset' => &$contacto, 'field' => 'activo', 'caption' => 'Activo']);

if ($nframework->isAjax()) {
	if (!csrfValidate()) {
		$result = ['error' => 'Formulario caducado, recargue la página.'];
	} elseif (($_POST['op'] ?? '') === 'save') {
		$result = ['error' => $contacto->save(), 'ids' => ['guardadoEn' => date('H:i:s')]];
	}
	return;
}

$filas = '';
foreach ($pdo->query('SELECT * FROM contactos ORDER BY rowid DESC LIMIT 10') as $fila) {
	$filas .= '<tr><td><a href="?id=' . urlencode($fila['id']) . '">' . htmlspecialchars($fila['id']) . '</a></td>';
	foreach (['nombre', 'correo', 'edad', 'alta', 'activo'] as $col) {
		$filas .= '<td>' . htmlspecialchars(var_export($fila[$col], true)) . '</td>';
	}
	$filas .= '</tr>';
}
?>
<div class="container">
	<?= docHeader('Databinding con SQL (datasetpdo)', '<code>datasetpdo</code> es el equivalente de <a href="databinding.php">dataset</a> para una tabla SQL (MySQL, PostgreSQL, SQLite…) por PDO: los mismos controles, ligados a las columnas de un registro.') ?>

	<div class="card p-4">
		<?= secureform() ?>
			<div class="grid">
				<div class="row">
					<div class="cell-md-6"><?= $nombre ?></div>
					<div class="cell-md-6"><?= $correo ?></div>
				</div>
				<div class="row">
					<div class="cell-md-4"><?= $edad ?></div>
					<div class="cell-md-4"><?= $alta ?></div>
					<div class="cell-md-4 pt-6"><?= $activo ?></div>
				</div>
				<div class="row">
					<div class="cell-md-8">Último guardado: <span id="guardadoEn">—</span> · <a href="?">Nuevo registro</a></div>
					<div class="cell-md-4"><button class="button success secureop w-100" value="save"><span class="mif-floppy-disk"></span> Guardar</button></div>
				</div>
			</div>
		</form>
	</div>

	<h3>Registros en la tabla <code>contactos</code></h3>
	<p>Valores tal como quedaron en la base (recargue después de guardar):</p>
	<table class="table striped compact">
		<thead><tr><th>id</th><th>nombre</th><th>correo</th><th>edad</th><th>alta</th><th>activo</th></tr></thead>
		<tbody><?= $filas ?></tbody>
	</table>

	<h3>Opciones</h3>
	<table class="table striped compact">
		<thead><tr><th>Opción</th><th>Descripción</th></tr></thead>
		<tbody>
			<tr><td><code>pdo</code></td><td>Conexión <code>PDO</code>. Use <code>PDO::ERRMODE_EXCEPTION</code> para que un error de SQL no pase desapercibido.</td></tr>
			<tr><td><code>table</code>, <code>key</code></td><td>Tabla y columna llave. Solo se aceptan nombres <code>[A-Za-z_][A-Za-z0-9_]*</code>: los identificadores no pueden ir como parámetros.</td></tr>
			<tr><td><code>_id</code></td><td>Valor de la llave. Si el registro no existe, <code>save()</code> hace <code>INSERT</code>; si existe, <code>UPDATE</code>.</td></tr>
			<tr><td><code>nameprefix</code></td><td>Los campos viajan como <code>data[columna]</code>.</td></tr>
		</tbody>
	</table>

	<h3>Diferencias con <code>dataset</code></h3>
	<ul>
		<li>Guarda el valor <strong>tal como llega del formulario</strong>: no llama a <code>__toMongo()</code>. Una fecha queda como texto <code>2026-06-03</code> (use <code>storagetype => inputDate::ST_STRING</code> para que el control la muestre bien al leerla), una casilla marcada como <code>'1'</code> y sin marcar como <code>NULL</code>. La conversión de tipos queda a cargo de la columna.</li>
		<li>Todas las consultas son preparadas (<code>?</code>): los valores nunca se concatenan en el SQL.</li>
		<li>No hay notación con punto, <code>historic</code>, transacciones ni asignación directa (<code>$dataset->campo = ...</code>).</li>
		<li>Cada <code>save()</code> escribe todas las columnas de los controles excepto la llave.</li>
	</ul>

	<?= docSource(__FILE__) ?>
</div>
