<?php
$noobfuscate = true;
require 'common.php';

$cliente = new Efirma([
	'name' => 'efirma_cliente',
	'caption' => 'e.firma o CSD guardada en este navegador',
	'tipo' => 'any',
	'storage' => 'client',
	'onSelect' => '$("#selCliente").text(JSON.stringify(info));',
]);
$servidor = new Efirma([
	'name' => 'efirma_servidor',
	'caption' => 'e.firma guardada en el servidor',
	'tipo' => 'fiel',
	'storage' => 'server',
	'onSelect' => '$("#selServidor").text(JSON.stringify(info));',
]);

$javas->addjs(<<<'JS'
$("#firmarCliente").click(function () {
	nfEfirma["efirma_cliente"].sign("cadena de prueba").then(function (b64) {
		$("#firmaCliente").text(b64);
	}, function (msg) { Swal.fire({icon: "error", text: String(msg)}); });
});
JS, 'ready');
?>
<div class="container">
	<?= docHeader('e.firma y CSD', '<code>Efirma</code> registra y selecciona certificados del SAT. Con <code>tipo</code> se pide e.firma (<code>fiel</code>), <code>csd</code> o cualquiera (<code>any</code>); con <code>storage</code> se elige si la llave privada se queda en el navegador (<code>client</code>) o se guarda cifrada en el servidor (<code>server</code>), en cuyo caso se exige una firma autógrafa con <code>Signature_pad</code>.') ?>

	<div class="grid">
		<div class="row">
			<div class="cell-md-6">
				<?= $cliente ?>
				<pre id="selCliente"></pre>
				<button type="button" class="button primary" id="firmarCliente">Firmar "cadena de prueba"</button>
				<pre id="firmaCliente" style="white-space:pre-wrap;word-break:break-all"></pre>
			</div>
			<div class="cell-md-6">
				<?php if ($user->isLoggedIn()) { ?>
					<?= $servidor ?>
					<pre id="selServidor"></pre>
				<?php } else { ?>
					<p>Inicie sesión para ver el modo servidor.</p>
				<?php } ?>
			</div>
		</div>
	</div>

	<h3>Código</h3>
	<?= docCode(<<<'PHP'
$efirma = new Efirma([
    'name' => 'efirma',          // input oculto: "client:<nocert>" o "server:<_id>"
    'tipo' => 'fiel',            // 'fiel' | 'csd' | 'any'
    'storage' => 'client',       // 'client' | 'server'
    'rfc' => '',                 // opcional: solo acepta este RFC
    'value' => 'server:' . $doc['efirma'],   // opcional: selección inicial
    'onSelect' => 'console.log(info);',
]);
echo $efirma;

// Cliente: firmar en el navegador (devuelve base64). alg por defecto SHA256withRSA.
// nfEfirma["efirma"].sign(cadena, "SHA1withRSA").then(b64 => ...);
// nfEfirma["efirma"].certificate();   // PEM del certificado seleccionado

// Servidor: firmar desde PHP (páginas o workers CLI).
[$source, $ref] = Efirma::parseValue($_POST['efirma']);
if ($source === 'server') {
    $firma = Efirma::sign($ref, $cadena, OPENSSL_ALGO_SHA256, $user->_id);   // binaria
    $credential = Efirma::credential($ref, $user->_id);                      // phpcfdi Credential
}
PHP) ?>

	<h3>Opciones</h3>
	<table class="table striped compact">
		<thead><tr><th>Opción</th><th>Descripción</th></tr></thead>
		<tbody>
			<tr><td><code>tipo</code></td><td><code>fiel</code>, <code>csd</code> o <code>any</code>. Se distingue por el OU del certificado (solo los CSD lo tienen).</td></tr>
			<tr><td><code>storage</code></td><td><code>client</code>: localStorage (clave <code>llaves</code>, compatible con <code>/sat/sing.php</code>). <code>server</code>: colección <code>efirmas</code>, cifrada con <code>efirma_key</code>; requiere sesión.</td></tr>
			<tr><td><code>value</code></td><td>Selección inicial (<code>client:&lt;nocert&gt;</code> o <code>server:&lt;_id&gt;</code>).</td></tr>
			<tr><td><code>rfc</code></td><td>Restringe a un RFC.</td></tr>
			<tr><td><code>consent</code></td><td>Texto que el usuario firma al guardar en el servidor.</td></tr>
			<tr><td><code>onSelect</code></td><td>JavaScript con <code>info</code> = {value, source, nocert, rfc, nombre, sucursal, tipo, vence}.</td></tr>
		</tbody>
	</table>
	<p>Las llaves del servidor nunca firman datos que mande el navegador: solo <code>Efirma::sign()</code> / <code>Efirma::credential()</code> desde PHP.</p>
</div>
