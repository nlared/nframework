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

$group = [
    'year'  => ['$year'  => '$createdAt'],
    'month' => ['$month' => '$createdAt'],
    'day'   => ['$dayOfMonth' => '$createdAt'],
];

$stats = [];
foreach (
    $m->{$config['sitedb']}->nfuristats->aggregate([
        ['$match' => ['createdAt' => ['$gte' => $dateini, '$lte' => $dateend]]],
        ['$group' => ['_id' => $group]],
    ]) as $stat
) {
    $groupstr = "$stat[_id][year]-$stat[_id][month]-$stat[_id][day]";
    $stats[$groupstr]['sessions'][] = $stat['sessions_id'];
    $stats[$groupstr]['uris'][] = $stat['uri'];
    $stats[$groupstr]['size_bytes'][] = $stat['size_bytes'];
    $stats[$groupstr]['response_time_ms'] += $stat['response_time_ms'];
}
foreach ($stats as $groupstr => $stat) {
    $stats[$groupstr]['sessions'] = array_sum($stat['sessions']);
    $stats[$groupstr]['uris'] = array_sum($stat['uris']);
    $stats[$groupstr]['size_bytes'] = array_sum($stat['size_bytes']);
    $stats[$groupstr]['response_time_ms'] = array_sum($stat['response_time_ms']);
}

echo json_encode($stats);
