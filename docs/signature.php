<?php
if (empty($_GET['_id']) || !preg_match('/^[a-f\d]{24}$/i', $_GET['_id'])) {
	header('Location: ?_id=' . new MongoDB\BSON\ObjectId());
	exit();
}
$noobfuscate = true;
require 'common.php';

// 1) Firma sola: guarda el PNG y nada más.
$firma = new Signature_pad([
	'name' => 'firma1',
	'path' => __DIR__ . '/tmp/firma.png',       // donde /nframework/imageup.php guarda la imagen
	'backgroundColor' => 'rgb(255,255,255)',
	'penColor' => '#00264d',
	'onSuccess' => '$("#firmaGuardada").attr("src", "/docs/tmp/firma.png?t=" + Date.now()).removeClass("d-none");',
]);

// 2) Firma dentro de un formulario: su botón Guardar también guarda el formulario.
$recibo = new dataset([
	'collection' => $m->{$config['sitedb']}->demo_firmas,
	'_id' => $_GET['_id'],
	'nameprefix' => 'data',
]);
$nombre = new inputText(['dataset' => &$recibo, 'field' => 'nombre', 'caption' => 'Nombre de quien recibe', 'required' => true]);
$firmaRecibo = new Signature_pad([
	'dataset' => &$recibo,
	'field' => 'firma',                                         // registra archivo, fecha, usuario y sha256
	'caption' => 'Firma',
	'path' => __DIR__ . '/tmp/firmas/' . $recibo->_id . '.png',
	'backgroundColor' => 'rgb(255,255,255)',
	'extension' => __DIR__ . '/signature_extension.php',        // evento del servidor
	'onsave' => 'firmaGuardada',
	'submit' => 'guardar',                                      // después envía el formulario con op=guardar
]);

if ($nframework->isAjax()) {
	if (!csrfValidate()) {
		$result = ['error' => 'Formulario caducado, recargue la página.'];
	} elseif (($_POST['op'] ?? '') === 'guardar') {
		$errores = $recibo->save();
		$recibo->refresh();
		$result = ['error' => $errores, 'ids' => ['documento' => htmlspecialchars(json_encode(mongoToArray($recibo->info), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))]];
	}
	return;
}

// Botones extra de la firma 1 (deshacer, rehacer, descargar) y registro de eventos.
$javas->addjs(<<<'JS'
var firmaDeshecha = [];
function descargarFirma(tipo, nombre) {
	if (signaturePad["firma1"].isEmpty()) { alert("Primero firme."); return; }
	var a = document.createElement("a");
	a.href = signaturePad["firma1"].toDataURL(tipo);
	a.download = nombre;
	a.click();
}
$("[data-firma=undo]").click(function () {
	var datos = signaturePad["firma1"].toData();
	if (datos.length) { firmaDeshecha.push(datos.pop()); signaturePad["firma1"].fromData(datos); }
});
$("[data-firma=redo]").click(function () {
	if (firmaDeshecha.length) {
		var datos = signaturePad["firma1"].toData();
		datos.push(firmaDeshecha.pop());
		signaturePad["firma1"].fromData(datos);
	}
});
$("[data-firma=png]").click(function () { descargarFirma("image/png", "firma.png"); });
$("[data-firma=svg]").click(function () { descargarFirma("image/svg+xml", "firma.svg"); });

// Eventos de todas las firmas de la página: burbujean hasta document.
$(document).on("signature:beforesave signature:saved signature:error signature:clear", function (e) {
	var d = e.originalEvent.detail || {}, info = d.onresult ? JSON.stringify(d.onresult) : (d.message || "");
	$("#eventosFirma").prepend($("<div>").text(new Date().toLocaleTimeString() + "  " + e.target.id + "  " + e.type + "  " + info));
});
JS, 'ready');

