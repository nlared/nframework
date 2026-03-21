<?
//Datatable reorder
namespace nframework;

$data = \json_decode(file_get_contents('php://input'), true);

$datainfo = $_SESSION['datatable'][$_GET['id']];

foreach ($data as $row) {

    $id = $row['id'];
    $newPos = intval($row['newPosition']);

    $m->{$datainfo['db']}->{$datainfo['collection']}->updateOne(['_id' => new \MongoDB\BSON\ObjectId($id)], ['$set' => ['position' => $newPos]]);
}
echo json_encode(['success' => true]);
