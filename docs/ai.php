<?php
require 'common.php';
$aiCatalog = nfAI::catalog();
try {
	$aiDefault = nfAI::provider();
} catch (RuntimeException $e) {
	$aiDefault = null;
}
?>
<div class="container">
	<?= docHeader('Proveedores de IA', '<code>nfAI</code> llama al proveedor de IA que el administrador configuró en <a href="/admin/ai/">Admin → IA</a> y le pide una respuesta JSON con un esquema. Lo usa <a href="tablefia.php">TablefIA</a> y sirve para cualquier extracción estructurada: Anthropic, OpenAI, Gemini o modelos locales con la misma llamada.') ?>

	<?php if ($aiDefault) { ?>
		<div class="remark success">Proveedor predeterminado de este sitio: <b><?= htmlspecialchars($aiDefault['name']) ?></b> (<code><?= htmlspecialchars($aiDefault['id']) ?></code>, modelo <code><?= htmlspecialchars($aiDefault['model']) ?></code>).</div>
	<?php } else { ?>
		<div class="remark warning">Este sitio no tiene proveedores de IA: agréguelos en <a href="/admin/ai/">Admin → IA</a>.</div>
	<?php } ?>

	<h3>Configuraciones disponibles</h3>
	<table class="table compact striped">
		<thead><tr><th>Configuración</th><th>Tipo de API</th><th>URL base</th><th>Llave</th></tr></thead>
		<tbody>
		<?php foreach ($aiCatalog as $key => $c) { ?>
			<tr><td><b><?= htmlspecialchars($c['label']) ?></b><br><small class="fg-gray"><?= htmlspecialchars($c['help']) ?></small></td>
				<td><code><?= htmlspecialchars($c['type']) ?></code></td>
				<td><code><?= htmlspecialchars($c['base_url'] ?: '(la de su servidor)') ?></code></td>
				<td><?= ['required' => 'obligatoria', 'optional' => 'opcional', 'none' => 'no'][$c['key']] ?></td></tr>
		<?php } ?>
		</tbody>
	</table>
	<p>Anthropic se llama con el SDK oficial (salida estructurada, <code>effort</code>, caché del prompt de sistema y respaldo del servidor cuando el modelo declina). OpenAI y los compatibles usan <code>/chat/completions</code>; Gemini, <code>generateContent</code>. Cada proveedor tiene su modo de respuesta JSON:</p>
	<ul>
		<li><b>Esquema estricto</b> (<code>schema</code>): el servidor garantiza el formato (<code>response_format: json_schema</code>, <code>responseJsonSchema</code>).</li>
		<li><b>Objeto JSON libre</b> (<code>object</code>): el servidor garantiza JSON y el esquema va en las instrucciones (DeepSeek, servidores sin esquemas).</li>
		<li><b>Solo instrucciones</b> (<code>prompt</code>): para modelos locales sin modo JSON; la respuesta se extrae del texto (también de un bloque <code>```json</code>).</li>
	</ul>
	<p>En los tres casos el código que recibe la respuesta debe validarla: TablefIA lo hace con la colección y el pipeline.</p>

	<h3>Uso</h3>
	<?= docCode(<<<'PHP'
$p = nfAI::provider();            // predeterminado; nfAI::provider('ollama') = uno por su id
$r = nfAI::json($p,
    'Extrae los datos de la factura. Los importes en pesos.',      // instrucciones
    $textoFactura,                                                  // mensaje
    [                                                               // JSON Schema de la respuesta
        'type' => 'object',
        'properties' => [
            'rfc' => ['type' => 'string'],
            'total' => ['type' => 'number'],
            'conceptos' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['rfc', 'total', 'conceptos'],                // todos: lo exige el modo estricto de OpenAI
        'additionalProperties' => false,
    ],
    ['max_tokens' => 4000]        // también model, effort (sustituyen los del proveedor) y name
);
$r['data']['rfc'];                // respuesta decodificada
$r['usage'];                      // ['input' => …, 'output' => …, 'cache' => …]
$r['model'];                      // modelo que respondió
PHP) ?>
	<p>Lanza <code>RuntimeException</code> con un mensaje para el usuario si no hay proveedor, la llave no es válida, el servicio está saturado, el modelo declina o la respuesta no es JSON. TablefIA elige el proveedor con <code>'provider' =&gt; 'id'</code> (por defecto el predeterminado).</p>

	<h3>Llaves</h3>
	<p>Las llaves escritas en Admin → IA se guardan en <code>configs</code> cifradas con AES-256-GCM, con <code>ai_key</code> de <code>includes/config.php</code> (32 bytes en base64; sin ella el panel no acepta llaves). Así un respaldo de la base no basta para usarlas. Si se pierde o cambia <code>ai_key</code>, hay que volver a escribirlas. También puede escribir <code>env:NOMBRE_VARIABLE</code> para leerla del entorno de PHP-FPM sin guardarla.</p>
	<?= docCode(<<<'PHP'
// includes/config.php, bloque del dominio
'ai_key' => '…',     // php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
PHP) ?>
	<p>Sin proveedores configurados se usa, como antes, <code>anthropic_api_key</code> de <code>config.php</code> (o <code>ANTHROPIC_API_KEY</code>) con <code>claude-opus-5-5</code>.</p>

	<h3>Datos que salen del servidor</h3>
	<p>Lo que se envía al proveedor (en TablefIA: nombres de campos, valores de muestra y la pregunta) sale del servidor hacia ese servicio. Para datos que no deben salir use un modelo local (Ollama, LM Studio, vLLM) en el mismo servidor o en la red interna.</p>
</div>
