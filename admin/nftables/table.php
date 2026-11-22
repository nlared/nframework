<?php
/**
 * Editor de una tabla administrable (documento de nftables): colección, textos y campos (nffields).
 * Los campos se editan en el navegador y se guardan juntos; el servidor los valida con nfTableSchema.
 * Operaciones AJAX: save, detectar (propone campos a partir de documentos), campos (rutas de una colección
 * para sugerir nombres), probar (muestra lo que devolverá un Select con colección o unas sugerencias) y
 * codigo (PHP equivalente de un campo).
 */
if (empty($_GET['_id']) || !preg_match('/^[a-f\d]{24}$/i', (string) $_GET['_id'])) {
    header('Location: ?_id=' . new MongoDB\BSON\ObjectId());
    exit();
}
require '../common2.php';
requireGroup('admins');

$db = $m->{$config['sitedb']};
$dataset = new dataset([
    'collection' => $db->nftables,
    '_id' => $_GET['_id'],
    'simpleid' => false,
    'nameprefix' => 'data',
]);

/** Colecciones del framework que no se editan como tabla (usuarios, configuración, las propias nftables...). */
const NFTABLES_RESERVED = ['nftables', 'nftablesfia', 'nfversions', 'users', 'usersgroups', 'configs', 'jobs', 'pages', 'menus'];

/** Nombres de las colecciones del sitio que se pueden usar. */
function nftablesCollections($db): array
{
    $names = [];
    try {
        foreach ($db->listCollectionNames() as $name) {
            if (nfTableSchema::validCollection($name)) {
                $names[] = $name;
            }
        }
    } catch (Exception $e) {
        error_log('nftables: ' . $e->getMessage());
    }
    sort($names);
    return $names;
}

/** Documentos de muestra de una colección (los más recientes y algunos al azar). */
function nftablesSample($db, string $collection, int $size = 200): array
{
    $docs = [];
    foreach ($db->{$collection}->find([], ['sort' => ['_id' => -1], 'limit' => (int) ($size / 2)]) as $doc) {
        $docs[(string) ($doc['_id'] ?? count($docs))] = mongoToArray($doc);
    }
    foreach ($db->{$collection}->aggregate([['$sample' => ['size' => (int) ($size / 2)]]]) as $doc) {
        $docs[(string) ($doc['_id'] ?? count($docs))] = mongoToArray($doc);
    }
    return array_values($docs);
}

/** Campo enviado como JSON por el editor (para probar o generar código). */
function nftablesPostedField(array &$errors): ?array
{
    $raw = json_decode(is_string($_POST['field'] ?? null) ? $_POST['field'] : '', true);
    return is_array($raw) ? nfTableSchema::normalizeField($raw, $errors, false, true) : null;
}

$tabla_coleccion = new inputText([
    'dataset' => &$dataset, 'field' => 'nfcollection', 'caption' => 'Colección', 'required' => true,
    'pattern' => '^[A-Za-z0-9_][A-Za-z0-9_.\-]*$', 'suggest' => nftablesCollections($db), 'id' => 'nft_collection',
]);
$tabla_singular = new inputText(['dataset' => &$dataset, 'field' => 'singular', 'caption' => 'Singular', 'required' => true]);
$tabla_plural = new inputText(['dataset' => &$dataset, 'field' => 'plural', 'caption' => 'Plural', 'required' => true]);
$tabla_descripcion = new textArea(['dataset' => &$dataset, 'field' => 'nfdescription', 'caption' => 'Descripción']);

