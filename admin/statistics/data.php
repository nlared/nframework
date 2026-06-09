<?
require 'include.php';



if (empty($_GET['dateini']) || empty($_GET['dateend'])) {
    $dateini = date('Y-m-d', strtotime('-7 days'));
    $dateend = date('Y-m-d');
} else {
    $dateini = $_GET['dateini'];
    $dateend = $_GET['dateend'];
}
$dateini = new MongoDB\BSON\UTCDatetime(strtotime($dateini) * 1000);
$dateend = new MongoDB\BSON\UTCDatetime(strtotime($dateend) * 1000);

$group = ['created_at' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$created_at']]];

$stats = [];
foreach (
    $m->{$config['sitedb']}->nfuristats->aggregate([
        ['$match' => ['date' => ['$gte' => $dateini, '$lte' => $dateend]]],
        ['group' => ['_id' => $group, 'count' => ['$sum' => 1], 'sessions_id' => ['$addToSet' => '$sessions_id'], 'uri' => ['$addToSet' => '$uri'], 'size_bytes' => ['$sum' => '$size_bytes'], 'response_time_ms' => ['$sum' => '$response_time_ms']]],
        ['$sort' => ['count' => -1]],
    ]) as $stat
) {
    $groupstr = $stat['_id']['created_at'];
    $stats[$groupstr]['sessions'][] = $stat['sessions_id'];
    $stats[$groupstr]['uris'][] = $stat['uri'];
    $stats[$groupstr]['size_bytes'][] = $stat['size_bytes'];
    $stats[$groupstr]['response_time_ms'] += $stat['response_time_ms'];
}

echo json_encode($stats);
