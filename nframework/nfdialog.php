<?

$tabla = $m->{$config['sitedb']}->nftables->findOne([
    'nfcollection' => $p['collection']
]);


if (!$tabla) {
    echo 'No se encontró la tabla';
}
if (!isset($tabla->nffields) || count($tabla->nffields) == 0) {
    echo 'La tabla no tiene campos definidos';
}

if ($p['_id'] == 'create') {
    header('Location: /nftables/' . $p['collection'] . '/' . newid());
    exit;
}
$elements = [];
$dataset = new dataset(
    [
        'collection' => $m->{$config['sitedb']}->{$tabla->nfcollection},
        '_id' => $p['_id'],
        'simpleid' => false,
        'nameprefix' => 'data'
    ]
);

foreach ($tabla->nffields as $field) {
    $className = $field->tipo;
    $elements[] = new $className([
        'field' => $field->nombre,
        'caption' => $field->descripcion_corta,
        'required' => $field->required
    ]);
}

if ($nframework->isAjax()) {
    if ($_POST['op'] == 'save') {
        try {
            $result = [
                'error' => $dataset->save(),
            ];
        } catch (Exception $e) {
            $result = [
                'error' => $e->getMessage()
            ];
        }
    }
} else {
    $nframework->usecommon = true;


?>
    <div class="container">
        <?= secureform() ?>
        <div class="box shadow-large">
            <div class="box-title"><?= $tabla->singular  ?></div>
            <div class="grid">
                <?
                foreach ($elements as $element) {
                    echo '<div class="cell">' . $element . '</div>';
                }
                ?>
                <div class="row">
                    <div class="cell-md-2 offset-md-8"><a href="/nftables/<?= $tabla->nfcollection ?>" class="button primary btn btn-primary w-100"><span class="mif-exit"></span>&nbsp;<?= $lng['close']  ?>?></a></div>
                    <div class="cell-md-2"><button class="button success btn btn-success secureop  w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;<?= $lng['save']  ?>?></button></div>
                </div>
            </div>
        </div>
        </form>
    </div>
<?
}
?>