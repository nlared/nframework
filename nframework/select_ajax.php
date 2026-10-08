<?php
//$developermode=true;
require_once 'include.php';
$datainfo = $_SESSION['selectajax'][(string) ($_GET['id'] ?? '')] ?? null;
if (empty($datainfo)) {
    echo 'error en session';
    die();
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
$options = [];
$result = [];
$error = '';
$pipeline = [];
// Todos los parámetros se fuerzan a string para impedir inyección de operadores ($ne, $gt...).
$qid = is_string($_GET['qid'] ?? null) ? $_GET['qid'] : '';
if ($qid === '') {
    $q = preg_quote(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
    $matchs = [];
    foreach ($datainfo['columns'] as $co) {
        $matchs[][$co] = new MongoDB\BSON\Regex($q, "i");
    }
    $pipeline[] = ['$match' => ['$or' => $matchs]];
}
$filter = [];
foreach ((array) ($datainfo['args'] ?? []) as $arg) {
    $filter[$arg] = is_scalar($_GET[$arg] ?? null) ? (string) $_GET[$arg] : '';
}

if (!empty($filter)) {
    $pipeline[] = ['$match' =>  $filter];
}



$pipeline = array_merge($datainfo['pipeline'], $pipeline);
$pipeline[] = ['$addFields' => ['label' => $datainfo['label'], 'value' => $datainfo['value']]];
if ($qid !== '') {
    $pipeline[] = ['$match' => ['value' => $qid]];
}
$pipeline[] = ['$project' => ['_id' => 0, 'label' => 1, 'value' => 1]];


try {
    $result = $m->{$datainfo['db']}->{$datainfo['collection']}->aggregate($pipeline, $options)->toArray();
} catch (Exception $e) {
    error_log('nframework select_ajax: ' . $e->getMessage());
    $error = 'Error en la consulta';
}
if ($error) {
    $result['error'] = $error;
    if ($developermode) {
        $result['pipeline'] = $pipeline;
    }
}
echo json_encode($result);
