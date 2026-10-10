<?php
/*
 * Base de todas las páginas de /docs: carga el framework, el menú lateral y las utilidades
 * para mostrar ejemplos (docHeader, docExamples, docCode, docSource).
 *
 * Para agregar una página: créela en /docs, empiece con `require 'common.php';` y agréguela
 * a $docsMenu. index.php construye la portada a partir del mismo arreglo.
 */
$developermode = true;
if (empty($nframework)) {
	require_once 'include.php';
}

/**
 * Menú de la documentación: sección => [archivo => [título, icono, descripción]].
 */
$docsMenu = [
	'Introducción' => [
		'index.php' => ['Inicio', 'mif-home', 'Índice de todos los ejemplos.'],
		'intro.php' => ['Primeros pasos', 'mif-apps', 'Anatomía de una página: include, usecommon, peticiones AJAX y $result.'],
		'vars.php' => ['Variables especiales', 'mif-money', 'Variables globales que cambian el comportamiento del framework.'],
	],
	'Formularios' => [
		'inputs.php' => ['Inputs', 'mif-widgets', 'Catálogo de controles: texto, números, selects, checkboxes, color, dirección...'],
		'datetime.php' => ['Fechas y horas', 'mif-calendar', 'inputDate, inputTime e inputDateTime y cómo se guardan en MongoDB.'],
		'inputsajax.php' => ['Select con AJAX', 'mif-search', 'Select que busca opciones en una colección mientras se escribe.'],
		'validations.php' => ['Validaciones', 'mif-checkmark', 'required, pattern, validate y validación del lado del servidor.'],
		'files.php' => ['Archivos', 'mif-upload', 'inputFile e inputFiles: subir, previsualizar, descargar y eliminar.'],
		'signature.php' => ['Firma', 'mif-pencil', 'Signature_pad: capturar una firma y guardarla como imagen.'],
	],
	'Datos' => [
		'databinding.php' => ['Databinding', 'mif-database', 'Formulario ligado a un documento de MongoDB con dataset.'],
		'databindingajax.php' => ['Databinding AJAX', 'mif-database', 'El mismo formulario guardando por AJAX con secureform().'],
		'arraylist.php' => ['Arreglos embebidos', 'mif-list', 'embededArray: editar una lista dentro de un documento.'],
		'datatable.php' => ['DataTable', 'mif-table', 'Tabla con datos generados en PHP.'],
		'datatableajax.php' => ['DataTable AJAX', 'mif-table', 'Tabla paginada en el servidor desde una colección.'],
		'inegi.php' => ['Caso: domicilio INEGI', 'mif-location', 'Formulario completo con mapa y catálogos de INEGI.'],
	],
	'Interfaz' => [
		'dialog.php' => ['Diálogos', 'mif-open-book', 'Dialog: ventanas modales con formulario.'],
		'javas.php' => ['JavaScript ($javas)', 'mif-file-code', 'Agregar JavaScript a la página desde PHP.'],
		'ui.php' => ['Notificaciones y utilidades', 'mif-bell', 'notify(), speak(), BreadCrumbs, ThemeSwitcher y CSS/JS por página.'],
		'xspreadsheet.php' => ['Hoja de cálculo', 'mif-table', 'xspreadsheet: ver y editar un .xlsx en el navegador.'],
	],
	'Backend' => [
		'user.php' => ['Usuarios y permisos', 'mif-user', '$user, grupos, permisos y páginas protegidas.'],
		'security.php' => ['Seguridad', 'mif-lock', 'CSRF, redirecciones seguras, consultas Mongo y archivos subidos.'],
		'functions.php' => ['Funciones', 'mif-code', 'Funciones auxiliares de functions.php con su resultado.'],
		'routes.php' => ['Rutas', 'mif-flow-tree', 'Rutas propias con crouter.php y las rutas integradas.'],
		'exports.php' => ['Excel, Word y PDF', 'mif-file-pdf', 'Descargar hojas de cálculo, documentos y PDF.'],
		'images.php' => ['Imágenes', 'mif-image', 'Miniaturas, conversión a WebP y páginas de PDF como imagen.'],
		'backgroundps.php' => ['Procesos en segundo plano', 'mif-cogs', 'bgprocess: lanzar y vigilar un comando largo.'],
		'jobs.php' => ['Colas de trabajo', 'mif-timer', 'Job y job_worker.php: tareas diferidas con reintentos.'],
	],
];

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
