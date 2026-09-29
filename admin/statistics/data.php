<?php
$nfshutdowndisable = true;
$nfjavaobfuscatedisable = true;
require __DIR__ . '/../../includes/include.php';

header('Content-Type: application/json; charset=utf-8');

function respondJson(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit();
}

if (!isset($user) || !$user->in('admins')) {
    respondJson(['error' => 'No autorizado'], 403);
}

function parseDateInput(string $date): ?DateTimeImmutable
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$dt) {
        return null;
    }
    return $dt->setTime(0, 0, 0);
}

$defaultStart = (new DateTimeImmutable('today'))->modify('-7 days');
$defaultEnd = new DateTimeImmutable('today');

$dateiniInput = $_GET['dateini'] ?? $defaultStart->format('Y-m-d');
$dateendInput = $_GET['dateend'] ?? $defaultEnd->format('Y-m-d');

$dateini = parseDateInput($dateiniInput);
$dateend = parseDateInput($dateendInput);

if (!$dateini || !$dateend) {
    respondJson(['error' => 'Formato de fecha invalido, se espera Y-m-d'], 422);
}

if ($dateini > $dateend) {
    respondJson(['error' => 'dateini no puede ser mayor que dateend'], 422);
}

$dateFromUtc = new MongoDB\BSON\UTCDateTime($dateini->getTimestamp() * 1000);
$dateToUtcExclusive = new MongoDB\BSON\UTCDateTime($dateend->modify('+1 day')->getTimestamp() * 1000);

$collection = $m->{$config['sitedb']}->nfuristats;

$methodFilter = strtoupper(trim((string) ($_GET['method'] ?? '')));
$statusFilter = strtoupper(trim((string) ($_GET['status'] ?? '')));
$blockedFilter = strtolower(trim((string) ($_GET['blocked'] ?? 'all')));

$allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
if ($methodFilter !== '' && !in_array($methodFilter, $allowedMethods, true)) {
    respondJson(['error' => 'Filtro de metodo invalido'], 422);
}

if ($statusFilter !== '' && !preg_match('/^[1-5]XX$/', $statusFilter) && !preg_match('/^[1-5][0-9]{2}$/', $statusFilter)) {
    respondJson(['error' => 'Filtro de status invalido'], 422);
}

if (!in_array($blockedFilter, ['all', 'exclude', 'only'], true)) {
    respondJson(['error' => 'Filtro de bloqueos invalido'], 422);
}

$dateMatch = [
    '$or' => [
        ['created_at' => ['$gte' => $dateFromUtc, '$lt' => $dateToUtcExclusive]],
        ['createdAt' => ['$gte' => $dateFromUtc, '$lt' => $dateToUtcExclusive]],
    ],
];

$matchClauses = [$dateMatch];

if ($methodFilter !== '') {
    $matchClauses[] = ['method' => $methodFilter];
}

if ($statusFilter !== '') {
    if (preg_match('/^[1-5]XX$/', $statusFilter)) {
        $family = (int) $statusFilter[0];
        $matchClauses[] = ['status_code' => ['$gte' => $family * 100, '$lt' => ($family + 1) * 100]];
    } else {
        $matchClauses[] = ['status_code' => (int) $statusFilter];
    }
}

if ($blockedFilter === 'exclude') {
    $matchClauses[] = [
        '$or' => [
            ['block_reason' => ['$exists' => false]],
            ['block_reason' => ''],
            ['block_reason' => null],
        ],
    ];
}

if ($blockedFilter === 'only') {
    $matchClauses[] = ['block_reason' => ['$nin' => ['', null]]];
}

$queryMatch = count($matchClauses) === 1 ? $matchClauses[0] : ['$and' => $matchClauses];

$dailyStats = [];
$dailyPipeline = [
    ['$addFields' => ['event_at' => ['$ifNull' => ['$created_at', '$createdAt']]]],
    ['$match' => $queryMatch],
    ['$group' => [
        '_id' => [
            'year' => ['$year' => '$event_at'],
            'month' => ['$month' => '$event_at'],
            'day' => ['$dayOfMonth' => '$event_at'],
        ],
        'request_count' => ['$sum' => 1],
        'sessions' => ['$addToSet' => ['$ifNull' => ['$session_id', '']]],
        'paths' => ['$addToSet' => ['$ifNull' => ['$path', '']]],
        'ips' => ['$addToSet' => ['$ifNull' => ['$ip', '']]],
        'size_bytes' => ['$sum' => ['$ifNull' => ['$size_bytes', 0]]],
        'response_time_ms' => ['$sum' => ['$ifNull' => ['$response_time_ms', 0]]],
        'error_count' => ['$sum' => ['$cond' => [['$gte' => [['$ifNull' => ['$status_code', 0]], 400]], 1, 0]]],
        'blocked_count' => ['$sum' => ['$cond' => [['$ne' => [['$ifNull' => ['$block_reason', '']], '']], 1, 0]]],
    ]],
    ['$sort' => ['_id.year' => 1, '_id.month' => 1, '_id.day' => 1]],
];

