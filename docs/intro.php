<?php
require 'common.php';

// Ejemplo vivo: el formulario de abajo se envía por AJAX a esta misma página.
$nombre = new inputText(['name' => 'nombre', 'caption' => 'Su nombre', 'required' => true]);

if ($nframework->isAjax()) {
	if (!csrfValidate()) {
		$result = ['error' => 'La sesión expiró, recargue la página.'];
	} elseif (($_POST['op'] ?? '') === 'saludar') {
		$texto = htmlspecialchars(trim((string) ($_POST['nombre'] ?? '')), ENT_QUOTES, 'UTF-8');
		$result = [
			'error' => false,                                         // false = éxito (muestra "Datos guardados")
			'ids' => ['saludo' => 'Hola, <b>' . $texto . '</b>'],   // #saludo recibe este HTML
			'js' => 'console.log("respuesta recibida")',              // JavaScript que se ejecuta al recibir la respuesta
		];
	}
	return; // en AJAX nframework responde json_encode($result) al terminar
}
?>
<div class="container">
	<?= docHeader('Primeros pasos', 'Una página de nframework es un archivo PHP normal que incluye <code>include.php</code>. El framework se encarga de la sesión, el usuario, la plantilla HTML y las respuestas AJAX.') ?>

	<h3>1. La página mínima</h3>
	<?= docCode(<<<'PHP'
<?php
require 'include.php';            // conexión a MongoDB ($m), $config, $user, $javas, $nframework
$nframework->usecommon = true;     // envolver la salida en la plantilla HTML (head, CSS y JS de Metro UI)
$nframework->metas['title'] = 'Mi página';
$nframework->metas['description'] = 'Descripción para buscadores';
?>
<div class="container">
    <h1>Hola <?= htmlspecialchars($user->name ?? 'invitado') ?></h1>
</div>
PHP) ?>
	<p>Sin <code>usecommon</code> la página devuelve solo lo que imprime (útil para fragmentos HTML, CSV, imágenes...).
		Para exigir sesión defina <code>$requiresession = true;</code> <em>antes</em> del <code>require</code>; el visitante sin sesión se envía a <code>/</code>.</p>

	<h3>2. Variables disponibles después de include.php</h3>
	<table class="table striped compact">
		<thead><tr><th>Variable</th><th>Qué es</th></tr></thead>
		<tbody>
			<tr><td><code>$m</code></td><td><code>MongoDB\Client</code>. La base del sitio es <code>$m->{$config['sitedb']}</code>.</td></tr>
			<tr><td><code>$config</code></td><td>Configuración: <code>includes/config.php</code> + documento <code>configs/site</code> de MongoDB.</td></tr>
			<tr><td><code>$user</code></td><td>Usuario actual (<code>User</code>); el invitado tiene <code>username = 'guest'</code>. Ver <a href="user.php">Usuarios</a>.</td></tr>
			<tr><td><code>$nframework</code></td><td>Página actual: CSS/JS, metas, idioma (<code>$nframework->lang</code>), exportaciones.</td></tr>
			<tr><td><code>$javas</code></td><td>Acumula JavaScript para el final de la página. Ver <a href="javas.php">$javas</a>.</td></tr>
			<tr><td><code>$ip</code></td><td>IP real del visitante (respeta proxies de confianza).</td></tr>
		</tbody>
	</table>

	<h3>3. Formularios AJAX</h3>
	<p><code>secureform()</code> abre un formulario que se envía por AJAX a la misma página con un token CSRF.
		El botón con clase <code>secureop</code> pone su <code>value</code> en el campo <code>op</code>.
		En la petición AJAX la página asigna <code>$result</code> y el framework lo devuelve como JSON.</p>

	<div class="card p-4">
		<?= secureform() ?>
			<?= $nombre ?>
			<button class="button primary secureop mt-2" value="saludar">Saludar</button>
		</form>
		<div id="saludo" class="mt-4 text-leader"></div>
	</div>

	<?= docCode(<<<'PHP'
$nombre = new inputText(['name' => 'nombre', 'caption' => 'Su nombre', 'required' => true]);

if ($nframework->isAjax()) {
    if (!csrfValidate()) {
        $result = ['error' => 'La sesión expiró, recargue la página.'];
    } elseif (($_POST['op'] ?? '') === 'saludar') {
        $texto = htmlspecialchars(trim((string) ($_POST['nombre'] ?? '')), ENT_QUOTES, 'UTF-8');
        $result = [
            'error' => false,                                        // false = éxito
            'ids' => ['saludo' => 'Hola, <b>' . $texto . '</b>'],  // #saludo recibe este HTML
            'js' => 'console.log("respuesta recibida")',             // se ejecuta en el navegador
        ];
    }
    return;
}
?>
<?= secureform() ?>
    <?= $nombre ?>
    <button class="button primary secureop" value="saludar">Saludar</button>
</form>
<div id="saludo"></div>
PHP) ?>
	<p>Claves de <code>$result</code> que entiende el navegador (<code>nAjaxFormDone</code>):</p>
	<ul>
		<li><code>error</code>: <code>false</code> muestra el aviso «Datos guardados»; un texto abre un diálogo con ese error.</li>
		<li><code>ids</code>: <code>['idElemento' => 'html']</code>, reemplaza el contenido de cada elemento.</li>
		<li><code>js</code>: JavaScript que se ejecuta al recibir la respuesta.</li>
	</ul>

	<h3>4. Siguiente paso</h3>
	<p>Vea el <a href="inputs.php">catálogo de inputs</a> y cómo ligarlos a MongoDB con <a href="databinding.php">databinding</a>.</p>
</div>
