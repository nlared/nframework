<?php
/**
 * Lista de una tabla administrable: /nftables/<colección>/. Columnas, filtros y etiquetas de los Select con
 * colección salen de nffields mediante nfTableSchema (Admin → nftables los define).
 *
 * @global MongoDB\Client $m
 * @global class_config $config
 * @global class_nframework $nframework
 * @global Javas $javas
 * @global array<string, string> $p Parámetros de la ruta: collection.
 */

$tabla = $m->{$config['sitedb']}->nftables->findOne([
	'nfcollection' => (string) $p['collection'],
]);
$nframework->usecommon = true;
if (!$tabla || !nfTableSchema::validCollection($tabla['nfcollection'] ?? null)) {
	http_response_code(404);
	echo '<div class="container p-5"><div class="remark alert">No existe la tabla ' . htmlspecialchars((string) $p['collection']) . '.</div></div>';
	return;
}
$collection = (string) $tabla['nfcollection'];
$fields = nfTableSchema::normalizeFields($tabla['nffields'] ?? []);
if ($fields === []) {
	echo '<div class="container p-5"><div class="remark warning">La tabla no tiene campos definidos. Agréguelos en <a href="/admin/nftables/">Admin → nftables</a>.</div></div>';
	return;
}

$headers = '';
$columns = [];
$columnDefs = [];
foreach (nfTableSchema::columns($fields) as $i => $col) {
	$headers .= '<th>' . htmlspecialchars($col['title']) . '</th>';
	$columns[] = $col['column'];
	$columnDefs[$i] = ['render' => $col['render']];
}
$base = '/nftables/' . rawurlencode($collection) . '/';
$jsbase = json_encode($base, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
$columns[] = '_id';
$headers .= '<th>Acciones</th>';
$columnDefs[count($columns) - 1] = ['render' => "'<a href=\"'+nfEsc($jsbase+data)+'\" class=\"square button small primary\"><span class=\"mif-pencil\"></span></a>'+"
	. "'<button type=\"button\" onclick=\"nftableRemove(\\''+nfEsc(data)+'\\')\" class=\"square small button alert\"><span class=\"mif-bin\"></span></button>'"];

$tableid = 'nftable_' . preg_replace('/[^A-Za-z0-9_]/', '_', $collection);
$datatable = new Table();
$datatable->Ajax([
	'id' => $tableid,
	'db' => $config['sitedb'],
	'collection' => $collection,
	'header' => $headers,
	'pipeline' => nfTableSchema::pipeline($fields),
	'columns' => $columns,
	'columnDefs' => $columnDefs,
]);

$tablef = new Tablef([
	'table' => &$datatable,
	'filters' => nfTableSchema::filters($fields),
]);

$jstable = json_encode($tableid);
$javas->addjs(<<<JS
function nfEsc(v){
	if (v === null || v === undefined) { return ''; }
	if (typeof v === 'object') { v = JSON.stringify(v); }
	return String(v).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; });
}
function nfList(v){ return Array.isArray(v) ? v : (v === null || v === undefined || v === '' ? [] : [v]); }
function nfDate(v, time){
	if (!v) { return ''; }
	var d = new Date(v);
	if (isNaN(d)) { return v; }
	return time ? d.toLocaleString() : d.toISOString().slice(0, 10);
}
function nftableRemove(id){
	Swal.fire({
		title: '¿Borrar el registro?',
		text: 'No se puede deshacer.',
		icon: 'warning',
		showCancelButton: true,
		confirmButtonColor: '#d33',
		confirmButtonText: 'Sí, borrar',
		cancelButtonText: 'Cancelar'
	}).then(function(r){
		if (!r.isConfirmed) { return; }
		$.ajax({ url: {$jsbase} + encodeURIComponent(id), method: 'DELETE', cache: false }).done(function(){
			var dt = datatables[{$jstable}];
			dt.clearPipeline();
			dt.draw();
			Swal.fire('Borrado', 'El registro se eliminó.', 'success');
		});
	});
}
JS, 'general');
?>
<div class="container p-5">
	<div class="box shadow-large">
		<div class="box-title"><?= htmlspecialchars((string) ($tabla['plural'] ?? $collection)) ?></div>
		<?php if (!empty($tabla['nfdescription'])) { ?><p class="text-muted"><?= htmlspecialchars((string) $tabla['nfdescription']) ?></p><?php } ?>
		<?= $tablef; ?>
		<a href="<?= htmlspecialchars($base) ?>create" class="button"><span class="mif-plus"></span> Nuevo</a>
		<a href="<?= htmlspecialchars($base) ?>import" class="button"><span class="mif-file-import"></span> Importar</a>
		<?= $datatable; ?>
	</div>
</div>
