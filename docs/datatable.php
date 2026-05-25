<?php
require 'common.php';
$datatable = new Table();
$datatable->header = '<th>id</th><th>Data</th><th>Opciones</th>';
foreach ($m->{$config['sitedb']}->exampledata->find() as $doc) {
    $datatable->data[] = [
        $doc['_id'],
        serialize($doc),
        '<a href="databinding.php?_id=' . $doc['_id'] . '"><span class="mif-pencil"></span></a>
        <a href="?eliminar=' . $doc['_id'] . '"><span class="mif-cross"></span></a>'
    ];
}
?>
<div class="container">
    <div class="box shadow-large">
        <div class="box-title">DataTable</div>
        <a href="databinding.php" class="button"><span class="mif-plus"></span> Nuevo</a>
        <?= $datatable; ?>
    </div>
</div>
<pre><code class="html">
<?= tocode(__file__) ?>
</code></pre>