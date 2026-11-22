<?php
/**
 * Lista de tablas administrables (colección nftables). Borrar una tabla quita su definición, no los datos
 * de la colección.
 */
require '../common2.php';
requireGroup('admins');
$nftables = $m->{$config['sitedb']}->nftables;

if ($nframework->isAjax()) {
    if (!csrfValidate()) {
        $result = ['error' => 'Formulario caducado, recargue la página.'];
    } elseif (($_POST['op'] ?? '') === 'eliminar' && isValidObjectId($_POST['_id'] ?? null)) {
        $nftables->deleteOne(['_id' => toMongoId($_POST['_id'])]);
        $result = ['error' => false, 'js' => 'location.reload();'];
    }
    return;
}

$nframework->metas['title'] = 'Tablas administrables';
$datatable = new Table();
$datatable->header = '<th>Colección</th><th>Nombre</th><th>Campos</th><th>Descripción</th><th></th>';
foreach ($nftables->find([], ['sort' => ['nfcollection' => 1]]) as $doc) {
    $id = (string) $doc['_id'];
    $collection = (string) ($doc['nfcollection'] ?? '');
    $datatable->data[] = [
        '<code>' . htmlspecialchars($collection) . '</code>',
        htmlspecialchars((string) ($doc['plural'] ?? '')),
        count(nfTableSchema::normalizeFields($doc['nffields'] ?? [])),
        htmlspecialchars((string) ($doc['nfdescription'] ?? '')),
        ($collection !== '' ? '<a href="/nftables/' . htmlspecialchars(rawurlencode($collection)) . '/" class="button square small" title="Abrir tabla"><span class="mif-table"></span></a> ' : '')
            . '<a href="table.php?_id=' . $id . '" class="button square small primary" title="Editar"><span class="mif-pencil"></span></a> '
            . '<button type="button" class="button square small alert" title="Borrar la definición" onclick="nftablesDelete(' . htmlspecialchars(json_encode($id)) . ', ' . htmlspecialchars(json_encode($collection)) . ')"><span class="mif-bin"></span></button>',
    ];
}
$token = json_encode(csrfToken(NF_AJAX_FORM_ACTION));
$javas->addjs(<<<JS
function nftablesDelete(id, name){
	Swal.fire({
		title: '¿Borrar la tabla ' + name + '?',
		text: 'Se borra la definición (campos y textos); los documentos de la colección se conservan.',
		icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Borrar', cancelButtonText: 'Cancelar'
	}).then(function(r){
		if (r.isConfirmed) {
			$.post(location.href, { op: 'eliminar', _id: id, CSRFToken: {$token} }).done(nAjaxFormDone);
		}
	});
}
JS, 'general');
?>
<div class="container p-5">
    <div class="box shadow-large">
        <div class="box-title">Tablas administrables</div>
        <p class="text-muted">Cada tabla publica en <code>/nftables/&lt;colección&gt;/</code> una lista con filtros, alta, edición e importación de los documentos de una colección, para los grupos <code>admins</code> y <code>nftables</code>.</p>
        <a href="table.php" class="button primary"><span class="mif-plus"></span> Nueva</a>
        <?= $datatable; ?>
    </div>
</div>
