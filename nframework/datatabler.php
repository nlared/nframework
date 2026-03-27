<?
//Datatable reorder
require 'include.php';
$datainfo = $_SESSION['datatable'][$_GET['id']];
if (empty($datainfo)) {
    echo 'error en session';
    die();
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
$data = \json_decode(file_get_contents('php://input'), true);

foreach ($data as $row) {

    $id = $row['id'];
    $newPos = intval($row['newPosition']);

    $m->{$datainfo['db']}->{$datainfo['collection']}->updateOne(['_id' => new \MongoDB\BSON\ObjectId($id)], ['$set' => ['position' => $newPos]]);
}
$result = ['success' => true,'data' => $data];
