<?php
/*
 * Base de todas las páginas de /docs: carga el framework, el menú lateral y las utilidades
 * para mostrar ejemplos (docHeader, docExamples, docCode, docSource).
 *
 * Para agregar una página: créela en /docs, empiece con `require 'common.php';` y agréguela
 * a docs/components.json. index.php construye la portada a partir del mismo catálogo.
 */
$developermode = true;
if (empty($nframework)) {
	require_once 'include.php';
}

/**
 * Menú de la documentación: sección => [archivo => [título, icono, descripción]].
 * La fuente es docs/components.json (legible por máquinas, junto con el catálogo de componentes).
 */
function docCatalog(): array
{
	static $catalog = null;
	return $catalog ??= json_decode(file_get_contents(__DIR__ . '/components.json'), true, 512, JSON_THROW_ON_ERROR);
}

$docsMenu = [];
foreach (docCatalog()['sections'] as $docsSection) {
	foreach ($docsSection['pages'] as $docsPage) {
		$docsMenu[$docsSection['section']][$docsPage['file']] = [$docsPage['title'], $docsPage['icon'], $docsPage['description']];
	}
}
unset($docsSection, $docsPage);

/**
 * Código fuente de $filetocode hasta el marcador <pre class="stay-on"><code (o completo con $all).
 */
function tocode($filetocode, $all = false): string
{
	$code = file_get_contents($filetocode);
	$pos = $all ? false : strpos($code, '<pre class="stay-on"><code');
	return htmlentities($pos === false ? $code : substr($code, 0, $pos));
}

/**
 * Encabezado de página: título y descripción.
 */
function docHeader(string $title, string $description = ''): string
{
	return '<h1 class="mt-4">' . htmlspecialchars($title) . '</h1>'
		. ($description !== '' ? '<p class="text-leader">' . $description . '</p>' : '');
}

/**
 * Bloque de código resaltado.
 */
function docCode(string $code, string $lang = 'php'): string
{
	return '<pre class="stay-on"><code class="language-' . $lang . '">' . htmlspecialchars(trim($code, "\r\n")) . '</code></pre>';
}

/**
 * Muestra una lista de ejemplos: cada elemento es
 *   ['title' => 'Sección']                       → subtítulo
 *   ['code' => "new inputText([...]);", 'desc' => '...', 'title' => '...'] → resultado + código
 * 'code' es una expresión PHP que se evalúa y cuyo resultado (un control) se dibuja a la izquierda.
 */
function docExamples(array $examples): string
{
	// Los ejemplos se evalúan aquí: necesitan las mismas variables que una página.
	global $m, $config, $user, $nframework, $javas;
	$html = '';
	foreach ($examples as $example) {
		if (empty($example['code'])) {
			$html .= '<div class="row mt-6"><div class="cell"><h3>' . $example['title'] . '</h3>'
				. (!empty($example['desc']) ? '<p>' . $example['desc'] . '</p>' : '') . '</div></div>';
			continue;
		}
		$rendered = eval('return ' . $example['code']);
		$html .= '<div class="row border-bottom bd-default pb-4 mb-4">'
			. (!empty($example['title']) ? '<div class="cell-12"><h5>' . $example['title'] . '</h5></div>' : '')
			. (!empty($example['desc']) ? '<div class="cell-12"><p class="text-muted">' . $example['desc'] . '</p></div>' : '')
			. '<div class="cell-md-5">' . $rendered . '</div>'
			. '<div class="cell-md-7">' . docCode($example['code']) . '</div>'
			. '</div>';
	}
	return '<div class="grid">' . $html . '</div>';
}

/**
 * Código fuente completo de la página actual, plegable.
 */
function docSource(string $file): string
{
	return '<details class="mt-6 mb-6"><summary class="text-bold">Código fuente de esta página</summary>'
		. '<pre class="stay-on"><code class="language-php">' . tocode($file, true) . '</code></pre></details>';
}

/**
 * Colecciones de ejemplo demo_clientes y demo_pedidos (TableF, TablefIA). Se crean la primera vez.
 */
function docDemoVentas(): void
{
	global $m, $config;
	$db = $m->{$config['sitedb']};
	if ($db->demo_clientes->estimatedDocumentCount() > 0) {
		return;
	}
	$ciudades = ['Saltillo', 'Monterrey', 'Torreón', 'Monclova'];
	$clientes = [];
	foreach (['Abarrotes Ruiz', 'Ferretería El Clavo', 'Papelería Lupita', 'Farmacia Central', 'Taller Hermanos Díaz', 'Café La Esquina', 'Refaccionaria Norte', 'Panadería San José'] as $i => $nombre) {
		$clientes[] = ['_id' => new MongoDB\BSON\ObjectId(), 'nombre' => $nombre, 'ciudad' => $ciudades[$i % 4], 'credito' => ($i % 3) === 0, 'alta' => new MongoDB\BSON\UTCDateTime(strtotime('-' . (400 - $i * 40) . ' days') * 1000)];
	}
	$db->demo_clientes->insertMany($clientes);
	$pedidos = [];
	mt_srand(42);
	for ($i = 1; $i <= 300; $i++) {
		$pedidos[] = [
			'folio' => $i,
			'cliente' => $clientes[mt_rand(0, count($clientes) - 1)]['_id'],
			'fecha' => new MongoDB\BSON\UTCDateTime(strtotime('-' . mt_rand(0, 365) . ' days') * 1000),
			'importe' => round(mt_rand(15000, 900000) / 100, 2),
			'estado' => ['pagado', 'pagado', 'pendiente', 'cancelado'][mt_rand(0, 3)],
			'productos' => mt_rand(1, 12),
		];
	}
	$db->demo_pedidos->insertMany($pedidos);
}

if (!$nframework->isAjax()) {
	$nframework->usecommon = true;
	$nframework->csss['998'] = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css';
	$nframework->jss['998'] = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js';
	$nframework->csss['101'] = '/css/index.css';
	$javas->addjs('hljs.highlightAll();', 'ready');

	$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
	$sidemenu = '';
	foreach ($docsMenu as $section => $pages) {
		$sidemenu .= '<li class="item-header">' . $section . '</li>';
		foreach ($pages as $file => [$caption, $icon]) {
			$sidemenu .= '<li' . ($file === $current ? ' class="active"' : '') . '>
				<a href="/docs/' . $file . '" class="side-menu__item">
					<span class="icon"><span class="' . $icon . '"></span></span>
					<span class="caption">' . $caption . '</span>
				</a>
			</li>';
		}
	}
	echo new Sidebar(['title' => 'nframework', 'sidemenu' => $sidemenu]);
}
