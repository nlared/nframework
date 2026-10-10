<?php
$noobfuscate = true;
require 'common.php';

$firma = new Signature_pad([
	'name' => 'firma1',
	'path' => __DIR__ . '/tmp/firma.png',       // donde /nframework/imageup.php guarda la imagen
	'backgroundColor' => 'rgb(255,255,255)',
	'penColor' => '#00264d',
	'onSuccess' => '$("#firmaGuardada").attr("src", "/docs/tmp/firma.png?t=" + Date.now()).removeClass("d-none");',
]);

// Botones extra: deshacer, rehacer y descargar sin pasar por el servidor.
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
JS, 'ready');
?>
<div class="container">
	<?= docHeader('Firma', '<code>Signature_pad</code> dibuja un lienzo para firmar con el dedo o el mouse. El botón de guardar envía la imagen PNG a <code>/nframework/imageup.php</code>, que la escribe en <code>path</code> solo si es una imagen válida.') ?>

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

	<h3>Código</h3>
	<?= docCode(<<<'PHP'
$firma = new Signature_pad([
    'name' => 'firma1',
    'path' => __DIR__ . '/tmp/firma.png',
    'backgroundColor' => 'rgb(255,255,255)',   // por defecto transparente
    'penColor' => '#00264d',
    'onSuccess' => '$("#firmaGuardada").attr("src", "/docs/tmp/firma.png?t=" + Date.now());',
]);
echo $firma;

// El objeto JavaScript queda en signaturePad["firma1"] (API de signature_pad):
$javas->addjs('$("#deshacer").click(function () {
    var datos = signaturePad["firma1"].toData();
    datos.pop();
    signaturePad["firma1"].fromData(datos);
});', 'ready');
PHP) ?>

	<h3>Opciones</h3>
	<table class="table striped compact">
		<thead><tr><th>Opción</th><th>Descripción</th></tr></thead>
		<tbody>
			<tr><td><code>path</code></td><td>Ruta del PNG en el servidor. Se guarda en la sesión: el navegador nunca la ve.</td></tr>
			<tr><td><code>penColor</code>, <code>backgroundColor</code></td><td>Colores del trazo y del fondo.</td></tr>
			<tr><td><code>minWidth</code>, <code>maxWidth</code></td><td>Grosor del trazo (0.5 a 2.5 por defecto).</td></tr>
			<tr><td><code>onSuccess</code>, <code>onError</code>, <code>onEmptyAlert</code></td><td>JavaScript al guardar, al fallar o al intentar guardar sin firma.</td></tr>
		</tbody>
	</table>
</div>
