<?php
require 'common.php';

// Ejemplos reales: lo que guarda el editor de Admin y el PHP que genera su botón «Código PHP».
$ejemplo = [
	['field' => 'nombre', 'type' => 'inputText', 'short_description' => 'Nombre', 'required' => true, 'options' => ['uppercase' => true]],
	['field' => 'ciudad', 'type' => 'inputText', 'short_description' => 'Ciudad', 'suggest' => ['mode' => 'self']],
	['field' => 'cliente', 'type' => 'Select', 'short_description' => 'Cliente', 'required' => true,
		'ajax' => ['collection' => 'clientes', 'label' => 'nombre, rfc', 'value' => '_id', 'match' => '{"activo": true}']],
	['field' => 'estado', 'type' => 'Select', 'short_description' => 'Estado', 'items' => "pendiente=Pendiente\npagado=Pagado\ncancelado=Cancelado"],
	['field' => 'importe', 'type' => 'inputNumber', 'short_description' => 'Importe', 'options' => ['min' => 0, 'step' => 0.01, 'prepend' => '$']],
	['field' => 'partidas', 'type' => 'embededArray', 'short_description' => 'Partidas', 'subfields' => [
		['field' => 'producto', 'type' => 'inputText', 'short_description' => 'Producto', 'required' => true],
		['field' => 'cantidad', 'type' => 'inputNumber', 'short_description' => 'Cantidad'],
		['field' => 'unidad', 'type' => 'Select', 'short_description' => 'Unidad', 'items' => "pza=Pieza\nkg=Kilo"],
	]],
];
$errores = [];
$campos = nfTableSchema::normalizeFields($ejemplo, $errores, false, true);
$porCampo = array_column($campos, null, 'field');
?>
<div class="container">
	<?= docHeader('Tablas administrables (nftables)', 'En <a href="/admin/nftables/">Admin → nftables</a> se describe una colección de MongoDB (campos, tipos y opciones) y el framework publica en <code>/nftables/&lt;colección&gt;/</code> una lista con filtros, alta, edición, borrado e importación, sin escribir código. Pueden entrar los grupos <code>admins</code> y <code>nftables</code>. Toda la interpretación de los campos vive en <code>nfTableSchema</code> (<code>includes/class.nfTableSchema.php</code>).') ?>

	<h3>Cómo se usa</h3>
	<ol>
		<li>Admin → nftables → <b>Nueva</b>. Escriba la colección (sugiere las que ya existen), el nombre en singular y plural.</li>
		<li><b>Detectar campos</b> lee una muestra de documentos y propone los campos con su tipo: textos, números, fechas, casillas, subdocumentos como <code>direccion.cp</code>, textos que se repiten como lista de opciones y arreglos de subdocumentos como lista embebida. Revise etiquetas y tipos.</li>
		<li>Edite cada campo: tipo (cualquier componente del framework de la tabla de abajo), etiqueta, ayuda, requerido, si se muestra como columna y las propiedades del componente.</li>
		<li>Guarde. El servidor valida todo con las mismas reglas con que se dibuja; si algo no es válido no se guarda y se explica por qué.</li>
	</ol>

	<h3>Tipos y propiedades</h3>
	<p>La tabla sale de <code>nfTableSchema::TYPES</code>: solo esas propiedades se pueden fijar desde Admin (las demás, como eventos JS o extensiones PHP, no).</p>
	<table class="table compact striped">
		<thead><tr><th>Clase</th><th>En el editor</th><th>Propiedades</th><th>Columna</th><th>En lista embebida</th></tr></thead>
		<tbody>
			<?php foreach (nfTableSchema::TYPES as $clase => $def) { ?>
				<tr>
					<td><code><?= htmlspecialchars($clase) ?></code></td>
					<td><?= htmlspecialchars($def['label']) ?><?= !empty($def['source']) ? ' <span class="text-muted">(' . htmlspecialchars(['items' => 'lista fija', 'items+ajax' => 'lista fija o colección', 'suggest' => 'autocompletar'][$def['source']]) . ')</span>' : '' ?></td>
					<td><?= htmlspecialchars(implode(', ', array_keys($def['options']))) ?></td>
					<td><?= !empty($def['column']) ? 'sí' : 'no' ?></td>
					<td><?= !empty($def['sub']) ? 'sí' : '' ?></td>
				</tr>
			<?php } ?>
		</tbody>
	</table>

	<h3>Select AJAX: opciones desde otra colección</h3>
	<p>En un campo <b>Lista de opciones</b> elija «Buscar en una colección». Se configura la colección, los campos que se muestran (varios se unen con « · »), el que se guarda (<code>_id</code> por omisión, que se guarda como ObjectId), dónde buscar, un filtro fijo en JSON y cuántos resultados traer. El botón <b>Probar</b> muestra los primeros resultados. En la tabla la columna muestra la etiqueta (un <code>$lookup</code>) y se puede buscar y filtrar por ella.</p>
	<?= docCode(nfTableSchema::toPhp($porCampo['cliente'], 'pedidos')) ?>
	<p class="remark info">No se aceptan las colecciones de sesiones ni campos con nombres como <code>password</code>, <code>token</code> o <code>hash</code>. El filtro pasa por <code>nfSanitizeMongoQuery()</code> (sin <code>$where</code>, <code>$expr</code>...). Ver <a href="inputsajax.php">Select con AJAX</a>.</p>

	<h3>Autocompletar texto libre</h3>
	<p>En un campo <b>Texto</b>, «Autocompletar» ofrece: lo que ya se capturó en ese mismo campo (valores distintos de la colección), los valores de un campo de otra colección, o una lista fija. El usuario puede escribir cualquier cosa; ver <a href="autocomplete.php">Autocompletar</a>.</p>
	<?= docCode(nfTableSchema::toPhp($porCampo['ciudad'], 'pedidos')) ?>

	<h3>Datos embebidos</h3>
	<p><b>Lista de subdocumentos</b> edita un arreglo dentro del documento (<code>partidas: [{producto, cantidad, unidad}, ...]</code>) con un diálogo, igual que <a href="arraylist.php">embededArray</a>. Sus subcampos usan los tipos marcados en la última columna de la tabla de arriba (un solo nivel, nombres sin punto y que no se repitan con otros controles de la página). Los subdocumentos de un solo objeto se capturan con punto en el nombre: <code>direccion.calle</code>.</p>
	<?= docCode(nfTableSchema::toPhp($porCampo['partidas'], 'pedidos')) ?>

	<h3>Archivos y firmas</h3>
	<p>Los tipos Archivo, Varios archivos y Firma guardan en <code>$config['nftables_files']</code> (carpeta fuera del document root, en <code>includes/config.php</code>), en <code>{carpeta}/{colección}/{_id}/{campo}/</code>. Sin esa clave esos campos se muestran con un aviso.</p>

	<h3>El documento en <code>nftables</code></h3>
	<p>Es lo que guarda el editor; también se puede crear desde código (por ejemplo en un instalador). Al leerlo, <code>nfTableSchema::normalizeFields()</code> acepta los documentos viejos (tipos en minúsculas como <code>inputtext</code>, <code>required: "1"</code>) y <a href="databinding.php"><code>$dataset-&gt;tonfTables()</code></a> registra los controles de una página.</p>
	<?= docCode(json_encode(['nfcollection' => 'pedidos', 'singular' => 'Pedido', 'plural' => 'Pedidos', 'nfdescription' => 'Pedidos de venta', 'nffields' => $campos], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'json') ?>

	<h3>nfTableSchema desde PHP</h3>
	<table class="table compact">
		<tbody>
			<tr><td><code>normalizeFields($nffields, &amp;$errores, $sub, $estricto)</code></td><td>Valida y normaliza la lista; con <code>$estricto</code> descarta lo inválido y anota el motivo.</td></tr>
			<tr><td><code>build($campo, $dataset, $contexto)</code></td><td>Crea el componente ligado al <code>dataset</code> (una lista embebida devuelve lista y diálogo).</td></tr>
			<tr><td><code>componentOptions($campo, $contexto)</code></td><td>Opciones del constructor; <code>$contexto</code> = <code>db</code>, <code>collection</code>, <code>_id</code>, <code>files</code>.</td></tr>
			<tr><td><code>columns()</code>, <code>pipeline()</code>, <code>filters()</code></td><td>Columnas (celdas escapadas), etapas extra (<code>$lookup</code> de etiquetas, subcampos) y filtros de <a href="tablef.php">TableF</a>.</td></tr>
			<tr><td><code>inferFields($docs)</code></td><td>Propone campos a partir de documentos (botón «Detectar campos»).</td></tr>
			<tr><td><code>toPhp($campo, $colección)</code></td><td>PHP equivalente para usar el mismo control en una página propia (botón «Código PHP»).</td></tr>
		</tbody>
	</table>
</div>
