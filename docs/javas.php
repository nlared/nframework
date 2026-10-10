<?php
require 'common.php';

$mayusculas = new inputText(['id' => 'toupperid', 'name' => 'toupper', 'caption' => 'Escriba aquí (se fuerza a mayúsculas)']);

// 'general': se ejecuta en cuanto carga el script (definir funciones y variables globales).
$javas->addjs('function contarClic() { $("#clics").text(+$("#clics").text() + 1); }');

// 'ready': cuando el DOM está listo (equivale a $(function () { ... })).
$javas->addjs('document.getElementById("toupperid").addEventListener("keypress", forceKeyPressUppercase, false);', 'ready');
$javas->addjs('$("#contador").on("click", contarClic);', 'ready');

// 'resize': al cambiar el tamaño de la ventana (con retardo de 100 ms) y una vez al cargar.
$javas->addjs('$("#ancho").text(window.innerWidth + " px");', 'resize');

// 'scroll': al desplazar la página.
$javas->addjs('$("#desplazamiento").text(Math.round(window.scrollY) + " px");', 'scroll');
?>
<div class="container">
	<?= docHeader('JavaScript ($javas)', '<code>$javas</code> junta el JavaScript que agregan la página y los controles, y lo imprime una sola vez al final del documento, dentro de las secciones adecuadas. Así un control puede inyectar su código sin preocuparse de dónde se imprime.') ?>

	<div class="grid">
		<div class="row">
			<div class="cell-md-6"><?= $mayusculas ?></div>
			<div class="cell-md-6">
				<button class="button" id="contador">Clics: <span id="clics">0</span></button>
				<div class="mt-2">Ancho de ventana: <b id="ancho"></b> · Desplazamiento: <b id="desplazamiento">0 px</b></div>
			</div>
		</div>
	</div>

	<?= docCode(<<<'PHP'
$javas->addjs('function contarClic() { ... }');                       // 'general' (por defecto)
$javas->addjs('$("#contador").on("click", contarClic);', 'ready');     // DOM listo
$javas->addjs('$("#ancho").text(window.innerWidth + " px");', 'resize');
$javas->addjs('$("#desplazamiento").text(window.scrollY);', 'scroll');
PHP) ?>

	<h3>Secciones</h3>
	<table class="table striped compact">
		<thead><tr><th>Sección</th><th>Cuándo se ejecuta</th></tr></thead>
		<tbody>
			<tr><td><code>general</code></td><td>Al cargar el script, antes de que el DOM esté listo. Para funciones y variables globales.</td></tr>
			<tr><td><code>ready</code></td><td>Con el DOM listo, después de inicializar los controles del framework.</td></tr>
			<tr><td><code>resize</code></td><td>Al redimensionar la ventana (100 ms después del último evento) y una vez al inicio.</td></tr>
			<tr><td><code>scroll</code></td><td>En cada desplazamiento de la página.</td></tr>
		</tbody>
	</table>

	<h3>Pasar datos de PHP a JavaScript</h3>
	<p>Use siempre <code>json_encode()</code>: escapa comillas y caracteres especiales, y evita inyecciones de código.</p>
	<?= docCode(<<<'PHP'
$datos = ['usuario' => $user->name, 'permisos' => ['ver', 'editar']];
$javas->addjs('const config = ' . json_encode($datos) . ';');

// Mal: un nombre con comillas rompe el script o permite inyectar código.
// $javas->addjs("const nombre = '$user->name';");
PHP) ?>

	<h3>Etiquetas &lt;script&gt; en la página</h3>
	<p>Los bloques <code>&lt;script&gt;</code> sin <code>src</code> que imprima la página también se mueven al final y se comprimen. Defina <code>$noobfuscate = true</code> para depurarlos sin comprimir (ver <a href="vars.php">Variables especiales</a>).</p>

	<h3>Funciones disponibles en el navegador</h3>
	<table class="table striped compact">
		<tbody>
			<tr><td><code>toast(texto)</code></td><td>Aviso breve de Metro UI. Desde PHP: <code>notify('', $texto)</code>.</td></tr>
			<tr><td><code>speak(texto)</code></td><td>Lee el texto en voz alta. Desde PHP: <code>speak($texto)</code>.</td></tr>
			<tr><td><code>forceKeyPressUppercase</code>, <code>forceKeyPressLowercase</code></td><td>Manejadores de teclado para forzar mayúsculas/minúsculas.</td></tr>
			<tr><td><code>nAjaxFormDone(respuesta)</code></td><td>Procesa una respuesta <code>$result</code> (error, ids, js).</td></tr>
			<tr><td><code>datatables[id]</code>, <code>tomselects[id]</code>, <code>signaturePad[id]</code></td><td>Instancias de tablas, selects AJAX y firmas creadas por el framework.</td></tr>
		</tbody>
	</table>
</div>
