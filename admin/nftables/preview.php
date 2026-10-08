<?php
require 'include.php';
requireGroup('admins');
$tabla = isValidObjectId($_GET['_id'] ?? null) ? $m->{$config['sitedb']}->nftables->findOne([
    '_id' => tomongoid($_GET['_id'])
]) : null;
$columns = [];
foreach ($tabla->nffields ?? [] as $field) {
    $columns[] = $field->field;
}
$result['columns'] = $columns;
echo json_encode($result);