if ($nframework->isAjax()) {
    $op = is_string($_POST['op'] ?? null) ? $_POST['op'] : '';
    $collection = is_string($_POST['collection'] ?? null) ? trim($_POST['collection']) : '';
    if (!csrfValidate()) {
        $result = ['error' => 'Formulario caducado, recargue la página.'];
        return;
    }
    try {
        if ($op === 'save') {
            $errors = [];
            $raw = json_decode(is_string($_POST['nffields'] ?? null) ? $_POST['nffields'] : '', true);
            $fields = is_array($raw) ? nfTableSchema::normalizeFields($raw, $errors, false, true) : [];
            $name = is_string($_POST['data']['nfcollection'] ?? null) ? trim($_POST['data']['nfcollection']) : '';
            if (!nfTableSchema::validCollection($name) || in_array($name, NFTABLES_RESERVED, true)) {
                $errors[] = 'Esa colección no se puede administrar desde aquí.';
            } elseif ($db->nftables->findOne(['nfcollection' => $name, '_id' => ['$ne' => toMongoId($dataset->_id)]])) {
                $errors[] = 'Ya hay otra tabla para la colección ' . $name . '.';
            }
            if (!is_array($raw)) {
                $errors[] = 'No llegaron los campos.';
            } elseif ($fields === [] && $errors === []) {
                $errors[] = 'Agregue al menos un campo.';
            }
            if ($errors) {
                $result = ['error' => false, 'errores' => array_values(array_unique($errors))];
                return;
            }
            $error = $dataset->save();
            if (!$error) {
                $dataset->nffields = $fields;
            }
            $result = ['error' => $error, 'fields' => $fields];
        } elseif ($op === 'detectar' || $op === 'campos') {
            if (!nfTableSchema::validCollection($collection)) {
                $result = ['error' => 'Escriba primero una colección válida.'];
                return;
            }
            $inferred = nfTableSchema::inferFields(nftablesSample($db, $collection), $op === 'campos' ? 200 : 40);
            if ($op === 'campos') {
                $paths = [];
                foreach ($inferred as $f) {
                    $paths[] = $f['field'];
                    foreach ($f['subfields'] ?? [] as $s) {
                        $paths[] = $f['field'] . '.' . $s['field'];
                    }
                }
                $result = ['error' => false, 'paths' => $paths];
            } else {
                $ignored = [];
                $result = ['error' => false, 'fields' => nfTableSchema::normalizeFields($inferred, $ignored, false, false)];
            }
        } elseif ($op === 'probar') {
            $errors = [];
            $field = nftablesPostedField($errors);
            if ($field === null || $errors) {
                $result = ['error' => false, 'errores' => $errors ?: ['Campo inválido.']];
                return;
            }
            $items = [];
            if (isset($field['ajax'])) {
                $a = nfTableSchema::selectAjaxOptions($field['ajax'], $config['sitedb']);
                $pipeline = array_merge($a['pipeline'], [
                    ['$limit' => 10],
                    ['$project' => ['_id' => 0, 'label' => $a['label'], 'value' => $a['value']]],
                ]);
                $items = $db->{$a['collection']}->aggregate($pipeline, ['maxTimeMS' => 5000])->toArray();
            } elseif (isset($field['suggest'])) {
                $s = $field['suggest'];
                if ($s['mode'] === 'list') {
                    $items = array_map(fn($v) => ['label' => $v, 'value' => $v], array_slice($s['items'], 0, 10));
                } else {
                    $from = $s['mode'] === 'self' ? $collection : $s['collection'];
                    $column = $s['mode'] === 'self' ? $field['field'] : $s['column'];
                    if (!nfTableSchema::validCollection($from)) {
                        $result = ['error' => false, 'errores' => ['Escriba primero una colección válida.']];
                        return;
                    }
                    $items = $db->{$from}->aggregate([
                        ['$match' => [$column => ['$nin' => [null, '']]]],
                        ['$group' => ['_id' => '$' . $column, 'n' => ['$sum' => 1]]],
                        ['$sort' => ['n' => -1]],
                        ['$limit' => 10],
                        ['$project' => ['_id' => 0, 'label' => ['$toString' => '$_id'], 'value' => ['$toString' => '$n']]],
                    ], ['maxTimeMS' => 5000])->toArray();
                }
            } elseif (isset($field['items'])) {
                $items = array_slice($field['items'], 0, 10);
            }
            $result = ['error' => false, 'items' => array_map(fn($i) => ['label' => (string) ($i['label'] ?? ''), 'value' => (string) ($i['value'] ?? '')], mongoToArray($items))];
        } elseif ($op === 'codigo' || $op === 'validar') {
            $errors = [];
            $field = nftablesPostedField($errors);
            $result = $field === null || $errors
                ? ['error' => false, 'errores' => $errors ?: ['Campo inválido.']]
                : ['error' => false, 'field' => $field, 'code' => nfTableSchema::toPhp($field, $collection)];
        }
    } catch (Exception $e) {
        error_log('nftables: ' . $e->getMessage());
        $result = ['error' => 'Error: ' . $e->getMessage()];
    }
    return;
}

