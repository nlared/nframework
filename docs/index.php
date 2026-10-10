<?php
require 'common.php';

$cards = '';
foreach ($docsMenu as $section => $pages) {
	$items = '';
	foreach ($pages as $file => [$caption, $icon, $description]) {
		if ($file === 'index.php') {
			continue;
		}
		$items .= '<li class="mb-2"><a href="/docs/' . $file . '"><span class="' . $icon . ' mr-1"></span>' . $caption . '</a>'
			. '<div class="text-small text-muted">' . $description . '</div></li>';
	}
	$cards .= '<div class="cell-md-6 cell-lg-4"><div class="card p-4 h-100"><h4>' . $section . '</h4><ul class="unstyled-list">' . $items . '</ul></div></div>';
}
?>
<div class="container">
	<?= docHeader('Documentación de nframework', 'Cada página es un ejemplo que funciona: muestra el resultado y el código que lo produce. Empiece por <a href="intro.php">Primeros pasos</a>.') ?>
	<div class="grid">
		<div class="row"><?= $cards ?></div>
	</div>
</div>
