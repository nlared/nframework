<?php
require 'include.php';
$tabla = $m->{$config['sitedb']}->nftables->findOne([
    '_id' => tomongoid($_GET['_id'])
]);
if (!$tabla) {
    echo 'No se encontró la tabla';
}
if (!isset($tabla->fields) || count($tabla->fields) == 0) {
    echo 'La tabla no tiene campos definidos';
}
$columns = [];
foreach ($tabla->nffields as $field) {
    $columns[] = $field->field;
}
$result['columns'] = $columns;
echo json_encode($result);
