<?php
//Datatable reorder
require 'include.php';
$datainfo = $_SESSION['datatable'][(string) ($_GET['id'] ?? '')] ?? null;
if (empty($datainfo)) {
    echo 'error en session';
    die();
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
$data = \json_decode(file_get_contents('php://input'), true);

foreach ((array) ($data['rows'] ?? []) as $row) {
    if (!isValidObjectId($row['id'] ?? null)) {
        continue;
    }
    $m->{$datainfo['db']}->{$datainfo['collection']}->updateOne(
        ['_id' => new \MongoDB\BSON\ObjectId($row['id'])],
        ['$set' => ['position' => intval($row['newPosition'] ?? 0)]]
    );
}
$result = ['success' => true];
