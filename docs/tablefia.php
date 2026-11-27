<?php
require 'common.php';

docDemoVentas();

$consultas = new TablefIA([
	'id' => 'demoia',
	'uso' => 'docs.tablefia',
	'collections' => [
		'demo_pedidos' => [
			'description' => 'Pedidos de venta',
			// Tipos, valores de 'estado' y rangos de 'importe'/'fecha' se infieren de una muestra; aquí solo el significado.
			'notes' => [
				'folio' => 'número de pedido',
				'cliente' => '_id del cliente',
				'importe' => 'total en pesos',
				'productos' => 'cantidad de productos',
			],
		],
		// Sin notas: campos, tipos y valores tomados de una muestra. 'values' => true agrega ejemplos de nombres.
		'demo_clientes' => ['description' => 'Clientes', 'values' => true],
	],
	'relations' => [
		['from' => 'demo_pedidos', 'localField' => 'cliente', 'to' => 'demo_clientes', 'foreignField' => '_id', 'as' => 'cliente'],
	],
	'instructions' => 'Los importes son en pesos mexicanos. Los pedidos cancelados no cuentan como venta salvo que se pida.',
]);
?>
<div class="container">
	<?= docHeader('Consultas con IA (TablefIA)', 'Como <code>TableF</code>, pero el usuario escribe la pregunta en español y la IA (Claude, OpenAI, Gemini o un modelo local, según <a href="ai.php">Admin → IA</a>) genera el pipeline de MongoDB. El servidor lo valida, lo ejecuta y guarda la consulta en <code>nftablesfia</code> para reutilizarla sin volver a llamar a la IA.') ?>

	<?php try { $tfProvider = nfAI::provider(); ?>
		<div class="remark success">Proveedor predeterminado: <b><?= htmlspecialchars($tfProvider['name']) ?></b> (<code><?= htmlspecialchars($tfProvider['model']) ?></code>). Cada pregunta nueva consume tokens; reutilizar una consulta guardada no.</div>
	<?php } catch (RuntimeException $e) { ?>
		<div class="remark warning">No hay proveedor de IA: agregue uno en <a href="/admin/ai/">Admin → IA</a> (Anthropic, OpenAI, Gemini, Ollama...) o defina <code>'anthropic_api_key'</code> en <code>includes/config.php</code>.</div>
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
            'notes' => ['importe' => 'total en pesos'],   // significado; tipos, valores y rangos se infieren
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
			<tr><td><code>collections</code></td><td>Colecciones que la IA puede consultar. Por colección: <code>description</code>; <code>fields</code> (<code>['campo' => 'tipo y significado']</code>; si se omite, se infieren de los documentos que cumplen su <code>match</code>: una muestra de <code>fieldsample</code> documentos más las claves de primer nivel de los 5000 más recientes, en caché 1 hora); <code>match</code> (filtro que se aplica siempre, p. ej. por empresa o usuario); <code>hidden</code> (campos que se eliminan antes de cualquier etapa; tampoco se muestrean sus valores); <code>notes</code> (<code>['campo' => 'significado']</code>, se agrega a lo inferido); <code>stats</code> (<code>true</code> por defecto: la IA recibe de la muestra los valores de los campos categóricos —hasta <?= TablefIA::ENUM_MAX ?> valores distintos que se repiten, como <code>estado</code>— y el rango de números y fechas); <code>values</code> (<code>false</code> por defecto: con <code>true</code> también recibe tres ejemplos de los campos de texto libre; pueden ser datos personales).</td></tr>
			<tr><td><code>relations</code></td><td>Relaciones entre colecciones: <code>from</code>.<code>localField</code> → <code>to</code>.<code>foreignField</code>, con <code>as</code> y <code>description</code> opcionales. Son los únicos <code>$lookup</code> que se aceptan; el lado unido también lleva su <code>match</code> y <code>hidden</code>.</td></tr>
			<tr><td><code>instructions</code></td><td>Contexto del negocio para la IA (significado de estados, moneda, reglas).</td></tr>
			<tr><td><code>db</code></td><td>Base de datos; por defecto <code>$config['sitedb']</code>.</td></tr>
			<tr><td><code>limit</code>, <code>exportlimit</code></td><td>Filas máximas en pantalla (1000) y en la exportación CSV/Excel (50000).</td></tr>
			<tr><td><code>fieldsample</code></td><td>Documentos de muestra para inferir los campos de las colecciones sin <code>fields</code> (200).</td></tr>
			<tr><td><code>attempts</code></td><td>Intentos con la IA cuando el pipeline no pasa la validación, MongoDB lo rechaza o el resultado no trae las columnas (3, máximo 5). Cada intento recibe los anteriores y el error de cada uno.</td></tr>
			<tr><td><code>maxtime</code></td><td>Tiempo máximo de ejecución del pipeline en MongoDB, en milisegundos (30000). Aplica también a la exportación.</td></tr>
			<tr><td><code>allowdiskuse</code></td><td><code>false</code> por defecto: un <code>$sort</code> o <code>$group</code> que exceda la memoria de MongoDB (100 MB por etapa) falla con un error. Con <code>true</code> escribe a disco y termina (más lento); úselo en colecciones grandes junto con un <code>maxtime</code> mayor.</td></tr>
			<tr><td><code>sample</code></td><td>Filas del resultado que se guardan como muestra en <code>nftablesfia</code> (20; 0 para no guardar datos).</td></tr>
			<tr><td><code>fewshot</code></td><td>Consultas guardadas de este <code>uso</code> que la IA recibe como ejemplos de pregunta → pipeline (5, máximo 10, 0 = ninguna). Se eligen las favoritas y las más ejecutadas, y solo si sus colecciones no cambiaron y pasan la validación actual.</td></tr>
			<tr><td><code>shared</code></td><td><code>true</code> (defecto): todos ven y reutilizan las consultas del uso; <code>false</code>: cada usuario ve solo las suyas.</td></tr>
			<tr><td><code>ratelimit</code></td><td>Preguntas nuevas a la IA por usuario: <code>[30, 3600]</code> = 30 por hora. Las reutilizadas no cuentan.</td></tr>
			<tr><td><code>provider</code></td><td>Id de un proveedor de <a href="ai.php">Admin → IA</a>; <code>''</code> (por defecto) = el predeterminado.</td></tr>
			<tr><td><code>model</code>, <code>effort</code></td><td><code>''</code> por defecto = los del proveedor. Si los fija, aplican a cualquier proveedor: un nombre de modelo de Claude falla con OpenAI. Sin proveedores configurados se usa <code>anthropic_api_key</code> con <code>claude-opus-5-5</code> y <code>medium</code>.</td></tr>
			<tr><td><code>requireauth</code></td><td>Exige sesión iniciada (por defecto <code>true</code>).</td></tr>
			<tr><td><code>caption</code>, <code>placeholder</code></td><td>Textos del cuadro de la pregunta.</td></tr>
		</tbody>
	</table>

	<h3>Cómo funciona</h3>
	<ol>
		<li>El navegador envía la pregunta a <code>/nframework/tablefia.php</code>; la configuración del componente vive en la sesión, así que el usuario no puede agregar colecciones ni relaciones.</li>
		<li>Si ya existe una consulta guardada con la misma pregunta y el mismo <code>uso</code>, y no cambió la configuración de las colecciones que usa (su colección base, las de sus <code>$lookup</code>, las relaciones entre ellas y <code>instructions</code>; ver <code>TablefIA::queryHash()</code>), se ejecuta esa sin llamar a la IA: agregar o cambiar otra colección del componente no la invalida. La pregunta se compara normalizada (<code>TablefIA::normalizePrompt()</code>: minúsculas, sin acentos ni signos de puntuación, espacios colapsados), así que <em>«¿Ventas por año?»</em> y <em>«ventas por ano»</em> no generan dos consultas.</li>
		<li>Si no, la IA recibe las colecciones con sus campos (tipo, valores de muestra y rangos, notas), las relaciones, las reglas y hasta <code>fewshot</code> consultas guardadas como ejemplo, y responde con la colección base, el pipeline (Extended JSON), las columnas y una explicación (salida estructurada con <code>json_schema</code>; con modelos que no la admiten, el esquema va en las instrucciones y la respuesta se valida igual).</li>
		<li>El servidor valida el pipeline y lo arma: <code>match</code> + <code>$unset</code> de <code>hidden</code> + etapas de la IA + <code>$limit</code>. Si no pasa la validación, MongoDB lo rechaza (se envía el código y el mensaje) o el resultado no trae las columnas declaradas, se le pide a la IA una corrección con todos los intentos anteriores, hasta <code>attempts</code> veces.</li>
		<li>Mientras espera, el usuario ve la etapa (buscando, leyendo campos, generando, corrigiendo, ejecutando) y los segundos transcurridos; <strong>Cancelar</strong> solo deja de esperar.</li>
		<li>Se ejecuta (máximo <code>maxtime</code>), se muestra en una DataTable y se guarda en <code>nftablesfia</code>. Los números llegan como número (la tabla los ordena numéricamente) y las fechas como <code>Y-m-d H:i:s</code>. Se indica si la consulta se reutilizó (sin costo) o se generó, con los tokens usados.</li>
		<li>En <strong>Consultas guardadas</strong> se puede buscar (pregunta, nombre o explicación), paginar, marcar favoritas (por usuario; aparecen primero), renombrar o eliminar (autor o <code>admins</code>) y <strong>ajustar con IA</strong>: la nueva petición (<em>«solo 2025»</em>) se envía junto con la consulta original y se guarda como <em>«pregunta original · ajuste»</em>.</li>
		<li><strong>Csv</strong>, <strong>Excel</strong> y <strong>Gráfica</strong> trabajan sobre la consulta mostrada. La exportación usa valores nativos: en Excel los números son números, las fechas son fechas y el texto se guarda como texto (un folio <code>00123</code> conserva los ceros); en CSV las fechas van en ISO. <strong>Gráfica</strong> abre <code>/nframework/tablefg.php</code> en otra pestaña, que vuelve a validar el pipeline guardado y permite graficar el resultado (hasta 5000 filas) eligiendo tipo, categoría, valores y agregación.</li>
	</ol>

	<h3>Seguridad</h3>
	<p>El pipeline de la IA se trata como entrada del usuario: con el texto de la pregunta se puede intentar que la IA genere cualquier cosa, así que la validación no depende del modelo.</p>
	<ul>
		<li>Solo etapas de lectura: <code><?= htmlspecialchars(implode(' ', TablefIA::STAGES)) ?></code>.</li>
		<li>Se rechazan en cualquier nivel <code>$where</code>, <code>$function</code>, <code>$accumulator</code>, <code>$out</code>, <code>$merge</code>, <code>$unionWith</code>, <code>$facet</code>, <code>$graphLookup</code>, <code>$lookup</code> anidado, entre otros.</li>
		<li><code>$lookup</code> solo en su forma simple y solo para las relaciones declaradas; sin <code>pipeline</code> ni <code>let</code>.</li>
		<li>El <code>match</code> obligatorio y el <code>$unset</code> de <code>hidden</code> van antes de las etapas generadas: la IA no puede saltarlos ni filtrar por campos ocultos.</li>
		<li>También se revisan los valores: expresiones regulares (<code>$regex</code>, <code>$regexMatch</code>, <code>/…/</code>) de más de <?= TablefIA::MAX_REGEX ?> caracteres, con cuantificadores anidados (<code>(a+)+</code>) o referencias; <code>$range</code> de más de 1000 elementos o con campos; más de <?= TablefIA::MAX_LOOKUPS ?> <code>$lookup</code> o <?= TablefIA::MAX_UNWINDS ?> <code>$unwind</code>; más de <?= TablefIA::MAX_DEPTH ?> niveles de anidación.</li>
		<li>Se ejecuta con <code>maxTimeMS</code> y, salvo <code>'allowdiskuse' => true</code>, sin <code>allowDiskUse</code>: un <code>$sort</code> o <code>$group</code> que exceda la memoria de MongoDB falla en lugar de escribir a disco.</li>
		<li>El token del componente vive en la sesión, ligado al usuario que cargó la página; caduca tras 8 horas sin uso y se renueva en cada petición y cada vez que se dibuja el componente.</li>
		<li>No incluya colecciones como <code>users</code> o <code>sessions</code>; si lo hace, oculte los campos sensibles con <code>hidden</code>.</li>
	</ul>

	<h3>Colección <code>nftablesfia</code></h3>
	<?= docCode(<<<'JSON'
{
  "uso": "reportes.ventas",
  "prompt": "ventas pagadas por ciudad este año",
  "promptnorm": "ventas pagadas por ciudad este año",   // clave de reutilización (normalizePrompt)
  "titulo": "Ventas por ciudad",   // opcional: nombre que le dio el usuario
  "coleccion": "pedidos",
  "pipeline": "[{\"$match\": {\"estado\": \"pagado\", ...}}, ...]",   // texto JSON tal como lo generó la IA
  "columnas": ["Ciudad", "Total"],
  "explicacion": "Suma de importes pagados en 2026 agrupada por la ciudad del cliente.",
  "colecciones": ["clientes", "pedidos"],   // las que usa: base + $lookup
  "confighash": "…",            // huella de esas colecciones, sus relaciones e instrucciones (queryHash);
                                // las consultas anteriores (sin "colecciones") usan la de toda la configuración
  "ejemplos": 3,                // consultas guardadas que recibió la IA como ejemplo
  "modelo": "claude-opus-5-5",
  "proveedor": "anthropic",
  "tokens": {"input": 1830, "output": 412, "cache": 0},   // suma de todos los intentos
  "intentos": 1,
  "base": null,                 // _id de la consulta que se ajustó
  "usuario": ObjectId("…"), "username": "ana@ejemplo.com",
  "fecha": ISODate("…"), "ultimaejecucion": ISODate("…"), "ejecuciones": 4,
  "favoritos": [ObjectId("…")], "fijada": true,   // fijada = alguien la marcó; no la borra la limpieza
  "filas": 4,
  "muestra": [["Saltillo", 182340.5], …]
}
JSON, 'javascript') ?>
	<p>Limpieza: con <code>'tablefia_ttl_days' => 180</code> en <code>includes/config.php</code> (o en <code>configs</code>), <code>nframework/test.php</code> crea un índice TTL que borra las consultas sin ejecutarse en ese tiempo que nadie marcó como favorita. También migra las consultas guardadas antes de <code>promptnorm</code> y <code>fijada</code>.</p>
	<p>Para usar una consulta guardada desde código (por ejemplo, en un reporte programado), valide y arme el pipeline igual que el componente:</p>
	<?= docCode(<<<'PHP'
$q = $m->{$config['sitedb']}->nftablesfia->findOne(['uso' => 'reportes.ventas', 'prompt' => 'ventas pagadas por ciudad este año']);
$cfg = ['collections' => TablefIA::normalizeCollections($colecciones), 'relations' => $relaciones];
$pipeline = TablefIA::buildPipeline($cfg, $q['coleccion'], TablefIA::parsePipeline($q['pipeline']), 1000);
$filas = TablefIA::run($m->{$config['sitedb']}->{$q['coleccion']}, $pipeline, (array) $q['columnas']);
// Valores nativos: int, float, string, null o DateTimeImmutable. TablefIA::jsonRows($filas) para JSON,
// TablefIA::formatValue($valor) para texto.
PHP) ?>

	<?= docSource(__FILE__) ?>
</div>