foreach ($collection->aggregate($dailyPipeline) as $row) {
    $year = (int) ($row['_id']['year'] ?? 0);
    $month = (int) ($row['_id']['month'] ?? 0);
    $day = (int) ($row['_id']['day'] ?? 0);
    $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $day);

    $sessions = array_values(array_filter((array) ($row['sessions'] ?? []), static function ($v) {
        return $v !== null && $v !== '';
    }));
    $paths = array_values(array_filter((array) ($row['paths'] ?? []), static function ($v) {
        return $v !== null && $v !== '';
    }));
    $ips = array_values(array_filter((array) ($row['ips'] ?? []), static function ($v) {
        return $v !== null && $v !== '';
    }));

    $requestCount = (int) ($row['request_count'] ?? 0);
    $responseTimeMs = (float) ($row['response_time_ms'] ?? 0);

    $dailyStats[$dateKey] = [
        'request_count' => $requestCount,
        'sessions' => count($sessions),
        'paths' => count($paths),
        'ips' => count($ips),
        'size_bytes' => (float) ($row['size_bytes'] ?? 0),
        'response_time_ms' => $responseTimeMs,
        'avg_response_time_ms' => $requestCount > 0 ? $responseTimeMs / $requestCount : 0,
        'error_count' => (int) ($row['error_count'] ?? 0),
        'blocked_count' => (int) ($row['blocked_count'] ?? 0),
    ];
}

$totalRequests = (int) $collection->countDocuments($queryMatch);

$totalsAgg = iterator_to_array($collection->aggregate([
    ['$match' => $queryMatch],
    ['$group' => [
        '_id' => null,
        'total_size_bytes' => ['$sum' => ['$ifNull' => ['$size_bytes', 0]]],
        'total_response_time_ms' => ['$sum' => ['$ifNull' => ['$response_time_ms', 0]]],
        'total_errors' => ['$sum' => ['$cond' => [['$gte' => [['$ifNull' => ['$status_code', 0]], 400]], 1, 0]]],
        'total_blocked' => ['$sum' => ['$cond' => [['$ne' => [['$ifNull' => ['$block_reason', '']], '']], 1, 0]]],
    ]],
]));

$totalsDoc = $totalsAgg[0] ?? [];

$allSessions = array_values(array_filter($collection->distinct('session_id', $queryMatch), static function ($v) {
    return $v !== null && $v !== '';
}));
$allPaths = array_values(array_filter($collection->distinct('path', $queryMatch), static function ($v) {
    return $v !== null && $v !== '';
}));
$allIps = array_values(array_filter($collection->distinct('ip', $queryMatch), static function ($v) {
    return $v !== null && $v !== '';
}));

$agents = [];
$agentsPipeline = [
    ['$match' => $queryMatch],
    ['$group' => ['_id' => ['$ifNull' => ['$agent', 'Desconocido']], 'count' => ['$sum' => 1]]],
    ['$sort' => ['count' => -1]],
    ['$limit' => 20],
];
foreach ($collection->aggregate($agentsPipeline) as $row) {
    $label = trim((string) ($row['_id'] ?? 'Desconocido'));
    if ($label === '') {
        $label = 'Desconocido';
    }
    $agents[$label] = (int) ($row['count'] ?? 0);
}

$topPaths = [];
$pathsPipeline = [
    ['$match' => $queryMatch],
    ['$group' => ['_id' => ['$ifNull' => ['$path', '']], 'count' => ['$sum' => 1]]],
    ['$match' => ['_id' => ['$ne' => '']]],
    ['$sort' => ['count' => -1]],
    ['$limit' => 15],
];
foreach ($collection->aggregate($pathsPipeline) as $row) {
    $topPaths[] = [
        'path' => (string) ($row['_id'] ?? ''),
        'count' => (int) ($row['count'] ?? 0),
    ];
}

$statusCodes = [];
$statusPipeline = [
    ['$match' => $queryMatch],
    ['$group' => ['_id' => ['$ifNull' => ['$status_code', 0]], 'count' => ['$sum' => 1]]],
    ['$sort' => ['_id' => 1]],
];
foreach ($collection->aggregate($statusPipeline) as $row) {
    $code = (int) ($row['_id'] ?? 0);
    $statusCodes[(string) $code] = (int) ($row['count'] ?? 0);
}

$payload = [
    'range' => [
        'dateini' => $dateini->format('Y-m-d'),
        'dateend' => $dateend->format('Y-m-d'),
    ],
    'filters' => [
        'method' => $methodFilter,
        'status' => $statusFilter,
        'blocked' => $blockedFilter,
    ],
    'stats' => $dailyStats,
    'total_requests' => $totalRequests,
    'total_sessions' => count($allSessions),
    'total_paths' => count($allPaths),
    'total_ips' => count($allIps),
    'total_size_bytes' => (float) ($totalsDoc['total_size_bytes'] ?? 0),
    'total_response_time_ms' => (float) ($totalsDoc['total_response_time_ms'] ?? 0),
    'total_errors' => (int) ($totalsDoc['total_errors'] ?? 0),
    'total_blocked' => (int) ($totalsDoc['total_blocked'] ?? 0),
    'avg_response_time_ms' => $totalRequests > 0 ? ((float) ($totalsDoc['total_response_time_ms'] ?? 0) / $totalRequests) : 0,
    'error_rate' => $totalRequests > 0 ? ((float) ($totalsDoc['total_errors'] ?? 0) / $totalRequests) : 0,
    'blocked_rate' => $totalRequests > 0 ? ((float) ($totalsDoc['total_blocked'] ?? 0) / $totalRequests) : 0,
    'agents' => $agents,
    'top_paths' => $topPaths,
    'status_codes' => $statusCodes,
];

respondJson($payload);
