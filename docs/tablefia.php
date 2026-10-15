<?php
require 'common.php';

docDemoVentas();

$consultas = new TablefIA([
	'id' => 'demoia',
	'uso' => 'docs.tablefia',
	'collections' => [
		'demo_pedidos' => [
			'description' => 'Pedidos de venta',
			'fields' => [
				'folio' => 'int, número de pedido',
				'cliente' => 'objectId, _id del cliente',
				'fecha' => 'date, fecha del pedido',
				'importe' => 'double, total en pesos',
				'estado' => "string: 'pagado', 'pendiente' o 'cancelado'",
				'productos' => 'int, cantidad de productos',
			],
		],
		'demo_clientes' => ['description' => 'Clientes'],   // campos tomados de una muestra de documentos
	],
	'relations' => [
		['from' => 'demo_pedidos', 'localField' => 'cliente', 'to' => 'demo_clientes', 'foreignField' => '_id', 'as' => 'cliente'],
	],
	'instructions' => 'Los importes son en pesos mexicanos. Los pedidos cancelados no cuentan como venta salvo que se pida.',
]);
?>
<div class="container">
	<?= docHeader('Consultas con IA (TablefIA)', 'Como <code>TableF</code>, pero el usuario escribe la pregunta en español y Claude genera el pipeline de MongoDB. El servidor lo valida, lo ejecuta y guarda la consulta en <code>nftablesfia</code> para reutilizarla sin volver a llamar a la IA.') ?>

	<?php if (!empty($config['anthropic_api_key']) || getenv('ANTHROPIC_API_KEY')) { ?>
		<div class="remark success">La llave de la API de Anthropic está configurada. Cada pregunta nueva consume tokens; reutilizar una consulta guardada no.</div>
	<?php } else { ?>
		<div class="remark warning">Falta la llave de la API: agregue <code>'anthropic_api_key'</code> al bloque de este dominio en <code>includes/config.php</code> (o defina la variable de entorno <code>ANTHROPIC_API_KEY</code>).</div>
	<?php } ?>

	<h3>Ejemplo</h3>
	<p>Pruebe con: <em>«ventas pagadas por ciudad este año»</em>, <em>«los 3 clientes con más pedidos pendientes»</em> o <em>«importe promedio por mes de los últimos 6 meses»</em>.</p>
	<div class="card p-4"><?= $consultas ?></div>

	<h3>Uso</h3>
	<?= docCode(<<<'PHP'
echo new TablefIA([
    'id' => 'ventasia',                    // id del componente en la página
    'uso' => 'reportes.ventas',            // agrupa las consultas guardadas en nftablesfia
    'collections' => [
        'pedidos' => [
            'description' => 'Pedidos de venta',
            'fields' => ['importe' => 'double, total en pesos', 'estado' => "'pagado' o 'pendiente'"],   // opcional
            'match' => ['empresa' => toMongoId($empresaId)],   // filtro obligatorio, antes de todo
        ],
        'clientes' => ['description' => 'Clientes', 'hidden' => ['notas_internas']],   // campos que la IA no ve ni puede leer
        'productos',                                                                    // solo el nombre
    ],
    'relations' => [   // los únicos $lookup permitidos (en ambos sentidos)
        ['from' => 'pedidos', 'localField' => 'cliente', 'to' => 'clientes', 'foreignField' => '_id', 'as' => 'cliente'],
        ['from' => 'pedidos', 'localField' => 'producto', 'to' => 'productos', 'foreignField' => '_id',
         'description' => 'producto principal del pedido'],
    ],
    'instructions' => 'Los importes son en pesos. "Venta" significa estado pagado.',
]);
PHP) ?>

	<h3>Opciones</h3>
	<table class="table striped compact">
		<thead><tr><th>Opción</th><th>Descripción</th></tr></thead>
		<tbody>
			<tr><td><code>id</code></td><td>Id del componente; varios en una página necesitan ids distintos.</td></tr>
			<tr><td><code>uso</code></td><td>Identifica dónde se usa: el listado muestra solo las consultas de este uso. Por defecto, la ruta de la página + <code>#id</code>.</td></tr>
			<tr><td><code>collections</code></td><td>Colecciones que la IA puede consultar. Por colección: <code>description</code>; <code>fields</code> (<code>['campo' => 'tipo y significado']</code>; si se omite, se leen de una muestra de 50 documentos, en caché 1 hora); <code>match</code> (filtro que se aplica siempre, p. ej. por empresa o usuario); <code>hidden</code> (campos que se eliminan antes de cualquier etapa).</td></tr>
			<tr><td><code>relations</code></td><td>Relaciones entre colecciones: <code>from</code>.<code>localField</code> → <code>to</code>.<code>foreignField</code>, con <code>as</code> y <code>description</code> opcionales. Son los únicos <code>$lookup</code> que se aceptan; el lado unido también lleva su <code>match</code> y <code>hidden</code>.</td></tr>
			<tr><td><code>instructions</code></td><td>Contexto del negocio para la IA (significado de estados, moneda, reglas).</td></tr>
			<tr><td><code>db</code></td><td>Base de datos; por defecto <code>$config['sitedb']</code>.</td></tr>
			<tr><td><code>limit</code>, <code>exportlimit</code></td><td>Filas máximas en pantalla (1000) y en la exportación CSV/Excel (50000).</td></tr>
			<tr><td><code>sample</code></td><td>Filas del resultado que se guardan como muestra en <code>nftablesfia</code> (20; 0 para no guardar datos).</td></tr>
			<tr><td><code>shared</code></td><td><code>true</code> (defecto): todos ven y reutilizan las consultas del uso; <code>false</code>: cada usuario ve solo las suyas.</td></tr>
			<tr><td><code>ratelimit</code></td><td>Preguntas nuevas a la IA por usuario: <code>[30, 3600]</code> = 30 por hora. Las reutilizadas no cuentan.</td></tr>
			<tr><td><code>model</code>, <code>effort</code></td><td><code>claude-opus-5-5</code> y <code>medium</code> por defecto.</td></tr>
			<tr><td><code>requireauth</code></td><td>Exige sesión iniciada (por defecto <code>true</code>).</td></tr>
			<tr><td><code>caption</code>, <code>placeholder</code></td><td>Textos del cuadro de la pregunta.</td></tr>
		</tbody>
	</table>

	<h3>Cómo funciona</h3>
	<ol>
		<li>El navegador envía la pregunta a <code>/nframework/tablefia.php</code>; la configuración del componente vive en la sesión, así que el usuario no puede agregar colecciones ni relaciones.</li>
		<li>Si ya existe una consulta guardada con la misma pregunta, el mismo <code>uso</code> y la misma configuración, se ejecuta esa sin llamar a la IA.</li>
		<li>Si no, Claude recibe las colecciones con sus campos, las relaciones y las reglas, y responde con la colección base, el pipeline (Extended JSON), las columnas y una explicación (salida estructurada con <code>json_schema</code>).</li>
		<li>El servidor valida el pipeline y lo arma: <code>match</code> + <code>$unset</code> de <code>hidden</code> + etapas de la IA + <code>$limit</code>. Si no pasa la validación o MongoDB lo rechaza, se le pide a la IA una corrección una vez.</li>
		<li>Se ejecuta (máximo 30 s), se muestra en una DataTable y se guarda en <code>nftablesfia</code>.</li>
	</ol>

	<h3>Seguridad</h3>
	<p>El pipeline de la IA se trata como entrada del usuario: con el texto de la pregunta se puede intentar que la IA genere cualquier cosa, así que la validación no depende del modelo.</p>
	<ul>
		<li>Solo etapas de lectura: <code><?= htmlspecialchars(implode(' ', TablefIA::STAGES)) ?></code>.</li>
		<li>Se rechazan en cualquier nivel <code>$where</code>, <code>$function</code>, <code>$accumulator</code>, <code>$out</code>, <code>$merge</code>, <code>$unionWith</code>, <code>$facet</code>, <code>$graphLookup</code>, <code>$lookup</code> anidado, entre otros.</li>
		<li><code>$lookup</code> solo en su forma simple y solo para las relaciones declaradas; sin <code>pipeline</code> ni <code>let</code>.</li>
		<li>El <code>match</code> obligatorio y el <code>$unset</code> de <code>hidden</code> van antes de las etapas generadas: la IA no puede saltarlos ni filtrar por campos ocultos.</li>
		<li>No incluya colecciones como <code>users</code> o <code>sessions</code>; si lo hace, oculte los campos sensibles con <code>hidden</code>.</li>
	</ul>

	<h3>Colección <code>nftablesfia</code></h3>
	<?= docCode(<<<'JSON'
{
  "uso": "reportes.ventas",
  "prompt": "ventas pagadas por ciudad este año",
  "coleccion": "pedidos",
  "pipeline": "[{\"$match\": {\"estado\": \"pagado\", ...}}, ...]",   // texto JSON tal como lo generó la IA
  "columnas": ["Ciudad", "Total"],
  "explicacion": "Suma de importes pagados en 2026 agrupada por la ciudad del cliente.",
  "confighash": "…",            // huella de colecciones/relaciones/instrucciones
  "modelo": "claude-opus-5-5",
  "tokens": {"input": 1830, "output": 412, "cache": 0},
  "usuario": ObjectId("…"), "username": "ana@ejemplo.com",
  "fecha": ISODate("…"), "ultimaejecucion": ISODate("…"), "ejecuciones": 4,
  "filas": 4,
  "muestra": [["Saltillo", "182340.5"], …]
}
JSON, 'javascript') ?>
	<p>Para usar una consulta guardada desde código (por ejemplo, en un reporte programado), valide y arme el pipeline igual que el componente:</p>
	<?= docCode(<<<'PHP'
$q = $m->{$config['sitedb']}->nftablesfia->findOne(['uso' => 'reportes.ventas', 'prompt' => 'ventas pagadas por ciudad este año']);
$cfg = ['collections' => TablefIA::normalizeCollections($colecciones), 'relations' => $relaciones];
$pipeline = TablefIA::buildPipeline($cfg, $q['coleccion'], TablefIA::parsePipeline($q['pipeline']), 1000);
$filas = TablefIA::run($m->{$config['sitedb']}->{$q['coleccion']}, $pipeline, (array) $q['columnas']);
PHP) ?>

	<?= docSource(__FILE__) ?>
</div>
