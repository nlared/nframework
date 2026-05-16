<?php
//$developermode=true;
require_once 'include.php';
$datainfo = $_SESSION['selectajax'][$_GET['id']];
if (empty($datainfo)) {
    echo 'error en session';
    die();
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
$options = [];
$result = [];
if (empty($_GET['qid'])) {
    foreach ($datainfo['columns'] as $cno => $co) {
        $matchs[][$co] = new MongoDB\BSON\Regex($_GET['q'], "i");
    }
    $pipeline[] = ['$match' => ['$or' => $matchs]];
}
$pipeline = array_merge($datainfo['pipeline'], $pipeline);
$pipeline[] = ['$addFields' => ['label' => $datainfo['label'], 'value' => $datainfo['value']]];
if (!empty($_GET['q'])) {
    $pipeline[] = ['$match' => ['value' => $_GET['qid']]];
}
$pipeline[] = ['$project' => ['_id' => 0, 'label' => 1, 'value' => 1]];


try {
    $result = $m->{$datainfo['db']}->{$datainfo['collection']}->aggregate($pipeline, $options)->toArray();
} catch (Exception $e) {
    $error = 'Error en la consulta: ' . $e->getMessage();
}
if ($error) {
    $result['error'] = $error;
    //if ($developermode) {
    $result['pipeline'] = $pipeline;
    //}
}
echo json_encode($result);