$fields = nfTableSchema::normalizeFields($dataset->nffields ?? []);
$nframework->metas['title'] = 'Tabla ' . ($dataset->nfcollection ?? 'nueva');
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;
$javas->addjs('var NFT = ' . json_encode([
    'fields' => $fields,
    'types' => nfTableSchema::editorCatalog(),
    'groups' => nfTableSchema::typeGroups(),
    'subgroups' => nfTableSchema::typeGroups(true),
    'collections' => nftablesCollections($db),
    'token' => csrfToken(NF_AJAX_FORM_ACTION),
    'files' => !empty($config['nftables_files']),
], $jsonFlags) . ';', 'general');
$javas->addjs(file_get_contents(__DIR__ . '/table.js'), 'ready');
$collectionUrl = '/nftables/' . rawurlencode((string) ($dataset->nfcollection ?? '')) . '/';
?>
<div class="container p-5">
    <div class="box shadow-large">
        <div class="box-title">Tabla administrable</div>
        <?= secureform('', false, 'nft_form') ?>
        <input type="hidden" name="nffields" id="nft_fields" value="">
        <div class="row">
            <div class="cell-md-4">
                <?= $tabla_coleccion ?>
                <?= $tabla_singular ?>
                <?= $tabla_plural ?>
                <?= $tabla_descripcion ?>
                <?php if (!empty($dataset->nfcollection)) { ?>
                    <a class="button small mt-2" href="<?= htmlspecialchars($collectionUrl) ?>" target="_blank"><span class="mif-table"></span> Abrir tabla</a>
                    <a class="button small mt-2" href="<?= htmlspecialchars($collectionUrl) ?>create" target="_blank"><span class="mif-plus"></span> Nuevo registro</a>
                <?php } ?>
            </div>
            <div class="cell-md-8">
                <div class="d-flex flex-justify-between flex-align-center mb-2">
                    <strong>Campos</strong>
                    <div>
                        <button type="button" class="button small" id="nft_detect" title="Propone campos a partir de los documentos de la colección"><span class="mif-search"></span> Detectar campos</button>
                        <button type="button" class="button small primary" id="nft_add"><span class="mif-plus"></span> Agregar campo</button>
                    </div>
                </div>
                <div id="nft_list" class="nf-scroll-x"></div>
            </div>
        </div>
        <div id="nft_errors" class="remark alert" hidden></div>
        <div class="row mt-4">
            <div class="cell-md-2 offset-md-8"><a href="./" class="button w-100"><span class="mif-exit"></span>&nbsp;<?= $nframework->language['close'] ?></a></div>
            <div class="cell-md-2"><button type="button" class="button success w-100" id="nft_save"><span class="mif-floppy-disk"></span>&nbsp;<?= $nframework->language['save'] ?></button></div>
        </div>
        </form>
    </div>
</div>