$guardado = mongoToArray($recibo->info);
?>
<div class="container">
	<?= docHeader('Firma', '<code>Signature_pad</code> dibuja un lienzo para firmar con el dedo o el mouse. Su botón Guardar envía la imagen PNG a <code>/nframework/imageup.php</code>, que la escribe en <code>path</code> solo si es una imagen válida, y lanza eventos en el navegador y en el servidor.') ?>

	<h3>Firma sola</h3>
	<div class="grid">
		<div class="row">
			<div class="cell-md-7">
				<?= $firma ?>
				<div class="mt-2">
					<button type="button" class="button" data-firma="undo"><span class="mif-undo"></span> Deshacer</button>
					<button type="button" class="button" data-firma="redo"><span class="mif-redo"></span> Rehacer</button>
					<button type="button" class="button" data-firma="png">Descargar PNG</button>
					<button type="button" class="button" data-firma="svg">Descargar SVG</button>
				</div>
			</div>
			<div class="cell-md-5">
				<h5>Última firma guardada</h5>
				<img id="firmaGuardada" class="border bd-default <?= is_file(__DIR__ . '/tmp/firma.png') ? '' : 'd-none' ?>" style="max-width:100%" src="/docs/tmp/firma.png?t=<?= time() ?>" alt="Firma">
			</div>
		</div>
	</div>
	<?= docCode(<<<'PHP'
$firma = new Signature_pad([
    'name' => 'firma1',
    'path' => __DIR__ . '/tmp/firma.png',
    'backgroundColor' => 'rgb(255,255,255)',   // por defecto transparente
    'penColor' => '#00264d',
    'onSuccess' => '$("#firmaGuardada").attr("src", "/docs/tmp/firma.png?t=" + Date.now());',
]);
echo $firma;
// El objeto JavaScript queda en signaturePad["firma1"] (API de signature_pad).
PHP) ?>

	<h3>Firma que guarda el formulario</h3>
	<p>Este formulario <strong>no tiene botón de guardar</strong>: el de la firma sube la imagen, ejecuta <code>onsave</code> en el servidor, registra la firma en el documento y luego envía el formulario con <code>op=guardar</code>.</p>
	<div class="grid">
		<div class="row">
			<div class="cell-md-7">
				<div class="card p-4">
					<?= secureform() ?>
						<?= $nombre ?>
						<div class="mt-4"><?= $firmaRecibo ?></div>
					</form>
				</div>
				<h5 class="mt-4">Eventos</h5>
				<pre id="eventosFirma" class="border bd-default p-2" style="font-size:12px; min-height:60px; max-height:200px; overflow:auto"></pre>
			</div>
			<div class="cell-md-5">
				<h5>Documento en <code>demo_firmas</code></h5>
				<pre id="documento" class="border bd-default p-2" style="font-size:12px; white-space:pre-wrap"><?= htmlspecialchars(json_encode($guardado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
			</div>
		</div>
	</div>
	<?= docCode(<<<'PHP'
$firma = new Signature_pad([
    'dataset' => &$recibo,
    'field' => 'firma',              // {archivo, fecha, usuario, sha256}; dataset->save() no lo sobrescribe
    'path' => __DIR__ . '/tmp/firmas/' . $recibo->_id . '.png',
    'extension' => __DIR__ . '/signature_extension.php',
    'onsave' => 'firmaGuardada',     // firmaGuardada(string $path, array $conf): lo que devuelva llega a onresult
    'submit' => 'guardar',           // luego envía el formulario contenedor con op=guardar
]);

// En el navegador, para cualquier firma de la página:
$javas->addjs('$(document).on("signature:saved", function (e) {
    console.log(e.target.id, e.originalEvent.detail.onresult);
});', 'ready');
PHP) ?>
	<h5>signature_extension.php</h5>
	<?= docCode(file_get_contents(__DIR__ . '/signature_extension.php')) ?>

	<h3>Eventos</h3>
	<table class="table striped compact">
		<thead><tr><th>Dónde</th><th>Evento</th><th>Cuándo</th></tr></thead>
		<tbody>
			<tr><td>Navegador</td><td><code>signature:beforesave</code></td><td>Antes de enviar la imagen. <code>event.preventDefault()</code> cancela el guardado (p. ej. si falta un dato del formulario).</td></tr>
			<tr><td>Navegador</td><td><code>signature:saved</code></td><td>El servidor la guardó. <code>event.detail</code> es la respuesta: <code>onresult</code> (lo que devolvió <code>onsave</code>) y <code>fecha</code>.</td></tr>
			<tr><td>Navegador</td><td><code>signature:error</code></td><td>No se pudo guardar; <code>event.detail.message</code> trae el motivo.</td></tr>
			<tr><td>Navegador</td><td><code>signature:clear</code></td><td>Se pulsó Limpiar.</td></tr>
			<tr><td>Servidor</td><td><code>onsave($path, $conf)</code></td><td>Después de escribir el PNG (y de registrarlo en el documento si hay <code>dataset</code>). Si lanza una excepción, su mensaje llega a <code>signature:error</code>.</td></tr>
		</tbody>
	</table>
	<p>Los eventos se lanzan sobre <code>#&lt;id&gt;_signature</code> y burbujean: escúchelos en el contenedor o en <code>document</code>. Con jQuery, los datos están en <code>e.originalEvent.detail</code>.</p>

	<h3>Opciones</h3>
	<table class="table striped compact">
		<thead><tr><th>Opción</th><th>Descripción</th></tr></thead>
		<tbody>
			<tr><td><code>path</code></td><td>Ruta del PNG en el servidor (la carpeta se crea si no existe). Se guarda en la sesión: el navegador nunca la ve.</td></tr>
			<tr><td><code>dataset</code>, <code>field</code></td><td>Al guardar, escribe en el documento <code>{archivo, fecha, usuario, sha256}</code>. El <code>save()</code> del formulario no toca ese campo.</td></tr>
			<tr><td><code>extension</code>, <code>onsave</code></td><td>Archivo PHP y nombre de la función que el servidor llama después de guardar.</td></tr>
			<tr><td><code>submit</code></td><td>Valor de <code>op</code> con que se envía el formulario (de <code>secureform()</code>) que contiene a la firma, después de guardarla. Pasa por la validación del formulario como un botón <code>.secureop</code>.</td></tr>
			<tr><td><code>onSuccess</code>, <code>onError</code>, <code>onEmptyAlert</code></td><td>JavaScript al guardar (variable <code>result</code>), al fallar (variable <code>message</code>) o al intentar guardar sin firma.</td></tr>
			<tr><td><code>penColor</code>, <code>backgroundColor</code></td><td>Colores del trazo y del fondo.</td></tr>
			<tr><td><code>minWidth</code>, <code>maxWidth</code></td><td>Grosor del trazo (0.5 a 2.5 por defecto).</td></tr>
			<tr><td><code>width</code>, <code>height</code>, <code>caption</code></td><td>Tamaño del lienzo en píxeles (400×200) y etiqueta.</td></tr>
		</tbody>
	</table>
</div>
