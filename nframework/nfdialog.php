<?php
/**
 * Formulario de un registro de una tabla administrable: /nftables/<colección>/<_id> (o /create).
 * Los controles salen de nffields mediante nfTableSchema (Admin → nftables los define).
 *
 * @global MongoDB\Client $m
 * @global class_config $config
 * @global class_nframework $nframework
 * @global array<string, string> $p Parámetros de la ruta: collection, id.
 */

$tabla = $m->{$config['sitedb']}->nftables->findOne([
    'nfcollection' => (string) $p['collection'],
]);

if (!$tabla || !nfTableSchema::validCollection($tabla['nfcollection'] ?? null)) {
    http_response_code(404);
    $nframework->usecommon = true;
    echo '<div class="container p-5"><div class="remark alert">No existe la tabla ' . htmlspecialchars((string) $p['collection']) . '.</div></div>';
    return;
}
$collection = (string) $tabla['nfcollection'];
$fields = nfTableSchema::normalizeFields($tabla['nffields'] ?? []);
if ($fields === []) {
    $nframework->usecommon = true;
    echo '<div class="container p-5"><div class="remark warning">La tabla no tiene campos definidos. Agréguelos en <a href="/admin/nftables/">Admin → nftables</a>.</div></div>';
    return;
}

if ($p['id'] === 'create') {
    header('Location: /nftables/' . rawurlencode($collection) . '/' . new MongoDB\BSON\ObjectId());
    exit;
}
if (!isValidObjectId($p['id'])) {
    http_response_code(400);
    echo 'Registro inválido';
    return;
}

$dataset = new dataset([
    'collection' => $m->{$config['sitedb']}->{$collection},
    '_id' => $p['id'],
    'simpleid' => false,
    'nameprefix' => 'data',
]);
$context = [
    'db' => $config['sitedb'],
    'collection' => $collection,
    '_id' => (string) $p['id'],
    'files' => $config['nftables_files'] ?? null,
];

$elements = [];   // [campo, control o arreglo embebido, ayuda]
foreach ($fields as $field) {
    $elements[] = [$field, nfTableSchema::build($field, $dataset, $context)];
}

if ($nframework->isAjax()) {
    $nframework->usecommon = false;
    if (($_POST['op'] ?? '') === 'save') {
        if (!csrfValidate()) {
            $result = ['error' => 'Formulario caducado, recargue la página.'];
        } else {
            try {
                $result = ['error' => $dataset->save()];
            } catch (Exception $e) {
                $result = ['error' => $e->getMessage()];
            }
        }
    }
    return;
}

$nframework->usecommon = true;
$nframework->metas['title'] = (string) ($tabla['singular'] ?? $collection);
$base = '/nftables/' . rawurlencode($collection) . '/';
$dialogs = [];
?>
<div class="container p-5">
    <?= secureform() ?>
    <div class="box shadow-large">
        <div class="box-title"><?= htmlspecialchars((string) ($tabla['singular'] ?? $collection)) ?></div>
        <div class="row">
            <?php foreach ($elements as [$field, $element]) {
                $wide = in_array($field['type'], ['textArea', 'inputMCE', 'embededArray', 'mapmarker', 'mapshape', 'inputaddress', 'inputFiles'], true);
                echo '<div class="' . ($wide ? 'cell-12' : 'cell-md-6') . ' mb-2">';
                if ($element === null) {
                    echo '<label>' . htmlspecialchars($field['short_description']) . '</label>'
                        . '<div class="remark warning">Para usar campos de archivo configure <code>nftables_files</code> (carpeta fuera del document root) en <code>includes/config.php</code>.</div>';
                } elseif (is_array($element)) {
                    // Lista de subdocumentos: su diálogo se imprime fuera de este formulario.
                    $dialogs[] = $element;
                    echo '<label>' . htmlspecialchars($field['short_description']) . '</label>'
                        . '<div id="' . htmlspecialchars($element['containerid']) . '"></div>'
                        . '<button type="button" class="button small mt-1" onclick="' . htmlspecialchars($element['list']->function_new()) . '"><span class="mif-plus"></span> Agregar</button>';
                } else {
                    echo $element;
                }
                if ($field['long_description'] !== '' && !is_array($element)) {
                    echo '<small class="text-muted">' . htmlspecialchars($field['long_description']) . '</small>';
                }
                echo '</div>';
            } ?>
        </div>
        <div class="row mt-4">
            <div class="cell-md-2 offset-md-8"><a href="<?= htmlspecialchars($base) ?>" class="button primary w-100"><span class="mif-exit"></span>&nbsp;<?= $nframework->language['close'] ?></a></div>
            <div class="cell-md-2"><button class="button success secureop w-100" value="save"><span class="mif-floppy-disk"></span>&nbsp;<?= $nframework->language['save'] ?></button></div>
        </div>
    </div>
    </form>
</div>
<?php
foreach ($dialogs as $embedded) {
    echo $embedded['dialog'];
    echo $embedded['list'];
}
if ($dialogs) {
    echo '<style>dialog{width:min(800px,calc(100vw - 2rem))}</style>';
}
