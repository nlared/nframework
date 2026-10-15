<?php
require 'common.php';

$idiomas = ['es-MX', 'en-US'];
if ($nframework->isAjax()) {
	if (!csrfValidate()) {
		$result = ['error' => 'Formulario caducado, recargue la página.'];
	} elseif (in_array($_POST['op'] ?? '', $idiomas, true)) {
		// Mismo lugar donde include.php guarda el idioma detectado; aplica desde la siguiente petición.
		$_SESSION['nf']['browser']['language'] = $_POST['op'];
		$result = ['error' => false, 'js' => 'location.reload();'];
	}
	return;
}

// Claves de primer nivel que tiene un idioma y el otro no.
foreach ($idiomas as $lang) {
	if (!isset($nframework->languages[$lang])) {
		require $nframework->include_path . '/i18n/' . $lang . '.php';
	}
}
$claves = array_map(fn($l) => array_keys($nframework->languages[$l]), array_combine($idiomas, $idiomas));
$soloEs = array_diff($claves['es-MX'], $claves['en-US']);
$soloEn = array_diff($claves['en-US'], $claves['es-MX']);
?>
<div class="container">
	<?= docHeader('Idiomas (i18n)', 'El framework detecta el idioma del navegador una vez por sesión y carga sus textos de <code>includes/i18n/&lt;idioma&gt;.php</code> en <code>$nframework->language</code>. Los controles (calendario, tablas, botones, archivos) ya los usan.') ?>

	<h3>Idioma actual</h3>
	<div class="card p-4 mb-4">
		<p>
			<code>$nframework->lang</code> = <strong><?= htmlspecialchars($nframework->lang) ?></strong> ·
			<code>langshort</code> = <strong><?= htmlspecialchars($nframework->langshort) ?></strong> ·
			<code>language['buttons']['save']</code> = <strong><?= htmlspecialchars($nframework->language['buttons']['save']) ?></strong> ·
			<code>language['calendar']['months'][0]</code> = <strong><?= htmlspecialchars($nframework->language['calendar']['months'][0]) ?></strong>
		</p>
		<?= secureform() ?>
			<?php foreach ($idiomas as $lang) { ?>
				<button class="button small secureop<?= $lang === $nframework->lang ? ' primary' : '' ?>" value="<?= $lang ?>"><?= $lang ?></button>
			<?php } ?>
			<span class="text-muted ml-2">Cambia el idioma de su sesión.</span>
		</form>
	</div>

	<h3>Cómo se elige</h3>
	<ul>
		<li>En la primera petición de la sesión, <code>loadBrowserInfo()</code> lee <code>Accept-Language</code>: <code>es</code>, <code>es-ES</code> y <code>es-MX</code> → <code>es-MX</code>; <code>en</code> y <code>en-US</code> → <code>en-US</code>; cualquier otro → <code>en-US</code>.</li>
		<li>Se guarda en <code>$_SESSION['nf']['browser']['language']</code>. Para que el usuario lo cambie, asigne ahí uno de los idiomas disponibles (como los botones de arriba); aplica desde la siguiente petición.</li>
		<li><code>$nframework->lang</code> también va en <code>&lt;html lang&gt;</code> y en el <code>locale</code> de Metro UI.</li>
	</ul>
	<?= docCode(<<<'PHP'
$nframework->lang;                          // 'es-MX'
$nframework->langshort;                     // 'es'
$nframework->language['buttons']['save'];   // 'Guardar'
$nframework->language['table']['empty'];    // 'Nada que mostrar'

// Textos propios del sitio según el idioma:
$textos = [
    'es' => ['bienvenida' => 'Bienvenido'],
    'en' => ['bienvenida' => 'Welcome'],
];
echo $textos[$nframework->langshort]['bienvenida'] ?? $textos['es']['bienvenida'];
PHP) ?>

	<h3>Archivos de idioma</h3>
	<p>Cada <code>includes/i18n/&lt;idioma&gt;.php</code> agrega su arreglo a <code>$nframework->languages['&lt;idioma&gt;']</code>. La carpeta del mismo nombre tiene las plantillas legales (<code>privacy.html</code>, <code>terms.html</code>, <code>righttoforget.html</code>). Para un idioma nuevo: copie ambos, tradúzcalos y agréguelo al mapa de <code>loadBrowserInfo()</code> en <code>includes/include.php</code>.</p>
	<p>Claves de primer nivel en <code>es-MX</code>: <code><?= htmlspecialchars(implode(', ', $claves['es-MX'])) ?></code>.</p>
	<?php if ($soloEs || $soloEn) { ?>
		<div class="remark warning">
			Los idiomas no tienen las mismas claves; usar una que falta da <code>null</code> (y un aviso) en el otro idioma.
			<?= $soloEs ? '<br>Solo en <code>es-MX</code>: <code>' . htmlspecialchars(implode(', ', $soloEs)) . '</code>' : '' ?>
			<?= $soloEn ? '<br>Solo en <code>en-US</code>: <code>' . htmlspecialchars(implode(', ', $soloEn)) . '</code>' : '' ?>
		</div>
	<?php } ?>

	<?= docSource(__FILE__) ?>
</div>
