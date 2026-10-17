<?php
require '../common2.php';

// Solo por POST: un enlace GET permitía borrar menús mediante CSRF.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isValidObjectId($_POST['eliminar'] ?? null)) {
	$borrado = $m->{$config['sitedb']}->menus->findOneAndDelete(['_id' => toMongoId($_POST['eliminar'])]);
	if ($borrado && !empty($borrado['name'])) {
		nfCacheForget('menu:' . $borrado['name']);
	}
}

$nframework->usecommon = true;
$nframework->metas['title'] = 'Menús';
$datatable = new Table(['order' => [[0, 'asc']]]);
$datatable->header = '<th>Nombre</th><th>Elementos</th><th>Estado</th><th>Modificado</th><th></th>';
foreach ($m->{$config['sitedb']}->menus->find([], ['sort' => ['name' => 1]]) as $doc) {
	$code = (string) ($doc['code'] ?? '');
	try {
		$nodes = nfNormalizeMenuNodes(json_decode($code, true));
		$estado = $nodes === []
			? '<span class="badge inline bg-orange fg-white">Vacío</span>'
			: '<span class="badge inline bg-green fg-white">Válido</span>';
		$elementos = nfCountMenuNodes($nodes);
	} catch (InvalidArgumentException $e) {
		// Un menú así no se muestra en el sitio (nfMetroMenu lo omite); hay que abrirlo y guardarlo.
		$estado = '<span class="badge inline bg-red fg-white" title="nfMetroMenu() no lo muestra">' . ($code === '' ? 'Sin código' : 'JSON inválido') . '</span>';
		$elementos = '—';
	}
	$modificado = !empty($doc['updated'])
		? $doc['updated']->toDateTime()->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i') . (!empty($doc['updatedby']) ? ' · ' . htmlspecialchars($doc['updatedby']) : '')
		: '';
	$id = (string) $doc['_id'];
	$datatable->data[] = [
		'<a href="menu.php?_id=' . $id . '"><code>' . htmlspecialchars((string) ($doc['name'] ?? '(sin nombre)')) . '</code></a>',
		$elementos,
		$estado,
		$modificado,
		'<a href="menu.php?_id=' . $id . '" class="button small square" title="Editar"><span class="mif-pencil"></span></a>
		<form method="POST" style="display:inline" onsubmit="return confirm(\'¿Eliminar el menú ' . htmlspecialchars(addslashes((string) ($doc['name'] ?? '')), ENT_QUOTES) . '?\')"><button class="button small square alert" name="eliminar" value="' . $id . '" title="Eliminar"><span class="mif-bin"></span></button></form>',
	];
}
?>
<div class="container p-5">
	<div class="d-flex flex-align-center flex-wrap">
		<h3 class="m-0"><span class="mif-dropdown-menu"></span> Menús</h3>
		<a href="menu.php" class="button primary ml-auto"><span class="mif-plus"></span> Nuevo menú</a>
	</div>
	<p class="text-muted">Los menús se muestran en el sitio con <code>&lt;?= nfMetroMenu('nombre') ?&gt;</code>. Las páginas del CMS usan el menú <code>_nav</code>. Un menú en rojo no se muestra: ábralo y guárdelo.</p>
	<?= $datatable ?>
</div>
