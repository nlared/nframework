<?php
require '../common2.php';
$nframework->usecommon = true;
$datatable = new Table();
$datatable->header = '<th>Tabla</th><th>Descripcion</th><th>Opciones</th>';
foreach ($m->{$config['sitedb']}->nftables->find() as $doc) {
    $datatable->data[] = [
        $doc['nfcollection'],
        $doc['nfdescription'],
        '<a href="table.php?_id=' . $doc['_id'] . '" class="button"><span class="mif-pencil"></span></a>
        <a href="dialog_preview.php?_id=' . $doc['_id'] . '" class="button"><span class="mif-pencil"></span></a>
        <a href="?eliminar=' . $doc['_id'] . '" class="button"><span class="mif-cross"></span></a>'
    ];
}
?>
<div class="container p-5">
    <div class="box shadow-large">
        <div class="box-title">Tablas</div>
        <a href="table.php" class="button"><span class="mif-plus"></span> Nuevo</a>
        <?= $datatable; ?>
    </div>
</div>