<dialog id="nft_dialog" class="nft-dialog">
    <form method="dialog" id="nft_field_form" onsubmit="return false">
        <h3 id="nft_dialog_title">Campo</h3>
        <div class="row">
            <div class="cell-md-6">
                <label for="nft_f_field">Nombre en MongoDB</label>
                <input type="text" id="nft_f_field" list="nft_paths" placeholder="nombre o direccion.calle" autocomplete="off" data-nf-noautofill>
                <datalist id="nft_paths"></datalist>
            </div>
            <div class="cell-md-6">
                <label for="nft_f_type">Tipo</label>
                <select id="nft_f_type"></select>
            </div>
            <div class="cell-md-6">
                <label for="nft_f_label">Etiqueta</label>
                <input type="text" id="nft_f_label">
            </div>
            <div class="cell-md-6">
                <label for="nft_f_help">Ayuda</label>
                <input type="text" id="nft_f_help">
            </div>
            <div class="cell-12">
                <label class="mr-4"><input type="checkbox" id="nft_f_required"> Requerido</label>
                <label><input type="checkbox" id="nft_f_list"> Mostrar como columna</label>
            </div>
        </div>
        <fieldset id="nft_options" class="nft-box"><legend>Propiedades</legend><div class="row" id="nft_options_body"></div></fieldset>

        <fieldset id="nft_source" class="nft-box"><legend>Opciones</legend>
            <div id="nft_source_mode">
                <label class="mr-4"><input type="radio" name="nft_src" value="items" checked> Lista fija</label>
                <label><input type="radio" name="nft_src" value="ajax"> Buscar en una colección (Select AJAX)</label>
            </div>
            <div id="nft_items_box">
                <label for="nft_items">Una opción por renglón: <code>valor=Texto</code> o solo <code>Texto</code></label>
                <textarea id="nft_items" rows="5"></textarea>
            </div>
            <div id="nft_ajax_box" class="row">
                <div class="cell-md-6"><label for="nft_a_collection">Colección</label><input type="text" id="nft_a_collection" list="nft_collections" autocomplete="off" data-nf-noautofill></div>
                <div class="cell-md-6"><label for="nft_a_label">Campo(s) que se muestran (separados por coma)</label><input type="text" id="nft_a_label" list="nft_a_paths" placeholder="nombre, rfc" autocomplete="off" data-nf-noautofill></div>
                <div class="cell-md-6"><label for="nft_a_value">Campo que se guarda</label><input type="text" id="nft_a_value" list="nft_a_paths" placeholder="_id" autocomplete="off" data-nf-noautofill></div>
                <div class="cell-md-6"><label for="nft_a_columns">Buscar en (separados por coma; vacío = los que se muestran)</label><input type="text" id="nft_a_columns" autocomplete="off" data-nf-noautofill></div>
                <div class="cell-md-9"><label for="nft_a_match">Filtro fijo (JSON, opcional)</label><input type="text" id="nft_a_match" placeholder='{"activo": true}' autocomplete="off" data-nf-noautofill></div>
                <div class="cell-md-3"><label for="nft_a_limit">Resultados</label><input type="number" id="nft_a_limit" min="1" max="100" value="20"></div>
                <datalist id="nft_a_paths"></datalist>
            </div>
        </fieldset>

        <fieldset id="nft_suggest" class="nft-box"><legend>Autocompletar</legend>
            <select id="nft_s_mode">
                <option value="">Sin sugerencias</option>
                <option value="self">Lo que ya se capturó en este campo</option>
                <option value="collection">Valores de un campo de otra colección</option>
                <option value="list">Lista fija</option>
            </select>
            <div id="nft_s_list_box"><label for="nft_s_items">Una sugerencia por renglón</label><textarea id="nft_s_items" rows="4"></textarea></div>
            <div id="nft_s_coll_box" class="row">
                <div class="cell-md-6"><label for="nft_s_collection">Colección</label><input type="text" id="nft_s_collection" list="nft_collections" autocomplete="off" data-nf-noautofill></div>
                <div class="cell-md-6"><label for="nft_s_column">Campo</label><input type="text" id="nft_s_column" list="nft_s_paths" autocomplete="off" data-nf-noautofill></div>
                <datalist id="nft_s_paths"></datalist>
            </div>
            <small class="text-muted">El usuario puede escribir cualquier texto; la lista solo ayuda a escribir.</small>
        </fieldset>

        <fieldset id="nft_sub" class="nft-box"><legend>Campos de cada elemento</legend>
            <div class="nf-scroll-x"><table class="table compact">
                <thead><tr><th>Nombre</th><th>Tipo</th><th>Etiqueta</th><th title="Requerido">Req.</th><th title="Columna en la lista">Col.</th><th>Opciones (valor=Texto)</th><th></th></tr></thead>
                <tbody id="nft_sub_body"></tbody>
            </table></div>
            <button type="button" class="button small" id="nft_sub_add"><span class="mif-plus"></span> Agregar subcampo</button>
        </fieldset>

        <div id="nft_test" class="nft-box" hidden></div>
        <div id="nft_dialog_errors" class="remark alert" hidden></div>
        <div class="d-flex flex-justify-between mt-4">
            <div>
                <button type="button" class="button small" id="nft_try" title="Muestra las primeras opciones o sugerencias"><span class="mif-eye"></span> Probar</button>
                <button type="button" class="button small" id="nft_code" title="Código PHP equivalente para usarlo en una página del sitio"><span class="mif-embed2"></span> Código PHP</button>
            </div>
            <div>
                <button type="button" class="button" id="nft_cancel">Cancelar</button>
                <button type="button" class="button primary" id="nft_ok"><span class="mif-checkmark"></span> Aceptar</button>
            </div>
        </div>
    </form>
</dialog>
<datalist id="nft_collections"></datalist>
<style>
    .nft-dialog { width: min(900px, calc(100vw - 2rem)); max-height: calc(100vh - 2rem); overflow: auto; border: 1px solid var(--nf-line, #ccc); border-radius: var(--nf-radius, 6px); padding: 1.5rem; }
    .nft-dialog input[type=text], .nft-dialog input[type=number], .nft-dialog select, .nft-dialog textarea { width: 100%; }
    .nft-box { border: 1px solid var(--nf-line, #ddd); border-radius: var(--nf-radius, 6px); padding: .75rem; margin-top: 1rem; }
    .nft-box legend { padding: 0 .5rem; font-weight: 600; }
    .nft-dialog pre { white-space: pre; overflow: auto; max-height: 300px; font-size: .8rem; }
    #nft_sub_body input, #nft_sub_body select, #nft_sub_body textarea { min-width: 6rem; }
    .nft-type { font-size: .8rem; opacity: .75; }
</style>
